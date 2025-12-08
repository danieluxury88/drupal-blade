<?php

namespace Drupal\Tests\site_audit\Functional;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the home page route.
 */
#[Group('site_audit')]
class HomePageTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests that the home page loads successfully.
   */
  public function testHomePageLoads(): void {
    $this->drupalGet('<front>');
    $this->assertSession()->statusCodeEquals(200);
  }

}
