<?php

/**
 * Contains Drupal\fapi_validation\Plugin\FapiValidationValidator\EmailValidator.
 */

namespace Drupal\fapi_validation\Plugin\FapiValidationValidator;

use Drupal\Core\Form\FormStateInterface;
use Drupal\fapi_validation\Annotation\FapiValidationValidator;
use Drupal\fapi_validation\FapiValidationValidatorsInterface;
use Drupal\fapi_validation\Validator;

/**
 * @FapiValidationValidator(
 *   id = "email",
 *   error_message = "%field is not a valid email."
 * )
 */
class EmailValidator implements FapiValidationValidatorsInterface {

  /**
   * {@inheritdoc}
   */
  public function validate(Validator $validator, array $element, FormStateInterface $form_state) {
    return \Drupal::service('email.validator')->isValid($validator->getValue());
  }

}
