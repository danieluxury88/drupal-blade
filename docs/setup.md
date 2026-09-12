# Setup

Last verified: 2026-09-12

## Prerequisites

Install and make available on the command line:

- PHP 8.3
- Composer
- pnpm 11.1.3 (the version recorded in `package.json`)
- DDEV with Docker

## First start

From the project root:

```bash
composer install
pnpm install
ddev start
```

The DDEV configuration uses project name `drupal-blade`, Drupal type `drupal11`, `web` as the
docroot, PHP 8.3, and MariaDB 10.11.

After the containers are running, complete the site's install or configuration import using the
project's normal Drupal workflow. The exact source database and environment-specific settings
are not committed to this documentation.

## Configuration

Drupal configuration is exported under `config/sync/`. Environment-specific local settings are
provided through DDEV and ignored environment files; do not commit credentials or private keys.
