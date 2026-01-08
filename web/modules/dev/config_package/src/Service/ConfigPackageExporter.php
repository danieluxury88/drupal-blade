<?php

namespace Drupal\config_package\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\File\FileSystemInterface;

/**
 * Exports a curated set of config YAMLs into a partial config folder.
 *
 * Option A structure:
 *   config/partial/<bundle>/config/*.yml   (importable config only)
 *   config/partial/<bundle>/meta/manifest.yml (non-import metadata)
 */
final class ConfigPackageExporter {

  public function __construct(
    private readonly StorageInterface $syncStorage,
    private readonly FileSystemInterface $fileSystem,
    private readonly TimeInterface $time,
    private readonly string $appRoot,
  ) {}

  /**
   * Export config YAMLs into config/partial/<bundle>/config and manifest to meta/.
   *
   * @param string $bundle
   * @param array<string> $config_names
   * @param array<string> $modules
   *
   * @return array{
   *   base_path:string,
   *   config_path:string,
   *   meta_path:string,
   *   exported:int,
   *   missing:string[],
   *   manifest:string
   * }
   */
  public function export(string $bundle, array $config_names, array $modules): array {
    $bundle = $this->sanitize($bundle);

    // We write under project root. appRoot is typically /path/to/project/web,
    // so go one directory up to project root.
    $projectRoot = dirname($this->appRoot);

    $baseDir = $projectRoot . "/config/partial/$bundle";
    $configDir = $baseDir . "/config";
    $metaDir = $baseDir . "/meta";

    $this->ensureDir($configDir);
    $this->ensureDir($metaDir);

    $missing = [];
    $exported = 0;

    $config_names = array_values(array_unique($config_names));
    sort($config_names);

    // Export YAML files (from config sync storage).
    foreach ($config_names as $name) {
      if (!$this->syncStorage->exists($name)) {
        $missing[] = $name;
        continue;
      }

      $data = $this->syncStorage->read($name);
      if (!is_array($data)) {
        $missing[] = $name;
        continue;
      }

      $yaml = Yaml::encode($data);
      $filePath = $configDir . '/' . $name . '.yml';
      $this->fileSystem->saveData($yaml, $filePath, FileSystemInterface::EXISTS_REPLACE);
      $exported++;
    }

    // Write manifest.yml into meta/ so it won't break partial imports.
    $modules = array_values(array_unique($modules));
    sort($modules);

    $manifest = [
      'generated_at' => date('c', $this->time->getCurrentTime()),
      'bundle' => $bundle,
      'modules' => $modules,
      'config' => $config_names,
      'missing_in_sync' => $missing,
      'paths' => [
        'base' => $baseDir,
        'config' => $configDir,
        'meta' => $metaDir,
      ],
      'notes' => [
        'Import on target with: drush cim -y --partial --source=../config/partial/' . $bundle . '/config',
        'Tip: run drush cex -y on source before exporting to keep sync dir aligned.',
      ],
    ];

    $manifestYaml = Yaml::encode($manifest);
    $manifestPath = $metaDir . '/manifest.yml';
    $this->fileSystem->saveData($manifestYaml, $manifestPath, FileSystemInterface::EXISTS_REPLACE);

    return [
      'base_path' => $baseDir,
      'config_path' => $configDir,
      'meta_path' => $metaDir,
      'exported' => $exported,
      'missing' => $missing,
      'manifest' => $manifestPath,
    ];
  }

  private function ensureDir(string $dir): void {
    if (!is_dir($dir)) {
      $this->fileSystem->mkdir($dir, NULL, TRUE);
    }
  }

  private function sanitize(string $value): string {
    // Safe folder name.
    return preg_replace('/[^a-z0-9_\-]/i', '_', $value) ?? $value;
  }

}
