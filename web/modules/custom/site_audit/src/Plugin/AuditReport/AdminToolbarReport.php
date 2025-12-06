<?php

declare(strict_types=1);

namespace Drupal\site_audit\Plugin\AuditReport;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\site_audit\Annotation\AuditReport;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an audit report for admin toolbar/navigation & admin themes.
 *
 * @AuditReport(
 *   id = "admin_toolbar_overview",
 *   label = @Translation("Admin toolbar & navigation overview"),
 *   description = @Translation("Collects information about toolbar/navigation modules and admin themes that may affect first-level admin menu click behavior.")
 * )
 */
class AdminToolbarReport extends AuditReportBase implements ContainerFactoryPluginInterface {

  /**
   * Important modules related to toolbar/navigation.
   *
   * @var string[]
   */
  protected array $interestingModules = [
    'toolbar',
    'navigation', // New admin navigation in Drupal 10+.
    'admin_toolbar',
    'admin_toolbar_tools',
    'admin_toolbar_links_access_filter',
    'gin_toolbar',
    'gin',
    'claro',
    'seven',
  ];

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * The theme handler.
   *
   * @var \Drupal\Core\Extension\ThemeHandlerInterface
   */
  protected ThemeHandlerInterface $themeHandler;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * Constructs an AdminToolbarReport object.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ModuleHandlerInterface $module_handler,
    ThemeHandlerInterface $theme_handler,
    ConfigFactoryInterface $config_factory,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->moduleHandler = $module_handler;
    $this->themeHandler = $theme_handler;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('module_handler'),
      $container->get('theme_handler'),
      $container->get('config.factory'),
    );
  }

  /**
   * {@inheritdoc}
   *
   * NOTE: AuditReportInterface expects buildData(), and your base class
   * will later call buildRender()/buildMarkdown() on this array.
   */
  public function buildData(array $context = []): array {
    $modules_info = $this->collectModulesInfo();
    $themes_info = $this->collectThemesInfo();
    $gin_settings = $this->collectGinSettings();
    $diagnostics = $this->buildDiagnostics($modules_info, $themes_info, $gin_settings);

    return [
      'title' => (string) $this->getLabel(),
      'description' => $this->getDescription(),
      'summary' => [
        'admin_theme' => $themes_info['admin_theme'] ?? null,
        'default_theme' => $themes_info['default_theme'] ?? null,
      ],
      'modules' => $modules_info,
      'themes' => $themes_info,
      'gin_settings' => $gin_settings,
      'diagnostics' => $diagnostics,
    ];
  }

  /**
   * Collects info about relevant modules (enabled/disabled).
   *
   * @return array
   *   An array keyed by module machine name.
   */
  protected function collectModulesInfo(): array {
    $info = [];

    foreach ($this->interestingModules as $module_name) {
      $enabled = $this->moduleHandler->moduleExists($module_name);
      $info[$module_name] = [
        'name' => $module_name,
        'enabled' => $enabled,
        // You can later enrich this with version info via extension.list.module.
        'version' => null,
      ];
    }

    return $info;
  }

  /**
   * Collect theme-related information.
   *
   * @return array
   *   Theme information including admin/default theme and installed themes.
   */
  protected function collectThemesInfo(): array {
    $config = $this->configFactory->get('system.theme');
    $admin_theme = $config->get('admin');
    $default_theme = $config->get('default');

    $themes = [];
    $list_info = $this->themeHandler->listInfo();

    foreach ($list_info as $name => $theme) {
      $is_admin_candidate = !empty($theme->info['admin']) || in_array($name, ['gin', 'claro', 'seven'], true);

      $themes[$name] = [
        'name' => $name,
        'is_admin_theme' => ($name === $admin_theme),
        'is_default_theme' => ($name === $default_theme),
        'is_admin_candidate' => $is_admin_candidate,
      ];
    }

    return [
      'admin_theme' => $admin_theme,
      'default_theme' => $default_theme,
      'themes' => $themes,
    ];
  }

  /**
   * Collects a few relevant Gin settings (if Gin is installed).
   *
   * @return array
   *   Gin settings subset or empty array.
   */
  protected function collectGinSettings(): array {
    if (!$this->moduleHandler->moduleExists('gin')) {
      return [];
    }

    $config = $this->configFactory->get('gin.settings');
    if ($config->isNew()) {
      return [];
    }

    $raw = $config->getRawData();

    // Adjust keys to whatever is useful for your setup.
    $keys_of_interest = [
      'navigation',        // e.g. Gin navigation behavior.
      'toolbar_variant',   // compact/standard/etc.
      'show_user_toolbar',
    ];

    $result = [];
    foreach ($keys_of_interest as $key) {
      if (array_key_exists($key, $raw)) {
        $result[$key] = $raw[$key];
      }
    }

    return $result;
  }

  /**
   * Builds diagnostic hints based on collected data.
   *
   * @param array $modules
   * @param array $themes
   * @param array $gin_settings
   *
   * @return array
   *   A list of diagnostic strings.
   */
  protected function buildDiagnostics(array $modules, array $themes, array $gin_settings): array {
    $flags = [];

    $admin_theme = $themes['admin_theme'] ?? null;

    $has_toolbar = $modules['toolbar']['enabled'] ?? false;
    $has_navigation = $modules['navigation']['enabled'] ?? false;
    $has_admin_toolbar = $modules['admin_toolbar']['enabled'] ?? false;
    $has_gin_toolbar = $modules['gin_toolbar']['enabled'] ?? false;
    $has_gin = $modules['gin']['enabled'] ?? false;

    if ($has_navigation && $has_toolbar) {
      $flags[] = $this->t('Both the core Toolbar module and the Navigation module are enabled. This combination may change how the admin menu behaves.');
    }

    if ($has_admin_toolbar && !$has_toolbar) {
      $flags[] = $this->t('Admin Toolbar is enabled but the core Toolbar module is disabled. Admin Toolbar usually requires Toolbar.');
    }

    if ($admin_theme === 'gin' && !$has_gin) {
      $flags[] = $this->t('The admin theme is set to Gin, but the Gin module is not reported as enabled. Check your configuration.');
    }

    if ($admin_theme === 'gin' && $has_admin_toolbar && $has_gin_toolbar) {
      $flags[] = $this->t('Gin is used as admin theme together with Admin Toolbar and Gin Toolbar. This is a valid combination, but if first-level menu clicks are not working, test with Claro or Seven to isolate the issue.');
    }

    if ($admin_theme === 'gin' && !empty($gin_settings)) {
      $flags[] = $this->t('Gin is the admin theme and custom Gin toolbar/navigation settings are present. If you have click issues, try temporarily switching to Claro or Seven and disabling Gin enhancements.');
    }

    if ($admin_theme !== 'gin' && $has_gin) {
      $flags[] = $this->t('Gin module is enabled but not set as admin theme. Verify whether this is intentional.');
    }

    if (empty($flags)) {
      $flags[] = $this->t('No obvious toolbar/navigation configuration conflicts were detected. If first-level admin menu items are still not clickable, check browser console for JavaScript errors and test with a different admin theme (e.g. Claro or Seven).');
    }

    return $flags;
  }

}
