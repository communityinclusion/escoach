<?php

/**
 * Contains Drupal\fapi_validation\Plugin\FapiValidationFilter\LowercaseFilter.
 */

namespace Drupal\fapi_validation\Plugin\FapiValidationFilter;

use Drupal\fapi_validation\FapiValidationFiltersInterface;

/**
 * @FapiValidationFilter(
 *   id = "lowercase"
 * )
 */
class LowercaseFilter implements FapiValidationFiltersInterface {

  /**
   * {@inheritdoc}
   */
  public function filter(string $value) {
    mb_strtolower($value);
  }

}
