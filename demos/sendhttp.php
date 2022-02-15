<?php
include_once "../vendor/autoload.php";

\PMVC\Load::plug();
\PMVC\addPlugInFolders(["../../"]);

$ws = \PMVC\plug("workerman");
$ws->sendHttp("jjjjjjj");
