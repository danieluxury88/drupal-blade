<?php

namespace Drupal\Tests\site_audit\Unit;

use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\my_module\Service\MyService
 *
 * @group my_module
 */
class MyServiceTest extends UnitTestCase {

  /**
   * @covers ::add
   */
  public function testAdd() {
    $this->assertSame(5, 5);
  }

}
