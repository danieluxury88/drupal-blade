<?php

namespace Drupal\site_audit\Plugin\AuditReport;

use Drupal;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\site_audit\Annotation\AuditReport;

/**
 * Provides a paragraph dependencies report.
 *
 * @AuditReport(
 *   id = "paragraph_dependencies",
 *   label = @Translation("Paragraph dependencies"),
 *   description = @Translation("Shows which content types and paragraph types use which paragraph types, including nesting.")
 * )
 */
class ParagraphDependenciesReport extends AuditReportBase {

  /**
   * {@inheritdoc}
   */
  public function buildData(): array {
    /** @var \Drupal\site_audit\Service\SiteAuditStructureCollector $collector */
    $collector = Drupal::service('site_audit.structure_collector');

    // Structural snapshots from the collector.
    $node_bundles = $collector->getNodeBundles();
    $paragraph_bundles = $collector->getParagraphBundles();
    $refs = $collector->getParagraphReferenceFields();

    $content_paragraph_fields = $refs['content_paragraph_fields'] ?? [];
    $paragraph_paragraph_fields = $refs['paragraph_paragraph_fields'] ?? [];

    // paragraphs_enabled: true if we actually have paragraph bundles.
    $paragraphs_enabled = !empty($paragraph_bundles);

    // Initialize usage summary.
    $paragraph_usage_summary = [];
    foreach ($paragraph_bundles as $pt_id => $info) {
      $paragraph_usage_summary[$pt_id] = [
        'id' => $pt_id,
        'label' => $info['label'],
        'used_in_node_bundles' => [],
        'used_in_paragraph_bundles' => [],
      ];
    }

    // Helper to ensure a paragraph type exists in the summary even if it's
    // referenced but not present as a bundle (defensive).
    $ensureUsageEntry = static function (string $pt_id) use (&$paragraph_usage_summary): void {
      if (!isset($paragraph_usage_summary[$pt_id])) {
        $paragraph_usage_summary[$pt_id] = [
          'id' => $pt_id,
          'label' => $pt_id,
          'used_in_node_bundles' => [],
          'used_in_paragraph_bundles' => [],
        ];
      }
    };

    // Node -> paragraph usage.
    foreach ($content_paragraph_fields as $node_bundle => $fields) {
      foreach ($fields as $field_info) {
        foreach ($field_info['allowed_paragraph_types'] as $pt_id) {
          $ensureUsageEntry($pt_id);
          $paragraph_usage_summary[$pt_id]['used_in_node_bundles'][$node_bundle] = TRUE;
        }
      }
    }

    // Paragraph -> paragraph usage (nesting).
    foreach ($paragraph_paragraph_fields as $parent_bundle => $fields) {
      foreach ($fields as $field_info) {
        foreach ($field_info['allowed_paragraph_types'] as $pt_id) {
          $ensureUsageEntry($pt_id);
          $paragraph_usage_summary[$pt_id]['used_in_paragraph_bundles'][$parent_bundle] = TRUE;
        }
      }
    }

    // Normalize usage arrays into sorted lists.
    foreach ($paragraph_usage_summary as $pt_id => &$usage) {
      $usage['used_in_node_bundles'] = array_values(array_unique(array_keys($usage['used_in_node_bundles'])));
      sort($usage['used_in_node_bundles']);

      $usage['used_in_paragraph_bundles'] = array_values(array_unique(array_keys($usage['used_in_paragraph_bundles'])));
      sort($usage['used_in_paragraph_bundles']);
    }
    unset($usage);

    // Sort base maps for readability.
    ksort($content_paragraph_fields);
    ksort($paragraph_paragraph_fields);
    ksort($paragraph_usage_summary);

    return [
      'paragraphs_enabled' => $paragraphs_enabled,
      'node_bundles' => $node_bundles,
      'paragraph_types' => $paragraph_bundles,
      'content_paragraph_fields' => $content_paragraph_fields,
      'paragraph_paragraph_fields' => $paragraph_paragraph_fields,
      'paragraph_usage_summary' => $paragraph_usage_summary,
    ];
  }

  /**
   * {@inheritdoc}
   *
   * HTML: 3 sections:
   *  - Content types that use paragraphs.
   *  - Paragraph types that contain paragraphs.
   *  - Usage summary per paragraph type.
   */
  public function buildRender(array $data): array {
    $build = [
      '#type' => 'container',
    ];

    if (empty($data['paragraphs_enabled'])) {
      $build['message'] = [
        '#markup' => $this->t('Paragraphs entity type is not available or has no bundles. No paragraph dependencies to show.'),
      ];
      return $build;
    }

    $node_bundles = $data['node_bundles'] ?? [];
    $paragraph_types = $data['paragraph_types'] ?? [];

    // Helper: label for node bundle.
    $bundleLabel = static function (string $bundle, array $node_bundles): string {
      if (isset($node_bundles[$bundle])) {
        return $bundle . ' (' . $node_bundles[$bundle]['label'] . ')';
      }
      return $bundle;
    };

    // Helper: label for paragraph bundle.
    $paragraphLabel = static function (string $bundle, array $paragraph_types): string {
      if (isset($paragraph_types[$bundle])) {
        return $bundle . ' (' . $paragraph_types[$bundle]['label'] . ')';
      }
      return $bundle;
    };

    // 1) Content types that use paragraphs.
    $build['content_paragraph_fields'] = [
      '#type' => 'details',
      '#title' => $this->t('Content types that use paragraphs'),
      '#open' => TRUE,
    ];

    if (!empty($data['content_paragraph_fields'])) {
      foreach ($data['content_paragraph_fields'] as $bundle => $fields) {
        $rows = [];
        foreach ($fields as $field) {
          $edit_link = NULL;
          if (!empty($field['edit_path'])) {
            $url = Url::fromUserInput($field['edit_path']);
            $url->setOption('attributes', ['target' => '_blank']);
            $edit_link = Link::fromTextAndUrl(
              $this->t('Edit field'),
              $url
            )->toRenderable();
          }

          $rows[] = [
            $field['field_name'],
            $field['label'],
            implode(', ', $field['allowed_paragraph_types']),
            $field['cardinality'] ?? '',
            ['data' => $edit_link],
          ];
        }

        // Bundle-level operations: Edit content type + Manage fields.
        $bundle_items = [];

        if (!empty($node_bundles[$bundle]['edit_path'])) {
          $url = Url::fromUserInput($node_bundles[$bundle]['edit_path']);
          $url->setOption('attributes', ['target' => '_blank']);
          $bundle_items[] = Link::fromTextAndUrl(
            $this->t('Edit content type'),
            $url
          )->toRenderable();
        }

        if (!empty($node_bundles[$bundle]['fields_path'])) {
          $url = Url::fromUserInput($node_bundles[$bundle]['fields_path']);
          $url->setOption('attributes', ['target' => '_blank']);
          $bundle_items[] = Link::fromTextAndUrl(
            $this->t('Manage fields'),
            $url
          )->toRenderable();
        }

        $build['content_paragraph_fields'][$bundle] = [
          '#type' => 'details',
          '#title' => $bundleLabel($bundle, $node_bundles),
          '#open' => FALSE,
        ];

        if (!empty($bundle_items)) {
          $build['content_paragraph_fields'][$bundle]['bundle_operations'] = [
            '#theme' => 'item_list',
            '#items' => $bundle_items,
          ];
        }

        $build['content_paragraph_fields'][$bundle]['table'] = [
          '#type' => 'table',
          '#header' => [
            $this->t('Field'),
            $this->t('Label'),
            $this->t('Allowed paragraph types'),
            $this->t('Cardinality'),
            $this->t('Field operations'),
          ],
          '#rows' => $rows,
        ];
      }
    }
    else {
      $build['content_paragraph_fields']['empty'] = [
        '#markup' => $this->t('No content types with paragraph reference fields were found.'),
      ];
    }

    // 2) Paragraph types that contain other paragraphs.
    $build['paragraph_paragraph_fields'] = [
      '#type' => 'details',
      '#title' => $this->t('Paragraph types that contain paragraphs'),
      '#open' => FALSE,
    ];

    if (!empty($data['paragraph_paragraph_fields'])) {
      foreach ($data['paragraph_paragraph_fields'] as $bundle => $fields) {
        $rows = [];
        foreach ($fields as $field) {
          $edit_link = NULL;
          if (!empty($field['edit_path'])) {
            $url = Url::fromUserInput($field['edit_path']);
            $url->setOption('attributes', ['target' => '_blank']);
            $edit_link = Link::fromTextAndUrl(
              $this->t('Edit field'),
              $url
            )->toRenderable();
          }

          $rows[] = [
            $field['field_name'],
            $field['label'],
            implode(', ', $field['allowed_paragraph_types']),
            $field['cardinality'] ?? '',
            ['data' => $edit_link],
          ];
        }

        $bundle_items = [];

        if (!empty($paragraph_types[$bundle]['edit_path'])) {
          $url = Url::fromUserInput($paragraph_types[$bundle]['edit_path']);
          $url->setOption('attributes', ['target' => '_blank']);
          $bundle_items[] = Link::fromTextAndUrl(
            $this->t('Edit paragraph type'),
            $url
          )->toRenderable();
        }

        if (!empty($paragraph_types[$bundle]['fields_path'])) {
          $url = Url::fromUserInput($paragraph_types[$bundle]['fields_path']);
          $url->setOption('attributes', ['target' => '_blank']);
          $bundle_items[] = Link::fromTextAndUrl(
            $this->t('Manage fields'),
            $url
          )->toRenderable();
        }

        $build['paragraph_paragraph_fields'][$bundle] = [
          '#type' => 'details',
          '#title' => $paragraphLabel($bundle, $paragraph_types),
          '#open' => FALSE,
        ];

        if (!empty($bundle_items)) {
          $build['paragraph_paragraph_fields'][$bundle]['bundle_operations'] = [
            '#theme' => 'item_list',
            '#items' => $bundle_items,
          ];
        }

        $build['paragraph_paragraph_fields'][$bundle]['table'] = [
          '#type' => 'table',
          '#header' => [
            $this->t('Field'),
            $this->t('Label'),
            $this->t('Allowed paragraph types'),
            $this->t('Cardinality'),
            $this->t('Field operations'),
          ],
          '#rows' => $rows,
        ];
      }
    }
    else {
      $build['paragraph_paragraph_fields']['empty'] = [
        '#markup' => $this->t('No paragraph types with paragraph reference fields (nesting) were found.'),
      ];
    }

    // 3) Usage summary per paragraph type.
    $build['usage_summary'] = [
      '#type' => 'details',
      '#title' => $this->t('Paragraph usage summary'),
      '#open' => FALSE,
    ];

    if (!empty($data['paragraph_usage_summary'])) {
      $rows = [];
      foreach ($data['paragraph_usage_summary'] as $usage) {
        // Paragraph type cell as link if we have edit_path.
        $para_cell = $usage['id'];
        if (!empty($paragraph_types[$usage['id']]['edit_path'])) {
          $url = Url::fromUserInput($paragraph_types[$usage['id']]['edit_path']);
          $url->setOption('attributes', ['target' => '_blank']);
          $para_cell = [
            'data' => Link::fromTextAndUrl(
              $usage['id'],
              $url
            )->toRenderable(),
          ];
        }

        // Display content types as "machine (Label)".
        $used_in_nodes = '—';
        if (!empty($usage['used_in_node_bundles'])) {
          $labels = [];
          foreach ($usage['used_in_node_bundles'] as $bundle) {
            $labels[] = $bundleLabel($bundle, $node_bundles);
          }
          $used_in_nodes = implode(', ', $labels);
        }

        // Display paragraph parents as "machine (Label)".
        $used_in_paragraphs = '—';
        if (!empty($usage['used_in_paragraph_bundles'])) {
          $labels = [];
          foreach ($usage['used_in_paragraph_bundles'] as $bundle) {
            $labels[] = $paragraphLabel($bundle, $paragraph_types);
          }
          $used_in_paragraphs = implode(', ', $labels);
        }

        $rows[] = [
          $para_cell,
          $usage['label'],
          $used_in_nodes,
          $used_in_paragraphs,
        ];
      }

      $build['usage_summary']['table'] = [
        '#type' => 'table',
        '#header' => [
          $this->t('Paragraph type'),
          $this->t('Label'),
          $this->t('Used in content types'),
          $this->t('Used in paragraph types'),
        ],
        '#rows' => $rows,
      ];
    }
    else {
      $build['usage_summary']['empty'] = [
        '#markup' => $this->t('No paragraph usage information available.'),
      ];
    }

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function buildMarkdown(array $data): string {
    if (empty($data['paragraphs_enabled'])) {
      return "Paragraphs entity type is not available or has no bundles.\n";
    }

    $node_bundles = $data['node_bundles'] ?? [];
    $paragraph_types = $data['paragraph_types'] ?? [];

    $bundleLabel = static function (string $bundle, array $node_bundles): string {
      if (isset($node_bundles[$bundle])) {
        return $bundle . ' (' . $node_bundles[$bundle]['label'] . ')';
      }
      return $bundle;
    };

    $paragraphLabel = static function (string $bundle, array $paragraph_types): string {
      if (isset($paragraph_types[$bundle])) {
        return $bundle . ' (' . $paragraph_types[$bundle]['label'] . ')';
      }
      return $bundle;
    };

    $lines = [];

    $lines[] = '# Paragraph dependencies';
    $lines[] = '';

    // 1) Content types that use paragraphs.
    $lines[] = '## Content types that use paragraphs';
    $lines[] = '';

    if (!empty($data['content_paragraph_fields'])) {
      foreach ($data['content_paragraph_fields'] as $bundle => $fields) {
        $label = $bundleLabel($bundle, $node_bundles);

        // Heading as link if we have edit_path.
        if (!empty($node_bundles[$bundle]['edit_path'])) {
          $lines[] = '### [' . $label . '](' . $node_bundles[$bundle]['edit_path'] . ')';
        }
        else {
          $lines[] = '### ' . $label;
        }

        // Manage fields link.
        if (!empty($node_bundles[$bundle]['fields_path'])) {
          $lines[] = '';
          $lines[] = '- [Manage fields](' . $node_bundles[$bundle]['fields_path'] . ')';
        }

        $lines[] = '';
        $lines[] = '| Field | Label | Allowed paragraph types | Cardinality |';
        $lines[] = '| ----- | ----- | ---------------------- | ----------- |';
        foreach ($fields as $field) {
          $lines[] = sprintf(
            '| %s | %s | %s | %s |',
            $field['field_name'],
            $field['label'],
            implode(', ', $field['allowed_paragraph_types']),
            $field['cardinality'] ?? ''
          );
        }
        $lines[] = '';
      }
    }
    else {
      $lines[] = '_No content types with paragraph reference fields were found._';
      $lines[] = '';
    }

    // 2) Paragraph types that contain other paragraphs.
    $lines[] = '## Paragraph types that contain paragraphs';
    $lines[] = '';

    if (!empty($data['paragraph_paragraph_fields'])) {
      foreach ($data['paragraph_paragraph_fields'] as $bundle => $fields) {
        $label = $paragraphLabel($bundle, $paragraph_types);

        if (!empty($paragraph_types[$bundle]['edit_path'])) {
          $lines[] = '### [' . $label . '](' . $paragraph_types[$bundle]['edit_path'] . ')';
        }
        else {
          $lines[] = '### ' . $label;
        }

        if (!empty($paragraph_types[$bundle]['fields_path'])) {
          $lines[] = '';
          $lines[] = '- [Manage fields](' . $paragraph_types[$bundle]['fields_path'] . ')';
        }

        $lines[] = '';
        $lines[] = '| Field | Label | Allowed paragraph types | Cardinality |';
        $lines[] = '| ----- | ----- | ---------------------- | ----------- |';
        foreach ($fields as $field) {
          $lines[] = sprintf(
            '| %s | %s | %s | %s |',
            $field['field_name'],
            $field['label'],
            implode(', ', $field['allowed_paragraph_types']),
            $field['cardinality'] ?? ''
          );
        }
        $lines[] = '';
      }
    }
    else {
      $lines[] = '_No paragraph nesting fields were found._';
      $lines[] = '';
    }

    // 3) Usage summary.
    $lines[] = '## Paragraph usage summary';
    $lines[] = '';

    if (!empty($data['paragraph_usage_summary'])) {
      $lines[] = '| Paragraph type | Label | Used in content types | Used in paragraph types |';
      $lines[] = '| -------------- | ----- | --------------------- | ------------------------ |';
      foreach ($data['paragraph_usage_summary'] as $usage) {
        // Paragraph type cell as link if we have edit_path.
        $para_cell = $usage['id'];
        if (!empty($paragraph_types[$usage['id']]['edit_path'])) {
          $para_cell = '[' . $usage['id'] . '](' . $paragraph_types[$usage['id']]['edit_path'] . ')';
        }

        $used_in_nodes = '—';
        if (!empty($usage['used_in_node_bundles'])) {
          $labels = [];
          foreach ($usage['used_in_node_bundles'] as $bundle) {
            $labels[] = $bundleLabel($bundle, $node_bundles);
          }
          $used_in_nodes = implode(', ', $labels);
        }

        $used_in_paragraphs = '—';
        if (!empty($usage['used_in_paragraph_bundles'])) {
          $labels = [];
          foreach ($usage['used_in_paragraph_bundles'] as $bundle) {
            $labels[] = $paragraphLabel($bundle, $paragraph_types);
          }
          $used_in_paragraphs = implode(', ', $labels);
        }

        $lines[] = sprintf(
          '| %s | %s | %s | %s |',
          $para_cell,
          $usage['label'],
          $used_in_nodes,
          $used_in_paragraphs
        );
      }
      $lines[] = '';
    }
    else {
      $lines[] = '_No paragraph usage information available._';
      $lines[] = '';
    }

    return implode("\n", $lines);
  }

}
