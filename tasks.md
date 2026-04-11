# Todo

- [ ] Update readme.
- [ ] Update modules.
- [ ] Install modules: admin language switcher.
- [ ] Create module to manage tasks.
- [ ] Set Spanish as non admin language.
- [ ] Update site_audit module.
- [ ] Set up eCommerce.
- [ ] Set up Proton Drupal workflow.

## Config Inspector follow-up

- Done: investigated `config:inspect` errors from `ddev drush core:requirements`.
- Done: added a local schema compatibility shim for DRD-related contrib gaps.
- Done: fixed malformed DRD boolean filter defaults in `views.view.drd_domain`.
- Done: removed unsupported `time_diff.description` from the AI Logs view config.
- Done: reduced the full `config:inspect --only-error` result set to three non-DRD configs.
- Remaining config-inspector follow-up:
  - `editor.editor.basic_html`
  - `editor.editor.full_html`
  - `pwa.config`
