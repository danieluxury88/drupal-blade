<?php

declare(strict_types=1);

namespace Drupal\site_audit\Service;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Collects configuration details for a specific bundle.
 *
 * Focus: fields, paragraph references, OCM-related dependencies.
 */
class SiteAuditConfigBundleCollector {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityFieldManagerInterface $entityFieldManager,
    protected SiteAuditStructureCollector $structureCollector,
    protected ModuleHandlerInterface $moduleHandler,
  ) {}

  /**
   * Get config summary for a node bundle.
   */
  public function getNodeBundleConfigSummary(string $bundle): array {
    $bundles = $this->structureCollector->getNodeBundles();
    $info = $bundles[$bundle] ?? [
      'label' => $bundle,
      'description' => '',
      'edit_path' => "/admin/structure/types/manage/$bundle",
      'fields_path' => "/admin/structure/types/manage/$bundle/fields",
    ];

    $fields_by_bundle = $this->structureCollector->getFields('node');
    $fields = $fields_by_bundle[$bundle] ?? [];

    // We can refine which are paragraph reference fields using existing helpers
    // or by inspecting field definitions:
    $paragraph_fields = $this->extractParagraphReferenceFields('node', $bundle, $fields);

    $ocm_dependencies = $this->getOcmDependenciesForBundle('node', $bundle);

    return [
      'bundle' => $bundle,
      'label' => (string) ($info['label'] ?? $bundle),
      'description' => (string) ($info['description'] ?? ''),
      'edit_path' => (string) ($info['edit_path'] ?? "/admin/structure/types/manage/$bundle"),
      'fields_path' => (string) ($info['fields_path'] ?? "/admin/structure/types/manage/$bundle/fields"),
      'fields' => $fields,
      'paragraph_fields' => $paragraph_fields,
      'ocm_dependencies' => $ocm_dependencies,
    ];
  }

  /**
   * Get config summary for a paragraph bundle.
   */
  public function getParagraphBundleConfigSummary(string $bundle): array {
    $bundles = $this->structureCollector->getParagraphBundles();
    $info = $bundles[$bundle] ?? [
      'label' => $bundle,
      'description' => '',
      'edit_path' => "/admin/structure/paragraphs_type/$bundle",
      'fields_path' => "/admin/structure/paragraphs_type/$bundle/fields",
    ];

    $fields_by_bundle = $this->structureCollector->getFields('paragraph');
    $fields = $fields_by_bundle[$bundle] ?? [];

    $paragraph_fields = $this->extractParagraphReferenceFields('paragraph', $bundle, $fields);
    $ocm_dependencies = $this->getOcmDependenciesForBundle('paragraph', $bundle);

    return [
      'bundle' => $bundle,
      'label' => (string) ($info['label'] ?? $bundle),
      'description' => (string) ($info['description'] ?? ''),
      'edit_path' => (string) ($info['edit_path'] ?? "/admin/structure/paragraphs_type/$bundle"),
      'fields_path' => (string) ($info['fields_path'] ?? "/admin/structure/paragraphs_type/$bundle/fields"),
      'fields' => $fields,
      'paragraph_fields' => $paragraph_fields,
      'ocm_dependencies' => $ocm_dependencies,
    ];
  }

  /**
   * Extract paragraph reference fields from a bundle's field list.
   *
   * @param string $entity_type_id
   *   'node' or 'paragraph'.
   * @param string $bundle
   *   Bundle machine name.
   * @param array $fields
   *   Field metadata from SiteAuditStructureCollector.
   *
   * @return array
   *   Array keyed by field_name => info + allowed paragraph types.
   */
  protected function extractParagraphReferenceFields(string $entity_type_id, string $bundle, array $fields): array {
    $paragraph_fields = [];

    // Option A: use existing getParagraphReferenceFields() and filter.
    // Option B: inspect field definitions here via $entityFieldManager.

    // Skeleton implementation; can be refined later.
    foreach ($fields as $field_name => $info) {
      // Later: check field type === 'entity_reference_revisions'
      // and target_type === 'paragraph', and include allowed bundles.
      if (!empty($info['target_type']) && $info['target_type'] === 'paragraph') {
        $paragraph_fields[$field_name] = $info;
      }
    }

    return $paragraph_fields;
  }

  /**
   * Detect OCM-related dependencies for a bundle.
   *
   * For now this is a stub; later we'll inspect field types, widgets,
   * third-party settings, and modules like 'ocx_*'.
   */
  public function getOcmDependenciesForBundle(string $entity_type_id, string $bundle): array {
    $dependencies = [];

    // Example idea:
    // - look at field definitions on this bundle
    // - if field type, widget, or third-party settings reference OCM modules,
    //   add them to this list.
    //
    // We can fill this in when we implement the OCM report.

    return $dependencies;
  }

}
