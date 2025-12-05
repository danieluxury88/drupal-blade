<?php

namespace Drupal\site_audit\Plugin\AuditReport;

use Drupal;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\site_audit\Annotation\AuditReport;

/**
 * Provides a field-level overview report.
 *
 * @AuditReport(
 *   id = "field_overview",
 *   label = @Translation("Field overview"),
 *   description = @Translation("Lists fields per content type and paragraph type."),
 *   enabled = FALSE,
 * )
 */
class FieldOverviewReport extends AuditReportBase {

  /**
   * {@inheritdoc}
   */
  public function buildData(): array {
    /** @var \Drupal\site_audit\Service\SiteAuditStructureCollector $collector */
    $collector = Drupal::service('site_audit.structure_collector');

    return [
      'node_fields' => $collector->getFields('node'),
      'paragraph_fields' => $collector->getFields('paragraph'),
    ];
  }

  /**
   * Fields per node bundle (content type).
   *
   * Structure:
   * [
   *   'article' => [
   *     'field_body' => [
   *       'field_name' => 'field_body',
   *       'label' => 'Body',
   *       'field_type' => 'text_with_summary',
   *       'required' => TRUE,
   *     ],
   *     ...
   *   ],
   *   ...
   * ]
   */
  protected function getNodeFieldOverview(): array {
    $etm = Drupal::entityTypeManager();

    /** @var \Drupal\field\FieldConfigInterface[] $configs */
    $configs = $etm->getStorage('field_config')->loadByProperties([
      'entity_type' => 'node',
    ]);

    $result = [];

    foreach ($configs as $config) {
      $bundle = $config->getTargetBundle();
      $field_name = $config->getName();

      $result[$bundle][$field_name] = [
        'field_name' => $field_name,
        'label' => $config->label(),
        'field_type' => $config->getType(),
        'required' => (bool) $config->isRequired(),
      ];
    }

    // Sort bundles and fields for readability.
    ksort($result);
    foreach ($result as &$fields) {
      ksort($fields);
    }

    return $result;
  }

  /**
   * Fields per paragraph type (if paragraphs exists).
   *
   * Same structure as getNodeFieldOverview(), but keyed by paragraph bundle.
   */
  protected function getParagraphFieldOverview(): array {
    $etm = Drupal::entityTypeManager();

    if (!$etm->hasDefinition('paragraph')) {
      return [];
    }

    /** @var \Drupal\field\FieldConfigInterface[] $configs */
    $configs = $etm->getStorage('field_config')->loadByProperties([
      'entity_type' => 'paragraph',
    ]);

    $result = [];

    foreach ($configs as $config) {
      $bundle = $config->getTargetBundle();
      $field_name = $config->getName();

      $result[$bundle][$field_name] = [
        'field_name' => $field_name,
        'label' => $config->label(),
        'field_type' => $config->getType(),
        'required' => (bool) $config->isRequired(),
      ];
    }

    ksort($result);
    foreach ($result as &$fields) {
      ksort($fields);
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   *
   * HTML: details per bundle, each with a table of fields.
   */
  public function buildRender(array $data): array {
    $build = [
      '#type' => 'container',
    ];

    // Node fields.
    $build['node_fields'] = [
      '#type' => 'details',
      '#title' => $this->t('Node fields'),
      '#open' => TRUE,
    ];

    if (!empty($data['node_fields'])) {
      foreach ($data['node_fields'] as $bundle => $fields) {
        $rows = [];
        foreach ($fields as $field) {
          $edit_link = NULL;
          if (!empty($field['edit_path'])) {
            $edit_link = Link::fromTextAndUrl(
              $this->t('Edit'),
              Url::fromUserInput($field['edit_path'])
            )->toRenderable();
          }

          $rows[] = [
            $field['field_name'],
            $field['label'],
            $field['field_type'],
            $field['required'] ? $this->t('Yes') : $this->t('No'),
            ['data' => $edit_link],
          ];
        }

        $build['node_fields'][$bundle] = [
          '#type' => 'details',
          '#title' => $bundle,
          '#open' => FALSE,
          'table' => [
            '#type' => 'table',
            '#header' => [
              $this->t('Field'),
              $this->t('Label'),
              $this->t('Type'),
              $this->t('Required'),
              $this->t('Operations'),
            ],
            '#rows' => $rows,
          ],
        ];
      }
    }
    else {
      $build['node_fields']['empty'] = [
        '#markup' => $this->t('No node fields found.'),
      ];
    }

    // Paragraph fields.
    $build['paragraph_fields'] = [
      '#type' => 'details',
      '#title' => $this->t('Paragraph fields'),
      '#open' => FALSE,
    ];

    if (!empty($data['paragraph_fields'])) {
      foreach ($data['paragraph_fields'] as $bundle => $fields) {
        $rows = [];
        foreach ($fields as $field) {
          $edit_link = NULL;
          if (!empty($field['edit_path'])) {
            $edit_link = Link::fromTextAndUrl(
              $this->t('Edit'),
              Url::fromUserInput($field['edit_path'])
            )->toRenderable();
          }

          $rows[] = [
            $field['field_name'],
            $field['label'],
            $field['field_type'],
            $field['required'] ? $this->t('Yes') : $this->t('No'),
            ['data' => $edit_link],
          ];
        }

        $build['paragraph_fields'][$bundle] = [
          '#type' => 'details',
          '#title' => $bundle,
          '#open' => FALSE,
          'table' => [
            '#type' => 'table',
            '#header' => [
              $this->t('Field'),
              $this->t('Label'),
              $this->t('Type'),
              $this->t('Required'),
              $this->t('Operations'),
            ],
            '#rows' => $rows,
          ],
        ];
      }
    }
    else {
      $build['paragraph_fields']['empty'] = [
        '#markup' => $this->t('No paragraph fields found or Paragraphs not enabled.'),
      ];
    }

    return $build;
  }
  /**
   * {@inheritdoc}
   */
  public function buildMarkdown(array $data): string {
    $lines = [];

    // Node fields.
    $lines[] = '# Field overview';
    $lines[] = '';
    $lines[] = '## Node fields';
    $lines[] = '';

    if (!empty($data['node_fields'])) {
      foreach ($data['node_fields'] as $bundle => $fields) {
        $lines[] = '### ' . $bundle;
        $lines[] = '';
        $lines[] = '| Field | Label | Type | Required |';
        $lines[] = '| ----- | ----- | ---- | -------- |';
        foreach ($fields as $field) {
          $lines[] = sprintf(
            '| %s | %s | %s | %s |',
            $field['field_name'],
            $field['label'],
            $field['field_type'],
            $field['required'] ? 'yes' : 'no'
          );
        }
        $lines[] = '';
      }
    } else {
      $lines[] = '_No node fields found._';
      $lines[] = '';
    }

    // Paragraph fields.
    $lines[] = '## Paragraph fields';
    $lines[] = '';

    if (!empty($data['paragraph_fields'])) {
      foreach ($data['paragraph_fields'] as $bundle => $fields) {
        $lines[] = '### ' . $bundle;
        $lines[] = '';
        $lines[] = '| Field | Label | Type | Required |';
        $lines[] = '| ----- | ----- | ---- | -------- |';
        foreach ($fields as $field) {
          $lines[] = sprintf(
            '| %s | %s | %s | %s |',
            $field['field_name'],
            $field['label'],
            $field['field_type'],
            $field['required'] ? 'yes' : 'no'
          );
        }
        $lines[] = '';
      }
    } else {
      $lines[] = '_No paragraph fields found or Paragraphs module not enabled._';
      $lines[] = '';
    }

    return implode("\n", $lines);
  }

}
