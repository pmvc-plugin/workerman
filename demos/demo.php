<?php
include_once __DIR__.'/../vendor/autoload.php';

\PMVC\Load::plug();
\PMVC\addPlugInFolders(['../../']);

$ws = \PMVC\plug('workerman');

$ws->attach(new job());

class job
{
    function onMessage($conn, $data)
    {
        $conn->send(
            json_encode([
                'data' => $data,
            ])
        );
    }
}

$ws->process();
