# DrupalBlade

DrupalBlade is a Drupal 11 codebase for local development with DDEV and Composer. The repository is based on Drupal's recommended project layout, with the web root in [`web/`](/home/daniel24/Projects/DrupalBlade/web) and project-level configuration in [`config/`](/home/daniel24/Projects/DrupalBlade/config).

## Stack

- Drupal 11
- PHP 8.3
- MariaDB 10.11
- DDEV for local environment management
- Composer for dependency management
- PHPUnit and PHPCS for testing and linting

## Project Layout

- [`composer.json`](/home/daniel24/Projects/DrupalBlade/composer.json): project dependencies and Drupal install paths
- [`web/`](/home/daniel24/Projects/DrupalBlade/web): Drupal document root
- [`config/`](/home/daniel24/Projects/DrupalBlade/config): exported site configuration
- [`patches/`](/home/daniel24/Projects/DrupalBlade/patches): local Composer patches
- [`.ddev/`](/home/daniel24/Projects/DrupalBlade/.ddev): local development environment configuration

## Custom Code

This repository currently contains a small amount of project-specific code:

- [`web/modules/dev/config_package`](/home/daniel24/Projects/DrupalBlade/web/modules/dev/config_package): developer tooling to analyze a content type and build partial config packages, including paragraph dependencies
- [`web/modules/custom/site_audit`](/home/daniel24/Projects/DrupalBlade/web/modules/custom/site_audit): a custom audit/reporting module with admin report pages and export formats

Most other modules in the codebase are contributed Drupal packages managed through Composer.

## Local Setup

Typical first-time setup:

```bash
composer install
ddev start
```

After the containers are running, install or import the Drupal site using your normal project workflow. The DDEV project is configured in [`.ddev/config.yaml`](/home/daniel24/Projects/DrupalBlade/.ddev/config.yaml) with:

- project name: `drupal-blade`
- Drupal type: `drupal11`
- docroot: `web`
- PHP: `8.3`
- database: `mariadb:10.11`

## Development Notes

- The project follows the standard Drupal recommended-project structure.
- There is no custom theme in the repository at the moment; the installed contributed admin theme is Gin.
- A local patch is applied for `drupal/advent_calendar` from [`patches/local/advent_calendar-fixes.patch`](/home/daniel24/Projects/DrupalBlade/patches/local/advent_calendar-fixes.patch).

## Languages

Exported configuration indicates the site is set up for multilingual use with:

- English (`en`)
- Spanish (`es`)
- Drupal special language entries for `und` (`Not specified`) and `zxx` (`Not applicable`)

There is also language-specific configuration under [`config/sync/language/es/`](/home/daniel24/Projects/DrupalBlade/config/sync/language/es), which indicates Spanish translations are present for part of the exported configuration.

## Roles

The exported roles currently defined in configuration are:

- Anonymous user
- Authenticated user
- Content editor
- Administrator

Role definitions are stored in [`config/sync/user.role.*.yml`](/home/daniel24/Projects/DrupalBlade/config/sync/user.role.administrator.yml), with Spanish label translations present under [`config/sync/language/es/`](/home/daniel24/Projects/DrupalBlade/config/sync/language/es).

## Content Types

The exported node content types currently present in configuration are:

- Article
- Basic page
- Book page
- Forum topic
- ToDo

Related content structure also exists for:

- Paragraph type: Text
- Webform: Contact
- Commerce store type: Online
- Commerce product types: Default, Drawing Slider

The node type definitions are stored under [`config/sync/node.type.*.yml`](/home/daniel24/Projects/DrupalBlade/config/sync/node.type.article.yml).

## Relevant Features

Based on the enabled modules in [`config/sync/core.extension.yml`](/home/daniel24/Projects/DrupalBlade/config/sync/core.extension.yml) and the installed packages in [`composer.json`](/home/daniel24/Projects/DrupalBlade/composer.json), the most relevant platform features currently available are:

- Multilingual support with content/config translation
- Editorial workflow support through roles, scheduling, and content moderation
- Flexible content authoring with Paragraphs, field grouping, media, media library, and inline entity form support
- Commerce capabilities including store, product, cart, checkout, and payment foundations
- Forms and contact handling through Webform and the core contact module
- SEO and URL management through Metatag, Pathauto, Redirect, Simple Sitemap, and Google Analytics integration
- Admin/developer tooling through Admin Toolbar, Gin Toolbar, Coffee, Devel, Config Split, Config Inspector, Upgrade Status, and project overview tooling
- Security and operational support through Honeypot, Backup and Migrate, Seckit, Encrypt/Key, and Trash
- Progressive web app support through the PWA modules
- Project-specific audit/reporting and config packaging through the custom `site_audit` and `config_package` modules

## Quality Checks

Two helper scripts already exist at the project root:

```bash
./lint.sh
./test.sh
```

They currently wrap:

- PHPCS / PHPCBF via [`lint.sh`](/home/daniel24/Projects/DrupalBlade/lint.sh)
- PHPUnit custom test suite via [`test.sh`](/home/daniel24/Projects/DrupalBlade/test.sh)

## Status

This README is an intentionally high-level first pass. It should be expanded later with project-specific install steps, configuration/import workflow, deployment notes, and any conventions the team wants to standardize.
