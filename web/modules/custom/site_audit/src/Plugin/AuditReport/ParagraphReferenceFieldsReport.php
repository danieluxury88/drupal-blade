<?php

declare(strict_types=1);

namespace Drupal\site_audit\Plugin\AuditReport;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\site_audit\Service\SiteAuditStructureCollector;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Lists paragraph bundles that have entity reference fields.
 *
 * @AuditReport(
 *   id = "paragraph_reference_fields",
 *   label = @Translation("Paragraph reference fields"),
 *   description = @Translation("Shows paragraph types that have entity_reference or entity_reference_revisions fields, including their targets.")
 * )
 */
class ParagraphReferenceFieldsReport extends AuditReportBase implements ContainerFactoryPluginInterface {

  /**
   * Structure collector.
   *
   * @var \Drupal\site_audit\Service\SiteAuditStructureCollector
   */
  protected SiteAuditStructureCollector $structureCollector;

  /**
   * Entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected EntityFieldManagerInterface $entityFieldManager;

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
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    SiteAuditStructureCollector $structure_collector,
    EntityFieldManagerInterface $entity_field_manager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->structureCollector = $structure_collector;
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
      $container->get('entity_field.manager')
    );
  }

  /**
   * {@inheritdoc}
   *
   * Data structure is suitable for JSON serialization.
   *
   * [
   *   'paragraphs' => [
   *     'hero' => [
   *       'machine_name' => 'hero',
   *       'label' => 'Hero section',
   *       'reference_fields_count' => 2,
   *       'edit_path' => '/admin/structure/paragraphs_type/hero',
   *       'fields_path' => '/admin/structure/paragraphs_type/hero/fields',
   *       'reference_fields' => [
   *         'field_media' => [
   *           'label' => 'Media',
   *           'type' => 'entity_reference',
   *           'target_type' => 'media',
   *           'target_bundles' => ['image', 'video'],
   *           'field_edit_path' => '/admin/structure/paragraphs_type/hero/fields/field_media',
   *         ],
   *       ],
   *     ],
   *   ],
   * ]
   */
  public function buildData(): array {
    $paragraph_bundles = $this->structureCollector->getParagraphBundles();

    $paragraphs = [];

    foreach ($paragraph_bundles as $bundle => $info) {
      $field_definitions = $this->entityFieldManager->getFieldDefinitions('paragraph', $bundle);

      $reference_fields = [];

      foreach ($field_definitions as $field_name => $definition) {
        // Only configurable fields.
        if ($definition->getFieldStorageDefinition()->isBaseField()) {
          continue;
        }

        $field_type = $definition->getType();
        if (!in_array($field_type, ['entity_reference', 'entity_reference_revisions'], TRUE)) {
          continue;
        }

        $settings = $definition->getSettings();
        $target_type = $settings['target_type'] ?? NULL;

        $target_bundles = [];
        $handler_settings = $settings['handler_settings'] ?? [];
        if (!empty($handler_settings['target_bundles']) && is_array($handler_settings['target_bundles'])) {
          $target_bundles = array_keys($handler_settings['target_bundles']);
        }

        $reference_fields[$field_name] = [
          'label' => (string) $definition->getLabel(),
          'type' => $field_type,
          'target_type' => $target_type,
          'target_bundles' => $target_bundles,
          // UI path to edit this field.
          'field_edit_path' => "/admin/structure/paragraphs_type/$bundle/fields/$field_name",
        ];
      }

      // Only include paragraph bundles that have at least one reference field.
      if (!empty($reference_fields)) {
        $paragraphs[$bundle] = [
          'machine_name' => $bundle,
          'label' => (string) ($info['label'] ?? $bundle),
          'reference_fields_count' => count($reference_fields),
          'reference_fields' => $reference_fields,
          'edit_path' => (string) ($info['edit_path'] ?? "/admin/structure/paragraphs_type/$bundle"),
          'fields_path' => (string) ($info['fields_path'] ?? "/admin/structure/paragraphs_type/$bundle/fields"),
        ];
      }
    }

    return [
      'paragraphs' => $paragraphs,
    ];
  }

  /**
   * {@inheritdoc}
   *
   * Build HTML render array with sortable table and links.
   */
  public function buildRender(array $data): array {
    $build = [
      '#type' => 'container',
    ];

    $paragraphs = $data['paragraphs'] ?? [];

    if (empty($paragraphs)) {
      $build['empty'] = [
        '#markup' => $this->t('No paragraph types with entity reference fields were found.'),
      ];
      return $build;
    }

    $request = \Drupal::request();
    $route_match = \Drupal::routeMatch();
    $route_name = $route_match->getRouteName();
    $route_params = $route_match->getRawParameters()->all();
    $current_query = $request->query->all();

    // Sorting state.
    $order = $request->query->get('order') ?: 'machine_name';
    $sort = strtolower($request->query->get('sort') ?: 'asc');

    $allowed_order = ['machine_name', 'label', 'reference_fields_count'];
    if (!in_array($order, $allowed_order, TRUE)) {
      $order = 'machine_name';
    }
    if (!in_array($sort, ['asc', 'desc'], TRUE)) {
      $sort = 'asc';
    }

    // Sort in PHP.
    uasort($paragraphs, static function (array $a, array $b) use ($order, $sort): int {
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

    // Build sortable header.
    $columns = [
      'machine_name' => $this->t('Paragraph machine name'),
      'label' => $this->t('Label'),
      'reference_fields_count' => $this->t('# reference fields'),
    ];

    $header = [];

    foreach ($columns as $key => $label) {
      $next_sort = ($order === $key && $sort === 'asc') ? 'desc' : 'asc';

      $query = $current_query;
      $query['order'] = $key;
      $query['sort'] = $next_sort;

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

    // Non-sortable columns.
    $header['reference_fields'] = [
      'data' => $this->t('Reference fields (type → target)'),
    ];
    $header['operations'] = [
      'data' => $this->t('Operations'),
    ];

    // Build rows.
    $rows = [];

    foreach ($paragraphs as $bundle => $info) {
      // Build reference fields markup with field-level links.
      $field_lines = [];
      foreach ($info['reference_fields'] as $field_name => $field_info) {
        // Field name + label.
        $field_label = $field_info['label'] ?? $field_name;

        // Link to edit field.
        $field_edit_path = $field_info['field_edit_path'] ?? NULL;
        if ($field_edit_path) {
          $field_url = Url::fromUserInput($field_edit_path, [
            'attributes' => ['target' => '_blank'],
          ]);
          $field_link = Link::fromTextAndUrl($field_name, $field_url)->toRenderable();
          $field_link['#prefix'] = '';
          $field_link['#suffix'] = '';
          // Render to string for the list; small inline render.
          $field_name_rendered = \Drupal::service('renderer')->renderPlain($field_link);
        }
        else {
          $field_name_rendered = htmlspecialchars($field_name, ENT_QUOTES, 'UTF-8');
        }

        $line = '`' . $field_name_rendered . '`';
        $line .= ' (' . htmlspecialchars((string) $field_label, ENT_QUOTES, 'UTF-8') . ')';
        $line .= ' – ' . htmlspecialchars((string) $field_info['type'], ENT_QUOTES, 'UTF-8');

        if (!empty($field_info['target_type'])) {
          $line .= ' → ' . htmlspecialchars((string) $field_info['target_type'], ENT_QUOTES, 'UTF-8');
          if (!empty($field_info['target_bundles'])) {
            $line .= ' [' . htmlspecialchars(implode(', ', $field_info['target_bundles']), ENT_QUOTES, 'UTF-8') . ']';
          }
        }

        $field_lines[] = $line;
      }

      $fields_markup = $field_lines
        ? '<ul><li>' . implode('</li><li>', $field_lines) . '</li></ul>'
        : $this->t('No reference fields.');

      // Paragraph type operations.
      $edit_url = Url::fromUserInput($info['edit_path'], [
        'attributes' => ['target' => '_blank'],
      ]);
      $fields_url = Url::fromUserInput($info['fields_path'], [
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
        'reference_fields_count' => [
          'data' => [
            '#markup' => (string) $info['reference_fields_count'],
          ],
        ],
        'reference_fields' => [
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

    $build['paragraphs'] = [
      '#type' => 'details',
      '#title' => $this->t('Paragraphs with entity reference fields'),
      '#open' => TRUE,
      'table' => [
        '#type' => 'table',
        '#header' => $header,
        '#rows' => $rows,
        '#empty' => $this->t('No paragraph types with entity reference fields were found.'),
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
    $paragraphs = $data['paragraphs'] ?? [];

    if (empty($paragraphs)) {
      return "No paragraph types with entity reference fields were found.\n";
    }

    $lines = [];

    $lines[] = '# Paragraph reference fields';
    $lines[] = '';
    $lines[] = '| Machine name | Label | # reference fields | Reference fields (type → target) |';
    $lines[] = '| --- | --- | ---: | --- |';

    foreach ($paragraphs as $bundle => $info) {
      $field_chunks = [];

      foreach ($info['reference_fields'] as $field_name => $field_info) {
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

      $fields_text = $field_chunks ? implode('<br>', $field_chunks) : 'No reference fields.';

      $lines[] = sprintf(
        '| `%s` | %s | %d | %s |',
        $bundle,
        $info['label'],
        $info['reference_fields_count'],
        $fields_text
      );
    }

    $lines[] = '';

    return implode("\n", $lines);
  }

}
