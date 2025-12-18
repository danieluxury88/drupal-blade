<?php

declare(strict_types=1);

namespace Drupal\site_audit\Plugin\AuditReport;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\site_audit\Service\SiteAuditViewsCollector;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Snapshot of Views and their complexity.
 *
 * @AuditReport(
 *   id = "views_overview",
 *   label = @Translation("Views overview"),
 *   description = @Translation("Lists all Views with a rough complexity score and quick links to edit.")
 * )
 */
class ViewsOverviewReport extends AuditReportBase implements ContainerFactoryPluginInterface {

  /**
   * Views collector service.
   *
   * @var \Drupal\site_audit\Service\SiteAuditViewsCollector
   */
  protected SiteAuditViewsCollector $viewsCollector;

  /**
   * Constructs the ViewsOverviewReport plugin instance.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\site_audit\Service\SiteAuditViewsCollector $views_collector
   *   The views collector service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    SiteAuditViewsCollector $views_collector,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->viewsCollector = $views_collector;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('site_audit.views_collector')
    );
  }

  /**
   * {@inheritdoc}
   *
   * Returns a data structure suitable for JSON serialization.
   *
   * [
   *   'views' => [
   *     'frontpage' => [
   *       'id' => 'frontpage',
   *       'label' => 'Front page',
   *       'status' => 'enabled',
   *       'base_table' => 'node_field_data',
   *       'totals' => [
   *         'displays' => 2,
   *         'fields' => 5,
   *         'filters' => 3,
   *         'sorts' => 2,
   *         'relationships' => 1,
   *         'contextual_filters' => 1,
   *         'exposed_filters' => 2,
   *       ],
   *       'complexity' => 18,
   *       'edit_path' => '/admin/structure/views/view/frontpage',
   *     ],
   *   ],
   * ]
   */
  public function buildData(): array {
    return $this->viewsCollector->getViewsOverview();
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

    $views = $data['views'] ?? [];

    if (empty($views)) {
      $build['empty'] = [
        '#markup' => $this->t('No Views found on this site.'),
      ];
      return $build;
    }

    $request = \Drupal::request();
    $route_match = \Drupal::routeMatch();
    $route_name = $route_match->getRouteName();
    $route_params = $route_match->getRawParameters()->all();
    $current_query = $request->query->all();

    // Sorting state.
    $order = $request->query->get('views_order') ?: 'id';
    $sort = strtolower($request->query->get('views_sort') ?: 'asc');

    $allowed_order = [
      'id',
      'label',
      'status',
      'base_table',
      'displays',
      'fields',
      'filters',
      'sorts',
      'relationships',
      'contextual_filters',
      'exposed_filters',
      'complexity',
    ];

    if (!in_array($order, $allowed_order, TRUE)) {
      $order = 'id';
    }
    if (!in_array($sort, ['asc', 'desc'], TRUE)) {
      $sort = 'asc';
    }

    // Sort in PHP.
    uasort($views, static function (array $a, array $b) use ($order, $sort): int {
      $totalsA = $a['totals'] ?? [];
      $totalsB = $b['totals'] ?? [];

      $getValue = static function (array $view, string $order, array $totals) {
        switch ($order) {
          case 'id':
          case 'label':
          case 'status':
          case 'base_table':
            return $view[$order] ?? '';

          case 'displays':
          case 'fields':
          case 'filters':
          case 'sorts':
          case 'relationships':
          case 'contextual_filters':
          case 'exposed_filters':
            return (int) ($totals[$order] ?? 0);

          case 'complexity':
            return (int) ($view['complexity'] ?? 0);

          default:
            return 0;
        }
      };

      $av = $getValue($a, $order, $totalsA);
      $bv = $getValue($b, $order, $totalsB);

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

    // Build sortable header.
    $columns = [
      'id' => $this->t('Machine name'),
      'label' => $this->t('Label'),
      'status' => $this->t('Status'),
      'base_table' => $this->t('Base table'),
      'displays' => $this->t('# displays'),
      'fields' => $this->t('# fields'),
      'filters' => $this->t('# filters'),
      'sorts' => $this->t('# sorts'),
      'relationships' => $this->t('# relationships'),
      'contextual_filters' => $this->t('# contextual filters'),
      'exposed_filters' => $this->t('# exposed filters'),
      'complexity' => $this->t('Complexity'),
    ];

    $header = [];

    foreach ($columns as $key => $label) {
      $next_sort = ($order === $key && $sort === 'asc') ? 'desc' : 'asc';

      $query = $current_query;
      $query['views_order'] = $key;
      $query['views_sort'] = $next_sort;

      $url = Url::fromRoute($route_name, $route_params, [
        'query' => $query,
      ]);

      $label_text = $label;
      if ($order === $key) {
        $arrow = $sort === 'asc' ? '↑' : '↓';
        $label_text = $this->t('@label @arrow', ['@label' => $label, '@arrow' => $arrow]);
      }

      $header[$key] = [
        'data' => Link::fromTextAndUrl($label_text, $url)->toRenderable(),
      ];
    }

    // Non-sortable column.
    $header['operations'] = [
      'data' => $this->t('Operations'),
    ];

    // Build rows.
    $rows = [];

    foreach ($views as $id => $info) {
      $totals = $info['totals'] ?? [];
      $status_label = $info['status'] === 'enabled'
        ? $this->t('Enabled')
        : $this->t('Disabled');

      $displays_count = (int) ($totals['displays'] ?? 0);
      $fields_count = (int) ($totals['fields'] ?? 0);
      $filters_count = (int) ($totals['filters'] ?? 0);
      $sorts_count = (int) ($totals['sorts'] ?? 0);
      $relationships_count = (int) ($totals['relationships'] ?? 0);
      $contextual_filters_count = (int) ($totals['contextual_filters'] ?? 0);
      $exposed_filters_count = (int) ($totals['exposed_filters'] ?? 0);
      $complexity = (int) ($info['complexity'] ?? 0);

      $edit_url = Url::fromUserInput($info['edit_path'], [
        'attributes' => ['target' => '_blank'],
      ]);

      $operations = [
        'edit' => [
          'title' => $this->t('Edit view'),
          'url' => $edit_url,
          'attributes' => ['target' => '_blank'],
        ],
      ];

      $rows[] = [
        'id' => [
          'data' => [
            '#markup' => $id,
          ],
        ],
        'label' => [
          'data' => [
            '#markup' => $info['label'],
          ],
        ],
        'status' => [
          'data' => [
            '#markup' => $status_label,
          ],
        ],
        'base_table' => [
          'data' => [
            '#markup' => $info['base_table'],
          ],
        ],
        'displays' => [
          'data' => [
            '#markup' => (string) $displays_count,
          ],
        ],
        'fields' => [
          'data' => [
            '#markup' => (string) $fields_count,
          ],
        ],
        'filters' => [
          'data' => [
            '#markup' => (string) $filters_count,
          ],
        ],
        'sorts' => [
          'data' => [
            '#markup' => (string) $sorts_count,
          ],
        ],
        'relationships' => [
          'data' => [
            '#markup' => (string) $relationships_count,
          ],
        ],
        'contextual_filters' => [
          'data' => [
            '#markup' => (string) $contextual_filters_count,
          ],
        ],
        'exposed_filters' => [
          'data' => [
            '#markup' => (string) $exposed_filters_count,
          ],
        ],
        'complexity' => [
          'data' => [
            '#markup' => (string) $complexity,
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

    $build['views'] = [
      '#type' => 'details',
      '#title' => $this->t('Views overview and complexity'),
      '#open' => TRUE,
      'table' => [
        '#type' => 'table',
        '#header' => $header,
        '#rows' => $rows,
        '#empty' => $this->t('No Views found on this site.'),
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
    $views = $data['views'] ?? [];

    if (empty($views)) {
      return "No Views found on this site.\n";
    }

    $lines = [];

    $lines[] = '# Views overview';
    $lines[] = '';
    $lines[] = '| Machine name | Label | Status | Base table | # displays | # fields | # filters | # sorts | # relationships | # contextual filters | # exposed filters | Complexity |';
    $lines[] = '| --- | --- | --- | --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: |';

    foreach ($views as $id => $info) {
      $totals = $info['totals'] ?? [];

      $status_label = $info['status'] === 'enabled' ? 'Enabled' : 'Disabled';

      $lines[] = sprintf(
        '| `%s` | %s | %s | %s | %d | %d | %d | %d | %d | %d | %d | %d |',
        $id,
        $info['label'],
        $status_label,
        $info['base_table'],
        (int) ($totals['displays'] ?? 0),
        (int) ($totals['fields'] ?? 0),
        (int) ($totals['filters'] ?? 0),
        (int) ($totals['sorts'] ?? 0),
        (int) ($totals['relationships'] ?? 0),
        (int) ($totals['contextual_filters'] ?? 0),
        (int) ($totals['exposed_filters'] ?? 0),
        (int) ($info['complexity'] ?? 0)
      );
    }

    $lines[] = '';

    return implode("\n", $lines);
  }

}
