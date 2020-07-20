<?php

/**
 * Contains Drupal\fapi_validation\Plugin\FapiValidationValidator\Ipv4Validator.
 */

namespace Drupal\fapi_validation\Plugin\FapiValidationValidator;

use Drupal\Core\Form\FormStateInterface;
use Drupal\fapi_validation\Annotation\FapiValidationValidator;
use Drupal\fapi_validation\FapiValidationValidatorsInterface;
use Drupal\fapi_validation\Validator;

/**
 * @FapiValidationValidator(
 *   id = "ipv4",
 *   error_message = "Invalid format of %field."
 * )
 */
class Ipv4Validator implements FapiValidationValidatorsInterface {

  /**
   * {@inheritdoc}
   */
  public function validate(Validator $validator, array $element, FormStateInterface $form_state) {
    $pattern = '/^([1-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])'
      . '(\.([0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])){3}$/';
    return (bool) preg_match($pattern, $validator->getValue());
  }

}
