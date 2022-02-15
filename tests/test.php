<?php
namespace PMVC\PlugIn\workerman;
use PMVC\TestCase;

class WorkermanTest extends TestCase
{
    private $_plug = 'workerman';
    function testPlugin()
    {
        ob_start();
        print_r(\PMVC\plug($this->_plug));
        $output = ob_get_contents();
        ob_end_clean();
        $this->haveString($this->_plug,$output);
    }

    function testWorkerman()
    {
      $this->assertTrue(true);
    }
}
