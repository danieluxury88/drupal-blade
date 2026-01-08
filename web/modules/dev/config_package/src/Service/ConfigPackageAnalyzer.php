<?php

namespace Drupal\config_package\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Field\FormatterPluginManager;
use Drupal\Core\Field\WidgetPluginManager;
use Drupal\field\FieldConfigInterface;

/**
 * Builds a dependency manifest for partial config import/export.
 */
final class ConfigPackageAnalyzer {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ConfigManagerInterface $configManager,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly ModuleExtensionList $moduleList,
    private readonly WidgetPluginManager $widgetManager,
    private readonly FormatterPluginManager $formatterManager,
  ) {}

  /**
   * Analyze a node bundle and return config + module requirements.
   *
   * @return array{
   *   bundle: string,
   *   config: array<string, array{group:string, reason:string}>,
   *   modules: array<string, array{reason:string}>,
   *   paragraphs: string[],
   * }
   */
  public function analyzeNodeBundle(string $bundle, bool $include_paragraphs = TRUE): array {
    $items = [];
    $modules = [];
    $paragraph_bundles = [];

    // 1) Seed configs.
    $this->addConfig($items, "node.type.$bundle", 'Node type', 'Selected content type.');

    // Node field instances (field.field.*).
    foreach ($this->listConfigNamesByPrefix("field.field.node.$bundle.") as $name) {
      $this->addConfig($items, $name, 'Node fields', 'Field instance on the content type.');
    }

    // Node displays.
    foreach ($this->listConfigNamesByPrefix("core.entity_form_display.node.$bundle.") as $name) {
      $this->addConfig($items, $name, 'Node displays', 'Form display for the content type.');
    }
    foreach ($this->listConfigNamesByPrefix("core.entity_view_display.node.$bundle.") as $name) {
      $this->addConfig($items, $name, 'Node displays', 'View display for the content type.');
    }

    // 2) Expand by config dependencies (recursive).
    $this->expandByConfigDependencies($items);

    // 3) From field instances, include field storages.
    $this->addFieldStoragesFromFieldInstances($items);

    // 4) Detect widget/formatter module providers from displays.
    $this->collectDisplayPluginProviders($items, $modules);

    // 5) Paragraph special handling (robust + recursive).
    if ($include_paragraphs) {
      $todo = $this->collectParagraphBundlesFromNodeFields($bundle);
      $seen = [];

      while (!empty($todo)) {
        $p_bundle = array_shift($todo);
        if (isset($seen[$p_bundle])) {
          continue;
        }
        $seen[$p_bundle] = TRUE;

        // Add paragraph type + its fields + its displays.
        $this->addParagraphBundleConfig($items, $p_bundle);

        // Recurse: discover nested paragraph bundles referenced inside this paragraph bundle.
        foreach ($this->collectParagraphBundlesFromParagraphBundle($p_bundle) as $child) {
          if (!isset($seen[$child])) {
            $todo[] = $child;
          }
        }
      }

      $paragraph_bundles = array_keys($seen);
      sort($paragraph_bundles);

      // Expand again after adding paragraph configs.
      $this->expandByConfigDependencies($items);
      $this->addFieldStoragesFromFieldInstances($items);
      $this->collectDisplayPluginProviders($items, $modules);
    }

    // Sort output.
    ksort($items);
    ksort($modules);

    return [
      'bundle' => $bundle,
      'config' => $items,
      'modules' => $modules,
      'paragraphs' => $paragraph_bundles,
    ];
  }

  // ---------------------------------------------------------------------------
  // Core helpers.
  // ---------------------------------------------------------------------------

  private function addConfig(array &$items, string $name, string $group, string $reason): void {
    if (!isset($items[$name])) {
      $items[$name] = ['group' => $group, 'reason' => $reason];
    }
  }

  private function listConfigNamesByPrefix(string $prefix): array {
    return $this->configFactory->listAll($prefix);
  }

  private function expandByConfigDependencies(array &$items): void {
    $queue = array_keys($items);
    $seen = array_fill_keys($queue, TRUE);

    while ($queue) {
      $name = array_shift($queue);
      $data = $this->configFactory->get($name)->getRawData();
      if (!is_array($data)) {
        continue;
      }

      $deps = $data['dependencies'] ?? [];
      $config_deps = $deps['config'] ?? [];
      if (!is_array($config_deps)) {
        continue;
      }

      foreach ($config_deps as $dep_name) {
        if (!isset($seen[$dep_name])) {
          $seen[$dep_name] = TRUE;
          $this->addConfig($items, $dep_name, 'Dependency', "Referenced by $name.");
          $queue[] = $dep_name;
        }
      }
    }
  }

  private function addFieldStoragesFromFieldInstances(array &$items): void {
    foreach (array_keys($items) as $name) {
      if (!str_starts_with($name, 'field.field.')) {
        continue;
      }
      $raw = $this->configFactory->get($name)->getRawData();
      if (!is_array($raw)) {
        continue;
      }

      $entity_type = $raw['entity_type'] ?? NULL;
      $field_name = $raw['field_name'] ?? NULL;
      if ($entity_type && $field_name) {
        $storage_name = "field.storage.$entity_type.$field_name";
        $this->addConfig($items, $storage_name, 'Field storages', "Storage required for field instance $name.");
      }
    }
  }

  private function collectDisplayPluginProviders(array $items, array &$modules): void {
    foreach ($items as $name => $meta) {
      if (!str_starts_with($name, 'core.entity_form_display.') && !str_starts_with($name, 'core.entity_view_display.')) {
        continue;
      }
      $raw = $this->configFactory->get($name)->getRawData();
      if (!is_array($raw)) {
        continue;
      }

      if (str_starts_with($name, 'core.entity_form_display.')) {
        $components = $raw['content'] ?? [];
        foreach ($components as $field_name => $component) {
          $plugin_id = $component['type'] ?? NULL; // Widget plugin id.
          if (!$plugin_id) {
            continue;
          }
          $def = $this->widgetManager->getDefinition($plugin_id, FALSE);
          $provider = $def['provider'] ?? NULL;
          if ($provider) {
            $modules[$provider] ??= ['reason' => "Used by widget '$plugin_id' in $name."];
          }
        }
      }
      else {
        $components = $raw['content'] ?? [];
        foreach ($components as $field_name => $component) {
          $plugin_id = $component['type'] ?? NULL; // Formatter plugin id.
          if (!$plugin_id) {
            continue;
          }
          $def = $this->formatterManager->getDefinition($plugin_id, FALSE);
          $provider = $def['provider'] ?? NULL;
          if ($provider) {
            $modules[$provider] ??= ['reason' => "Used by formatter '$plugin_id' in $name."];
          }
        }
      }
    }

    // Also include modules explicitly required by config dependencies.
    foreach (array_keys($items) as $name) {
      $raw = $this->configFactory->get($name)->getRawData();
      if (!is_array($raw)) {
        continue;
      }
      $deps = $raw['dependencies']['module'] ?? [];
      if (is_array($deps)) {
        foreach ($deps as $m) {
          $modules[$m] ??= ['reason' => "Declared module dependency in $name."];
        }
      }
    }
  }

  // ---------------------------------------------------------------------------
  // Paragraph discovery: robust + recursive.
  // ---------------------------------------------------------------------------

  /**
   * Collect paragraph bundles allowed by paragraph reference fields on node bundle.
   *
   * IMPORTANT: For entity_reference_revisions, target_type is on FIELD STORAGE.
   */
  private function collectParagraphBundlesFromNodeFields(string $bundle): array {
    $bundles = [];

    /** @var \Drupal\field\FieldConfigInterface[] $field_configs */
    $field_configs = $this->entityTypeManager->getStorage('field_config')->loadByProperties([
      'entity_type' => 'node',
      'bundle' => $bundle,
    ]);

    foreach ($field_configs as $fc) {
      if (!$fc instanceof FieldConfigInterface) {
        continue;
      }

      $storage = $fc->getFieldStorageDefinition();
      $field_type = $storage->getType();

      if (!in_array($field_type, ['entity_reference_revisions', 'entity_reference'], TRUE)) {
        continue;
      }

      if (($storage->getSetting('target_type') ?? NULL) !== 'paragraph') {
        continue;
      }

      $handler_settings = $fc->getSetting('handler_settings') ?? [];
      $restricted = $this->extractTargetBundlesFromHandlerSettings($handler_settings);

      if (!empty($restricted)) {
        foreach ($restricted as $p_bundle) {
          $bundles[$p_bundle] = TRUE;
        }
      }
      else {
        // Unrestricted: allow all paragraph types.
        foreach ($this->listAllParagraphTypeIds() as $p_bundle) {
          $bundles[$p_bundle] = TRUE;
        }
      }
    }

    return array_keys($bundles);
  }

  /**
   * Collect paragraph bundles referenced within a paragraph bundle (nested).
   */
  private function collectParagraphBundlesFromParagraphBundle(string $p_bundle): array {
    $bundles = [];

    /** @var \Drupal\field\FieldConfigInterface[] $field_configs */
    $field_configs = $this->entityTypeManager->getStorage('field_config')->loadByProperties([
      'entity_type' => 'paragraph',
      'bundle' => $p_bundle,
    ]);

    foreach ($field_configs as $fc) {
      if (!$fc instanceof FieldConfigInterface) {
        continue;
      }

      $storage = $fc->getFieldStorageDefinition();
      $field_type = $storage->getType();

      if (!in_array($field_type, ['entity_reference_revisions', 'entity_reference'], TRUE)) {
        continue;
      }

      if (($storage->getSetting('target_type') ?? NULL) !== 'paragraph') {
        continue;
      }

      $handler_settings = $fc->getSetting('handler_settings') ?? [];
      $restricted = $this->extractTargetBundlesFromHandlerSettings($handler_settings);

      if (!empty($restricted)) {
        foreach ($restricted as $child) {
          $bundles[$child] = TRUE;
        }
      }
      else {
        // Unrestricted: allow all paragraph types.
        foreach ($this->listAllParagraphTypeIds() as $child) {
          $bundles[$child] = TRUE;
        }
      }
    }

    return array_keys($bundles);
  }

  /**
   * Extract allowed bundles from handler_settings (supports drag_drop variant).
   */
  private function extractTargetBundlesFromHandlerSettings(array $handler_settings): array {
    $tb = $handler_settings['target_bundles'] ?? [];
    if (is_array($tb) && $tb) {
      return array_keys($tb);
    }

    $dd = $handler_settings['target_bundles_drag_drop'] ?? [];
    if (is_array($dd) && $dd) {
      $out = [];
      foreach ($dd as $bundle => $info) {
        if (is_array($info)) {
          if (!empty($info['enabled'])) {
            $out[] = $bundle;
          }
        }
        else {
          $out[] = $bundle;
        }
      }
      return $out;
    }

    return [];
  }

  private function listAllParagraphTypeIds(): array {
    $types = $this->entityTypeManager->getStorage('paragraphs_type')->loadMultiple();
    return array_keys($types);
  }

  // ---------------------------------------------------------------------------
  // Paragraph config inclusion.
  // ---------------------------------------------------------------------------

  private function addConfigEntity(array &$items, ConfigEntityInterface $entity, string $group, string $reason): void {
    $this->addConfig($items, $entity->getConfigDependencyName(), $group, $reason);
  }

  /**
   * Adds all paragraph bundle-related config entities:
   * - paragraph type
   * - fields on the paragraph bundle
   * - form displays for the paragraph bundle
   * - view displays for the paragraph bundle
   */
  private function addParagraphBundleConfig(array &$items, string $p_bundle): void {
    // Paragraph type config.
    $p_type = $this->entityTypeManager->getStorage('paragraphs_type')->load($p_bundle);
    if ($p_type instanceof ConfigEntityInterface) {
      $this->addConfigEntity($items, $p_type, 'Paragraph types', 'Allowed paragraph bundle.');
    }

    // Field instances on this paragraph bundle.
    $field_configs = $this->entityTypeManager->getStorage('field_config')->loadByProperties([
      'entity_type' => 'paragraph',
      'bundle' => $p_bundle,
    ]);
    foreach ($field_configs as $fc) {
      if ($fc instanceof ConfigEntityInterface) {
        $this->addConfigEntity($items, $fc, 'Paragraph fields', 'Field instance on paragraph bundle.');
      }
    }

    // Form displays.
    $form_displays = $this->entityTypeManager->getStorage('entity_form_display')->loadByProperties([
      'targetEntityType' => 'paragraph',
      'bundle' => $p_bundle,
    ]);
    foreach ($form_displays as $fd) {
      if ($fd instanceof ConfigEntityInterface) {
        $this->addConfigEntity($items, $fd, 'Paragraph displays', 'Form display for paragraph bundle.');
      }
    }

    // View displays.
    $view_displays = $this->entityTypeManager->getStorage('entity_view_display')->loadByProperties([
      'targetEntityType' => 'paragraph',
      'bundle' => $p_bundle,
    ]);
    foreach ($view_displays as $vd) {
      if ($vd instanceof ConfigEntityInterface) {
        $this->addConfigEntity($items, $vd, 'Paragraph displays', 'View display for paragraph bundle.');
      }
    }
  }

}
