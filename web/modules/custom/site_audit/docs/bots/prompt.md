You are helping me extend a custom Drupal 10/11 module called `site_audit`.

Context:

- The module provides a plugin-based architecture for "audit reports".
- There is a service `AuditReportManager` that discovers and runs report plugins.
- Each report is a plugin with an `@AuditReport` annotation and usually lives in:
  `modules/custom/site_audit/src/Plugin/AuditReport/`
- Examples of existing plugins (names are illustrative):
  - `ContentOverviewReport`
  - `FieldOverviewReport`
  - `ParagraphDependenciesReport`
- There is a controller that lists available reports and runs a single report:
  `/admin/reports/site-audit`
  `/admin/reports/site-audit/{report_id}`

Goal:

I want you to create a **new audit report plugin** class for this module.

### Requirements for the new report

1. Plugin name: `EnvironmentOverviewReport`
2. Purpose: give a quick overview of the Drupal environment and configuration, including at least:
   - Drupal core version
   - PHP version
   - Database driver (e.g., mysql, pgsql) and version if easily available
   - Default site language and additional enabled languages
   - Number of enabled modules vs disabled modules
   - List of a few “key” contrib modules detected (e.g. gin, admin_toolbar, pathauto, token, views_data_export, etc.) and whether they are enabled
3. The plugin should:
   - Use the existing `@AuditReport` annotation style used by the other plugins.
   - Implement the same interface / base class they use (e.g. `AuditReportBase` or similar – infer from the other report classes).
   - Return structured data (e.g., an array) that can be rendered by the existing Twig template used for reports.
   - Include a short description and label in the annotation.

4. Code style:
   - PHP 8+ syntax.
   - Type hints where possible.
   - Use dependency injection instead of `\Drupal::service()` whenever appropriate.
   - If necessary, add new service dependencies via the plugin’s constructor or `create()` method (container factory pattern).

### What you should output

1. The full PHP code for:
   - `src/Plugin/AuditReport/EnvironmentOverviewReport.php`

2. If needed, show any **small changes** to:
   - `site_audit.services.yml` (only if new services are required)
   - Any relevant interface or base class, but keep changes minimal and backwards compatible.

3. Make sure your example integrates cleanly with a typical Drupal 10 / 11 setup:
   - Use `\Drupal::VERSION` or appropriate APIs for Drupal version if helpful.
   - Use `\Drupal::moduleHandler()` or dependency-injected `ModuleHandlerInterface` to inspect modules.
   - Use `\Drupal::languageManager()` or dependency-injected `LanguageManagerInterface` to inspect languages.
   - For DB info, you can use `\Drupal::database()` or dependency-injected `Connection` to get the driver and version.

Please generate the code in a way that I can copy-paste directly into my existing `site_audit` module.
If something is ambiguous (like names of base classes or interfaces), infer them from typical Drupal patterns and clearly mark any assumptions in comments.
