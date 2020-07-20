<?php

/**
 * Contains Drupal\fapi_validation\Plugin\FapiValidationFilter\StripTagsFilter.
 */

namespace Drupal\fapi_validation\Plugin\FapiValidationFilter;

use Drupal\fapi_validation\FapiValidationFiltersInterface;

/**
 * @FapiValidationFilter(
 *   id = "strip_tags"
 * )
 */
class StripTagsFilter implements FapiValidationFiltersInterface {

  /**
   * {@inheritdoc}
   */
  public function filter(string $value) {
    return strip_tags($value);
  }

}
