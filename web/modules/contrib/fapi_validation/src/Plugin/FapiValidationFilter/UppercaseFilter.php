<?php

/**
 * Contains Drupal\fapi_validation\Plugin\FapiValidationFilter\UppercaseFilter.
 */

namespace Drupal\fapi_validation\Plugin\FapiValidationFilter;

use Drupal\fapi_validation\FapiValidationFiltersInterface;

/**
 * @FapiValidationFilter(
 *   id = "uppercase"
 * )
 */
class UppercaseFilter implements FapiValidationFiltersInterface {

  /**
   * {@inheritdoc}
   */
  public function filter(string $value) {
    return mb_strtoupper($value);
  }

}
