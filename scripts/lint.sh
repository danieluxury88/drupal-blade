#!/usr/bin/env bash
printf "Linting PHP files...\n"
# ./vendor/bin/phpcs --extensions=php,module,inc,install,test,profile,theme,info,txt,yml web/modules/custom
./vendor/bin/phpcs   # lint
./vendor/bin/phpcbf  # auto-fix (uses phpcs.xml)
