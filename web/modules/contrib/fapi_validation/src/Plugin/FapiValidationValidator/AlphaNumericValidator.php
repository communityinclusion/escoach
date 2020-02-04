<?php

/**
 * Contains Drupal\fapi_validation\Plugin\FapiValidationValidator\AlphaNumericValidator.
 */

namespace Drupal\fapi_validation\Plugin\FapiValidationValidator;

use Drupal\Core\Form\FormStateInterface;
use Drupal\fapi_validation\Annotation\FapiValidationValidator;
use Drupal\fapi_validation\FapiValidationValidatorsInterface;
use Drupal\fapi_validation\Validator;

/**
 * @FapiValidationValidator(
 *   id = "alpha_numeric",
 *   error_message = "Use only alpha numerics characters at %field."
 * )
 */
class AlphaNumericValidator implements FapiValidationValidatorsInterface {

  /**
   * {@inheritdoc}
   */
  public function validate(Validator $validator, array $element, FormStateInterface $form_state) {
    return (bool) preg_match('/^[\pL]++$/uD', (string) $validator->getValue());
  }

}
