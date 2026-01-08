<?php

namespace Drupal\config_package\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\config_package\Service\ConfigPackageAnalyzer;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\config_package\Service\ConfigPackageExporter;


final class ConfigPackageAnalyzeForm extends FormBase {

  public function __construct(
    private readonly ConfigPackageAnalyzer $analyzer,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ConfigPackageExporter $exporter,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config_package.analyzer'),
      $container->get('entity_type.manager'),
       $container->get('config_package.exporter'),
    );
  }

  public function getFormId(): string {
    return 'config_package_analyze_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $bundle_options = $this->loadNodeBundleOptions();

    $form['bundle'] = [
      '#type' => 'select',
      '#title' => $this->t('Content type'),
      '#options' => $bundle_options,
      '#required' => TRUE,
    ];

    $form['include_paragraphs'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include Paragraph dependencies'),
      '#default_value' => TRUE,
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Analyze'),
    ];
    $form['actions']['generate'] = [
        '#type' => 'submit',
        '#value' => $this->t('Generate partial folder'),
        '#submit' => ['::generatePartialFolder'],
        '#states' => [
            'visible' => [
            ':input[name="bundle"]' => ['!value' => ''],
            ],
        ],
    ];


    $result = $form_state->get('analysis_result');
    if (is_array($result)) {
      $form['result'] = [
        '#type' => 'details',
        '#title' => $this->t('Result'),
        '#open' => TRUE,
      ];

      $form['result']['drush'] = [
        '#type' => 'item',
        '#title' => $this->t('Suggested import command'),
        '#markup' => '<code>drush cim -y --partial --source=../config/partial/' . $result['bundle'] . '</code>',
      ];

      $form['result']['paragraphs'] = [
        '#type' => 'item',
        '#title' => $this->t('Paragraph bundles'),
        '#markup' => $result['paragraphs'] ? implode(', ', $result['paragraphs']) : $this->t('None detected'),
      ];

      $form['result']['modules'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Required modules (enable on target)'),
        '#default_value' => implode("\n", array_keys($result['modules'])),
        '#rows' => 8,
      ];

      $grouped = [];
      foreach ($result['config'] as $name => $meta) {
        $grouped[$meta['group']][] = $name . '  # ' . $meta['reason'];
      }
      ksort($grouped);

      $out = [];
      foreach ($grouped as $group => $lines) {
        $out[] = "### $group";
        $out[] = implode("\n", $lines);
        $out[] = "";
      }

      $form['result']['config'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Config names to include in the partial package'),
        '#default_value' => implode("\n", $out),
        '#rows' => 24,
      ];
    }

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $bundle = (string) $form_state->getValue('bundle');
    $include_paragraphs = (bool) $form_state->getValue('include_paragraphs');

    $result = $this->analyzer->analyzeNodeBundle($bundle, $include_paragraphs);
    $form_state->set('analysis_result', $result);
    $form_state->setRebuild(TRUE);
  }

  private function loadNodeBundleOptions(): array {
    $options = [];
    $types = $this->entityTypeManager->getStorage('node_type')->loadMultiple();
    foreach ($types as $type) {
      /** @var \Drupal\node\NodeTypeInterface $type */
      $options[$type->id()] = $type->label() . ' (' . $type->id() . ')';
    }
    asort($options);
    return $options;
  }

  public function generatePartialFolder(array &$form, FormStateInterface $form_state): void {
  $bundle = (string) $form_state->getValue('bundle');
  $include_paragraphs = (bool) $form_state->getValue('include_paragraphs');

  // Always analyze fresh before exporting (avoid stale form_state).
  $result = $this->analyzer->analyzeNodeBundle($bundle, $include_paragraphs);

  $config_names = array_keys($result['config']);
  $modules = array_keys($result['modules']);

  $export = $this->exporter->export($bundle, $config_names, $modules);

    $this->messenger()->addStatus($this->t(
    'Exported @count YAML files to @config_path. Manifest: @manifest',
    [
        '@count' => $export['exported'],
        '@config_path' => $export['config_path'],
        '@manifest' => $export['manifest'],
    ]
    ));


  if (!empty($export['missing'])) {
    $this->messenger()->addWarning($this->t(
      'Some config names were not found in the sync directory (did you run drush cex?): @missing',
      ['@missing' => implode(', ', $export['missing'])]
    ));
  }

  // Rebuild and show results.
  $form_state->set('analysis_result', $result);
  $form_state->setRebuild(TRUE);
}


}
