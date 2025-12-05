<?php

declare(strict_types=1);

namespace Drupal\site_audit\Plugin\AuditReport;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\site_audit\Plugin\AuditReport\AuditReportBase;
use Drupal\site_audit\Service\SiteAuditContentCollector;
use Drupal\site_audit\Service\SiteAuditStructureCollector;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Overview of node bundles (content types), their fields, and usage.
 *
 * @AuditReport(
 *   id = "nodes_overview",
 *   label = @Translation("Nodes overview"),
 *   description = @Translation("Shows all node bundles, how many nodes exist, how many fields they have, and details about each field.")
 * )
 */
class NodesOverviewReport extends AuditReportBase implements ContainerFactoryPluginInterface {

  /**
   * Structure collector.
   *
   * @var \Drupal\site_audit\Service\SiteAuditStructureCollector
   */
  protected SiteAuditStructureCollector $structureCollector;

  /**
   * Content collector.
   *
   * @var \Drupal\site_audit\Service\SiteAuditContentCollector
   */
  protected SiteAuditContentCollector $contentCollector;

  /**
   * Entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected EntityFieldManagerInterface $entityFieldManager;

  /**
   * Constructs the nodes overview report plugin instance.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\site_audit\Service\SiteAuditStructureCollector $structure_collector
   *   The structure collector service.
   * @param \Drupal\site_audit\Service\SiteAuditContentCollector $content_collector
   *   The content collector service.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    SiteAuditStructureCollector $structure_collector,
    SiteAuditContentCollector $content_collector,
    EntityFieldManagerInterface $entity_field_manager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->structureCollector = $structure_collector;
    $this->contentCollector = $content_collector;
    $this->entityFieldManager = $entity_field_manager;
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
      $container->get('site_audit.content_collector'),
      $container->get('entity_field.manager')
    );
  }

  /**
   * {@inheritdoc}
   *
   * Data structure is suitable for JSON serialization.
   *
   * [
   *   'nodes' => [
   *     'page' => [
   *       'machine_name' => 'page',
   *       'label' => 'Basic page',
   *       'description' => '...',
   *       'count' => 123,
   *       'fields_count' => 10,
   *       'fields' => [
   *         'field_body' => [
   *           'label' => 'Body',
   *           'type' => 'text_with_summary',
   *           'target_type' => null,
   *           'target_bundles' => [],
   *         ],
   *         'field_related_nodes' => [
   *           'label' => 'Related content',
   *           'type' => 'entity_reference',
   *           'target_type' => 'node',
   *           'target_bundles' => ['article', 'page'],
   *         ],
   *       ],
   *       'edit_path' => '/admin/structure/types/manage/page',
   *       'fields_path' => '/admin/structure/types/manage/page/fields',
   *       'list_path' => '/admin/content?type=page',
   *     ],
   *   ],
   * ]
   */
  public function buildData(): array {
    $node_bundles = $this->structureCollector->getNodeBundles();
    $bundle_ids = array_keys($node_bundles);

    // Get entity counts per node bundle.
    $counts = $this->contentCollector->getNodeCountsByBundle($bundle_ids);

    $nodes = [];

    foreach ($node_bundles as $bundle => $info) {
      // Get configurable fields for this node bundle.
      $field_definitions = $this->entityFieldManager->getFieldDefinitions('node', $bundle);

      $fields = [];
      foreach ($field_definitions as $field_name => $definition) {
        // Skip base fields; we only want configurable fields.
        if ($definition->getFieldStorageDefinition()->isBaseField()) {
          continue;
        }

        $field_type = $definition->getType();
        $label = $definition->getLabel();
        $target_type = NULL;
        $target_bundles = [];

        if (in_array($field_type, ['entity_reference', 'entity_reference_revisions'], TRUE)) {
          $settings = $definition->getSettings();
          $target_type = $settings['target_type'] ?? NULL;

          $handler_settings = $settings['handler_settings'] ?? [];
          if (!empty($handler_settings['target_bundles']) && is_array($handler_settings['target_bundles'])) {
            $target_bundles = array_keys($handler_settings['target_bundles']);
          }
        }

        $fields[$field_name] = [
          'label' => (string) $label,
          'type' => $field_type,
          'target_type' => $target_type,
          'target_bundles' => $target_bundles,
        ];
      }

      // Build list path (admin content filtered by type).
      $list_url = Url::fromRoute('system.admin_content', [], [
        'query' => ['type' => $bundle],
      ]);

      $nodes[$bundle] = [
        'machine_name' => $bundle,
        'label' => (string) ($info['label'] ?? $bundle),
        'description' => (string) ($info['description'] ?? ''),
        'count' => (int) ($counts[$bundle] ?? 0),
        'fields_count' => count($fields),
        'fields' => $fields,
        'edit_path' => (string) ($info['edit_path'] ?? "/admin/structure/types/manage/$bundle"),
        'fields_path' => (string) ($info['fields_path'] ?? "/admin/structure/types/manage/$bundle/fields"),
        'list_path' => $list_url->toString(),
      ];
    }

    return [
      'nodes' => $nodes,
    ];
  }

  /**
   * {@inheritdoc}
   *
   * Build HTML render array with a sortable table.
   */
  public function buildRender(array $data): array {
    $build = [
      '#type' => 'container',
    ];

    $nodes = $data['nodes'] ?? [];

    if (empty($nodes)) {
      $build['empty'] = [
        '#markup' => $this->t('No node bundles (content types) found.'),
      ];
      return $build;
    }

    // --- Sorting state from query params.
    $request = \Drupal::request();
    $order = $request->query->get('order') ?: 'machine_name';
    $sort = strtolower($request->query->get('sort') ?: 'asc');

    $allowed_order = ['machine_name', 'label', 'count', 'fields_count'];
    if (!in_array($order, $allowed_order, TRUE)) {
      $order = 'machine_name';
    }
    if (!in_array($sort, ['asc', 'desc'], TRUE)) {
      $sort = 'asc';
    }

    // --- Sort data in PHP.
    uasort($nodes, static function (array $a, array $b) use ($order, $sort): int {
      $av = $a[$order] ?? NULL;
      $bv = $b[$order] ?? NULL;

      if ($av == $bv) {
        return 0;
      }

      if ($sort === 'asc') {
        return ($av < $bv) ? -1 : 1;
      }
      else {
        return ($av > $bv) ? -1 : 1;
      }
    });

    // --- Build sortable header with links.
    $route_match = \Drupal::routeMatch();
    $route_name = $route_match->getRouteName();
    $route_params = $route_match->getRawParameters()->all();

    $columns = [
      'machine_name' => $this->t('Content type machine name'),
      'label' => $this->t('Label'),
      'count' => $this->t('Nodes'),
      'fields_count' => $this->t('Fields'),
    ];

    $header = [];

    foreach ($columns as $key => $label) {
      // Toggle sort direction if clicking the same column; otherwise default to asc.
      $next_sort = ($order === $key && $sort === 'asc') ? 'desc' : 'asc';

      $url = Url::fromRoute($route_name, $route_params, [
        'query' => [
          'order' => $key,
          'sort' => $next_sort,
        ],
      ]);

      // Indicate current sort column + direction in the label.
      $label_text = $label;
      if ($order === $key) {
        $arrow = $sort === 'asc' ? '↑' : '↓';
        $label_text = $this->t('@label @arrow', ['@label' => $label, '@arrow' => $arrow]);
      }

      $header[$key] = [
        'data' => Link::fromTextAndUrl($label_text, $url)->toRenderable(),
      ];
    }

    // Non-sortable columns.
    $header['fields'] = [
      'data' => $this->t('Fields detail'),
    ];
    $header['operations'] = [
      'data' => $this->t('Operations'),
    ];

    // --- Build rows.
    $rows = [];
    foreach ($nodes as $bundle => $info) {
      // Build fields detail markup.
      $field_lines = [];
      foreach ($info['fields'] as $field_name => $field_info) {
        $line = '`' . $field_name . '`';
        $line .= ' (' . $field_info['label'] . ')';
        $line .= ' – ' . $field_info['type'];

        if (!empty($field_info['target_type'])) {
          $line .= ' → ' . $field_info['target_type'];
          if (!empty($field_info['target_bundles'])) {
            $line .= ' [' . implode(', ', $field_info['target_bundles']) . ']';
          }
        }

        $field_lines[] = $line;
      }

      $fields_markup = $field_lines
        ? '<ul><li>' . implode('</li><li>', array_map('htmlspecialchars', $field_lines)) . '</li></ul>'
        : $this->t('No configurable fields.');

      // Operations: edit type, manage fields, view list.
      $edit_url = Url::fromUserInput($info['edit_path'], [
        'attributes' => ['target' => '_blank'],
      ]);
      $fields_url = Url::fromUserInput($info['fields_path'], [
        'attributes' => ['target' => '_blank'],
      ]);
      $list_url = Url::fromUserInput($info['list_path'], [
        'attributes' => ['target' => '_blank'],
      ]);

      $operations = [
        'edit' => [
          'title' => $this->t('Edit type'),
          'url' => $edit_url,
          'attributes' => ['target' => '_blank'],
        ],
        'fields' => [
          'title' => $this->t('Manage fields'),
          'url' => $fields_url,
          'attributes' => ['target' => '_blank'],
        ],
        'list' => [
          'title' => $this->t('View nodes'),
          'url' => $list_url,
          'attributes' => ['target' => '_blank'],
        ],
      ];

      $rows[] = [
        'machine_name' => [
          'data' => [
            '#markup' => $bundle,
          ],
        ],
        'label' => [
          'data' => [
            '#markup' => $info['label'],
          ],
        ],
        'count' => [
          'data' => [
            '#markup' => (string) $info['count'],
          ],
        ],
        'fields_count' => [
          'data' => [
            '#markup' => (string) $info['fields_count'],
          ],
        ],
        'fields' => [
          'data' => [
            '#markup' => $fields_markup,
          ],
        ],
        'operations' => [
          'data' => [
            '#type' => 'operations',
            '#links' => $operations,
          ],
        ],
      ];
    }

    $build['nodes'] = [
      '#type' => 'details',
      '#title' => $this->t('Node bundles overview'),
      '#open' => TRUE,
      'table' => [
        '#type' => 'table',
        '#header' => $header,
        '#rows' => $rows,
        '#empty' => $this->t('No node bundles found.'),
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
    $nodes = $data['nodes'] ?? [];
    if (empty($nodes)) {
      return "No node bundles (content types) found.\n";
    }

    $lines = [];

    $lines[] = '# Nodes overview';
    $lines[] = '';
    $lines[] = '| Machine name | Label | Nodes | Fields | Field details |';
    $lines[] = '| --- | --- | ---: | ---: | --- |';

    foreach ($nodes as $bundle => $info) {
      $field_chunks = [];
      foreach ($info['fields'] as $field_name => $field_info) {
        $chunk = sprintf(
          '`%s` (%s) – %s',
          $field_name,
          $field_info['label'],
          $field_info['type']
        );

        if (!empty($field_info['target_type'])) {
          $chunk .= ' → ' . $field_info['target_type'];
          if (!empty($field_info['target_bundles'])) {
            $chunk .= ' [' . implode(', ', $field_info['target_bundles']) . ']';
          }
        }

        $field_chunks[] = $chunk;
      }

      $fields_text = $field_chunks ? implode('<br>', $field_chunks) : 'No configurable fields.';

      $lines[] = sprintf(
        '| `%s` | %s | %d | %d | %s |',
        $bundle,
        $info['label'],
        $info['count'],
        $info['fields_count'],
        $fields_text
      );
    }

    $lines[] = '';

    return implode("\n", $lines);
  }

}
