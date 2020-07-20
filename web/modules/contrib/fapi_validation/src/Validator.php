<?php
/**
 * @file
 * Contains Drupal\fapi_validation\Validator.
 */

namespace Drupal\fapi_validation;

/**
 * Validator Class to parse Form Element validators content
 */
class Validator {
  /**
   * Raw validator content
   *
   * @var array|string
   */
  private $raw_validator;

  /**
   * Form element value
   *
   * @var string
   */
  private $value;

  /**
   * Rule name
   *
   * @var string
   */
  private $name;

  /**
   * Rule parameters
   *
   * @var array
   */
  private $params = [];

  /**
   * User defined error message
   *
   * @var string
   */
  private $error_message;

  /**
   * User defined error callback
   *
   * @var string
   */
  private $error_callback;

  /**
   * Create object and parse validator data
   *
   * @param array|string $raw_validator Raw user defined validator
   * @param string $value               Form element value
   */
  public function __construct($raw_validator, string $value) {
    $this->raw_validator = $raw_validator;
    $this->value = $value;
    $this->parse();
  }

  /**
   * Parse user defined validator
   */
  private function parse() {
    if (is_array($this->raw_validator)) {
      if (isset($this->raw_validator['error'])) {
        $this->error_message = $this->raw_validator['error'];
      }

      if (isset($this->raw_validator['error callback'])) {
        $this->error_callback = $this->raw_validator['error callback'];
      }

      if (!isset($this->raw_validator['rule'])) {
        throw new \LogicException("You can't define a validator as array and don't define 'rule' key.");
      }

      $this->raw_validator = $this->raw_validator['rule'];
    }

    preg_match('/^(.*?)(\[(.*)\])?$/', $this->raw_validator, $rs);

    $this->name = $rs[1];

    if (isset($rs[3])) {
      if ($this->name == 'regexp') {
        $this->params = [$rs[3]];
      }
      else {
        $this->params = preg_split('/ *, */', $rs[3]);
      }
    }
  }

  /**
   * Return Form Element value
   *
   * @return string
   */
  public function getValue() {
    return $this->value;
  }

  /**
   * Return rule name
   *
   * @return string
   */
  public function getName() {
    return $this->name;
  }

  /**
   * Return rule parameters
   *
   * @return array
   */
  public function getParams() {
    return $this->params;
  }

  /**
   * Check if there is user defined error message
   *
   * @return boolean
   */
  public function hasErrorMessageDefined() {
    return $this->error_message !== null;
  }

  /**
   * get User defined error error message
   *
   * @return string
   */
  public function getErrorMessage() {
    return $this->error_message;
  }

  /**
   * Check if there is user defined error callback
   *
   * @return boolean
   */
  public function hasErrorCallbackDefined() {
    return $this->error_callback !== null;
  }

  /**
   * Return user defined error callback
   *
   * @return string
   */
  public function getErrorCallback() {
    return $this->error_callback;
  }
}
