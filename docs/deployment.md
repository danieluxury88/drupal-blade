# Deployment

Last verified: 2026-09-12

The repository currently exposes a production deployment target that copies a configured source
path to a remote host over SCP.

## Deploy

```bash
make deploy-prod
```

`make deploy` is an alias for `make deploy-prod`. Before running either target, provide the
deployment variables expected by the `Makefile`:

- `DEPLOY_USER`
- `DEPLOY_HOST_PROD`
- `DEPLOY_PATH_PROD`
- `DEPLOY_SRC`

The target fails when any required value is missing. Keep actual credentials and host-specific
values in ignored local configuration; do not place them in this document or in version control.

## Release checks

At minimum, run the relevant lint/tests and build the frontend assets before deployment. Drupal
configuration import, database updates, cache rebuilds, and the production rollback procedure
are environment-specific and should be documented here once the deployment owner confirms them.
