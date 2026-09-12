# Testing

Last verified: 2026-09-12

## Available checks

The repository provides shell wrappers for PHP quality checks:

```bash
./scripts/lint.sh
./scripts/test.sh
```

`scripts/lint.sh` runs PHPCS and PHPCBF using the repository configuration. `scripts/test.sh`
runs the PHPUnit `custom` test suite.

The corresponding direct tools are available through Composer's `vendor/bin/` directory. Use
the project configuration and installed dependencies rather than adding a second test runner.

## Dependency checks

```bash
make audit
make audit-php
make audit-js
make check-updates
```

These commands inspect Composer and JavaScript dependencies. Network access may be required for
audit and update checks.

## Before committing

Run the lint and custom test wrappers, then run the relevant Drupal configuration or frontend
build checks for the files changed. Keep generated build output and local environment files out
of commits unless the project explicitly tracks them.
