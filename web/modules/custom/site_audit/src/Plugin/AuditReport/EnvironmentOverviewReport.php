<?php

namespace Drupal\site_audit\Plugin\AuditReport;

use Drupal\Core\Database\Connection;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an environment overview report.
 *
 * @AuditReport(
 *   id = "environment_overview",
 *   label = @Translation("Environment overview"),
 *   description = @Translation("Summaries of core, PHP, database, languages, and key contrib extensions.")
 * )
 */
class EnvironmentOverviewReport extends AuditReportBase implements ContainerFactoryPluginInterface {

  /**
   * Key contrib extensions to highlight.
   */
  private const KEY_EXTENSIONS = [
    'gin' => ['label' => 'Gin', 'type' => 'theme'],
    'admin_toolbar' => ['label' => 'Admin Toolbar', 'type' => 'module'],
    'pathauto' => ['label' => 'Pathauto', 'type' => 'module'],
    'token' => ['label' => 'Token', 'type' => 'module'],
    'views_data_export' => ['label' => 'Views Data Export', 'type' => 'module'],
  ];

  /**
   * Constructs a new EnvironmentOverviewReport instance.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected ModuleHandlerInterface $moduleHandler,
    protected ModuleExtensionList $moduleExtensionList,
    protected LanguageManagerInterface $languageManager,
    protected Connection $connection,
    protected ThemeHandlerInterface $themeHandler,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('module_handler'),
      $container->get('extension.list.module'),
      $container->get('language_manager'),
      $container->get('database'),
      $container->get('theme_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildData(): array {
    $default_language = $this->languageManager->getDefaultLanguage();
    $languages = $this->languageManager->getLanguages();
    $language_data = [];
    foreach ($languages as $langcode => $language) {
      $language_data[$langcode] = $this->formatLanguageInfo($language);
    }
    $default_langcode = $default_language->getId();
    $additional_languages = $language_data;
    unset($additional_languages[$default_langcode]);

    $all_modules = $this->moduleExtensionList->getList();
    $enabled_modules = $this->moduleHandler->getModuleList();
    $module_counts = [
      'total' => count($all_modules),
      'enabled' => count($enabled_modules),
    ];
    $module_counts['disabled'] = max(0, $module_counts['total'] - $module_counts['enabled']);

    $db_version = NULL;
    try {
      $db_version = $this->connection->getServerVersion();
    }
    catch (\Throwable $e) {
      // Leave as NULL if version cannot be detected.
    }

    return [
      'drupal' => [
        'version' => \Drupal::VERSION,
      ],
      'php' => [
        'version' => PHP_VERSION,
      ],
      'database' => [
        'driver' => $this->connection->driver(),
        'version' => $db_version,
      ],
      'languages' => [
        'default' => $this->formatLanguageInfo($default_language),
        'additional' => array_values($additional_languages),
        'all' => $language_data,
      ],
      'modules' => [
        'counts' => $module_counts,
        'key_extensions' => $this->getKeyExtensions($all_modules),
      ],
    ];
  }

  /**
   * Build keyed extension information (modules/themes).
   */
  protected function getKeyExtensions(array $all_modules): array {
    $themes = $this->themeHandler->listInfo();
    $key_extensions = [];

    foreach (self::KEY_EXTENSIONS as $machine_name => $info) {
      $type = $info['type'];
      $present = FALSE;
      $enabled = FALSE;
      $version = NULL;

      if ($type === 'theme') {
        $present = isset($themes[$machine_name]);
        $enabled = $present ? (bool) ($themes[$machine_name]->status ?? FALSE) : FALSE;
        if ($present) {
          $version = $themes[$machine_name]->info['version'] ?? NULL;
        }
      }
      else {
        $present = isset($all_modules[$machine_name]);
        $enabled = $this->moduleHandler->moduleExists($machine_name);
        if ($present) {
          $version = $all_modules[$machine_name]->info['version'] ?? NULL;
        }
      }

      $key_extensions[$machine_name] = [
        'label' => $info['label'],
        'type' => $type,
        'present' => $present,
        'enabled' => $enabled,
        'version' => $version,
      ];
    }

    return $key_extensions;
  }

  /**
   * Normalize language information.
   */
  protected function formatLanguageInfo(LanguageInterface $language): array {
    return [
      'id' => $language->getId(),
      'name' => $language->getName(),
      'direction' => $language->getDirection() === LanguageInterface::DIRECTION_RTL ? 'rtl' : 'ltr',
      'weight' => $language->getWeight(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildRender(array $data): array {
    $build = [
      '#type' => 'container',
    ];

    $build['runtime'] = [
      '#type' => 'details',
      '#title' => $this->t('Runtime'),
      '#open' => TRUE,
      'table' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Item'),
          $this->t('Value'),
        ],
        '#rows' => [
          [$this->t('Drupal core'), $data['drupal']['version'] ?? ''],
          [$this->t('PHP'), $data['php']['version'] ?? ''],
          [$this->t('Database driver'), $data['database']['driver'] ?? ''],
          [
            $this->t('Database version'),
            $data['database']['version'] ?? $this->t('Unknown'),
          ],
        ],
      ],
    ];

    $language_rows = [];
    $default_langcode = $data['languages']['default']['id'] ?? '';

    foreach ($data['languages']['all'] as $langcode => $language) {
      $language_rows[] = [
        $langcode,
        $language['name'] ?? '',
        $language['direction'] ?? '',
        $langcode === $default_langcode ? $this->t('Yes') : $this->t('No'),
      ];
    }

    $build['languages'] = [
      '#type' => 'details',
      '#title' => $this->t('Languages'),
      '#open' => TRUE,
      'table' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Code'),
          $this->t('Label'),
          $this->t('Direction'),
          $this->t('Default'),
        ],
        '#rows' => $language_rows,
      ],
    ];

    $build['modules'] = [
      '#type' => 'details',
      '#title' => $this->t('Modules'),
      '#open' => TRUE,
    ];

    $build['modules']['counts'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Metric'),
        $this->t('Value'),
      ],
      '#rows' => [
        [$this->t('Enabled'), $data['modules']['counts']['enabled'] ?? 0],
        [$this->t('Disabled'), $data['modules']['counts']['disabled'] ?? 0],
        [$this->t('Total detected'), $data['modules']['counts']['total'] ?? 0],
      ],
    ];

    $key_rows = [];
    foreach ($data['modules']['key_extensions'] as $machine_name => $info) {
      $status = $this->t('Not installed');
      if ($info['present']) {
        $status = $info['enabled'] ? $this->t('Enabled') : $this->t('Disabled');
      }

      $key_rows[] = [
        $machine_name,
        $info['label'] ?? $machine_name,
        $info['type'] ?? 'module',
        $status,
        $info['version'] ?? '',
      ];
    }

    $build['modules']['key_extensions'] = [
      '#type' => 'table',
      '#caption' => $this->t('Key contrib extensions'),
      '#header' => [
        $this->t('Machine name'),
        $this->t('Label'),
        $this->t('Type'),
        $this->t('Status'),
        $this->t('Version'),
      ],
      '#rows' => $key_rows,
    ];

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function buildMarkdown(array $data): string {
    $lines = [];
    $lines[] = '# ' . $this->getLabel();
    if ($description = $this->getDescription()) {
      $lines[] = '';
      $lines[] = $description;
    }

    $lines[] = '';
    $lines[] = '## Runtime';
    $lines[] = '';
    $lines[] = '| Item | Value |';
    $lines[] = '| ---- | ----- |';
    $lines[] = sprintf('| Drupal core | %s |', $data['drupal']['version'] ?? '');
    $lines[] = sprintf('| PHP | %s |', $data['php']['version'] ?? '');
    $lines[] = sprintf('| Database driver | %s |', $data['database']['driver'] ?? '');
    $lines[] = sprintf('| Database version | %s |', $data['database']['version'] ?? 'Unknown');

    $lines[] = '';
    $lines[] = '## Languages';
    $lines[] = '';
    $lines[] = '| Code | Label | Direction | Default |';
    $lines[] = '| ---- | ----- | --------- | ------- |';
    $default_langcode = $data['languages']['default']['id'] ?? '';
    foreach ($data['languages']['all'] as $langcode => $language) {
      $lines[] = sprintf(
        '| %s | %s | %s | %s |',
        $langcode,
        $language['name'] ?? '',
        $language['direction'] ?? '',
        $langcode === $default_langcode ? 'yes' : 'no'
      );
    }

    $lines[] = '';
    $lines[] = '## Modules';
    $lines[] = '';
    $lines[] = '| Metric | Value |';
    $lines[] = '| ------ | ----- |';
    $lines[] = sprintf('| Enabled | %d |', $data['modules']['counts']['enabled'] ?? 0);
    $lines[] = sprintf('| Disabled | %d |', $data['modules']['counts']['disabled'] ?? 0);
    $lines[] = sprintf('| Total detected | %d |', $data['modules']['counts']['total'] ?? 0);

    $lines[] = '';
    $lines[] = '### Key contrib extensions';
    $lines[] = '';
    $lines[] = '| Machine name | Label | Type | Status | Version |';
    $lines[] = '| ------------ | ----- | ---- | ------ | ------- |';
    foreach ($data['modules']['key_extensions'] as $machine_name => $info) {
      $status = 'Not installed';
      if ($info['present']) {
        $status = $info['enabled'] ? 'Enabled' : 'Disabled';
      }

      $lines[] = sprintf(
        '| %s | %s | %s | %s | %s |',
        $machine_name,
        $info['label'] ?? $machine_name,
        $info['type'] ?? 'module',
        $status,
        $info['version'] ?? ''
      );
    }

    $lines[] = '';
    return implode("\n", $lines);
  }

}
