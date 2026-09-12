# Overview

Last verified: 2026-09-12

DrupalBlade is a Drupal 11 application using the Drupal recommended-project layout. It is
developed locally with DDEV and Composer, with frontend assets managed by pnpm and Webpack.

## Stack

- Drupal 11
- PHP 8.3
- MariaDB 10.11
- DDEV for local containers
- Composer for PHP dependencies
- pnpm, Sass, and Webpack for frontend assets
- PHPUnit and PHPCS for quality checks

## Project areas

- `web/` is the Drupal document root.
- `config/` contains exported Drupal configuration.
- `web/modules/custom/` contains project-specific Drupal modules.
- `web/modules/dev/` contains developer tooling, including `config_package`.
- `web/themes/custom/proton_systems/` contains the custom frontend theme and its source assets.
- `patches/` contains local Composer patches.
- `.ddev/` contains local environment configuration.

The repository also contains contributed Drupal modules and themes installed through Composer.
