<?php

namespace Drupal\site_audit\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines an Audit Report plugin annotation object.
 *
 * @Annotation
 */
class AuditReport extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * Human-readable label.
   *
   * @var \Drupal\Core\StringTranslation\TranslatableMarkup
   */
  public $label;

  /**
   * Short description of what this report does.
   *
   * @var \Drupal\Core\StringTranslation\TranslatableMarkup
   */
  public $description;

  /**
   * Whether this report is enabled.
   *
   * @var bool
   */
  public $enabled = TRUE;

}
