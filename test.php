<?php
namespace PMVC\PlugIn\workerman;
use PHPUnit_Framework_TestCase;

\PMVC\Load::plug();
\PMVC\addPlugInFolders(['../']);

class WorkermanTest extends PHPUnit_Framework_TestCase
{
    private $_plug = 'workerman';
    function testPlugin()
    {
        ob_start();
        print_r(\PMVC\plug($this->_plug));
        $output = ob_get_contents();
        ob_end_clean();
        $this->assertContains($this->_plug,$output);
    }

    function testWorkerman()
    {

    }
}
