<?php

namespace Drupal\site_audit\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\site_audit\Report\AuditReportManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller for audit report views.
 */
class AuditReportViewController extends ControllerBase {

  public function __construct(
    protected AuditReportManager $reportManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('site_audit.audit_report_manager')
    );
  }

  /**
   * Dynamic page title based on report label.
   */
  public function title(string $audit_report): string {
    $instance = $this->getReportInstance($audit_report);
    return $instance ? $instance->getLabel() : $this->t('Unknown report')->render();
  }

  /**
   * HTML view of a report.
   */
  public function html(string $audit_report): array {
    $instance = $this->getReportInstance($audit_report);
    if (!$instance) {
      throw $this->createNotFoundException();
    }

    $data = $instance->buildData();
    return $instance->buildRender($data);
  }

  /**
   * JSON view of a report.
   */
  public function json(string $audit_report): JsonResponse {
    $instance = $this->getReportInstance($audit_report);
    if (!$instance) {
      throw $this->createNotFoundException();
    }

    $data = $instance->buildData();

    // Create normal JsonResponse (Symfony will json_encode the array).
    $response = new JsonResponse($data, 200, []);

    // Now configure encoding options (pretty print, etc.).
    $response->setEncodingOptions(JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    return $response;
  }

  /**
   * Markdown view of a report.
   */
  public function markdown(string $audit_report): Response {
    $instance = $this->getReportInstance($audit_report);
    if (!$instance) {
      throw $this->createNotFoundException();
    }

    $data = $instance->buildData();
    $markdown = $instance->buildMarkdown($data);

    $response = new Response($markdown);
    $response->headers->set('Content-Type', 'text/markdown; charset=utf-8');
    return $response;
  }

  /**
   * Helper: safely create report instance.
   */
  protected function getReportInstance(string $id) {
    $definitions = $this->reportManager->getEnabledDefinitions();
    if (!isset($definitions[$id])) {
      return NULL;
    }
    return $this->reportManager->createInstance($id);
  }

}
