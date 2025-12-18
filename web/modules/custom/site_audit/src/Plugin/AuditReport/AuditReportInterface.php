<?php

namespace Drupal\site_audit\Plugin\AuditReport;

/**
 * Interface for audit reports.
 */
interface AuditReportInterface {

  /**
   * Machine ID for this report.
   */
  public function getId(): string;

  /**
   * Human label.
   */
  public function getLabel(): string;

  /**
   * Short description.
   */
  public function getDescription(): string;

  /**
   * Build raw data array for this report.
   *
   * @return array
   *   Report data.
   */
  public function buildData(): array;

  /**
   * Build a render array (HTML) from data.
   *
   * @param array $data
   *   Report data from buildData().
   *
   * @return array
   *   A render array.
   */
  public function buildRender(array $data): array;

  /**
   * Build a Markdown representation from data.
   *
   * @param array $data
   *   Report data from buildData().
   *
   * @return string
   *   A Markdown string.
   */
  public function buildMarkdown(array $data): string;

}
