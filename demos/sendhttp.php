<?php
include_once "../vendor/autoload.php";

\PMVC\Load::plug();
\PMVC\addPlugInFolders(["../../"]);

$oPHPUnit = \PMVC\plug("dev")->phpunit("workerman");

$ws = \PMVC\plug("workerman");
$ws->sendHttp("jjjjjjj");

$array = $oPHPUnit->toArray();

print_r($array);
