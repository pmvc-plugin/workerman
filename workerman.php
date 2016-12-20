<?php
namespace PMVC\PlugIn\workerman;

use Workerman\Worker;
use SplObjectStorage;

// \PMVC\l(__DIR__.'/xxx.php');

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

    public function onConnect ($conn)
    {
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
        $host = 'websocket://'.$this['ip'].':'.$this['port'];
        $ws = new Worker($host);
        $ws->count = $this['count'];
        $ws->onConnect = [$this, 'onConnect']; 
        $ws->onMessage = [$this, 'onMessage'];
        $ws->onClose = [$this, 'onClose'];
        $this['ws'] = $ws;
    }

    public function process()
    {
        Worker::runAll();
    }

    public function defaultProps()
    {
        return [
            'port' => 8888,
            'count' => 5,
            'ip'=> '0.0.0.0'
        ];
    }
}
