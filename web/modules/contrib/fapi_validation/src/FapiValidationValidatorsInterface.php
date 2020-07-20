<?php
/**
 * @file
 * Contains Drupal\fapi_validation\FapiValidationValidatorsInterface.
 */

namespace Drupal\fapi_validation;

use Drupal\Core\Form\FormStateInterface;
use Drupal\fapi_validation\Validator;

/**
 * Fapi Validation Validator Plugin Interface
 */
interface FapiValidationValidatorsInterface {

  /**
   * Execute validation
   *
   * @param  Validator          $validator  Validator
   * @param  array              $element    Form Element
   * @param  FormStateInterface $form_state Form State
   * @return boolean
   */
  public function validate(Validator $validator, array $element, FormStateInterface $form_state);

}
