<?php

declare(strict_types=1);

namespace Drupal\site_audit\Plugin\AuditReport;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Drupal\site_audit\Service\SiteAuditConfigBundleCollector;
use Drupal\site_audit\Service\SiteAuditContentBundleCollector;
use Drupal\site_audit\Service\SiteAuditStructureCollector;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Deep analysis of a single content bundle (node type).
 *
 * @AuditReport(
 *   id = "bundle_deep_analysis",
 *   label = @Translation("Bundle deep analysis"),
 *   description = @Translation("Analyze a content bundle in depth: fields, paragraph usage, and OCM dependencies."),
 *   enabled = FALSE,
 * )
 */
class BundleDeepAnalysisReport extends AuditReportBase implements ContainerFactoryPluginInterface {

  /**
   * Structure collector.
   *
   * @var \Drupal\site_audit\Service\SiteAuditStructureCollector
   */
  protected SiteAuditStructureCollector $structureCollector;

  /**
   * Config bundle collector.
   *
   * @var \Drupal\site_audit\Service\SiteAuditConfigBundleCollector
   */
  protected SiteAuditConfigBundleCollector $configBundleCollector;

  /**
   * Content bundle collector.
   *
   * @var \Drupal\site_audit\Service\SiteAuditContentBundleCollector
   */
  protected SiteAuditContentBundleCollector $contentBundleCollector;

  /**
   * Constructs the BundleDeepAnalysisReport plugin instance.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\site_audit\Service\SiteAuditStructureCollector $structure_collector
   *   The structure collector service.
   * @param \Drupal\site_audit\Service\SiteAuditConfigBundleCollector $config_bundle_collector
   *   The config bundle collector service.
   * @param \Drupal\site_audit\Service\SiteAuditContentBundleCollector $content_bundle_collector
   *   The content bundle collector service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    SiteAuditStructureCollector $structure_collector,
    SiteAuditConfigBundleCollector $config_bundle_collector,
    SiteAuditContentBundleCollector $content_bundle_collector,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->structureCollector = $structure_collector;
    $this->configBundleCollector = $config_bundle_collector;
    $this->contentBundleCollector = $content_bundle_collector;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('site_audit.structure_collector'),
      $container->get('site_audit.config_bundle_collector'),
      $container->get('site_audit.content_bundle_collector')
    );
  }

  /**
   * {@inheritdoc}
   *
   * Returns a data structure suitable for JSON serialization.
   */
  public function buildData(): array {
    // All available node bundles.
    $node_bundles = $this->structureCollector->getNodeBundles();

    // Determine selected bundle from query (?bundle=page).
    $request = \Drupal::request();
    $selected_bundle = $request->query->get('bundle');

    if (!$selected_bundle || !isset($node_bundles[$selected_bundle])) {
      // Fallback: first bundle key or null.
      $selected_bundle = $node_bundles ? array_key_first($node_bundles) : NULL;
    }

    if ($selected_bundle === NULL) {
      // No node bundles at all.
      return [
        'selected_bundle' => NULL,
        'bundles' => [],
        'bundle_label' => '',
        'node_summary' => [],
        'config_summary' => [],
        'paragraph_usage' => [],
      ];
    }

    $bundle_info = $node_bundles[$selected_bundle];

    // Config perspective for the selected bundle.
    $config_summary = $this->configBundleCollector->getNodeBundleConfigSummary($selected_bundle);

    // Content perspective for the selected bundle.
    $node_summary = $this->contentBundleCollector->getNodeBundleUsageSummary($selected_bundle);

    // Paragraph usage for this bundle (may be empty until implemented).
    $paragraph_usage = $this->contentBundleCollector->getParagraphUsageForNodeBundle($selected_bundle);

    // Build a simple bundles list: machine_name => label.
    $bundles_list = [];
    foreach ($node_bundles as $machine_name => $info) {
      $bundles_list[$machine_name] = (string) ($info['label'] ?? $machine_name);
    }

    return [
      'selected_bundle' => $selected_bundle,
      'bundle_label' => (string) ($bundle_info['label'] ?? $selected_bundle),
      'bundles' => $bundles_list,
      'node_summary' => $node_summary,
      'config_summary' => $config_summary,
      'paragraph_usage' => $paragraph_usage,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildRender(array $data): array {
    $build = [
      '#type' => 'container',
    ];

    // If there are no bundles, show an empty message.
    if (empty($data['bundles']) || empty($data['selected_bundle'])) {
      $build['empty'] = [
        '#markup' => $this->t('No content bundles found.'),
      ];
      return $build;
    }

    $selected_bundle = $data['selected_bundle'];
    $bundle_label = $data['bundle_label'];

    // Determine current route to build bundle filter links.
    $route_match = \Drupal::routeMatch();
    $route_name = $route_match->getRouteName();
    $route_params = $route_match->getRawParameters()->all();

    // 🔹 Bundle selector: simple list of links.
    $bundle_links = [];
    foreach ($data['bundles'] as $bundle => $label) {
      $url = Url::fromRoute($route_name, $route_params, [
        'query' => ['bundle' => $bundle],
        'attributes' => ['target' => '_self'],
      ]);

      $bundle_links[] = [
        '#type' => 'link',
        '#title' => $bundle === $selected_bundle
          ? $this->t('@label (@bundle) [current]', ['@label' => $label, '@bundle' => $bundle])
          : $this->t('@label (@bundle)', ['@label' => $label, '@bundle' => $bundle]),
        '#url' => $url,
      ];
    }

    $build['bundle_selector'] = [
      '#type' => 'details',
      '#title' => $this->t('Select content bundle'),
      '#open' => TRUE,
      'list' => [
        '#theme' => 'item_list',
        '#items' => $bundle_links,
      ],
    ];

    // 🔹 Overview section.
    $node_summary = $data['node_summary'] ?? [];
    $config_summary = $data['config_summary'] ?? [];

    $build['overview'] = [
      '#type' => 'details',
      '#title' => $this->t('Overview for @bundle (@label)', [
        '@bundle' => $selected_bundle,
        '@label' => $bundle_label,
      ]),
      '#open' => TRUE,
      'info' => [
        '#theme' => 'item_list',
        '#items' => [
          $this->t('Machine name: @bundle', ['@bundle' => $selected_bundle]),
          $this->t('Label: @label', ['@label' => $bundle_label]),
          $this->t('Total nodes: @count', ['@count' => $node_summary['total'] ?? 0]),
          $this->t('Published: @count', ['@count' => $node_summary['published'] ?? 0]),
          $this->t('Unpublished: @count', ['@count' => $node_summary['unpublished'] ?? 0]),
        ],
      ],
    ];

    // 🔹 Fields table.
    $fields = $config_summary['fields'] ?? [];
    $field_rows = [];
    foreach ($fields as $field_name => $info) {
      $label = $info['label'] ?? $field_name;
      $type = $info['type'] ?? ($info['field_type'] ?? '');
      $edit_path = $info['edit_path'] ?? ($config_summary['fields_path'] ?? NULL);

      $row = [
        'field_name' => ['data' => ['#markup' => $field_name]],
        'label' => ['data' => ['#markup' => (string) $label]],
        'type' => ['data' => ['#markup' => (string) $type]],
      ];

      if ($edit_path) {
        $url = Url::fromUserInput($edit_path, [
          'attributes' => ['target' => '_blank'],
        ]);
        $row['operations'] = [
          'data' => [
            '#type' => 'operations',
            '#links' => [
              'edit' => [
                'title' => $this->t('Edit field'),
                'url' => $url,
                'attributes' => ['target' => '_blank'],
              ],
            ],
          ],
        ];
      }
      else {
        $row['operations'] = ['data' => ['#markup' => '']];
      }

      $field_rows[] = $row;
    }

    $build['fields'] = [
      '#type' => 'details',
      '#title' => $this->t('Fields on this bundle'),
      '#open' => TRUE,
      'table' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Field name'),
          $this->t('Label'),
          $this->t('Type'),
          $this->t('Operations'),
        ],
        '#rows' => $field_rows,
        '#empty' => $this->t('No fields found.'),
      ],
    ];

    // 🔹 Paragraph reference fields (config-level).
    $paragraph_fields = $config_summary['paragraph_fields'] ?? [];
    $paragraph_field_rows = [];
    foreach ($paragraph_fields as $field_name => $info) {
      $label = $info['label'] ?? $field_name;
      $allowed = $info['allowed_bundles'] ?? ($info['allowed_paragraphs'] ?? []);
      $allowed_list = $allowed ? implode(', ', (array) $allowed) : '';

      $paragraph_field_rows[] = [
        'field_name' => ['data' => ['#markup' => $field_name]],
        'label' => ['data' => ['#markup' => (string) $label]],
        'allowed_paragraphs' => ['data' => ['#markup' => $allowed_list]],
      ];
    }

    $build['paragraph_fields'] = [
      '#type' => 'details',
      '#title' => $this->t('Paragraph reference fields (config)'),
      '#open' => TRUE,
      'table' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Field name'),
          $this->t('Label'),
          $this->t('Allowed paragraph types'),
        ],
        '#rows' => $paragraph_field_rows,
        '#empty' => $this->t('No paragraph reference fields detected on this bundle.'),
      ],
    ];

    // 🔹 Paragraph usage (content-level) – will get more interesting once implemented.
    $paragraph_usage = $data['paragraph_usage'] ?? [];
    $usage_rows = [];
    foreach ($paragraph_usage as $paragraph_type => $info) {
      $usage_rows[] = [
        'paragraph_type' => ['data' => ['#markup' => $paragraph_type]],
        'label' => ['data' => ['#markup' => (string) ($info['label'] ?? $paragraph_type)]],
        'total_paragraphs' => ['data' => ['#markup' => (string) ($info['total_paragraphs'] ?? 0)]],
        'node_count' => ['data' => ['#markup' => (string) ($info['node_count'] ?? 0)]],
      ];
    }

    $build['paragraph_usage'] = [
      '#type' => 'details',
      '#title' => $this->t('Paragraph usage in this bundle (content)'),
      '#open' => TRUE,
      'table' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Paragraph type'),
          $this->t('Label'),
          $this->t('Total paragraphs'),
          $this->t('Nodes using this type'),
        ],
        '#rows' => $usage_rows,
        '#empty' => $this->t('Paragraph usage data not available yet or no paragraphs found.'),
      ],
    ];

    // 🔹 OCM dependencies.
    $ocm_deps = $config_summary['ocm_dependencies'] ?? [];
    $ocm_items = [];

    foreach ($ocm_deps as $dep_id => $dep_info) {
      $label = $dep_info['label'] ?? $dep_id;
      $description = $dep_info['description'] ?? '';
      $source = $dep_info['source'] ?? '';

      $text = $label;
      if ($description) {
        $text .= ' – ' . $description;
      }
      if ($source) {
        $text .= ' (' . $source . ')';
      }

      $ocm_items[] = $text;
    }

    $build['ocm_dependencies'] = [
      '#type' => 'details',
      '#title' => $this->t('OCM / OpenCampus dependencies for this bundle'),
      '#open' => FALSE,
      'list' => [
        '#theme' => 'item_list',
        '#items' => $ocm_items ?: [$this->t('No OCM-specific dependencies detected (collector may still be a stub).')],
      ],
    ];

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function buildMarkdown(array $data): string {
    if (empty($data['bundles']) || empty($data['selected_bundle'])) {
      return "No content bundles found.\n";
    }

    $selected_bundle = $data['selected_bundle'];
    $bundle_label = $data['bundle_label'] ?? $selected_bundle;
    $node_summary = $data['node_summary'] ?? [];
    $config_summary = $data['config_summary'] ?? [];
    $paragraph_usage = $data['paragraph_usage'] ?? [];
    $ocm_deps = $config_summary['ocm_dependencies'] ?? [];

    $lines = [];

    $lines[] = sprintf(
      '# Bundle deep analysis: `%s` (%s)',
      $selected_bundle,
      $bundle_label
    );
    $lines[] = '';

    // Overview.
    $lines[] = '## Overview';
    $lines[] = '';
    $lines[] = sprintf('- Machine name: `%s`', $selected_bundle);
    $lines[] = sprintf('- Label: %s', $bundle_label);
    $lines[] = sprintf('- Total nodes: %d', $node_summary['total'] ?? 0);
    $lines[] = sprintf('- Published: %d', $node_summary['published'] ?? 0);
    $lines[] = sprintf('- Unpublished: %d', $node_summary['unpublished'] ?? 0);
    $lines[] = '';

    // Fields.
    $lines[] = '## Fields on this bundle';
    $lines[] = '';
    $lines[] = '| Field name | Label | Type |';
    $lines[] = '| --- | --- | --- |';

    $fields = $config_summary['fields'] ?? [];
    if ($fields) {
      foreach ($fields as $field_name => $info) {
        $label = $info['label'] ?? $field_name;
        $type = $info['type'] ?? ($info['field_type'] ?? '');
        $lines[] = sprintf('| `%s` | %s | %s |', $field_name, $label, $type);
      }
    }
    else {
      $lines[] = '| *(none)* | | |';
    }
    $lines[] = '';

    // Paragraph reference fields.
    $lines[] = '## Paragraph reference fields (config)';
    $lines[] = '';
    $lines[] = '| Field name | Label | Allowed paragraph types |';
    $lines[] = '| --- | --- | --- |';

    $paragraph_fields = $config_summary['paragraph_fields'] ?? [];
    if ($paragraph_fields) {
      foreach ($paragraph_fields as $field_name => $info) {
        $label = $info['label'] ?? $field_name;
        $allowed = $info['allowed_bundles'] ?? ($info['allowed_paragraphs'] ?? []);
        $allowed_list = $allowed ? implode(', ', (array) $allowed) : '';
        $lines[] = sprintf('| `%s` | %s | %s |', $field_name, $label, $allowed_list);
      }
    }
    else {
      $lines[] = '| *(none)* | | |';
    }
    $lines[] = '';

    // Paragraph usage.
    $lines[] = '## Paragraph usage in this bundle (content)';
    $lines[] = '';
    $lines[] = '| Paragraph type | Label | Total paragraphs | Nodes using this type |';
    $lines[] = '| --- | --- | ---: | ---: |';

    if ($paragraph_usage) {
      foreach ($paragraph_usage as $paragraph_type => $info) {
        $label = $info['label'] ?? $paragraph_type;
        $total = $info['total_paragraphs'] ?? 0;
        $nodes = $info['node_count'] ?? 0;
        $lines[] = sprintf('| `%s` | %s | %d | %d |', $paragraph_type, $label, $total, $nodes);
      }
    }
    else {
      $lines[] = '| *(none)* | | 0 | 0 |';
    }
    $lines[] = '';

    // OCM dependencies.
    $lines[] = '## OCM / OpenCampus dependencies';
    $lines[] = '';

    if ($ocm_deps) {
      foreach ($ocm_deps as $dep_id => $dep_info) {
        $label = $dep_info['label'] ?? $dep_id;
        $description = $dep_info['description'] ?? '';
        $source = $dep_info['source'] ?? '';

        $line = sprintf('- **%s**', $label);
        if ($description) {
          $line .= ' – ' . $description;
        }
        if ($source) {
          $line .= ' (' . $source . ')';
        }
        $lines[] = $line;
      }
    }
    else {
      $lines[] = '- No OCM-specific dependencies detected (collector may still be a stub).';
    }

    $lines[] = '';

    return implode("\n", $lines);
  }

}
