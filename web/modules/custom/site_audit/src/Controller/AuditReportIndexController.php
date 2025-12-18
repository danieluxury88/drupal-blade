<?php

namespace Drupal\site_audit\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\site_audit\Report\AuditReportManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for audit report index page.
 */
class AuditReportIndexController extends ControllerBase {

  public function __construct(
    protected AuditReportManager $reportManager,
  ) {}

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('site_audit.audit_report_manager')
    );
  }

  /**
   * Index page listing all available audit reports.
   */
  public function index(): array {
    $definitions = $this->reportManager->getEnabledDefinitions();

    $rows = [];
    foreach ($definitions as $id => $def) {
      $label = (string) ($def['label'] ?? $id);
      $description = (string) ($def['description'] ?? '');

      $rows[] = [
        'id' => $id,
        'label' => $label,
        'description' => [
          'data' => ['#markup' => $description],
        ],
        'links' => [
          'data' => [
            '#theme' => 'links',
            '#links' => [
              [
                'title' => $this->t('View'),
                'url' => Url::fromRoute('site_audit.report_view', ['audit_report' => $id]),
              ],
              [
                'title' => $this->t('JSON'),
                'url' => Url::fromRoute('site_audit.report_view_json', ['audit_report' => $id]),
              ],
              [
                'title' => $this->t('Markdown'),
                'url' => Url::fromRoute('site_audit.report_view_markdown', ['audit_report' => $id]),
              ],
            ],
          ],
        ],
      ];
    }

    return [
      '#type' => 'container',
      'intro' => [
        '#markup' => $this->t('Available site audit reports.'),
      ],
      'table' => [
        '#type' => 'table',
        '#header' => [
          $this->t('ID'),
          $this->t('Label'),
          $this->t('Description'),
          $this->t('Links'),
        ],
        '#rows' => array_map(function ($row) {
          return [
            $row['id'],
            $row['label'],
            $row['description'],
            $row['links'],
          ];
        }, $rows),
      ],
    ];
  }

}
