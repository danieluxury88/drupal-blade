<?php

declare(strict_types=1);

namespace Drupal\site_audit\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\views\ViewEntityInterface;

/**
 * Collects metadata and complexity statistics for Views.
 */
class SiteAuditViewsCollector {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs the SiteAuditViewsCollector service.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Get an overview of all views with complexity stats.
   *
   * Structure:
   * [
   *   'views' => [
   *     'frontpage' => [
   *       'id' => 'frontpage',
   *       'label' => 'Front page',
   *       'status' => 'enabled',
   *       'base_table' => 'node_field_data',
   *       'displays' => [
   *         'default' => [
   *           'id' => 'default',
   *           'display_title' => 'Master',
   *           'display_plugin' => 'default',
   *           'fields' => 3,
   *           'filters' => 1,
   *           'sorts' => 1,
   *           'relationships' => 0,
   *           'contextual_filters' => 0,
   *           'exposed_filters' => 1,
   *         ],
   *         // ...
   *       ],
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
  public function getViewsOverview(): array {
    /** @var \Drupal\views\ViewEntityInterface[] $views */
    $views = $this->entityTypeManager
      ->getStorage('view')
      ->loadMultiple();

    $data = [
      'views' => [],
    ];

    foreach ($views as $id => $view) {
      $data['views'][$id] = $this->buildViewStats($view);
    }

    return $data;
  }

  /**
   * Build stats for a single view entity.
   *
   * @param \Drupal\views\ViewEntityInterface $view
   *   The view config entity.
   *
   * @return array
   *   Stats array for this view.
   */
  protected function buildViewStats(ViewEntityInterface $view): array {
    $id = $view->id();
    $label = $view->label();
    $status = $view->status() ? 'enabled' : 'disabled';
    $base_table = $view->get('base_table') ?? '';

    $displays_config = $view->get('display') ?? [];
    if (!is_array($displays_config)) {
      $displays_config = [];
    }

    $displays = [];

    $totals = [
      'displays' => 0,
      'fields' => 0,
      'filters' => 0,
      'sorts' => 0,
      'relationships' => 0,
      'contextual_filters' => 0,
      'exposed_filters' => 0,
    ];

    foreach ($displays_config as $display_id => $display) {
      if (!is_array($display)) {
        continue;
      }

      $display_title = $display['display_title'] ?? $display_id;
      $display_plugin = $display['display_plugin'] ?? '';
      $options = $display['display_options'] ?? [];

      $fields = isset($options['fields']) && is_array($options['fields'])
        ? count($options['fields'])
        : 0;

      $filters = isset($options['filters']) && is_array($options['filters'])
        ? count($options['filters'])
        : 0;

      $sorts = isset($options['sorts']) && is_array($options['sorts'])
        ? count($options['sorts'])
        : 0;

      $relationships = isset($options['relationships']) && is_array($options['relationships'])
        ? count($options['relationships'])
        : 0;

      $contextual_filters = isset($options['arguments']) && is_array($options['arguments'])
        ? count($options['arguments'])
        : 0;

      $exposed_filters = $this->countExposedFilters($options['filters'] ?? []);

      $displays[$display_id] = [
        'id' => $display_id,
        'display_title' => $display_title,
        'display_plugin' => $display_plugin,
        'fields' => $fields,
        'filters' => $filters,
        'sorts' => $sorts,
        'relationships' => $relationships,
        'contextual_filters' => $contextual_filters,
        'exposed_filters' => $exposed_filters,
      ];

      $totals['displays']++;
      $totals['fields'] += $fields;
      $totals['filters'] += $filters;
      $totals['sorts'] += $sorts;
      $totals['relationships'] += $relationships;
      $totals['contextual_filters'] += $contextual_filters;
      $totals['exposed_filters'] += $exposed_filters;
    }

    $complexity = $this->calculateComplexity($totals);

    return [
      'id' => $id,
      'label' => $label,
      'status' => $status,
      'base_table' => $base_table,
      'displays' => $displays,
      'totals' => $totals,
      'complexity' => $complexity,
      'edit_path' => "/admin/structure/views/view/$id",
    ];
  }

  /**
   * Count exposed filters in the given filters config array.
   *
   * @param array $filters
   *   Filters config from a display's display_options['filters'].
   *
   * @return int
   *   Number of exposed filters.
   */
  protected function countExposedFilters(array $filters): int {
    $count = 0;

    foreach ($filters as $filter) {
      if (!is_array($filter)) {
        continue;
      }

      // Heuristic: exposed filters usually have 'exposed' => TRUE or an 'expose' block.
      $is_exposed = !empty($filter['exposed'])
        || (!empty($filter['expose']) && is_array($filter['expose']));

      if ($is_exposed) {
        $count++;
      }
    }

    return $count;
  }

  /**
   * Calculate a simple complexity score for the view.
   *
   * @param array $totals
   *   Totals array with keys: fields, filters, sorts, displays, relationships,
   *   contextual_filters, exposed_filters.
   *
   * @return int
   *   Complexity score.
   */
  protected function calculateComplexity(array $totals): int {
    $fields = (int) ($totals['fields'] ?? 0);
    $filters = (int) ($totals['filters'] ?? 0);
    $sorts = (int) ($totals['sorts'] ?? 0);
    $displays = (int) ($totals['displays'] ?? 0);
    $relationships = (int) ($totals['relationships'] ?? 0);
    $contextual_filters = (int) ($totals['contextual_filters'] ?? 0);
    $exposed_filters = (int) ($totals['exposed_filters'] ?? 0);

    // Simple weighted formula; tweak as needed later.
    return $fields
      + $filters
      + $sorts
      + $displays
      + (2 * $relationships)
      + (2 * $contextual_filters)
      + $exposed_filters;
  }

}
