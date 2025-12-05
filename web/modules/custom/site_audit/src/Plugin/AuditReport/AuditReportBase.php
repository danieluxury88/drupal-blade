<?php

namespace Drupal\site_audit\Plugin\AuditReport;

use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Base class for Audit Report plugins.
 */
abstract class AuditReportBase extends PluginBase implements AuditReportInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getId(): string {
    return $this->getPluginId();
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel(): string {
    return (string) ($this->pluginDefinition['label'] ?? $this->getPluginId());
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): string {
    return (string) ($this->pluginDefinition['description'] ?? '');
  }

  /**
   * {@inheritdoc}
   *
   * Default HTML: pretty-printed JSON.
   */
  public function buildRender(array $data): array {
    return [
      '#type' => 'details',
      '#title' => $this->getLabel(),
      '#open' => TRUE,
      'description' => [
        '#markup' => $this->getDescription(),
      ],
      'pre' => [
        '#type' => 'html_tag',
        '#tag' => 'pre',
        '#value' => json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
      ],
    ];
  }

  /**
   * {@inheritdoc}
   *
   * Default Markdown: JSON dump.
   */
  public function buildMarkdown(array $data): string {
    $md = [];
    $md[] = '## ' . $this->getLabel();
    $md[] = '';
    if ($desc = $this->getDescription()) {
      $md[] = $desc;
      $md[] = '';
    }
    $md[] = '```json';
    $md[] = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    $md[] = '```';
    $md[] = '';

    return implode("\n", $md);
  }

}
