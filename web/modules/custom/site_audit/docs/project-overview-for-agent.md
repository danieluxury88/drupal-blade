We are continuing work on a custom Drupal module called **`site_audit`** for content & structure auditing.

The current file structure is:

```bash
daniel24@Flex13:~/Clients/ProtonSystems/uni.li/web/modules/custom/site_audit (migration-mvp)% tree -cL 5
.
├── site_audit.info.yml
├── site_audit.routing.yml
├── site_audit.services.yml
├── src
│   ├── Annotation
│   │   └── AuditReport.php
│   ├── Controller
│   │   ├── AuditReportIndexController.php
│   │   └── AuditReportViewController.php
│   ├── Plugin
│   │   └── AuditReport
│   │       ├── AuditReportBase.php
│   │       ├── AuditReportInterface.php
│   │       ├── ContentOverviewReport.php
│   │       ├── FieldOverviewReport.php
│   │       └── ParagraphDependenciesReport.php
│   ├── Report
│   │   └── AuditReportManager.php
│   └── Service
│       └── SiteAuditStructureCollector.php
└── site_audit.links.menu.yml
```

Quick summary of the architecture:

* We use a **plugin-based report system**:

  * `AuditReport` annotation, `AuditReportManager`, `AuditReportBase`, `AuditReportInterface`.
  * Reports live under `Plugin/AuditReport/*Report.php`.
  * Controllers:

    * `AuditReportIndexController` → list all reports.
    * `AuditReportViewController` → show a single report in HTML / JSON / Markdown.
* We already have 3 reports:

  1. `ContentOverviewReport`

     * Basic site info and counts of content types / paragraph types.
  2. `FieldOverviewReport`

     * Lists fields per content type and paragraph type, using the `SiteAuditStructureCollector`.
     * Includes links to edit each field.
  3. `ParagraphDependenciesReport`

     * Shows how paragraphs are used:

       * Which content types use which paragraph types.
       * Which paragraph types contain other paragraph types (nesting).
     * Uses `SiteAuditStructureCollector` and provides links:

       * Edit content type / paragraph type.
       * Manage fields.
       * Edit individual fields.
       * Paragraph type name links to its edit form.
* We already have a service: `SiteAuditStructureCollector`

  * `getNodeBundles()` → node bundles with label, description, `edit_path`, `fields_path`.
  * `getParagraphBundles()` → paragraph bundles with label, description, `edit_path`, `fields_path`.
  * `getFields('node')` / `getFields('paragraph')` → fields per bundle, with `edit_path`.
  * `getParagraphReferenceFields()` → node→paragraph and paragraph→paragraph fields, with cardinality and allowed paragraph types.

Environment & constraints:

* Drupal 10/11 project.
* We have full permissions and can rely on `/admin/structure/...` and `/admin/content` routes.
* We already expose each report in:

  * HTML
  * JSON
  * Markdown

---

🎯 **New goal: Content volume report with smooth navigation**

I want to create a **new report plugin** (e.g. `ContentVolumeReport` or similar) that focuses on **how many elements exist** in the database and provides convenient links to work on them.

Requirements:

1. **Scope of entities**

   * At minimum:

     * **Nodes** per content type.
     * **Paragraphs** per paragraph type.
   * Later it should be easy to extend to:

     * Media by type.
     * Taxonomy terms by vocabulary.
     * Etc. (so please design it with extension in mind).

2. **Counts**

   * For each **content type**:

     * Show the total **number of nodes** of that type.
   * For each **paragraph type**:

     * Show the total **number of paragraph entities** of that type.
   * Make sure entity queries explicitly use `accessCheck(FALSE)` where appropriate, because of the newer Drupal requirement that entity queries choose access behavior explicitly.

3. **Navigation / links**
   For **nodes** (content types), per row we want:

   * A label like: `page (Basic page)` or `article (Article)`.
   * A column with **node count**.
   * **Links**, opened in new tabs, to:

     * Edit the bundle: `/admin/structure/types/manage/{bundle}`
     * Manage fields: `/admin/structure/types/manage/{bundle}/fields`
     * View a **list of nodes of that type**, e.g.:

       * `/admin/content?type={bundle}` (or another good canonical URL that filters by type)

   For **paragraphs** (paragraph types), per row:

   * Label like: `hero (Hero section)`.
   * Paragraph entity count.
   * Links, opened in new tabs, to:

     * Edit paragraph type: `/admin/structure/paragraphs_type/{bundle}`
     * Manage fields: `/admin/structure/paragraphs_type/{bundle}/fields`

4. **Implementation style**

   * Reuse the existing architecture:

     * Add a new `AuditReport` plugin (e.g. `ContentVolumeReport.php`) extending `AuditReportBase`.
   * Prefer creating a **new service** like `SiteAuditContentCollector` (in `src/Service`) to encapsulate content counts:

     * `getNodeCountsByBundle(): array`
     * `getParagraphCountsByBundle(): array`
   * The report plugin should call:

     * `SiteAuditStructureCollector` for bundle metadata (labels, edit/fields paths).
     * `SiteAuditContentCollector` for counts.
   * The plugin must implement:

     * `buildData()` → return a structured array with node + paragraph counts and links.
     * `buildRender(array $data)` → HTML with tables and links (using `Link` + `Url`, `target="_blank"` for navigation links).
     * `buildMarkdown(array $data)` → Markdown tables, reusing the same information.

       * Use Markdown links for edit/manage/list URLs when reasonable.

5. **JSON output**

   * The JSON route should expose a structure that is easy to parse programmatically, e.g.:

     ```php
     [
       'nodes' => [
         'article' => [
           'label' => 'Article',
           'count' => 123,
           'edit_path' => '/admin/structure/types/manage/article',
           'fields_path' => '/admin/structure/types/manage/article/fields',
           'list_path' => '/admin/content?type=article',
         ],
         // ...
       ],
       'paragraphs' => [
         'hero' => [
           'label' => 'Hero section',
           'count' => 45,
           'edit_path' => '/admin/structure/paragraphs_type/hero',
           'fields_path' => '/admin/structure/paragraphs_type/hero/fields',
         ],
         // ...
       ],
     ]
     ```

6. **UI / UX**

   * Prefer something similar in style to the other reports:

     * Use `#type => 'details'` sections:

       * `Nodes by content type`
       * `Paragraphs by paragraph type`
     * Each section with a `#type => 'table'`:

       * Node table columns:

         * Machine name
         * Label
         * Count
         * Operations (Edit, Manage fields, View list)
       * Paragraph table columns:

         * Machine name
         * Label
         * Count
         * Operations (Edit, Manage fields)

---

Please:

1. Propose the design of `SiteAuditContentCollector` (methods, return formats).
2. Implement:

   * `SiteAuditContentCollector.php`
   * `ContentVolumeReport.php` (or another good name) under `Plugin/AuditReport`.
3. Update `site_audit.services.yml` to register the new collector.
4. Ensure:

   * Entity queries use `accessCheck(FALSE)` where we just need raw counts.
   * All links for operations open in new tabs (`target="_blank"`).
5. Show me the full code for the new service + report plugin, and any changes needed in `site_audit.services.yml`.

We don’t need to change the existing reports; just add this new one in a clean, extensible way.
