<?php

declare(strict_types=1);

namespace Drupal\site_audit\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Collects content usage statistics for a specific bundle.
 */
class SiteAuditContentBundleCollector
{

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * Global content collector (bundle-level counts).
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
   * Constructs the content bundle collector.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\site_audit\Service\SiteAuditContentCollector $content_collector
   *   The global content collector.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    Connection $database,
    SiteAuditContentCollector $content_collector,
    EntityFieldManagerInterface $entity_field_manager,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->database = $database;
    $this->contentCollector = $content_collector;
    $this->entityFieldManager = $entity_field_manager;
  }

  /**
   * High-level stats for a node bundle.
   *
   * @return array{
   *   bundle: string,
   *   total: int,
   *   published: int,
   *   unpublished: int
   *   }
   */
  public function getNodeBundleUsageSummary(string $bundle): array
  {
    $node_counts = $this->contentCollector->getNodeCountsByBundle([$bundle]);
    $total = $node_counts[$bundle] ?? 0;

    $storage = $this->entityTypeManager->getStorage('node');

    // Published count.
    $published = (int) $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', $bundle)
      ->condition('status', 1)
      ->count()
      ->execute();

    // Unpublished = total - published.
    $unpublished = max(0, $total - $published);

    return [
      'bundle' => $bundle,
      'total' => $total,
      'published' => $published,
      'unpublished' => $unpublished,
    ];
  }

  /**
   * Paragraph usage per type for nodes of this bundle.
   *
   * Strategy:
   * - Find all paragraph reference fields on this bundle.
   * - For each field, join node base table + node__field + paragraph base table.
   * - Aggregate:
   *     - total paragraphs per type
   *     - distinct node count per type.
   *
   * Returns an array keyed by paragraph_type:
   *   [
   *     'text' => [
   *       'paragraph_type' => 'text',
   *       'label' => 'Text',
   *       'total_paragraphs' => 123,
   *       'node_count' => 45,
   *     ],
   *     ...
   *   ]
   *
   * @param string $bundle
   *   Node bundle (content type) machine name.
   *
   * @return array
   *   Paragraph usage info keyed by paragraph type.
   */
  public function getParagraphUsageForNodeBundle(string $bundle): array
  {
    $usage = [];

    // 1. Identify all fields on this bundle that reference paragraphs.
    $field_definitions = $this->entityFieldManager->getFieldDefinitions('node', $bundle);

    $paragraph_fields = [];
    foreach ($field_definitions as $field_name => $definition) {
      if ($definition->getType() !== 'entity_reference_revisions') {
        continue;
      }
      $settings = $definition->getSettings();
      if (($settings['target_type'] ?? NULL) !== 'paragraph') {
        continue;
      }
      $paragraph_fields[] = $field_name;
    }

    if (empty($paragraph_fields)) {
      // This bundle doesn't use paragraphs at all.
      return [];
    }

    // 2. Determine base tables for node and paragraph entities.
    $node_storage = $this->entityTypeManager->getStorage('node');
    $paragraph_storage = $this->entityTypeManager->getStorage('paragraph');

    $node_base_table = method_exists($node_storage, 'getBaseTable')
      ? $node_storage->getBaseTable()
      : 'node_field_data';

    $paragraph_base_table = method_exists($paragraph_storage, 'getBaseTable')
      ? $paragraph_storage->getBaseTable()
      : ($this->database->schema()->tableExists('paragraphs_item') ? 'paragraphs_item' : 'paragraph');

    // Map: paragraph_type => [ 'total_paragraphs' => int, 'nodes' => [nid => TRUE] ].
    $stats = [];

    // 3. For each paragraph field, collect usage.
    foreach ($paragraph_fields as $field_name) {
      $field_table = 'node__' . $field_name;
      $target_column = $field_name . '_target_id';

      // Skip if field table does not exist.
      if (!$this->database->schema()->tableExists($field_table)) {
        continue;
      }

      // Build the query; if something is odd (missing column, etc.), catch and skip.
      try {
        $query = $this->database->select($node_base_table, 'n');
        $query->addField('n', 'nid', 'nid');
        $query->join($field_table, 'f', 'f.entity_id = n.nid');
        $query->join($paragraph_base_table, 'p', 'p.id = f.' . $target_column);
        $query->addField('p', 'type', 'paragraph_type');

        $query->condition('n.type', $bundle);

        $result = $query->execute();
      } catch (\Throwable $e) {
        // If the table/column/join is weird for this field, just skip it.
        continue;
      }

      foreach ($result as $row) {
        $paragraph_type = $row->paragraph_type;
        $nid = (int) $row->nid;

        if (!isset($stats[$paragraph_type])) {
          $stats[$paragraph_type] = [
            'total_paragraphs' => 0,
            'nodes' => [],
          ];
        }

        $stats[$paragraph_type]['total_paragraphs']++;
        $stats[$paragraph_type]['nodes'][$nid] = TRUE;
      }
    }

    if (empty($stats)) {
      return [];
    }

    // 4. Load paragraph type labels for nicer output.
    $paragraph_type_ids = array_keys($stats);
    $paragraph_type_storage = $this->entityTypeManager->getStorage('paragraphs_type');
    /** @var \Drupal\paragraphs\Entity\ParagraphsType[] $paragraph_types */
    $paragraph_types = $paragraph_type_storage->loadMultiple($paragraph_type_ids);

    $usage = [];
    foreach ($stats as $paragraph_type => $info) {
      $label = isset($paragraph_types[$paragraph_type])
        ? $paragraph_types[$paragraph_type]->label()
        : $paragraph_type;

      $usage[$paragraph_type] = [
        'paragraph_type' => $paragraph_type,
        'label' => (string) $label,
        'total_paragraphs' => (int) $info['total_paragraphs'],
        'node_count' => count($info['nodes']),
      ];
    }

    // 5. Sort by total paragraphs descending to surface most-used components first.
    uasort($usage, static function (array $a, array $b): int {
      return $b['total_paragraphs'] <=> $a['total_paragraphs'];
    });

    return $usage;
  }

  /**
   * Example nodes that use a given paragraph type in this bundle.
   *
   * NOTE: Still a stub; can be implemented later using a similar approach.
   *
   * @return int[]
   *   Node IDs.
   */
  public function getExampleNodeIdsForBundleAndParagraph(
    string $bundle,
    string $paragraph_type,
    int $limit = 5,
  ): array {
    $node_ids = [];

    // @todo Implement if/when needed for the report UI.
    return $node_ids;
  }
}
