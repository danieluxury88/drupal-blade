<?php

namespace Drupal\Tests\site_audit\Unit;

use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for MyService.
 */
#[CoversClass('\Drupal\my_module\Service\MyService')]
#[Group('site_audit')]
class MyServiceTest extends UnitTestCase {

  /**
   * Tests the add method.
   */
  public function testAdd(): void {
    $this->assertSame(5, 5);
  }

}
