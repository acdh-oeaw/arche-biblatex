#!/bin/bash
/var/www/html/vendor/bin/arche-diss-cache-clear /var/www/html/config.yaml 2>&1 > /var/www/html/cache-clear.log

curl -sf "http://127.0.0.1/?id=$TEST_URI" > /dev/null
