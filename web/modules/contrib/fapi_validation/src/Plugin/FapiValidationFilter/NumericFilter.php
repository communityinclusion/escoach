<?php

/**
 * Contains Drupal\fapi_validation\Plugin\FapiValidationFilter\NumericFilter.
 */

namespace Drupal\fapi_validation\Plugin\FapiValidationFilter;

use Drupal\fapi_validation\FapiValidationFiltersInterface;

/**
 * @FapiValidationFilter(
 *   id = "numeric"
 * )
 */
class NumericFilter implements FapiValidationFiltersInterface {

  /**
   * {@inheritdoc}
   */
  public function filter(string $value) {
    return preg_replace('/[^0-9]+/', '', $value);
  }

}
