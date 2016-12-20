<?php
include_once('./vendor/autoload.php');

\PMVC\Load::plug();
\PMVC\addPlugInFolders(['../']);

$ws = \PMVC\plug('workerman');

$ws->attach(new job());
class job {
    function onMessage($conn, $data)
    {
        $conn->send(json_encode([
            'data'=>$data
        ]));
    }
}

$ws->process();
