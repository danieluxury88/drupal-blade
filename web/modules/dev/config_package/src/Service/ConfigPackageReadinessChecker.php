<?php

namespace Drupal\config_package\Service;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FormatterPluginManager;
use Drupal\Core\Field\WidgetPluginManager;
use Drupal\Core\File\FileSystemInterface;

final class ConfigPackageReadinessChecker {

  public function __construct(
    private readonly FileSystemInterface $fileSystem,
    private readonly StorageInterface $activeStorage,
    private readonly ModuleExtensionList $moduleList,
    private readonly ModuleHandlerInterface $moduleHandler,
    private readonly WidgetPluginManager $widgetManager,
    private readonly FormatterPluginManager $formatterManager,
    private readonly string $appRoot,
  ) {}

  /**
   * Scan project root config/partial/<bundle>/meta/manifest.yml and return bundle => info.
   *
   * @return array<string, array{bundle:string, path:string, manifest_path:string, config_path:string, meta_path:string}>
   */
  public function listLocalPackages(): array {
    $projectRoot = dirname($this->appRoot);
    $base = $projectRoot . '/config/partial';
    if (!is_dir($base)) {
      return [];
    }

    $out = [];
    foreach (glob($base . '/*/meta/manifest.yml') ?: [] as $manifest_path) {
      $bundle = basename(dirname(dirname($manifest_path))); // .../<bundle>/meta/manifest.yml
      $base_path = dirname(dirname($manifest_path)); // .../<bundle>
      $out[$bundle] = [
        'bundle' => $bundle,
        'path' => $base_path,
        'manifest_path' => $manifest_path,
        'config_path' => $base_path . '/config',
        'meta_path' => $base_path . '/meta',
      ];
    }
    ksort($out);
    return $out;
  }

  /**
   * Run readiness checks on the current site for the selected package.
   *
   * @return array{
   *   bundle: string,
   *   package_path: string,
   *   required_modules: array<string, string>, // module => status(enabled|available|missing)
   *   missing_modules: string[],
   *   disabled_modules: string[],
   *   enabled_modules: string[],
   *   missing_plugins: array<int, array{type:string, plugin_id:string, used_in:string, provider:?string}>,
   *   config_status: array{new:int, identical:int, different:int},
   *   config_diffs: array<string, string>, // config_name => status(new|identical|different)
   *   suggested_commands: array{composer:string, enable:string, import:string},
   * }
   */
  public function checkPackage(string $package_path): array {
    $package_path = rtrim($package_path, '/');

    // Option A: manifest lives in meta/, config YAMLs in config/.
    $manifest_path = $package_path . '/meta/manifest.yml';
    $config_dir = $package_path . '/config';

    if (!is_file($manifest_path)) {
      throw new \RuntimeException("manifest.yml not found at: $manifest_path");
    }
    if (!is_dir($config_dir)) {
      throw new \RuntimeException("config directory not found at: $config_dir");
    }

    $manifest = Yaml::decode(file_get_contents($manifest_path));
    if (!is_array($manifest)) {
      throw new \RuntimeException("manifest.yml could not be parsed: $manifest_path");
    }

    $bundle = (string) ($manifest['bundle'] ?? basename($package_path));
    $config_names = array_values(array_unique((array) ($manifest['config'] ?? [])));
    $required_module_list = array_values(array_unique((array) ($manifest['modules'] ?? [])));

    // 1) Module readiness.
    $required_modules = [];
    $enabled = [];
    $disabled = [];
    $missing = [];

    foreach ($required_module_list as $m) {
      $m = (string) $m;
      if ($m === '') {
        continue;
      }

      if ($this->moduleHandler->moduleExists($m)) {
        $required_modules[$m] = 'enabled';
        $enabled[] = $m;
      }
      elseif ($this->moduleList->exists($m)) {
        $required_modules[$m] = 'available';
        $disabled[] = $m;
      }
      else {
        $required_modules[$m] = 'missing';
        $missing[] = $m;
      }
    }

    sort($enabled);
    sort($disabled);
    sort($missing);

    // 2) Missing plugin checks (widgets/formatters) by reading YAML in package/config.
    $missing_plugins = $this->checkDisplayPlugins($config_dir, $config_names);

    // 3) Config collisions (compare package/config vs active).
    [$config_status, $config_diffs] = $this->compareConfigToActive($config_dir, $config_names);

    // 4) Suggested commands.
    $composer = $missing
      ? 'composer require ' . implode(' ', array_map(static fn($m) => "drupal/$m", $missing))
      : '# No composer requires detected (all required modules are in codebase).';

    $enable = $disabled
      ? 'drush en -y ' . implode(' ', $disabled)
      : '# No modules to enable (all required modules already enabled).';

    $import = 'drush cim -y --partial --source=../config/partial/' . $bundle . '/config';

    return [
      'bundle' => $bundle,
      'package_path' => $package_path,
      'required_modules' => $required_modules,
      'missing_modules' => $missing,
      'disabled_modules' => $disabled,
      'enabled_modules' => $enabled,
      'missing_plugins' => $missing_plugins,
      'config_status' => $config_status,
      'config_diffs' => $config_diffs,
      'suggested_commands' => [
        'composer' => $composer,
        'enable' => $enable,
        'import' => $import,
      ],
    ];
  }

  /**
   * Parse display configs in config_dir and validate widget/formatter plugin IDs exist.
   *
   * @param string $config_dir Absolute path to package/config.
   * @param array<string> $config_names
   * @return array<int, array{type:string, plugin_id:string, used_in:string, provider:?string}>
   */
  private function checkDisplayPlugins(string $config_dir, array $config_names): array {
    $missing = [];

    foreach ($config_names as $name) {
      $name = (string) $name;
      if (
        !str_starts_with($name, 'core.entity_form_display.')
        && !str_starts_with($name, 'core.entity_view_display.')
      ) {
        continue;
      }

      $file = rtrim($config_dir, '/') . '/' . $name . '.yml';
      if (!is_file($file)) {
        continue;
      }

      $raw = Yaml::decode(file_get_contents($file));
      if (!is_array($raw)) {
        continue;
      }

      $components = $raw['content'] ?? [];
      if (!is_array($components)) {
        continue;
      }

      if (str_starts_with($name, 'core.entity_form_display.')) {
        foreach ($components as $component) {
          if (!is_array($component)) {
            continue;
          }
          $plugin_id = $component['type'] ?? NULL;
          if (!$plugin_id) {
            continue;
          }

          $def = $this->widgetManager->getDefinition($plugin_id, FALSE);
          if (!$def) {
            $missing[] = [
              'type' => 'widget',
              'plugin_id' => (string) $plugin_id,
              'used_in' => $name,
              'provider' => NULL,
            ];
          }
        }
      }
      else {
        foreach ($components as $component) {
          if (!is_array($component)) {
            continue;
          }
          $plugin_id = $component['type'] ?? NULL;
          if (!$plugin_id) {
            continue;
          }

          $def = $this->formatterManager->getDefinition($plugin_id, FALSE);
          if (!$def) {
            $missing[] = [
              'type' => 'formatter',
              'plugin_id' => (string) $plugin_id,
              'used_in' => $name,
              'provider' => NULL,
            ];
          }
        }
      }
    }

    return $missing;
  }

  /**
   * Compare package config YAML data vs active config.
   *
   * @param string $config_dir Absolute path to package/config.
   * @param array<string> $config_names
   * @return array{0: array{new:int, identical:int, different:int}, 1: array<string, string>}
   */
  private function compareConfigToActive(string $config_dir, array $config_names): array {
    $status = ['new' => 0, 'identical' => 0, 'different' => 0];
    $diffs = [];

    foreach ($config_names as $name) {
      $name = (string) $name;
      $file = rtrim($config_dir, '/') . '/' . $name . '.yml';
      if (!is_file($file)) {
        continue;
      }

      $pkg = Yaml::decode(file_get_contents($file));
      if (!is_array($pkg)) {
        continue;
      }

      if (!$this->activeStorage->exists($name)) {
        $status['new']++;
        $diffs[$name] = 'new';
        continue;
      }

      $active = $this->activeStorage->read($name);
      if (!is_array($active)) {
        $status['different']++;
        $diffs[$name] = 'different';
        continue;
      }

      if ($active == $pkg) {
        $status['identical']++;
        $diffs[$name] = 'identical';
      }
      else {
        $status['different']++;
        $diffs[$name] = 'different';
      }
    }

    return [$status, $diffs];
  }

}
