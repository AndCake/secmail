#!/bin/sh
PHP=`which php`
DOCROOT=`dirname $0`/webfront
ERRORLOG=/dev/null
INFOLOG=/dev/null

$PHP -t $DOCROOT -S 127.0.0.1:8080 & #2>$ERRORLOG >$INFOLOG &
open http://localhost:8080/index.html
