<?php

declare(strict_types=1);

namespace Drupal\site_audit\Plugin\AuditReport;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\site_audit\Service\SiteAuditContentCollector;
use Drupal\site_audit\Service\SiteAuditStructureCollector;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Content volume report: nodes & paragraphs by bundle.
 *
 * @AuditReport(
 *   id = "content_volume",
 *   label = @Translation("Content volume"),
 *   description = @Translation("Shows counts of nodes and paragraphs per bundle, with quick navigation links.")
 * )
 */
class ContentVolumeReport extends AuditReportBase implements ContainerFactoryPluginInterface {

  /**
   * The structure collector service.
   *
   * @var \Drupal\site_audit\Service\SiteAuditStructureCollector
   */
  protected SiteAuditStructureCollector $structureCollector;

  /**
   * The content collector service.
   *
   * @var \Drupal\site_audit\Service\SiteAuditContentCollector
   */
  protected SiteAuditContentCollector $contentCollector;

  /**
   * Constructs a ContentVolumeReport plugin instance.
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
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    SiteAuditStructureCollector $structure_collector,
    SiteAuditContentCollector $content_collector,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->structureCollector = $structure_collector;
    $this->contentCollector = $content_collector;
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
      $container->get('site_audit.content_collector')
    );
  }

  /**
   * {@inheritdoc}
   *
   * Returns a data structure also suitable for JSON serialization.
   */
  public function buildData(): array {
    // Get bundle metadata from the existing structure collector.
    $node_bundles = $this->structureCollector->getNodeBundles();
    $paragraph_bundles = $this->structureCollector->getParagraphBundles();

    // Get counts for each bundle.
    $node_counts = $this->contentCollector->getNodeCountsByBundle(array_keys($node_bundles));
    $paragraph_counts = $this->contentCollector->getParagraphCountsByBundle(array_keys($paragraph_bundles));

    $data = [
      'nodes' => [],
      'paragraphs' => [],
    ];

    // Nodes.
    foreach ($node_bundles as $bundle => $info) {
      $count = $node_counts[$bundle] ?? 0;

      // URL for admin content listing filtered by type.
      $list_url = Url::fromRoute('system.admin_content', [], [
        'query' => ['type' => $bundle],
      ]);

      $data['nodes'][$bundle] = [
        'machine_name' => $bundle,
        'label' => (string) ($info['label'] ?? $bundle),
        'description' => (string) ($info['description'] ?? ''),
        'count' => $count,
        'edit_path' => (string) ($info['edit_path'] ?? "/admin/structure/types/manage/$bundle"),
        'fields_path' => (string) ($info['fields_path'] ?? "/admin/structure/types/manage/$bundle/fields"),
        'list_path' => $list_url->toString(),
      ];
    }

    // Paragraphs.
    foreach ($paragraph_bundles as $bundle => $info) {
      $count = $paragraph_counts[$bundle] ?? 0;

      $data['paragraphs'][$bundle] = [
        'machine_name' => $bundle,
        'label' => (string) ($info['label'] ?? $bundle),
        'description' => (string) ($info['description'] ?? ''),
        'count' => $count,
        'edit_path' => (string) ($info['edit_path'] ?? "/admin/structure/paragraphs_type/$bundle"),
        'fields_path' => (string) ($info['fields_path'] ?? "/admin/structure/paragraphs_type/$bundle/fields"),
      ];
    }

    return $data;
  }

  /**
   * {@inheritdoc}
   *
   * Build HTML render array with "details" + "table" sections.
   */
  public function buildRender(array $data): array {
    $build = [
      '#type' => 'container',
    ];

    $nodes = $data['nodes'] ?? [];
    $paragraphs = $data['paragraphs'] ?? [];

    $request = \Drupal::request();
    $route_match = \Drupal::routeMatch();
    $route_name = $route_match->getRouteName();
    $route_params = $route_match->getRawParameters()->all();
    $current_query = $request->query->all();

    // ------------------------
    // Nodes: sorting in PHP.
    // ------------------------
    if (!empty($nodes)) {
      $node_order = $request->query->get('nodes_order') ?: 'machine_name';
      $node_sort = strtolower($request->query->get('nodes_sort') ?: 'asc');

      $allowed_order = ['machine_name', 'label', 'count'];
      if (!in_array($node_order, $allowed_order, TRUE)) {
        $node_order = 'machine_name';
      }
      if (!in_array($node_sort, ['asc', 'desc'], TRUE)) {
        $node_sort = 'asc';
      }

      uasort($nodes, static function (array $a, array $b) use ($node_order, $node_sort): int {
        $av = $a[$node_order] ?? NULL;
        $bv = $b[$node_order] ?? NULL;

        if ($av == $bv) {
          return 0;
        }

        if ($node_sort === 'asc') {
          return ($av < $bv) ? -1 : 1;
        }
        else {
          return ($av > $bv) ? -1 : 1;
        }
      });

      // Build sortable header for nodes.
      $node_columns = [
        'machine_name' => $this->t('Machine name'),
        'label' => $this->t('Label'),
        'count' => $this->t('Count'),
      ];

      $node_header = [];
      foreach ($node_columns as $key => $label) {
        $next_sort = ($node_order === $key && $node_sort === 'asc') ? 'desc' : 'asc';

        $query = $current_query;
        $query['nodes_order'] = $key;
        $query['nodes_sort'] = $next_sort;

        $url = Url::fromRoute($route_name, $route_params, [
          'query' => $query,
        ]);

        $label_text = $label;
        if ($node_order === $key) {
          $arrow = $node_sort === 'asc' ? '↑' : '↓';
          $label_text = $this->t('@label @arrow', ['@label' => $label, '@arrow' => $arrow]);
        }

        $node_header[$key] = [
          'data' => Link::fromTextAndUrl($label_text, $url)->toRenderable(),
        ];
      }

      // Non-sortable column.
      $node_header['operations'] = [
        'data' => $this->t('Operations'),
      ];

      // Build node rows.
      $node_rows = [];
      foreach ($nodes as $bundle => $info) {
        $edit_url = Url::fromUserInput($info['edit_path'], [
          'attributes' => ['target' => '_blank'],
        ]);
        $fields_url = Url::fromUserInput($info['fields_path'], [
          'attributes' => ['target' => '_blank'],
        ]);
        // list_path is a path like "/admin/content?type=article".
        $list_url = Url::fromUserInput($info['list_path'], [
          'attributes' => ['target' => '_blank'],
        ]);

        $operations = [
          'edit' => [
            'title' => $this->t('Edit'),
            'url' => $edit_url,
            'attributes' => ['target' => '_blank'],
          ],
          'fields' => [
            'title' => $this->t('Manage fields'),
            'url' => $fields_url,
            'attributes' => ['target' => '_blank'],
          ],
          'list' => [
            'title' => $this->t('View list'),
            'url' => $list_url,
            'attributes' => ['target' => '_blank'],
          ],
        ];

        $node_rows[] = [
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
            'data' => Link::fromTextAndUrl((string) $info['count'], $list_url)->toRenderable(),
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
        '#title' => $this->t('Nodes by content type'),
        '#open' => TRUE,
        'table' => [
          '#type' => 'table',
          '#header' => $node_header,
          '#rows' => $node_rows,
          '#empty' => $this->t('No content types found.'),
        ],
      ];
    }
    else {
      $build['nodes'] = [
        '#type' => 'details',
        '#title' => $this->t('Nodes by content type'),
        '#open' => TRUE,
        'table' => [
          '#type' => 'table',
          '#header' => [
            $this->t('Machine name'),
            $this->t('Label'),
            $this->t('Count'),
            $this->t('Operations'),
          ],
          '#rows' => [],
          '#empty' => $this->t('No content types found.'),
        ],
      ];
    }

    // ------------------------
    // Paragraphs: sorting in PHP.
    // ------------------------
    if (!empty($paragraphs)) {
      $para_order = $request->query->get('paragraphs_order') ?: 'machine_name';
      $para_sort = strtolower($request->query->get('paragraphs_sort') ?: 'asc');

      $allowed_order = ['machine_name', 'label', 'count'];
      if (!in_array($para_order, $allowed_order, TRUE)) {
        $para_order = 'machine_name';
      }
      if (!in_array($para_sort, ['asc', 'desc'], TRUE)) {
        $para_sort = 'asc';
      }

      uasort($paragraphs, static function (array $a, array $b) use ($para_order, $para_sort): int {
        $av = $a[$para_order] ?? NULL;
        $bv = $b[$para_order] ?? NULL;

        if ($av == $bv) {
          return 0;
        }

        if ($para_sort === 'asc') {
          return ($av < $bv) ? -1 : 1;
        }
        else {
          return ($av > $bv) ? -1 : 1;
        }
      });

      // Build sortable header for paragraphs.
      $para_columns = [
        'machine_name' => $this->t('Machine name'),
        'label' => $this->t('Label'),
        'count' => $this->t('Count'),
      ];

      $para_header = [];
      foreach ($para_columns as $key => $label) {
        $next_sort = ($para_order === $key && $para_sort === 'asc') ? 'desc' : 'asc';

        $query = $current_query;
        $query['paragraphs_order'] = $key;
        $query['paragraphs_sort'] = $next_sort;

        $url = Url::fromRoute($route_name, $route_params, [
          'query' => $query,
        ]);

        $label_text = $label;
        if ($para_order === $key) {
          $arrow = $para_sort === 'asc' ? '↑' : '↓';
          $label_text = $this->t('@label @arrow', ['@label' => $label, '@arrow' => $arrow]);
        }

        $para_header[$key] = [
          'data' => Link::fromTextAndUrl($label_text, $url)->toRenderable(),
        ];
      }

      // Non-sortable column.
      $para_header['operations'] = [
        'data' => $this->t('Operations'),
      ];

      // Build paragraph rows.
      $paragraph_rows = [];
      foreach ($paragraphs as $bundle => $info) {
        $edit_url = Url::fromUserInput($info['edit_path'], [
          'attributes' => ['target' => '_blank'],
        ]);
        $fields_url = Url::fromUserInput($info['fields_path'], [
          'attributes' => ['target' => '_blank'],
        ]);

        $operations = [
          'edit' => [
            'title' => $this->t('Edit'),
            'url' => $edit_url,
            'attributes' => ['target' => '_blank'],
          ],
          'fields' => [
            'title' => $this->t('Manage fields'),
            'url' => $fields_url,
            'attributes' => ['target' => '_blank'],
          ],
        ];

        $paragraph_rows[] = [
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
          'operations' => [
            'data' => [
              '#type' => 'operations',
              '#links' => $operations,
            ],
          ],
        ];
      }

      $build['paragraphs'] = [
        '#type' => 'details',
        '#title' => $this->t('Paragraphs by paragraph type'),
        '#open' => TRUE,
        'table' => [
          '#type' => 'table',
          '#header' => $para_header,
          '#rows' => $paragraph_rows,
          '#empty' => $this->t('No paragraph types found.'),
        ],
      ];
    }
    else {
      $build['paragraphs'] = [
        '#type' => 'details',
        '#title' => $this->t('Paragraphs by paragraph type'),
        '#open' => TRUE,
        'table' => [
          '#type' => 'table',
          '#header' => [
            $this->t('Machine name'),
            $this->t('Label'),
            $this->t('Count'),
            $this->t('Operations'),
          ],
          '#rows' => [],
          '#empty' => $this->t('No paragraph types found.'),
        ],
      ];
    }

    return $build;
  }

  /**
   * {@inheritdoc}
   *
   * Build a Markdown representation of the report.
   */
  public function buildMarkdown(array $data): string {
    $lines = [];

    $lines[] = '# Content volume';
    $lines[] = '';

    // Nodes.
    $lines[] = '## Nodes by content type';
    $lines[] = '';
    $lines[] = '| Machine name | Label | Count | Edit | Fields | List |';
    $lines[] = '| --- | --- | ---: | --- | --- | --- |';

    foreach ($data['nodes'] as $bundle => $info) {
      $edit_link = sprintf('[Edit](%s)', $info['edit_path']);
      $fields_link = sprintf('[Fields](%s)', $info['fields_path']);
      $list_link = sprintf('[List](%s)', $info['list_path']);

      $lines[] = sprintf(
        '| `%s` | %s | %d | %s | %s | %s |',
        $bundle,
        $info['label'],
        $info['count'],
        $edit_link,
        $fields_link,
        $list_link
      );
    }

    if (empty($data['nodes'])) {
      $lines[] = '| *(none)* |  | 0 |  |  |  |';
    }

    $lines[] = '';
    $lines[] = '## Paragraphs by paragraph type';
    $lines[] = '';
    $lines[] = '| Machine name | Label | Count | Edit | Fields |';
    $lines[] = '| --- | --- | ---: | --- | --- |';

    foreach ($data['paragraphs'] as $bundle => $info) {
      $edit_link = sprintf('[Edit](%s)', $info['edit_path']);
      $fields_link = sprintf('[Fields](%s)', $info['fields_path']);

      $lines[] = sprintf(
        '| `%s` | %s | %d | %s | %s |',
        $bundle,
        $info['label'],
        $info['count'],
        $edit_link,
        $fields_link
      );
    }

    if (empty($data['paragraphs'])) {
      $lines[] = '| *(none)* |  | 0 |  |  |';
    }

    $lines[] = '';

    return implode("\n", $lines);
  }

}
