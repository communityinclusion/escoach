<?php

namespace Drupal\Tests\fapi_validation\Unit\Filters;

use Drupal\Tests\UnitTestCase;
use Drupal\fapi_validation\Plugin\FapiValidationFilter\TrimFilter;

/**
 * Tests generation of ice cream.
 *
 * @group fapi_validation
 * @group fapi_validation_filters
 */
class TrimFilterTest extends UnitTestCase {

  public function testValidString() {
    $plugin = new TrimFilter;
    $this->assertEquals('test', $plugin->filter('   test    '));
  }

}
