# Architecture

Last verified: 2026-09-12

## Runtime layout

Drupal is installed using Composer's recommended-project structure. Composer places the public
application under `web/`; the web server should therefore use `web/` as its document root.

```text
DrupalBlade/
├── config/                           exported Drupal configuration
├── patches/                          local Composer patches
├── scripts/                          project PHP and shell utilities
├── web/
│   ├── modules/custom/               project-specific modules
│   ├── modules/dev/                  developer-only modules
│   └── themes/custom/proton_systems/ custom theme and frontend assets
├── composer.json                     PHP dependencies and install paths
├── package.json                      frontend scripts and dependencies
└── .ddev/                            local container configuration
```

## Custom code

- `web/modules/custom/site_audit` provides project-specific audit and reporting functionality.
- `web/modules/dev/config_package` provides developer tooling for building partial configuration
  packages and their paragraph dependencies.
- `web/themes/custom/proton_systems` contains the custom theme, Twig templates, Sass sources,
  JavaScript, images, icons, and compiled assets.

## Configuration and dependencies

`composer.json` and `composer.lock` define the PHP/Drupal dependency set. `package.json` and
`pnpm-lock.yaml` define the frontend dependency set. Drupal configuration is exported to
`config/sync/`, including enabled extensions, content model configuration, and translations.

The local `drupal/advent_calendar` fix is registered through the patch under
`patches/local/advent_calendar-fixes.patch`.
