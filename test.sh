#!/usr/bin/env bash
printf "Testing PHP files...\n"
#vendor/bin/phpunit --testsuite unit
vendor/bin/phpunit web/modules/custom/site_audit/tests/src/Unit/MyServiceTest.php
