<?php

/**
 * Contains Drupal\fapi_validation\Plugin\FapiValidationValidator\AlphaDashValidator.
 */

namespace Drupal\fapi_validation\Plugin\FapiValidationValidator;

use Drupal\Core\Form\FormStateInterface;
use Drupal\fapi_validation\Annotation\FapiValidationValidator;
use Drupal\fapi_validation\FapiValidationValidatorsInterface;
use Drupal\fapi_validation\Validator;

/**
 * @FapiValidationValidator(
 *   id = "alpha_dash",
 *   error_message = "Use only alpha numerics, hyphen and underscore at %field."
 * )
 */
class AlphaValidator implements FapiValidationValidatorsInterface {

  /**
   * {@inheritdoc}
   */
  public function validate(Validator $validator, array $element, FormStateInterface $form_state) {
    return (bool) preg_match('/^[-\pL\pN_]+$/uD', (string) $validator->getValue());
  }

}
