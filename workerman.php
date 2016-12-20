<?php
namespace PMVC\PlugIn\workerman;

use Workerman\Worker;
use Channel;
use SplObjectStorage;

${_INIT_CONFIG}[_CLASS] = __NAMESPACE__.'\workerman';

class workerman extends \PMVC\PlugIn
{
    public $_storage;

    public function init()
    {
        foreach ($this->defaultProps() as $k=>$v) {
            if (!isset($this[$k])) {
                $this[$k] = $v;
            }
        }
        $this->_storage = [];
        $this->_initChannelServer();
        $this->_initHttpServer();
        $this->_initWsServer();
    }

    public function attach ($class)
    {
        if (is_a($class, '\PMVC\PlugIn')) {
            $class = $class['this'];
        }
        $this->_storage[] = $class; 
    }


    public function go ($method,$params)
    {
        foreach ($this->_storage as $obj) {
            if (method_exists($obj, 'isCallable')) {
                $func = $obj->isCallable($method);
                if ($func) {
                    call_user_func_array($func, $params);
                }
            } elseif (is_callable([$obj, $method])) {
                call_user_func_array([$obj, $method], $params);
            }
        }
    }

    public function send($conn, $data)
    {
        $conn->send(json_encode(['data'=>$data]));
    }

    public function onConnect ($conn)
    {
        $workerId = \PMVC\value($conn, ['worker','workerId']);
        $connectionId = \PMVC\value($conn, ['id']);
        $token = $this->hash((string)$workerId,(string)$connectionId);
        $this->send($conn, [
            'id'=> $connectionId,
            'token'=> $token,
            'type'=> 'auth'
        ]);
        return $this->go(__FUNCTION__, [$conn]);
    }

    public function onMessage ($conn, $data)
    {
        return $this->go(__FUNCTION__, [$conn, $data]);
    }

    public function onClose ($conn)
    {
        return $this->go(__FUNCTION__, [$conn]);
    }

    private function _initWsServer()
    {
        $host = 'websocket://'.$this['ip'].':'.$this['wsPort'];
        $ws = new Worker($host);
        $ws->count = $this['wsCount'];
        $self = $this['this'];
        $ws->onConnect = [$self, 'onConnect']; 
        $ws->onMessage = [$self, 'onMessage'];
        $ws->onClose = [$self, 'onClose'];
        $ws->onWorkerStart = [$self, 'handleWsStart'];
        $this['ws'] = $ws;
    }

    public function handleWsStart($ws)
    {
        Channel\Client::connect($this['ip'], $this['channelPort']);
        Channel\Client::on($ws->workerId, function($e) use ($ws) {
            $to = $e['to'];
            $data = $e['data'];
            if (isset($ws->connections[$to])) {
                $toConn = $ws->connections[$to];
                $this->send($toConn, $data);
            }
        });
        Channel\Client::on('all', function($e) use ($ws) {
            $data = $e['data'];
            foreach ($ws->connections as $conn) {
                $this->send($conn, $data);
            }
        });
    }

    private function _initChannelServer()
    {
        $this['channel'] = new Channel\Server($this['ip'], $this['channelPort']);
    }

    private function _initHttpServer()
    {
        $self = $this['this'];
        $host = 'http://'.$this['ip'].':'.$this['httpPort'];
        $http = new Worker($host);
        $http->onWorkerStart = function ()
        {
            Channel\Client::connect($this['ip'], $this['channelPort']);
        };
        $http->onMessage = [$self, 'handleHttpGetMessage'];
        $this['http'] = $http;
    }

    public function handleHttpGetMessage($conn, $data)
    {
        $conn->send('ok');
        $data = \PMVC\fromJson(\PMVC\value($_REQUEST, ['data']));
        if (empty($data)) {
            return;
        }
        $toConnectionId = \PMVC\value($_REQUEST, ['toConnectionId']);
        $toWorkerId = \PMVC\value($this['ws'], ['workerId']);
        $token = \PMVC\value($_REQUEST, ['token']); 
        if (empty($toConnectionId) && empty($token)) {
            Channel\Client::publish( 'all', [ 
                'data' => $data
            ]);
        } elseif ($token === (string)$this->hash((string)$toWorkerId,(string)$toConnectionId)) {
            Channel\Client::publish( $toWorkerId, [ 
                'to' => $toConnectionId,
                'data' => $data
            ]);
        }
    }

    public function hash()
    {
        return \PMVC\hash($this['secret'], func_get_args());
    }

    public function process()
    {
        Worker::runAll();
    }

    public function defaultProps()
    {
        return [
            'channelPort' => 8886,
            'httpPort' => 8887,
            'wsPort' => 8888,
            'wsCount' => 6,
            'ip'=> '0.0.0.0',
            'secret'=> 'some-secret'
        ];
    }
}
