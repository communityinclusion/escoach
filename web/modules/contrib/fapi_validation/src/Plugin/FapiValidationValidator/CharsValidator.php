<?php

/**
 * Contains Drupal\fapi_validation\Plugin\FapiValidationValidator\CharsValidator.
 */

namespace Drupal\fapi_validation\Plugin\FapiValidationValidator;

use Drupal\Core\Form\FormStateInterface;
use Drupal\fapi_validation\Annotation\FapiValidationValidator;
use Drupal\fapi_validation\FapiValidationValidatorsInterface;
use Drupal\fapi_validation\Validator;

/**
 * @FapiValidationValidator(
 *   id = "chars",
 *   error_message = "Use only alpha characters at %field."
 * )
 */
class CharsValidator implements FapiValidationValidatorsInterface {

  /**
   * {@inheritdoc}
   */
  public function validate(Validator $validator, array $element, FormStateInterface $form_state) {
    $params = $validator->getParams();
    $value = $validator->getValue();

    for ($i = 0; $i < mb_strlen($value); $i++) {
      $current = substr($value, $i, 1);
      if (! in_array($current, $params)) {
        return FALSE;
      }
    }
    return TRUE;
  }

}
