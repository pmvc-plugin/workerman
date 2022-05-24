<?php
namespace PMVC\PlugIn\workerman;

use Workerman\Worker;
use Workerman\Connection\TcpConnection;
use Channel;
use SplObjectStorage;

${_INIT_CONFIG}[_CLASS] = __NAMESPACE__ . '\workerman';

class workerman extends \PMVC\PlugIn
{
    private $_storage;

    public function init()
    {
        foreach ($this->_defaultProps() as $k => $v) {
            if (!isset($this[$k])) {
                $this[$k] = $v;
            }
        }
    }

    public function attach($class)
    {
        if (is_a($class, '\PMVC\PlugIn')) {
            $class = $class['this'];
        }
        $this->_storage[] = $class;
    }

    public function go($method, $params)
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
        $ip = $this['ip'];
        if ('0.0.0.0' === $ip) {
            $ip = '127.0.0.1';
        }
        $curl = \PMVC\plug('curl');
        $host = 'http://' . $ip . ':' . $this['httpPort'];
        $curl->post(
            $host,
            function ($r) {
                \PMVC\dev(function () use ($r) {
                    return \PMVC\fromJson($r->body, true);
                }, 'workerman');
            },
            [
                'data' => json_encode($data),
            ]
        );
        $curl->process();
    }

    private function _sendWs($conn, $data, array $params = [])
    {
        $conn->send(json_encode(array_replace($data, $params)));
    }

    public function getToken($conn)
    {
        $workerId = \PMVC\value($conn, ['worker', 'workerId']);
        $connectionId = \PMVC\value($conn, ['id']);
        $now = time();
        $token =
            $this->hash(
                (string) $workerId,
                (string) $connectionId,
                (string) $now,
                (string) $conn->getRemoteIp(),
                (string) $conn->getRemotePort()
            ) .
            ',' .
            $now;
        $conn->token = $token;

        $this->_sendWs(
            $conn,
            [
                'auth' => [
                    'webSocketConnId' => $connectionId,
                    'webSocketToken' => $token,
                ],
            ],
            ['type' => 'ws-auth']
        );
    }

    public function verifyToken($tokens, $toWorkerId, $toConnectionId, $conn)
    {
        list($token, $now) = explode(',', $tokens);
        $bool =
            $token ===
            (string) $this->hash(
                (string) $toWorkerId,
                (string) $toConnectionId,
                (string) $now,
                (string) $conn->getRemoteIp(),
                (string) $conn->getRemotePort()
            );
        return $bool;
    }

    public function hash()
    {
        return \PMVC\hash($this['secret'], func_get_args());
    }

    public function onConnect($conn)
    {
        $this->getToken($conn);
        return $this->go(__FUNCTION__, [$conn]);
    }

    public function onMessage($conn, $data)
    {
        $data = \PMVC\fromJson($data);
        switch (\PMVC\get($data, 'type')) {
            case 'ping':
                $conn->send('pong');
                break;
            default:
                return $this->go(__FUNCTION__, [$conn, $data]);
        }
    }

    public function onClose($conn)
    {
        return $this->go(__FUNCTION__, [$conn]);
    }

    public function handleWsStart($ws)
    {
        Channel\Client::connect($this['ip'], $this['channelPort']);
        Channel\Client::on($ws->workerId, function ($e) use ($ws) {
            $to = $e['to'];
            $data = $e['data'];
            $token = $e['token'];
            $toConn = \PMVC\value($ws->connections, [$to]);
            if ($toConn && \PMVC\value($toConn, ['token']) === $token) {
                if ($this->verifyToken($token, $ws->workerId, $to, $toConn)) {
                    $this->_sendWs($toConn, $data, ['type' => 'ws-message']);
                }
            }
        });
        Channel\Client::on('all', function ($e) use ($ws) {
            $data = $e['data'];
            foreach ($ws->connections as $conn) {
                $this->_sendWs($conn, $data, ['type' => 'ws-message']);
            }
        });
    }

    public function handleHttpGetMessage($conn, $request)
    {
        $post = $request->post();
        $data = \PMVC\get($post, 'data');
        if (empty($data)) {
            return;
        } else {
            $data = \PMVC\fromJson($data, true);
        }
        $keys = is_array($data) ? array_keys($data) : $data;
        $conn->send(json_encode([$keys, gettype($data)]));
        $toConnectionId = \PMVC\get($post, 'toConnectionId');
        $toWorkerId = \PMVC\value($this, ['ws', 'workerId']);
        $token = \PMVC\get($post, 'token');
        if (empty($toConnectionId) && empty($token)) {
            Channel\Client::publish('all', [
                'data' => $data,
            ]);
        } else {
            Channel\Client::publish($toWorkerId, [
                'to' => $toConnectionId,
                'data' => $data,
                'token' => $token,
            ]);
        }
    }

    public function initServer()
    {
        if (!is_file($this['pid'])) {
            $this->_storage = [];
            $this->_initChannelServer();
            $this->_initHttpServer();
            $this->_initWsServer();
        } else {
            \PMVC\d('PID already exists. [' . $this['pid'] . ']');
        }
    }

    public function process()
    {
        $this->initServer();
        Worker::$pidFile = $this['pid'];
        Worker::runAll();
    }

    public function stop()
    {
        Worker::stopAll();
    }

    private function _initChannelServer()
    {
        $this['channel'] = new Channel\Server(
            $this['ip'],
            $this['channelPort']
        );
    }

    private function _initHttpServer()
    {
        $self = $this['this'];
        $host = 'http://' . $this['ip'] . ':' . $this['httpPort'];
        $http = new Worker($host);
        $http->onWorkerStart = function () {
            Channel\Client::connect($this['ip'], $this['channelPort']);
        };
        $http->onMessage = [$self, 'handleHttpGetMessage'];
        $this['http'] = $http;
    }

    private function _initWsServer()
    {
        $host = 'websocket://' . $this['ip'] . ':' . $this['wsPort'];
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
            'channelPort' => 8086,
            'httpPort' => 8087,
            'wsPort' => 8088,
            'wsCount' => 4,
            'ip' => '0.0.0.0',
            'secret' => 'some-secret',
            'pid' => '/dev/shm/workerman.pid',
        ];
    }
}
