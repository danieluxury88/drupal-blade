<?php

declare(strict_types=1);

namespace Drupal\site_audit\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Collects content volume information (entity counts).
 */
class SiteAuditContentCollector {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs a SiteAuditContentCollector object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Get node counts keyed by bundle (content type).
   *
   * @param string[] $bundles
   *   List of node bundle machine names (e.g. ['page', 'article']).
   *
   * @return int[]
   *   Array keyed by bundle => count.
   */
  public function getNodeCountsByBundle(array $bundles): array {
    return $this->getCountsByBundle('node', 'type', $bundles);
  }

  /**
   * Get paragraph counts keyed by bundle (paragraph type).
   *
   * @param string[] $bundles
   *   List of paragraph bundle machine names.
   *
   * @return int[]
   *   Array keyed by bundle => count.
   */
  public function getParagraphCountsByBundle(array $bundles): array {
    return $this->getCountsByBundle('paragraph', 'type', $bundles);
  }

  /**
   * Generic helper to count entities by bundle.
   *
   * @param string $entity_type_id
   *   The entity type ID (e.g. 'node', 'paragraph', 'media').
   * @param string $bundle_key
   *   The bundle property key (e.g. 'type', 'bundle', 'vid').
   * @param string[] $bundles
   *   List of bundle machine names.
   *
   * @return int[]
   *   Array keyed by bundle => count.
   */
  protected function getCountsByBundle(string $entity_type_id, string $bundle_key, array $bundles): array {
    $storage = $this->entityTypeManager->getStorage($entity_type_id);
    $counts = [];

    foreach ($bundles as $bundle) {
      $query = $storage->getQuery()
        ->accessCheck(FALSE)
        ->condition($bundle_key, $bundle);

      $counts[$bundle] = (int) $query->count()->execute();
    }

    return $counts;
  }

}
