<?php

namespace Drupal\config_package\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\config_package\Service\ConfigPackageReadinessChecker;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class ConfigPackageCheckForm extends FormBase {

  public function __construct(
    private readonly ConfigPackageReadinessChecker $checker,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config_package.readiness_checker'),
    );
  }

  public function getFormId(): string {
    return 'config_package_check_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $packages = $this->checker->listLocalPackages();

    $options = [];
    foreach ($packages as $bundle => $info) {
      $options[$info['path']] = $bundle . ' — ' . $info['path'];
    }

    $form['package_path'] = [
      '#type' => 'select',
      '#title' => $this->t('Local package'),
      '#options' => $options,
      '#required' => TRUE,
      '#empty_option' => $this->t('- Select -'),
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Run readiness check'),
    ];

    $result = $form_state->get('check_result');
    if (is_array($result)) {
      $form['result'] = [
        '#type' => 'details',
        '#title' => $this->t('Readiness report'),
        '#open' => TRUE,
      ];

      $form['result']['commands'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Suggested commands'),
        '#rows' => 6,
        '#default_value' => implode("\n", [
          $result['suggested_commands']['composer'],
          $result['suggested_commands']['enable'],
          $result['suggested_commands']['import'],
        ]),
      ];

      $form['result']['modules'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Modules status (module: status)'),
        '#rows' => 10,
        '#default_value' => $this->formatKeyValue($result['required_modules']),
      ];

      $form['result']['plugins'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Missing plugins (blocking)'),
        '#rows' => 10,
        '#default_value' => $this->formatMissingPlugins($result['missing_plugins']),
      ];

      $form['result']['config_summary'] = [
        '#type' => 'item',
        '#title' => $this->t('Config collisions summary'),
        '#markup' => sprintf(
          'New: <b>%d</b> — Identical: <b>%d</b> — Different: <b>%d</b>',
          $result['config_status']['new'] ?? 0,
          $result['config_status']['identical'] ?? 0,
          $result['config_status']['different'] ?? 0,
        ),
      ];
    }

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $path = (string) $form_state->getValue('package_path');

    try {
      $result = $this->checker->checkPackage($path);
      $form_state->set('check_result', $result);
      $form_state->setRebuild(TRUE);

      // Helpful messages.
      if (!empty($result['missing_modules'])) {
        $this->messenger()->addError($this->t('Missing modules in codebase: @m', ['@m' => implode(', ', $result['missing_modules'])]));
      }
      if (!empty($result['missing_plugins'])) {
        $this->messenger()->addError($this->t('Missing plugins detected. Import will fail until you add/enable their provider modules.'));
      }
      if (($result['config_status']['different'] ?? 0) > 0) {
        $this->messenger()->addWarning($this->t('Some config already exists and differs. On a blank site, this should usually be 0.'));
      }
      if (empty($result['missing_modules']) && empty($result['missing_plugins'])) {
        $this->messenger()->addStatus($this->t('Site looks ready (modules/plugins) for importing this package.'));
      }
    }
    catch (\Throwable $e) {
      $this->messenger()->addError($this->t('Readiness check failed: @msg', ['@msg' => $e->getMessage()]));
    }
  }

  private function formatKeyValue(array $kv): string {
    ksort($kv);
    $lines = [];
    foreach ($kv as $k => $v) {
      $lines[] = "$k: $v";
    }
    return implode("\n", $lines);
  }

  private function formatMissingPlugins(array $missing): string {
    if (!$missing) {
      return 'None';
    }
    $lines = [];
    foreach ($missing as $m) {
      $lines[] = sprintf(
        '%s plugin "%s" used in %s',
        $m['type'] ?? 'plugin',
        $m['plugin_id'] ?? 'unknown',
        $m['used_in'] ?? 'unknown'
      );
    }
    return implode("\n", $lines);
  }

}
