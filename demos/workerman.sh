#!/bin/bash
PHP="/usr/bin/env php"
PWD=`dirname $0`
CMD+="$PHP demo.php $1 $2";
echo $CMD
echo $CMD | bash
