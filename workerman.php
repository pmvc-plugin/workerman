<?php
namespace PMVC\PlugIn\workerman;

use Workerman\Worker;
use Workerman\Connection\TcpConnection;
use Channel;
use SplObjectStorage;

${_INIT_CONFIG}[_CLASS] = __NAMESPACE__.'\workerman';

class workerman extends \PMVC\PlugIn
{
    public $_storage;

    public function init()
    {
        foreach ($this->_defaultProps() as $k=>$v) {
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
            if (is_callable([$obj, 'isCallable'])) {
                $func = $obj->isCallable($method);
                if ($func) {
                    call_user_func_array($func, $params);
                }
            } elseif (is_callable([$obj, $method])) {
                call_user_func_array([$obj, $method], $params);
            }
        }
    }

    public function sendHttp($data)
    {
        $curl = \PMVC\plug('curl');
        $host = 'http://'.$this['ip'].':'.$this['httpPort'];
        $curl->post($host,
            function ($r) {
                \PMVC\dev(function() use ($r){
                    return \PMVC\fromJson($r->body);
                }, 'http');
            },
            [
                'data'=>json_encode($data)
            ]
        );
        $curl->process();
    }

    public function send($conn, $data, array $params=[])
    {
        $conn->send(json_encode(array_replace(
            ['--realTimeData--'=>$data],
            $params
        )));
    }

    public function getToken($conn)
    {
        $workerId = \PMVC\value($conn, ['worker','workerId']);
        $connectionId = \PMVC\value($conn, ['id']);
        $now = time();
        $token = $this->hash(
            (string)$workerId,
            (string)$connectionId,
            (string)$now,
            (string)$conn->getRemoteIp(),
            (string)$conn->getRemotePort()
        ).','.$now;
        $conn->token = $token;

        $this->send($conn, [
            'webSocketConnId'=> $connectionId,
            'webSocketToken'=> $token,
        ], ['type'=>'ws-auth']);
    }

    public function verifyToken($tokens, $toWorkerId, $toConnectionId, $conn)
    {
         list($token, $now) = explode(',', $tokens);
         $bool = $token === (string)$this->hash(
            (string)$toWorkerId,
            (string)$toConnectionId,
            (string)$now,
            (string)$conn->getRemoteIp(),
            (string)$conn->getRemotePort()
        );
        return $bool;
    }

    public function hash()
    {
        return \PMVC\hash($this['secret'], func_get_args());
    }

    public function onConnect ($conn)
    {
        $this->getToken($conn);
        return $this->go(__FUNCTION__, [$conn]);
    }

    public function onMessage ($conn, $data)
    {
        $data = \PMVC\fromJson($data);
        switch (\PMVC\get($data,'type')) {
            case 'ping':
                $conn->send('ping');
                break;
            default:
                return $this->go(__FUNCTION__, [$conn, $data]);
        }
    }

    public function onClose ($conn)
    {
        return $this->go(__FUNCTION__, [$conn]);
    }


    public function handleWsStart($ws)
    {
        Channel\Client::connect($this['ip'], $this['channelPort']);
        Channel\Client::on($ws->workerId, function($e) use ($ws) {
            $to = $e['to'];
            $data = $e['data'];
            $token = $e['token'];
            $toConn = \PMVC\value($ws->connections, [$to]);
            if ($toConn && \PMVC\value($toConn,['token']) === $token) {
                if ( $this->verifyToken($token, $ws->workerId, $to, $toConn) ) {
                    $this->send($toConn, $data, ['type'=>'ws-message']);
                }
            }
        });
        Channel\Client::on('all', function($e) use ($ws) {
            $data = $e['data'];
            foreach ($ws->connections as $conn) {
                $this->send($conn, $data, ['type'=>'ws-message']);
            }
        });
    }

    public function handleHttpGetMessage($conn, $data)
    {
        $json = \PMVC\get($_REQUEST, 'data');
        $data = \PMVC\fromJson($json, true);
        $conn->send(json_encode([array_keys($data), gettype($data)]));
        if (empty($data)) {
            return;
        }
        $toConnectionId = \PMVC\get($_REQUEST, 'toConnectionId');
        $toWorkerId = \PMVC\value($this, ['ws','workerId']);
        $token = \PMVC\get($_REQUEST, 'token'); 
        if (empty($toConnectionId) && empty($token)) {
            Channel\Client::publish( 'all', [ 
                'data' => $data
            ]);
        } else {
            Channel\Client::publish( $toWorkerId, [ 
                'to' => $toConnectionId,
                'data' => $data,
                'token'=> $token
            ]);
        }
    }

    public function process()
    {
        Worker::$pidFile = $this['pid'];
        Worker::runAll();
    }

    public function stop()
    {
        Worker::stopAll();
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


    private function _defaultProps()
    {
        return [
            'channelPort' => 8886,
            'httpPort' => 8887,
            'wsPort' => 8888,
            'wsCount' => 6,
            'ip'=> '127.0.0.1',
            'secret'=> 'some-secret',
            'pid'=>'/dev/shm/workerman.pid'
        ];
    }
}
