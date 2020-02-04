<?php

/**
 * Contains Drupal\fapi_validation\Plugin\FapiValidationValidator\LengthValidator.
 */

namespace Drupal\fapi_validation\Plugin\FapiValidationValidator;

use Drupal\Core\Form\FormStateInterface;
use Drupal\fapi_validation\Annotation\FapiValidationValidator;
use Drupal\fapi_validation\FapiValidationValidatorsInterface;
use Drupal\fapi_validation\Validator;

/**
 * @FapiValidationValidator(
 *   id = "length",
 *   error_message = "Invalid size of %field value."
 * )
 */
class LengthValidator implements FapiValidationValidatorsInterface {

  /**
   * {@inheritdoc}
   */
  public function validate(Validator $validator, array $element, FormStateInterface $form_state) {
    $params = $validator->getParams();
    $size = mb_strlen($validator->getValue());
    if (count($params) == 1) {
      return $size == (int) $params[0];
    }
    elseif (count($params) == 2) {
      if ($params[1] == '*') {
        return ($size >= (int) $params[0]);
      }
      return ($size >= (int) $params[0] && $size <= (int) $params[1]);
    }
  }

}
