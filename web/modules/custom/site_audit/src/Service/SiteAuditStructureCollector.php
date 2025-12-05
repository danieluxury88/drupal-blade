<?php

namespace Drupal\site_audit\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Collects structural/config information (bundles, fields, references).
 */
class SiteAuditStructureCollector {

  /**
   * Constructs the collector.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Node bundles (content types) basic info.
   *
   * @return array
   *   [
   *     'article' => [
   *       'id' => 'article',
   *       'label' => 'Article',
   *       'description' => '...',
   *       'edit_path' => '/admin/structure/types/manage/article',
   *       'fields_path' => '/admin/structure/types/manage/article/fields',
   *     ],
   *     ...
   *   ]
   */
  public function getNodeBundles(): array {
    $storage = $this->entityTypeManager->getStorage('node_type');
    $types = $storage->loadMultiple();

    $result = [];
    foreach ($types as $id => $type) {
      // Be defensive about getDescription().
      $description = '';
      if (method_exists($type, 'getDescription')) {
        $description = (string) $type->getDescription();
      }
      elseif (method_exists($type, 'get')) {
        $description = (string) $type->get('description');
      }

      $edit_path = '';
      try {
        $edit_path = $type->toUrl('edit-form')->toString();
      }
      catch (\Throwable $e) {
        // Leave edit_path empty if something goes wrong.
      }

      // Fields management path follows a stable pattern.
      $fields_path = '/admin/structure/types/manage/' . $id . '/fields';

      $result[$id] = [
        'id' => $id,
        'label' => $type->label(),
        'description' => $description,
        'edit_path' => $edit_path ?: '/admin/structure/types/manage/' . $id,
        'fields_path' => $fields_path,
      ];
    }

    ksort($result);
    return $result;
  }

  /**
   * Paragraph bundles basic info.
   *
   * @return array
   *   [
   *     'text' => [
   *       'id' => 'text',
   *       'label' => 'Text',
   *       'description' => '...',
   *       'edit_path' => '/admin/structure/paragraphs_type/text',
   *       'fields_path' => '/admin/structure/paragraphs_type/text/fields',
   *     ],
   *     ...
   *   ]
   */
  public function getParagraphBundles(): array {
    if (!$this->entityTypeManager->hasDefinition('paragraph')) {
      return [];
    }

    $storage = $this->entityTypeManager->getStorage('paragraphs_type');
    $types = $storage->loadMultiple();

    $result = [];
    foreach ($types as $id => $type) {
      // ParagraphsType may or may not have getDescription().
      $description = '';
      if (method_exists($type, 'getDescription')) {
        $description = (string) $type->getDescription();
      }
      elseif (method_exists($type, 'get')) {
        $description = (string) $type->get('description');
      }

      $edit_path = '';
      try {
        $edit_path = $type->toUrl('edit-form')->toString();
      }
      catch (\Throwable $e) {
        // Leave edit_path empty if URL cannot be generated.
      }

      $fields_path = '/admin/structure/paragraphs_type/' . $id . '/fields';

      $result[$id] = [
        'id' => $id,
        'label' => $type->label(),
        'description' => $description,
        'edit_path' => $edit_path ?: '/admin/structure/paragraphs_type/' . $id,
        'fields_path' => $fields_path,
      ];
    }

    ksort($result);
    return $result;
  }

  /**
   * Field configs for an entity type, keyed by bundle then field name.
   *
   * @param string $entity_type
   *   The entity type ID ('node', 'paragraph', etc.).
   *
   * @return array
   *   [
   *     'bundle' => [
   *       'field_name' => [
   *         'field_name' => 'field_x',
   *         'label' => 'My field',
   *         'field_type' => 'text_long',
   *         'required' => TRUE,
   *         'edit_path' => '/admin/.../fields/....',
   *       ],
   *       ...
   *     ],
   *     ...
   *   ]
   */
  public function getFields(string $entity_type): array {
    $storage = $this->entityTypeManager->getStorage('field_config');
    $configs = $storage->loadByProperties(['entity_type' => $entity_type]);

    $result = [];
    foreach ($configs as $config) {
      $bundle = $config->getTargetBundle();
      $field_name = $config->getName();

      $edit_path = '';
      try {
        $edit_path = $config->toUrl('edit-form')->toString();
      }
      catch (\Throwable $e) {
        // Leave empty if route not available.
      }

      $result[$bundle][$field_name] = [
        'field_name' => $field_name,
        'label' => $config->label(),
        'field_type' => $config->getType(),
        'required' => (bool) $config->isRequired(),
        'edit_path' => $edit_path,
      ];
    }

    ksort($result);
    foreach ($result as &$fields) {
      ksort($fields);
    }

    return $result;
  }

  /**
   * Node/paragraph fields that reference paragraphs (dependencies).
   *
   * @return array
   *   [
   *     'content_paragraph_fields' => [
   *       'page' => [
   *         'field_sections' => [
   *           'field_name' => 'field_sections',
   *           'label' => 'Sections',
   *           'cardinality' => 'unlimited',
   *           'allowed_paragraph_types' => ['hero', 'text', ...],
   *           'edit_path' => '/admin/...field edit...',
   *         ],
   *         ...
   *       ],
   *       ...
   *     ],
   *     'paragraph_paragraph_fields' => [
   *       'section' => [
   *         'field_items' => [
   *           'field_name' => 'field_items',
   *           'label' => 'Items',
   *           'cardinality' => 'unlimited',
   *           'allowed_paragraph_types' => ['text', 'image', ...],
   *           'edit_path' => '/admin/...field edit...',
   *         ],
   *         ...
   *       ],
   *       ...
   *     ],
   *   ]
   */
  public function getParagraphReferenceFields(): array {
    // Paragraph bundles list used when "all" paragraph types are allowed.
    $paragraph_bundles = array_keys($this->getParagraphBundles());

    $field_config_storage = $this->entityTypeManager->getStorage('field_config');

    /** @var \Drupal\field\Entity\FieldConfig[] $node_field_configs */
    $node_field_configs = $field_config_storage->loadByProperties([
      'entity_type' => 'node',
    ]);

    /** @var \Drupal\field\Entity\FieldConfig[] $paragraph_field_configs */
    $paragraph_field_configs = $field_config_storage->loadByProperties([
      'entity_type' => 'paragraph',
    ]);

    $content_paragraph_fields = [];
    $paragraph_paragraph_fields = [];

    $interpret_cardinality = static function (int $cardinality): string {
      if ($cardinality === -1) {
        return 'unlimited';
      }
      if ($cardinality === 1) {
        return '1';
      }
      return (string) $cardinality;
    };

    // Node -> paragraph fields.
    foreach ($node_field_configs as $config) {
      if ($config->getType() !== 'entity_reference_revisions') {
        continue;
      }
      if ($config->getSetting('target_type') !== 'paragraph') {
        continue;
      }

      $bundle = $config->getTargetBundle();
      $field_name = $config->getName();
      $cardinality = $interpret_cardinality($config->getFieldStorageDefinition()->getCardinality());

      $handler_settings = $config->getSetting('handler_settings') ?? [];
      $target_bundles = $handler_settings['target_bundles'] ?? [];

      $allowed = empty($target_bundles) ? $paragraph_bundles : array_keys($target_bundles);

      $edit_path = '';
      try {
        $edit_path = $config->toUrl('edit-form')->toString();
      }
      catch (\Throwable $e) {}

      $content_paragraph_fields[$bundle][$field_name] = [
        'field_name' => $field_name,
        'label' => $config->label(),
        'cardinality' => $cardinality,
        'allowed_paragraph_types' => $allowed,
        'edit_path' => $edit_path,
      ];
    }

    // Paragraph -> paragraph (nesting).
    foreach ($paragraph_field_configs as $config) {
      if ($config->getType() !== 'entity_reference_revisions') {
        continue;
      }
      if ($config->getSetting('target_type') !== 'paragraph') {
        continue;
      }

      $bundle = $config->getTargetBundle();
      $field_name = $config->getName();
      $cardinality = $interpret_cardinality($config->getFieldStorageDefinition()->getCardinality());

      $handler_settings = $config->getSetting('handler_settings') ?? [];
      $target_bundles = $handler_settings['target_bundles'] ?? [];

      $allowed = empty($target_bundles) ? $paragraph_bundles : array_keys($target_bundles);

      $edit_path = '';
      try {
        $edit_path = $config->toUrl('edit-form')->toString();
      }
      catch (\Throwable $e) {}

      $paragraph_paragraph_fields[$bundle][$field_name] = [
        'field_name' => $field_name,
        'label' => $config->label(),
        'cardinality' => $cardinality,
        'allowed_paragraph_types' => $allowed,
        'edit_path' => $edit_path,
      ];
    }

    ksort($content_paragraph_fields);
    ksort($paragraph_paragraph_fields);

    return [
      'content_paragraph_fields' => $content_paragraph_fields,
      'paragraph_paragraph_fields' => $paragraph_paragraph_fields,
    ];
  }

}
