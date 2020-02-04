<?php

/**
 * Contains Drupal\fapi_validation\Plugin\FapiValidationFilter\HtmlEntitiesFilter.
 */

namespace Drupal\fapi_validation\Plugin\FapiValidationFilter;

use Drupal\fapi_validation\FapiValidationFiltersInterface;

/**
 * @FapiValidationFilter(
 *   id = "html_entitites"
 * )
 */
class HtmlEntitiesFilter implements FapiValidationFiltersInterface {

  /**
   * {@inheritdoc}
   */
  public function filter(string $value) {
    htmlentities(html_entity_decode($value));
  }

}
