<?php

declare(strict_types=1);

namespace Drupal\site_audit\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Database\Database;

/**
 * Collects high-level project information and metadata.
 */
class SiteAuditProjectCollector {

  /**
   * Module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * Module extension list.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected ModuleExtensionList $moduleExtensionList;

  /**
   * Language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * Config storage.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected StorageInterface $configStorage;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs the SiteAuditProjectCollector service.
   */
  public function __construct(
    ModuleHandlerInterface $module_handler,
    ModuleExtensionList $module_extension_list,
    LanguageManagerInterface $language_manager,
    ConfigFactoryInterface $config_factory,
    StorageInterface $config_storage,
    EntityTypeManagerInterface $entity_type_manager,
  ) {
    $this->moduleHandler = $module_handler;
    $this->moduleExtensionList = $module_extension_list;
    $this->languageManager = $language_manager;
    $this->configFactory = $config_factory;
    $this->configStorage = $config_storage;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Get full project overview data structure.
   *
   * [
   *   'drupal' => [...],
   *   'modules' => [...],
   *   'languages' => [...],
   *   'config_collections' => [...],
   *   'content_model' => [...],
   * ]
   */
  public function getProjectOverview(): array {
    return [
      'drupal' => $this->getCoreInfo(),
      'modules' => $this->getModuleStats(),
      'languages' => $this->getLanguagesInfo(),
      'config_collections' => $this->getConfigCollectionsSummary(),
      'content_model' => $this->getContentModelSummary(),
    ];
  }

  /**
   * Core / PHP / site / DB info.
   */
  public function getCoreInfo(): array {
    $system_site = $this->configFactory->get('system.site');
    $system_theme = $this->configFactory->get('system.theme');

    $site_name = (string) $system_site->get('name');
    $site_mail = (string) $system_site->get('mail');
    $default_theme = (string) $system_theme->get('default');
    $admin_theme = (string) ($system_theme->get('admin') ?: '');

    // Database info (non-sensitive).
    $connection_info = Database::getConnectionInfo();
    $default_db = $connection_info['default'] ?? reset($connection_info) ?? [];
    $database_driver = (string) ($default_db['driver'] ?? '');
    $database_name = (string) ($default_db['database'] ?? '');

    return [
      'core_version' => \Drupal::VERSION,
      'php_version' => PHP_VERSION,
      'site_name' => $site_name,
      'site_mail' => $site_mail,
      'default_theme' => $default_theme,
      'admin_theme' => $admin_theme,
      'database' => [
        'driver' => $database_driver,
        'database' => $database_name,
      ],
    ];
  }

  /**
   * Enabled/disabled modules and basic classification.
   *
   * Returns:
   * [
   *   'enabled' => int,
   *   'disabled' => int,
   *   'custom_enabled' => int,
   *   'contrib_enabled' => int,
   *   'enabled_module_names' => string[],
   *   'modules' => [
   *     'node' => [
   *       'name' => 'node',
   *       'status' => TRUE,
   *       'type' => 'core'|'contrib'|'custom',
   *       'relative_path' => 'core/modules/node',
   *       'package' => 'Core',
   *       'version' => '10.2.3',
   *     ],
   *     ...
   *   ],
   * ]
   */
  public function getModuleStats(): array {
    $enabled_modules = array_keys($this->moduleHandler->getModuleList());
    $all_modules_info = $this->moduleExtensionList->getList();

    $enabled_count = 0;
    $disabled_count = 0;
    $custom_enabled = 0;
    $contrib_enabled = 0;

    $modules = [];

    foreach ($all_modules_info as $name => $info) {
      $status = (int) ($info->status ?? 0);
      $is_enabled = $status === 1;

      $relative = $info->getPath();
      $package = (string) ($info->info['package'] ?? '');
      $version = (string) ($info->info['version'] ?? '');

      // Classify type.
      $type = 'contrib';
      if (str_starts_with($relative, 'core/modules')) {
        $type = 'core';
      }
      elseif (str_contains($relative, 'modules/custom')) {
        $type = 'custom';
      }

      if ($is_enabled) {
        $enabled_count++;
        if ($type === 'custom') {
          $custom_enabled++;
        }
        else {
          $contrib_enabled++;
        }
      }
      else {
        $disabled_count++;
      }

      $modules[$name] = [
        'name' => $name,
        'status' => $is_enabled,
        'type' => $type,
        'relative_path' => $relative,
        'package' => $package,
        'version' => $version,
      ];
    }

    // Sort modules by name for stable output / diffs.
    ksort($modules);

    return [
      'enabled' => $enabled_count,
      'disabled' => $disabled_count,
      'custom_enabled' => $custom_enabled,
      'contrib_enabled' => $contrib_enabled,
      'enabled_module_names' => $enabled_modules,
      'modules' => $modules,
    ];
  }

  /**
   * Languages + default + direction.
   */
  public function getLanguagesInfo(): array {
    $languages = [];
    $default_lang = $this->languageManager->getDefaultLanguage()->getId();

    foreach ($this->languageManager->getLanguages() as $langcode => $language) {
      $languages[$langcode] = [
        'id' => $langcode,
        'name' => $language->getName(),
        'default' => ($langcode === $default_lang),
        'direction' => $language->getDirection() === 0 ? 'ltr' : 'rtl',
      ];
    }

    return $languages;
  }

  /**
   * Config collections and number of items in each.
   */
  public function getConfigCollectionsSummary(): array {
    $config_collections = [];

    // Base collection.
    $base_items = $this->configStorage->listAll();
    $config_collections['base'] = [
      'name' => 'base',
      'item_count' => count($base_items),
    ];

    // Other collections, e.g. language.en, language.de, etc.
    $collections = $this->configStorage->getAllCollectionNames();
    foreach ($collections as $collection) {
      $collection_storage = $this->configStorage->createCollection($collection);
      $items = $collection_storage->listAll();
      $config_collections[$collection] = [
        'name' => $collection,
        'item_count' => count($items),
      ];
    }

    return $config_collections;
  }

  /**
   * Content model summary: content types, vocabularies, media types, views.
   */
  public function getContentModelSummary(): array {
    $content_types_count = count($this->entityTypeManager->getStorage('node_type')->loadMultiple());

    $taxonomy_count = 0;
    if ($this->entityTypeManager->hasDefinition('taxonomy_vocabulary')) {
      $taxonomy_count = count($this->entityTypeManager->getStorage('taxonomy_vocabulary')->loadMultiple());
    }

    $media_types_count = 0;
    if ($this->entityTypeManager->hasDefinition('media_type')) {
      $media_types_count = count($this->entityTypeManager->getStorage('media_type')->loadMultiple());
    }

    // Views & displays (if Views exists).
    $views_count = 0;
    $views_displays_count = 0;

    if ($this->entityTypeManager->hasDefinition('view')) {
      $views = $this->entityTypeManager->getStorage('view')->loadMultiple();
      $views_count = count($views);
      foreach ($views as $view) {
        $display_config = $view->get('display') ?? [];
        if (is_array($display_config)) {
          $views_displays_count += count($display_config);
        }
      }
    }

    return [
      'content_types' => $content_types_count,
      'taxonomy_vocabularies' => $taxonomy_count,
      'media_types' => $media_types_count,
      'views' => $views_count,
      'view_displays' => $views_displays_count,
    ];
  }

}
