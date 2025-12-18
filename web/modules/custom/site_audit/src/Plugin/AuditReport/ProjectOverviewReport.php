<?php

declare(strict_types=1);

namespace Drupal\site_audit\Plugin\AuditReport;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\site_audit\Service\SiteAuditProjectCollector;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * High-level project overview report.
 *
 * @AuditReport(
 *   id = "project_overview",
 *   label = @Translation("Project overview"),
 *   description = @Translation("Shows high-level Drupal project information and configuration footprint.")
 * )
 */
class ProjectOverviewReport extends AuditReportBase implements ContainerFactoryPluginInterface {

  /**
   * Project collector.
   *
   * @var \Drupal\site_audit\Service\SiteAuditProjectCollector
   */
  protected SiteAuditProjectCollector $projectCollector;

  /**
   * Constructs the ProjectOverviewReport plugin instance.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    SiteAuditProjectCollector $project_collector,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->projectCollector = $project_collector;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('site_audit.project_collector')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildData(): array {
    // Delegate all data gathering to the collector.
    return $this->projectCollector->getProjectOverview();
  }

  /**
   * {@inheritdoc}
   *
   * Build HTML render array with details sections + simple tables.
   */
  public function buildRender(array $data): array {
    $build = [
      '#type' => 'container',
    ];

    $drupal = $data['drupal'] ?? [];
    $modules = $data['modules'] ?? [];
    $languages = $data['languages'] ?? [];
    $config_collections = $data['config_collections'] ?? [];
    $content_model = $data['content_model'] ?? [];

    // ------------------------------------------------------------------
    // Project summary.
    // ------------------------------------------------------------------
    $summary_rows = [];

    $summary_rows[] = [
      ['data' => $this->t('Drupal core version')],
      ['data' => $drupal['core_version'] ?? ''],
    ];
    $summary_rows[] = [
      ['data' => $this->t('PHP version')],
      ['data' => $drupal['php_version'] ?? ''],
    ];
    $summary_rows[] = [
      ['data' => $this->t('Site name')],
      ['data' => $drupal['site_name'] ?? ''],
    ];
    $summary_rows[] = [
      ['data' => $this->t('Site email')],
      ['data' => $drupal['site_mail'] ?? ''],
    ];
    $summary_rows[] = [
      ['data' => $this->t('Default theme')],
      ['data' => $drupal['default_theme'] ?? ''],
    ];
    $summary_rows[] = [
      ['data' => $this->t('Admin theme')],
      ['data' => $drupal['admin_theme'] ?? $this->t('None / inherited')],
    ];
    $summary_rows[] = [
      ['data' => $this->t('Database driver')],
      ['data' => $drupal['database']['driver'] ?? ''],
    ];
    $summary_rows[] = [
      ['data' => $this->t('Database name')],
      ['data' => $drupal['database']['database'] ?? ''],
    ];

    $build['project_summary'] = [
      '#type' => 'details',
      '#title' => $this->t('Project summary'),
      '#open' => TRUE,
      'table' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Property'),
          $this->t('Value'),
        ],
        '#rows' => $summary_rows,
      ],
    ];

    // ------------------------------------------------------------------
    // Modules summary.
    // ------------------------------------------------------------------
    $enabled = (int) ($modules['enabled'] ?? 0);
    $disabled = (int) ($modules['disabled'] ?? 0);
    $custom_enabled = (int) ($modules['custom_enabled'] ?? 0);
    $contrib_enabled = (int) ($modules['contrib_enabled'] ?? 0);

    $modules_rows = [
      [
        ['data' => $this->t('Enabled modules')],
        ['data' => (string) $enabled],
      ],
      [
        ['data' => $this->t('Disabled modules')],
        ['data' => (string) $disabled],
      ],
      [
        ['data' => $this->t('Enabled custom modules')],
        ['data' => (string) $custom_enabled],
      ],
      [
        ['data' => $this->t('Enabled contrib/core modules')],
        ['data' => (string) $contrib_enabled],
      ],
    ];

    $build['modules'] = [
      '#type' => 'details',
      '#title' => $this->t('Modules'),
      '#open' => TRUE,
      'table' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Metric'),
          $this->t('Value'),
        ],
        '#rows' => $modules_rows,
      ],
    ];

    // ------------------------------------------------------------------
    // Modules list (for comparisons / audits).
    // ------------------------------------------------------------------
    $modules_list = $modules['modules'] ?? [];
    $module_rows = [];

    foreach ($modules_list as $name => $info) {
      $status_label = !empty($info['status']) ? $this->t('Enabled') : $this->t('Disabled');
      $type_label = $info['type'] ?? '';
      $package = $info['package'] ?? '';
      $version = $info['version'] ?? '';
      $relative_path = $info['relative_path'] ?? '';

      $module_rows[] = [
        'name' => ['data' => ['#markup' => $name]],
        'status' => ['data' => ['#markup' => $status_label]],
        'type' => ['data' => ['#markup' => $type_label]],
        'package' => ['data' => ['#markup' => $package]],
        'version' => ['data' => ['#markup' => $version]],
        'path' => ['data' => ['#markup' => $relative_path]],
      ];
    }

    $build['modules_list'] = [
      '#type' => 'details',
      '#title' => $this->t('Modules list (enabled & disabled)'),
      '#open' => FALSE,
      'table' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Machine name'),
          $this->t('Status'),
          $this->t('Type'),
          $this->t('Package'),
          $this->t('Version'),
          $this->t('Path'),
        ],
        '#rows' => $module_rows,
        '#empty' => $this->t('No modules found.'),
      ],
    ];

    // ------------------------------------------------------------------
    // Languages.
    // ------------------------------------------------------------------
    $language_rows = [];
    foreach ($languages as $langcode => $info) {
      $label = $info['name'] ?? $langcode;
      $is_default = !empty($info['default']);
      $direction = $info['direction'] ?? 'ltr';

      $language_rows[] = [
        'code' => ['data' => ['#markup' => $langcode]],
        'label' => ['data' => ['#markup' => $label]],
        'default' => [
          'data' => [
            '#markup' => $is_default ? $this->t('Yes') : $this->t('No'),
          ],
        ],
        'direction' => [
          'data' => [
            '#markup' => $direction,
          ],
        ],
      ];
    }

    $build['languages'] = [
      '#type' => 'details',
      '#title' => $this->t('Languages'),
      '#open' => TRUE,
      'table' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Language code'),
          $this->t('Label'),
          $this->t('Default'),
          $this->t('Direction'),
        ],
        '#rows' => $language_rows,
        '#empty' => $this->t('No languages configured.'),
      ],
    ];

    // ------------------------------------------------------------------
    // Config collections.
    // ------------------------------------------------------------------
    $config_rows = [];
    foreach ($config_collections as $name => $info) {
      $collection_label = ($name === 'base')
        ? $this->t('Base')
        : $name;

      $config_rows[] = [
        'name' => ['data' => ['#markup' => $collection_label]],
        'items' => [
          'data' => [
            '#markup' => (string) ($info['item_count'] ?? 0),
          ],
        ],
      ];
    }

    $build['config'] = [
      '#type' => 'details',
      '#title' => $this->t('Configuration collections'),
      '#open' => TRUE,
      'table' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Collection'),
          $this->t('# config items'),
        ],
        '#rows' => $config_rows,
        '#empty' => $this->t('No configuration collections found.'),
      ],
    ];

    // ------------------------------------------------------------------
    // Content model summary.
    // ------------------------------------------------------------------
    $cm = $content_model;

    $content_rows = [
      [
        ['data' => $this->t('Content types')],
        ['data' => (string) ($cm['content_types'] ?? 0)],
      ],
      [
        ['data' => $this->t('Taxonomy vocabularies')],
        ['data' => (string) ($cm['taxonomy_vocabularies'] ?? 0)],
      ],
      [
        ['data' => $this->t('Media types')],
        ['data' => (string) ($cm['media_types'] ?? 0)],
      ],
      [
        ['data' => $this->t('Views')],
        ['data' => (string) ($cm['views'] ?? 0)],
      ],
      [
        ['data' => $this->t('View displays (total)')],
        ['data' => (string) ($cm['view_displays'] ?? 0)],
      ],
    ];

    $build['content_model'] = [
      '#type' => 'details',
      '#title' => $this->t('Content model summary'),
      '#open' => TRUE,
      'table' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Entity / feature'),
          $this->t('Count'),
        ],
        '#rows' => $content_rows,
      ],
    ];

    return $build;
  }

  /**
   * {@inheritdoc}
   *
   * Build a Markdown representation of the report.
   */
  public function buildMarkdown(array $data): string {
    $drupal = $data['drupal'] ?? [];
    $modules = $data['modules'] ?? [];
    $languages = $data['languages'] ?? [];
    $config_collections = $data['config_collections'] ?? [];
    $cm = $data['content_model'] ?? [];

    $modules_list = $modules['modules'] ?? [];

    $lines = [];

    $lines[] = '# Project overview';
    $lines[] = '';

    // Project summary.
    $lines[] = '## Project summary';
    $lines[] = '';
    $lines[] = '- **Drupal core version:** ' . ($drupal['core_version'] ?? '');
    $lines[] = '- **PHP version:** ' . ($drupal['php_version'] ?? '');
    $lines[] = '- **Site name:** ' . ($drupal['site_name'] ?? '');
    $lines[] = '- **Site email:** ' . ($drupal['site_mail'] ?? '');
    $lines[] = '- **Default theme:** ' . ($drupal['default_theme'] ?? '');
    $lines[] = '- **Admin theme:** ' . ($drupal['admin_theme'] ?? 'None / inherited');
    $lines[] = '- **Database driver:** ' . ($drupal['database']['driver'] ?? '');
    $lines[] = '- **Database name:** ' . ($drupal['database']['database'] ?? '');
    $lines[] = '';

    // Modules summary.
    $lines[] = '## Modules';
    $lines[] = '';
    $lines[] = '| Metric | Value |';
    $lines[] = '| --- | ---: |';
    $lines[] = sprintf('| Enabled modules | %d |', (int) ($modules['enabled'] ?? 0));
    $lines[] = sprintf('| Disabled modules | %d |', (int) ($modules['disabled'] ?? 0));
    $lines[] = sprintf('| Enabled custom modules | %d |', (int) ($modules['custom_enabled'] ?? 0));
    $lines[] = sprintf('| Enabled contrib/core modules | %d |', (int) ($modules['contrib_enabled'] ?? 0));
    $lines[] = '';

    // Modules list.
    $lines[] = '### Modules list (enabled & disabled)';
    $lines[] = '';
    if (!empty($modules_list)) {
      $lines[] = '| Machine name | Status | Type | Package | Version | Path |';
      $lines[] = '| --- | --- | --- | --- | --- | --- |';
      foreach ($modules_list as $name => $info) {
        $status_label = !empty($info['status']) ? 'Enabled' : 'Disabled';
        $type_label = $info['type'] ?? '';
        $package = $info['package'] ?? '';
        $version = $info['version'] ?? '';
        $path = $info['relative_path'] ?? '';

        $lines[] = sprintf(
          '| `%s` | %s | %s | %s | %s | %s |',
          $name,
          $status_label,
          $type_label,
          $package,
          $version,
          $path
        );
      }
      $lines[] = '';
    }
    else {
      $lines[] = '_No modules found._';
      $lines[] = '';
    }

    // Languages.
    $lines[] = '## Languages';
    $lines[] = '';
    if (!empty($languages)) {
      $lines[] = '| Code | Label | Default | Direction |';
      $lines[] = '| --- | --- | --- | --- |';
      foreach ($languages as $langcode => $info) {
        $label = $info['name'] ?? $langcode;
        $default = !empty($info['default']) ? 'Yes' : 'No';
        $direction = $info['direction'] ?? 'ltr';
        $lines[] = sprintf(
          '| `%s` | %s | %s | %s |',
          $langcode,
          $label,
          $default,
          $direction
        );
      }
      $lines[] = '';
    }
    else {
      $lines[] = '_No languages configured._';
      $lines[] = '';
    }

    // Config collections.
    $lines[] = '## Configuration collections';
    $lines[] = '';
    $lines[] = '| Collection | # items |';
    $lines[] = '| --- | ---: |';
    foreach ($config_collections as $name => $info) {
      $label = ($name === 'base') ? 'Base' : $name;
      $lines[] = sprintf(
        '| %s | %d |',
        $label,
        (int) ($info['item_count'] ?? 0)
      );
    }
    $lines[] = '';

    // Content model.
    $lines[] = '## Content model summary';
    $lines[] = '';
    $lines[] = '| Entity / feature | Count |';
    $lines[] = '| --- | ---: |';
    $lines[] = sprintf('| Content types | %d |', (int) ($cm['content_types'] ?? 0));
    $lines[] = sprintf('| Taxonomy vocabularies | %d |', (int) ($cm['taxonomy_vocabularies'] ?? 0));
    $lines[] = sprintf('| Media types | %d |', (int) ($cm['media_types'] ?? 0));
    $lines[] = sprintf('| Views | %d |', (int) ($cm['views'] ?? 0));
    $lines[] = sprintf('| View displays (total) | %d |', (int) ($cm['view_displays'] ?? 0));
    $lines[] = '';

    return implode("\n", $lines);
  }

}
