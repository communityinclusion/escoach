<?php
/**
 * @file
 * Contains Drupal\fapi_validation\FapiValidationFiltersInterface.
 */

namespace Drupal\fapi_validation;

/**
 * Fapi Validation Filter Plugin Interface
 */
interface FapiValidationFiltersInterface {

  /**
   * Execute filter
   *
   * @param  string $value Form Element Value
   * @return string        Processed Value
   */
  public function filter(string $value);

}
