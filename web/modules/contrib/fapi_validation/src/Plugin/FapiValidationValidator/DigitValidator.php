<?php

/**
 * Contains Drupal\fapi_validation\Plugin\FapiValidationValidator\DigitValidator.
 */

namespace Drupal\fapi_validation\Plugin\FapiValidationValidator;

use Drupal\Core\Form\FormStateInterface;
use Drupal\fapi_validation\Annotation\FapiValidationValidator;
use Drupal\fapi_validation\FapiValidationValidatorsInterface;
use Drupal\fapi_validation\Validator;

/**
 * @FapiValidationValidator(
 *   id = "digit",
 *   error_message = "Use only digits on %field."
 * )
 */
class DigitValidator implements FapiValidationValidatorsInterface {

  /**
   * {@inheritdoc}
   */
  public function validate(Validator $validator, array $element, FormStateInterface $form_state) {
    return (bool) preg_match('/^\d+$/', (string) $validator->getValue());
  }

}
