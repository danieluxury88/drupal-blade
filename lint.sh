#!/usr/bin/env bash
printf "Linting PHP files...\n"
./vendor/bin/phpcs   # lint
./vendor/bin/phpcbf  # auto-fix (uses phpcs.xml)
