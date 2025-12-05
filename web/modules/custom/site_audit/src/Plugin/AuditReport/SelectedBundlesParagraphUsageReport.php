<?php

declare(strict_types=1);

namespace Drupal\site_audit\Plugin\AuditReport;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\site_audit\Plugin\AuditReport\AuditReportBase;
use Drupal\site_audit\Service\SiteAuditStructureCollector;
use Drupal\site_audit\Service\SiteAuditContentBundleCollector;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Paragraph usage report for a specific set of node bundles.
 *
 * @AuditReport(
 *   id = "selected_bundles_paragraph_usage",
 *   label = @Translation("Selected bundles – paragraph usage"),
 *   description = @Translation("Shows paragraph usage for a specific set of node bundles and which paragraph types are not used.")
 * )
 */
class SelectedBundlesParagraphUsageReport extends AuditReportBase implements ContainerFactoryPluginInterface {

  /**
   * Structure collector.
   *
   * @var \Drupal\site_audit\Service\SiteAuditStructureCollector
   */
  protected SiteAuditStructureCollector $structureCollector;

  /**
   * Content bundle collector.
   *
   * @var \Drupal\site_audit\Service\SiteAuditContentBundleCollector
   */
  protected SiteAuditContentBundleCollector $contentBundleCollector;

  /**
   * Node bundles (content types) to focus on.
   *
   * Machine name => human label.
   *
   * @var string[]
   */
  protected array $targetBundles = [
    // CSM bundles.
    'csm_documents' => 'Documents',
    'csm_document_type' => 'Document Type',
    'csm_externe_projekt_institutione' => 'Externe Projekt Institutionen',
    'csm_higher_education_institution' => 'Higher Education Institutions',
    'csm_kurse' => 'Kurse/Module',
    'csm_media_releases' => 'Medienmitteilungen',
    'csm_mitarbeitende' => 'Mitarbeitende',
    'csm_profile' => 'Mitarbeiter Profile',
    'csm_mitarbeiter_profil_typ' => 'Mitarbeiter Profil Typ',
    'csm_projekte' => 'Projekte',
    'csm_projektmitarbeiter_rolle' => 'Projektmitarbeiter/Rolle',
    'csm_projekt_ober_typ' => 'Projekt Ober Typ',
    'csm_projekt_positionen' => 'Projekt Positionen',
    'csm_projekt_schlagworte' => 'Projekt Schlagworte',
    'csm_projekt_status' => 'Projekt Status',
    'csm_projekt_typ' => 'Projekt Typ',
    'csm_publikationen' => 'Publikationen',
    'csm_publikations_typen' => 'Publikations Typen',
    'csm_sprache' => 'Sprache',
    'csm_studienangebot' => 'Studienangebot',
    'csm_veranstalltungs_kategorie' => 'Veranstalltungs Kategorie',
    'csm_veranstaltung' => 'Veranstaltung "UNI NEU"',
    'csm_veranstaltungstyp' => 'Veranstaltungstyp',
    // CSX.
    'csx_veranstaltungsart' => 'Veranstaltungsart',

    // OCM bundles.
    'ocm_blog_entry' => 'Blog Entry',
    'ocm_course' => 'Course',
    'page' => 'Einfache Seite',
    'ocm_functionassignment' => 'FunctionAssignment',
    'ocm_funktion_mitarbeiter_' => 'Funktion (Mitarbeiter)',
    'ocm_google_search' => 'Google Search',
    'ocm_institution' => 'Institution',
    'ocm_job_offer' => 'Jobangebot',
    'ocm_job_application' => 'Job application',
    'ocm_country' => 'Land',
    'ocm_news_entry' => 'News Entry',
    'ocm_semester' => 'Semester',
    'ocm_sprachen' => 'Sprachen',
    'ocm_studiengang' => 'Studiengang',
  ];

  /**
   * Constructs the report plugin instance.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\site_audit\Service\SiteAuditStructureCollector $structure_collector
   *   The structure collector service.
   * @param \Drupal\site_audit\Service\SiteAuditContentBundleCollector $content_bundle_collector
   *   The content bundle collector service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    SiteAuditStructureCollector $structure_collector,
    SiteAuditContentBundleCollector $content_bundle_collector
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->structureCollector = $structure_collector;
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
      $container->get('site_audit.content_bundle_collector')
    );
  }

  /**
   * {@inheritdoc}
   *
   * Returns a data structure suitable for JSON serialization.
   *
   * Structure:
   * [
   *   'bundles' => [
   *     'page' => [
   *       'machine_name' => 'page',
   *       'label' => 'Einfache Seite',
   *       'exists' => true,
   *       'node_summary' => [
   *         'bundle' => 'page',
   *         'total' => 123,
   *         'published' => 100,
   *         'unpublished' => 23,
   *       ],
   *       'paragraph_usage' => [ ... ],
   *       'edit_path' => '/admin/structure/types/manage/page',
   *       'fields_path' => '/admin/structure/types/manage/page/fields',
   *       'list_path' => '/admin/content?type=page',
   *     ],
   *   ],
   *   'paragraph_usage_aggregated' => [
   *     'hero' => [
   *       'paragraph_type' => 'hero',
   *       'label' => 'Hero section',
   *       'total_paragraphs' => 120,
   *       'total_nodes' => 45,
   *       'bundles' => ['page', 'csm_documents'],
   *       'edit_path' => '/admin/structure/paragraphs_type/hero',
   *       'fields_path' => '/admin/structure/paragraphs_type/hero/fields',
   *     ],
   *   ],
   *   'unused_paragraphs' => [
   *     'text' => [
   *       'paragraph_type' => 'text',
   *       'label' => 'Text',
   *       'edit_path' => '/admin/structure/paragraphs_type/text',
   *       'fields_path' => '/admin/structure/paragraphs_type/text/fields',
   *     ],
   *   ],
   * ]
   */
  public function buildData(): array {
    $all_node_bundles = $this->structureCollector->getNodeBundles();
    $all_paragraph_bundles = $this->structureCollector->getParagraphBundles();

    $bundles_data = [];
    $aggregated_usage = [];

    // Quick map of paragraph labels.
    $paragraph_labels = [];
    foreach ($all_paragraph_bundles as $ptype => $info) {
      $paragraph_labels[$ptype] = (string) ($info['label'] ?? $ptype);
    }

    // 1. Per-bundle stats and per-bundle paragraph usage.
    foreach ($this->targetBundles as $bundle => $label) {
      $exists = isset($all_node_bundles[$bundle]);

      $node_summary = NULL;
      $paragraph_usage = [];
      $edit_path = NULL;
      $fields_path = NULL;
      $list_path = NULL;

      if ($exists) {
        $bundle_info = $all_node_bundles[$bundle];

        // Node counts for this bundle.
        $node_summary = $this->contentBundleCollector->getNodeBundleUsageSummary($bundle);

        // Paragraph usage for this bundle.
        $paragraph_usage = $this->contentBundleCollector->getParagraphUsageForNodeBundle($bundle);

        // Paths.
        $edit_path = (string) ($bundle_info['edit_path'] ?? "/admin/structure/types/manage/$bundle");
        $fields_path = (string) ($bundle_info['fields_path'] ?? "/admin/structure/types/manage/$bundle/fields");
        $list_url = Url::fromRoute('system.admin_content', [], [
          'query' => ['type' => $bundle],
        ]);
        $list_path = $list_url->toString();

        // Aggregate across all selected bundles.
        foreach ($paragraph_usage as $ptype => $usage) {
          if (!isset($aggregated_usage[$ptype])) {
            $p_info = $all_paragraph_bundles[$ptype] ?? [];
            $aggregated_usage[$ptype] = [
              'paragraph_type' => $ptype,
              'label' => $usage['label'] ?? ($paragraph_labels[$ptype] ?? $ptype),
              'total_paragraphs' => 0,
              'total_nodes' => 0,
              'bundles' => [],
              'edit_path' => (string) ($p_info['edit_path'] ?? "/admin/structure/paragraphs_type/$ptype"),
              'fields_path' => (string) ($p_info['fields_path'] ?? "/admin/structure/paragraphs_type/$ptype/fields"),
            ];
          }

          $aggregated_usage[$ptype]['total_paragraphs'] += (int) ($usage['total_paragraphs'] ?? 0);
          $aggregated_usage[$ptype]['total_nodes'] += (int) ($usage['node_count'] ?? 0);
          if (!in_array($bundle, $aggregated_usage[$ptype]['bundles'], TRUE)) {
            $aggregated_usage[$ptype]['bundles'][] = $bundle;
          }
        }
      }

      $bundles_data[$bundle] = [
        'machine_name' => $bundle,
        'label' => $label,
        'exists' => $exists,
        'node_summary' => $node_summary,
        'paragraph_usage' => $paragraph_usage,
        'edit_path' => $edit_path,
        'fields_path' => $fields_path,
        'list_path' => $list_path,
      ];
    }

    // Sort bundles by label for base order (UI sorting will override).
    uasort($bundles_data, static function (array $a, array $b): int {
      return strcmp($a['label'], $b['label']);
    });

    // Sort aggregated paragraph usage by total_paragraphs desc by default.
    uasort($aggregated_usage, static function (array $a, array $b): int {
      return $b['total_paragraphs'] <=> $a['total_paragraphs'];
    });

    // 2. Paragraph types not used by any selected bundle.
    $unused_paragraphs = [];
    foreach ($all_paragraph_bundles as $ptype => $info) {
      if (!isset($aggregated_usage[$ptype])) {
        $unused_paragraphs[$ptype] = [
          'paragraph_type' => $ptype,
          'label' => (string) ($info['label'] ?? $ptype),
          'edit_path' => (string) ($info['edit_path'] ?? "/admin/structure/paragraphs_type/$ptype"),
          'fields_path' => (string) ($info['fields_path'] ?? "/admin/structure/paragraphs_type/$ptype/fields"),
        ];
      }
    }

    // Sort unused paragraphs alphabetically by label.
    uasort($unused_paragraphs, static function (array $a, array $b): int {
      return strcmp($a['label'], $b['label']);
    });

    return [
      'bundles' => $bundles_data,
      'paragraph_usage_aggregated' => $aggregated_usage,
      'unused_paragraphs' => $unused_paragraphs,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildRender(array $data): array {
    $build = [
      '#type' => 'container',
    ];

    $bundles = $data['bundles'] ?? [];
    $aggregated = $data['paragraph_usage_aggregated'] ?? [];
    $unused = $data['unused_paragraphs'] ?? [];

    $request = \Drupal::request();
    $route_match = \Drupal::routeMatch();
    $route_name = $route_match->getRouteName();
    $route_params = $route_match->getRawParameters()->all();
    $current_query = $request->query->all();

    // ----------------------------------------------------------------------
    // 1. Bundles overview (with sorting + operations).
    // ----------------------------------------------------------------------
    if (!empty($bundles)) {
      $bundles_order = $request->query->get('bundles_order') ?: 'label';
      $bundles_sort = strtolower($request->query->get('bundles_sort') ?: 'asc');

      $allowed_bundles_order = [
        'machine_name',
        'label',
        'exists',
        'total_nodes',
        'published',
        'unpublished',
        'paragraph_types',
      ];
      if (!in_array($bundles_order, $allowed_bundles_order, TRUE)) {
        $bundles_order = 'label';
      }
      if (!in_array($bundles_sort, ['asc', 'desc'], TRUE)) {
        $bundles_sort = 'asc';
      }

      uasort($bundles, static function (array $a, array $b) use ($bundles_order, $bundles_sort): int {
        $getValue = static function (array $info, string $key) {
          $node_summary = $info['node_summary'] ?? [];
          switch ($key) {
            case 'machine_name':
            case 'label':
              return $info[$key] ?? '';
            case 'exists':
              return $info['exists'] ? 1 : 0;
            case 'total_nodes':
              return $node_summary['total'] ?? 0;
            case 'published':
              return $node_summary['published'] ?? 0;
            case 'unpublished':
              return $node_summary['unpublished'] ?? 0;
            case 'paragraph_types':
              return is_array($info['paragraph_usage'] ?? NULL)
                ? count($info['paragraph_usage'])
                : 0;
            default:
              return 0;
          }
        };

        $av = $getValue($a, $bundles_order);
        $bv = $getValue($b, $bundles_order);

        if ($av == $bv) {
          return 0;
        }
        if ($bundles_sort === 'asc') {
          return ($av < $bv) ? -1 : 1;
        }
        else {
          return ($av > $bv) ? -1 : 1;
        }
      });

      // Build sortable header.
      $bundle_columns = [
        'machine_name' => $this->t('Machine name'),
        'label' => $this->t('Label'),
        'exists' => $this->t('Exists'),
        'total_nodes' => $this->t('Total nodes'),
        'published' => $this->t('Published'),
        'unpublished' => $this->t('Unpublished'),
        'paragraph_types' => $this->t('Paragraph types used'),
      ];

      $bundle_header = [];
      foreach ($bundle_columns as $key => $label) {
        $next_sort = ($bundles_order === $key && $bundles_sort === 'asc') ? 'desc' : 'asc';

        $query = $current_query;
        $query['bundles_order'] = $key;
        $query['bundles_sort'] = $next_sort;

        $url = Url::fromRoute($route_name, $route_params, [
          'query' => $query,
        ]);

        $label_text = $label;
        if ($bundles_order === $key) {
          $arrow = $bundles_sort === 'asc' ? '↑' : '↓';
          $label_text = $this->t('@label @arrow', ['@label' => $label, '@arrow' => $arrow]);
        }

        $bundle_header[$key] = [
          'data' => Link::fromTextAndUrl($label_text, $url)->toRenderable(),
        ];
      }

      // Non-sortable column.
      $bundle_header['operations'] = [
        'data' => $this->t('Operations'),
      ];

      // Build rows.
      $bundle_rows = [];
      foreach ($bundles as $bundle => $info) {
        $exists = $info['exists'];
        $node_summary = $info['node_summary'] ?? [];
        $paragraph_usage = $info['paragraph_usage'] ?? [];

        $total_nodes = $node_summary['total'] ?? 0;
        $published = $node_summary['published'] ?? 0;
        $unpublished = $node_summary['unpublished'] ?? 0;
        $paragraph_types_count = is_array($paragraph_usage) ? count($paragraph_usage) : 0;

        $status = $exists
          ? $this->t('Yes')
          : $this->t('No (bundle not present in this site)');

        // Operations only if the bundle exists.
        $operations_links = [];
        if ($exists && $info['edit_path'] && $info['fields_path']) {
          $edit_url = Url::fromUserInput($info['edit_path'], [
            'attributes' => ['target' => '_blank'],
          ]);
          $fields_url = Url::fromUserInput($info['fields_path'], [
            'attributes' => ['target' => '_blank'],
          ]);
          $operations_links['edit'] = [
            'title' => $this->t('Edit type'),
            'url' => $edit_url,
            'attributes' => ['target' => '_blank'],
          ];
          $operations_links['fields'] = [
            'title' => $this->t('Manage fields'),
            'url' => $fields_url,
            'attributes' => ['target' => '_blank'],
          ];
          if (!empty($info['list_path'])) {
            $list_url = Url::fromUserInput($info['list_path'], [
              'attributes' => ['target' => '_blank'],
            ]);
            $operations_links['list'] = [
              'title' => $this->t('View nodes'),
              'url' => $list_url,
              'attributes' => ['target' => '_blank'],
            ];
          }
        }

        $operations_cell = !empty($operations_links)
          ? [
            '#type' => 'operations',
            '#links' => $operations_links,
          ]
          : [
            '#markup' => $this->t('N/A'),
          ];

        $bundle_rows[] = [
          'machine_name' => ['data' => ['#markup' => $bundle]],
          'label' => ['data' => ['#markup' => $info['label']]],
          'exists' => ['data' => ['#markup' => $status]],
          'total_nodes' => ['data' => ['#markup' => (string) $total_nodes]],
          'published' => ['data' => ['#markup' => (string) $published]],
          'unpublished' => ['data' => ['#markup' => (string) $unpublished]],
          'paragraph_types' => [
            'data' => ['#markup' => (string) $paragraph_types_count],
          ],
          'operations' => [
            'data' => $operations_cell,
          ],
        ];
      }

      $build['bundles_overview'] = [
        '#type' => 'details',
        '#title' => $this->t('Selected bundles overview'),
        '#open' => TRUE,
        'table' => [
          '#type' => 'table',
          '#header' => $bundle_header,
          '#rows' => $bundle_rows,
          '#empty' => $this->t('No bundles in the configured list.'),
        ],
      ];
    }
    else {
      $build['bundles_overview'] = [
        '#type' => 'details',
        '#title' => $this->t('Selected bundles overview'),
        '#open' => TRUE,
        'table' => [
          '#type' => 'table',
          '#header' => [
            $this->t('Machine name'),
            $this->t('Label'),
            $this->t('Exists'),
            $this->t('Total nodes'),
            $this->t('Published'),
            $this->t('Unpublished'),
            $this->t('Paragraph types used'),
            $this->t('Operations'),
          ],
          '#rows' => [],
          '#empty' => $this->t('No bundles in the configured list.'),
        ],
      ];
    }

    // ----------------------------------------------------------------------
    // 2. Aggregated paragraph usage (with sorting + operations).
    // ----------------------------------------------------------------------
    if (!empty($aggregated)) {
      $paragraphs_order = $request->query->get('paragraphs_order') ?: 'total_paragraphs';
      $paragraphs_sort = strtolower($request->query->get('paragraphs_sort') ?: 'desc');

      $allowed_paragraphs_order = [
        'paragraph_type',
        'label',
        'total_paragraphs',
        'total_nodes',
      ];
      if (!in_array($paragraphs_order, $allowed_paragraphs_order, TRUE)) {
        $paragraphs_order = 'total_paragraphs';
      }
      if (!in_array($paragraphs_sort, ['asc', 'desc'], TRUE)) {
        $paragraphs_sort = 'desc';
      }

      uasort($aggregated, static function (array $a, array $b) use ($paragraphs_order, $paragraphs_sort): int {
        $av = $a[$paragraphs_order] ?? NULL;
        $bv = $b[$paragraphs_order] ?? NULL;

        if ($av == $bv) {
          return 0;
        }
        if ($paragraphs_sort === 'asc') {
          return ($av < $bv) ? -1 : 1;
        }
        else {
          return ($av > $bv) ? -1 : 1;
        }
      });

      // Build sortable header.
      $paragraph_columns = [
        'paragraph_type' => $this->t('Paragraph type'),
        'label' => $this->t('Label'),
        'total_paragraphs' => $this->t('Total paragraphs'),
        'total_nodes' => $this->t('Nodes using this type'),
      ];

      $paragraph_header = [];
      foreach ($paragraph_columns as $key => $label) {
        $next_sort = ($paragraphs_order === $key && $paragraphs_sort === 'asc') ? 'desc' : 'asc';

        $query = $current_query;
        $query['paragraphs_order'] = $key;
        $query['paragraphs_sort'] = $next_sort;

        $url = Url::fromRoute($route_name, $route_params, [
          'query' => $query,
        ]);

        $label_text = $label;
        if ($paragraphs_order === $key) {
          $arrow = $paragraphs_sort === 'asc' ? '↑' : '↓';
          $label_text = $this->t('@label @arrow', ['@label' => $label, '@arrow' => $arrow]);
        }

        $paragraph_header[$key] = [
          'data' => Link::fromTextAndUrl($label_text, $url)->toRenderable(),
        ];
      }

      // Non-sortable columns.
      $paragraph_header['bundles'] = [
        'data' => $this->t('Bundles using this type'),
      ];
      $paragraph_header['operations'] = [
        'data' => $this->t('Operations'),
      ];

      // Build rows.
      $usage_rows = [];
      foreach ($aggregated as $ptype => $info) {
        $bundles_list = $info['bundles'] ?? [];
        $bundles_markup = '';

        if ($bundles_list) {
          $bundle_labels = [];
          foreach ($bundles_list as $bundle) {
            $label = $bundles[$bundle]['label'] ?? $bundle;
            $bundle_labels[] = $bundle . ' (' . $label . ')';
          }
          $bundles_markup = implode(', ', $bundle_labels);
        }

        // Paragraph operations.
        $operations_links = [];
        if (!empty($info['edit_path']) && !empty($info['fields_path'])) {
          $edit_url = Url::fromUserInput($info['edit_path'], [
            'attributes' => ['target' => '_blank'],
          ]);
          $fields_url = Url::fromUserInput($info['fields_path'], [
            'attributes' => ['target' => '_blank'],
          ]);

          $operations_links['edit'] = [
            'title' => $this->t('Edit type'),
            'url' => $edit_url,
            'attributes' => ['target' => '_blank'],
          ];
          $operations_links['fields'] = [
            'title' => $this->t('Manage fields'),
            'url' => $fields_url,
            'attributes' => ['target' => '_blank'],
          ];
        }

        $operations_cell = !empty($operations_links)
          ? [
            '#type' => 'operations',
            '#links' => $operations_links,
          ]
          : [
            '#markup' => $this->t('N/A'),
          ];

        $usage_rows[] = [
          'paragraph_type' => ['data' => ['#markup' => $ptype]],
          'label' => ['data' => ['#markup' => $info['label']]],
          'total_paragraphs' => [
            'data' => ['#markup' => (string) $info['total_paragraphs']],
          ],
          'total_nodes' => [
            'data' => ['#markup' => (string) $info['total_nodes']],
          ],
          'bundles' => [
            'data' => ['#markup' => $bundles_markup],
          ],
          'operations' => [
            'data' => $operations_cell,
          ],
        ];
      }

      $build['paragraph_usage'] = [
        '#type' => 'details',
        '#title' => $this->t('Paragraph usage across selected bundles'),
        '#open' => TRUE,
        'table' => [
          '#type' => 'table',
          '#header' => $paragraph_header,
          '#rows' => $usage_rows,
          '#empty' => $this->t('No paragraph usage detected for the selected bundles.'),
        ],
      ];
    }
    else {
      $build['paragraph_usage'] = [
        '#type' => 'details',
        '#title' => $this->t('Paragraph usage across selected bundles'),
        '#open' => TRUE,
        'table' => [
          '#type' => 'table',
          '#header' => [
            $this->t('Paragraph type'),
            $this->t('Label'),
            $this->t('Total paragraphs'),
            $this->t('Nodes using this type'),
            $this->t('Bundles using this type'),
            $this->t('Operations'),
          ],
          '#rows' => [],
          '#empty' => $this->t('No paragraph usage detected for the selected bundles.'),
        ],
      ];
    }

    // ----------------------------------------------------------------------
    // 3. Unused paragraphs (with sorting + operations).
    // ----------------------------------------------------------------------
    if (!empty($unused)) {
      $unused_order = $request->query->get('unused_order') ?: 'label';
      $unused_sort = strtolower($request->query->get('unused_sort') ?: 'asc');

      $allowed_unused_order = ['paragraph_type', 'label'];
      if (!in_array($unused_order, $allowed_unused_order, TRUE)) {
        $unused_order = 'label';
      }
      if (!in_array($unused_sort, ['asc', 'desc'], TRUE)) {
        $unused_sort = 'asc';
      }

      uasort($unused, static function (array $a, array $b) use ($unused_order, $unused_sort): int {
        $av = $a[$unused_order] ?? '';
        $bv = $b[$unused_order] ?? '';

        if ($av == $bv) {
          return 0;
        }
        if ($unused_sort === 'asc') {
          return ($av < $bv) ? -1 : 1;
        }
        else {
          return ($av > $bv) ? -1 : 1;
        }
      });

      $unused_columns = [
        'paragraph_type' => $this->t('Paragraph type'),
        'label' => $this->t('Label'),
      ];

      $unused_header = [];
      foreach ($unused_columns as $key => $label) {
        $next_sort = ($unused_order === $key && $unused_sort === 'asc') ? 'desc' : 'asc';

        $query = $current_query;
        $query['unused_order'] = $key;
        $query['unused_sort'] = $next_sort;

        $url = Url::fromRoute($route_name, $route_params, [
          'query' => $query,
        ]);

        $label_text = $label;
        if ($unused_order === $key) {
          $arrow = $unused_sort === 'asc' ? '↑' : '↓';
          $label_text = $this->t('@label @arrow', ['@label' => $label, '@arrow' => $arrow]);
        }

        $unused_header[$key] = [
          'data' => Link::fromTextAndUrl($label_text, $url)->toRenderable(),
        ];
      }

      // Non-sortable column.
      $unused_header['operations'] = [
        'data' => $this->t('Operations'),
      ];

      $unused_rows = [];
      foreach ($unused as $ptype => $info) {
        $operations_links = [];
        if (!empty($info['edit_path']) && !empty($info['fields_path'])) {
          $edit_url = Url::fromUserInput($info['edit_path'], [
            'attributes' => ['target' => '_blank'],
          ]);
          $fields_url = Url::fromUserInput($info['fields_path'], [
            'attributes' => ['target' => '_blank'],
          ]);

          $operations_links['edit'] = [
            'title' => $this->t('Edit type'),
            'url' => $edit_url,
            'attributes' => ['target' => '_blank'],
          ];
          $operations_links['fields'] = [
            'title' => $this->t('Manage fields'),
            'url' => $fields_url,
            'attributes' => ['target' => '_blank'],
          ];
        }

        $operations_cell = !empty($operations_links)
          ? [
            '#type' => 'operations',
            '#links' => $operations_links,
          ]
          : [
            '#markup' => $this->t('N/A'),
          ];

        $unused_rows[] = [
          'paragraph_type' => ['data' => ['#markup' => $ptype]],
          'label' => ['data' => ['#markup' => $info['label']]],
          'operations' => [
            'data' => $operations_cell,
          ],
        ];
      }

      $build['unused_paragraphs'] = [
        '#type' => 'details',
        '#title' => $this->t('Paragraph types not used by selected bundles'),
        '#open' => FALSE,
        'table' => [
          '#type' => 'table',
          '#header' => $unused_header,
          '#rows' => $unused_rows,
          '#empty' => $this->t('All paragraph types are used by at least one of the selected bundles.'),
        ],
      ];
    }
    else {
      $build['unused_paragraphs'] = [
        '#type' => 'details',
        '#title' => $this->t('Paragraph types not used by selected bundles'),
        '#open' => FALSE,
        'table' => [
          '#type' => 'table',
          '#header' => [
            $this->t('Paragraph type'),
            $this->t('Label'),
            $this->t('Operations'),
          ],
          '#rows' => [],
          '#empty' => $this->t('All paragraph types are used by at least one selected bundle.'),
        ],
      ];
    }

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function buildMarkdown(array $data): string {
    $bundles = $data['bundles'] ?? [];
    $aggregated = $data['paragraph_usage_aggregated'] ?? [];
    $unused = $data['unused_paragraphs'] ?? [];

    $lines = [];

    $lines[] = '# Selected bundles – paragraph usage';
    $lines[] = '';

    // Bundles overview.
    $lines[] = '## Bundles overview';
    $lines[] = '';
    $lines[] = '| Machine name | Label | Exists | Total nodes | Published | Unpublished | Paragraph types used |';
    $lines[] = '| --- | --- | --- | ---: | ---: | ---: | ---: |';

    foreach ($bundles as $bundle => $info) {
      $exists = $info['exists']
        ? 'Yes'
        : 'No (bundle not present)';

      $node_summary = $info['node_summary'] ?? [];
      $total_nodes = $node_summary['total'] ?? 0;
      $published = $node_summary['published'] ?? 0;
      $unpublished = $node_summary['unpublished'] ?? 0;
      $paragraph_types_count = is_array($info['paragraph_usage'] ?? NULL)
        ? count($info['paragraph_usage'])
        : 0;

      $lines[] = sprintf(
        '| `%s` | %s | %s | %d | %d | %d | %d |',
        $bundle,
        $info['label'],
        $exists,
        $total_nodes,
        $published,
        $unpublished,
        $paragraph_types_count
      );
    }

    if (empty($bundles)) {
      $lines[] = '| *(none)* | | | 0 | 0 | 0 | 0 |';
    }

    $lines[] = '';
    $lines[] = '## Paragraph usage across selected bundles';
    $lines[] = '';
    $lines[] = '| Paragraph type | Label | Total paragraphs | Nodes using this type | Bundles using this type |';
    $lines[] = '| --- | --- | ---: | ---: | --- |';

    foreach ($aggregated as $ptype => $info) {
      $bundles_list = $info['bundles'] ?? [];
      $bundle_texts = [];

      foreach ($bundles_list as $bundle) {
        $label = $bundles[$bundle]['label'] ?? $bundle;
        $bundle_texts[] = sprintf('`%s` (%s)', $bundle, $label);
      }

      $bundles_text = $bundle_texts ? implode(', ', $bundle_texts) : '';

      $lines[] = sprintf(
        '| `%s` | %s | %d | %d | %s |',
        $ptype,
        $info['label'],
        $info['total_paragraphs'],
        $info['total_nodes'],
        $bundles_text
      );
    }

    if (empty($aggregated)) {
      $lines[] = '| *(none)* | | 0 | 0 | |';
    }

    $lines[] = '';
    $lines[] = '## Paragraph types not used by selected bundles';
    $lines[] = '';
    $lines[] = '| Paragraph type | Label |';
    $lines[] = '| --- | --- |';

    foreach ($unused as $ptype => $info) {
      $lines[] = sprintf(
        '| `%s` | %s |',
        $ptype,
        $info['label']
      );
    }

    if (empty($unused)) {
      $lines[] = '| *(none)* | All paragraph types are used by at least one selected bundle. |';
    }

    $lines[] = '';

    return implode("\n", $lines);
  }

}
