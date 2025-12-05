<?php

namespace Drupal\site_audit\Report;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Traversable;

/**
 * Manages AuditReport plugins.
 */
class AuditReportManager extends DefaultPluginManager {

  /**
   * Constructs a new AuditReportManager.
   *
   * @param string $subdir
   *   The subdirectory where plugin implementations are found.
   * @param \Traversable $namespaces
   *   Namespaces to search for plugins.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   Module handler.
   */
  public function __construct(
    string $subdir,
    Traversable $namespaces,
    CacheBackendInterface $cache_backend,
    ModuleHandlerInterface $module_handler,
  ) {
    parent::__construct(
      $subdir,
      $namespaces,
      $module_handler,
      'Drupal\site_audit\Plugin\AuditReport\AuditReportInterface',
      'Drupal\site_audit\Annotation\AuditReport'
    );

    $this->alterInfo('site_audit_audit_report_info');
    $this->setCacheBackend($cache_backend, 'site_audit_audit_report_plugins', ['site_audit_audit_report_plugins']);
  }

  /**
   * Gets definitions for enabled audit reports only.
   *
   * @return array
   *   An array of plugin definitions, filtered to only include enabled reports.
   */
  public function getEnabledDefinitions(): array {
    $definitions = $this->getDefinitions();
    return array_filter($definitions, function ($definition) {
      return !isset($definition['enabled']) || $definition['enabled'] !== FALSE;
    });
  }

}
