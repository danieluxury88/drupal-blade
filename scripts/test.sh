#!/usr/bin/env bash
printf "Testing PHP files...\n"
#vendor/bin/phpunit --testsuite unit
vendor/bin/phpunit --testsuite custom
