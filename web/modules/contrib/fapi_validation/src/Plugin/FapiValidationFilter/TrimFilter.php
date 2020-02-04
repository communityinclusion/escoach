<?php

/**
 * Contains Drupal\fapi_validation\Plugin\FapiValidationFilter\TrimFilter.
 */

namespace Drupal\fapi_validation\Plugin\FapiValidationFilter;

use Drupal\fapi_validation\FapiValidationFiltersInterface;

/**
 * @FapiValidationFilter(
 *   id = "trim"
 * )
 */
class TrimFilter implements FapiValidationFiltersInterface {

  /**
   * {@inheritdoc}
   */
  public function filter(string $value) {
    return trim($value);
  }

}
