# Commands

Last verified: 2026-09-12

Run commands from the repository root.

## Local environment

```bash
ddev start
ddev stop
```

Use `ddev drush ...` for Drupal CLI commands inside the web container. One-off content-model and
demo-content scripts are documented in their file headers under `scripts/`.

## Frontend

```bash
pnpm install
pnpm run build
pnpm run watch
pnpm run preview
```

The production build uses `webpack.production.config.js`; watch mode uses
`webpack.dev.config.js`.

## Make targets

```bash
make help
make audit
make check-updates
make clean
```

`make audit` runs Composer and pnpm audits. `make clean` removes the configured generated asset
and cache paths. The `sites-default-unlock` and `sites-default-lock` targets manage the write
protection used for `web/sites/default`.

## Drupal scripts

```bash
ddev drush php:script scripts/build-proton-systems-content-model.php
ddev drush php:script scripts/seed-proton-systems-demo-content.php
```

These scripts are intended for local/project setup tasks and are safe to re-run according to
their file-level documentation.
