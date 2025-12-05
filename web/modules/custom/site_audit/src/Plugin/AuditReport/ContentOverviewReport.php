<?php

namespace Drupal\site_audit\Plugin\AuditReport;

/**
 * Provides a content & paragraph overview report.
 *
 * @AuditReport(
 *   id = "content_overview",
 *   label = @Translation("Content & paragraph overview"),
 *   description = @Translation("Summaries of content types and paragraph types with usage counts.")
 * )
 */
class ContentOverviewReport extends AuditReportBase {

  /**
   * {@inheritdoc}
   */
  public function buildData(): array {
    $time = \Drupal::time()->getRequestTime();
    $config_factory = \Drupal::configFactory();
    $site_config = $config_factory->get('system.site');

    return [
      'generated' => [
        'timestamp' => $time,
        'iso8601' => gmdate('c', $time),
      ],
      'site' => [
        'name' => $site_config->get('name'),
        'mail' => $site_config->get('mail'),
        'slogan' => $site_config->get('slogan'),
        'default_front_page' => $site_config->get('page.front'),
      ],
      'content_types' => $this->getContentTypeOverview(),
      'paragraph_types' => $this->getParagraphTypeOverview(),
    ];
  }

  /**
   * Content types + node counts, sorted by usage desc.
   */
  protected function getContentTypeOverview(): array {
    /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $etm */
    $etm = \Drupal::entityTypeManager();
    $storage = $etm->getStorage('node_type');
    $types = $storage->loadMultiple();

    $connection = \Drupal::database();

    $query = $connection->select('node_field_data', 'n');
    $query->fields('n', ['type']);
    $query->addExpression('COUNT(n.nid)', 'count');
    $query->groupBy('type');
    $counts = $query->execute()->fetchAllKeyed(0, 1);

    $result = [];
    foreach ($types as $machine_name => $type) {
      $result[$machine_name] = [
        'id' => $machine_name,
        'label' => $type->label(),
        'description' => $type->getDescription(),
        'nodes' => (int) ($counts[$machine_name] ?? 0),
      ];
    }

    // Sort by node count desc, then id.
    uasort($result, static function (array $a, array $b): int {
      if ($a['nodes'] === $b['nodes']) {
        return $a['id'] <=> $b['id'];
      }
      return $b['nodes'] <=> $a['nodes'];
    });

    return $result;
  }

  /**
   * Paragraph types + counts, sorted by usage desc.
   */
  protected function getParagraphTypeOverview(): array {
    /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $etm */
    $etm = \Drupal::entityTypeManager();

    if (!$etm->hasDefinition('paragraph')) {
      return [];
    }

    $type_storage = $etm->getStorage('paragraphs_type');
    $paragraph_types = $type_storage->loadMultiple();

    $paragraph_storage = $etm->getStorage('paragraph');

    $result = [];

    foreach ($paragraph_types as $machine_name => $type) {
      $query = $paragraph_storage->getQuery();
      $query->condition('type', $machine_name);
      $query->accessCheck(FALSE);
      $count = (int) $query->count()->execute();

      $result[$machine_name] = [
        'id' => $machine_name,
        'label' => $type->label(),
        'description' => $type->getDescription(),
        'paragraphs' => $count,
      ];
    }

    uasort($result, static function (array $a, array $b): int {
      if ($a['paragraphs'] === $b['paragraphs']) {
        return $a['id'] <=> $b['id'];
      }
      return $b['paragraphs'] <=> $a['paragraphs'];
    });

    return $result;
  }

  /**
   * {@inheritdoc}
   *
   * Nicer HTML: two tables.
   */
  public function buildRender(array $data): array {
    $build = [
      '#type' => 'container',
      'meta' => [
        '#markup' => $this->t(
          'Site: @site (generated: @date)',
          [
            '@site' => $data['site']['name'] ?? '',
            '@date' => $data['generated']['iso8601'] ?? '',
          ]
        ),
      ],
    ];

    // Content types.
    $rows = [];
    foreach ($data['content_types'] as $ct) {
      $rows[] = [
        $ct['id'],
        $ct['label'],
        $ct['nodes'],
        $ct['description'],
      ];
    }

    $build['content_types'] = [
      '#type' => 'details',
      '#title' => $this->t('Content types'),
      '#open' => TRUE,
      'table' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Machine name'),
          $this->t('Label'),
          $this->t('Nodes'),
          $this->t('Description'),
        ],
        '#rows' => $rows,
      ],
    ];

    // Paragraph types.
    if (!empty($data['paragraph_types'])) {
      $rows = [];
      foreach ($data['paragraph_types'] as $pt) {
        $rows[] = [
          $pt['id'],
          $pt['label'],
          $pt['paragraphs'],
          $pt['description'],
        ];
      }

      $build['paragraph_types'] = [
        '#type' => 'details',
        '#title' => $this->t('Paragraph types'),
        '#open' => TRUE,
        'table' => [
          '#type' => 'table',
          '#header' => [
            $this->t('Machine name'),
            $this->t('Label'),
            $this->t('Paragraphs'),
            $this->t('Description'),
          ],
          '#rows' => $rows,
        ],
      ];
    }

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function buildMarkdown(array $data): string {
    $lines = [];
    $lines[] = '# Site content audit';
    $lines[] = '';
    $lines[] = '**Site:** ' . ($data['site']['name'] ?? '(unknown)');
    $lines[] = '**Generated:** ' . ($data['generated']['iso8601'] ?? '');
    $lines[] = '';

    // Content types.
    $lines[] = '## Content types';
    $lines[] = '';
    $lines[] = '| Machine name | Label | Nodes |';
    $lines[] = '| ------------ | ----- | ----- |';
    foreach ($data['content_types'] as $ct) {
      $lines[] = sprintf(
        '| %s | %s | %d |',
        $ct['id'],
        $ct['label'],
        $ct['nodes']
      );
    }

    // Paragraph types.
    if (!empty($data['paragraph_types'])) {
      $lines[] = '';
      $lines[] = '## Paragraph types';
      $lines[] = '';
      $lines[] = '| Machine name | Label | Paragraphs |';
      $lines[] = '| ------------ | ----- | ---------- |';
      foreach ($data['paragraph_types'] as $pt) {
        $lines[] = sprintf(
          '| %s | %s | %d |',
          $pt['id'],
          $pt['label'],
          $pt['paragraphs']
        );
      }
    }

    $lines[] = '';
    return implode("\n", $lines);
  }

}
