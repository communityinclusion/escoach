<?php

namespace Drupal\tamper\Plugin\Tamper;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Form\FormStateInterface;
use Drupal\tamper\TamperBase;
use Drupal\tamper\TamperableItemInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Plugin implementation for encoding / decoding.
 *
 * @Tamper(
 *   id = "encode",
 *   label = @Translation("Encode/Decode"),
 *   description = @Translation("Encode (or Decode) the field contents."),
 *   category = "Text",
 *   handle_multiples = TRUE
 * )
 */
class Encode extends TamperBase {

  const SETTING_MODE = 'mode';

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $config = parent::defaultConfiguration();
    $config[self::SETTING_MODE] = 'serialize';
    return $config;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form[self::SETTING_MODE] = [
      '#type' => 'radios',
      '#title' => $this->t('Conversion mode:'),
      '#options' => $this->getOptions(),
      '#default_value' => $this->getSetting(self::SETTING_MODE),
    ];

    foreach ($this->getOptionsDescriptions() as $key => $description) {
      $form[self::SETTING_MODE][$key]['#description'] = $description;
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    $this->setConfiguration([self::SETTING_MODE => $form_state->getValue(self::SETTING_MODE)]);
  }

  /**
   * Get the encode / decode options.
   *
   * @return array
   *   List of options, keyed by method.
   */
  protected function getOptions(): array {
    return [
      'serialize' => $this->t('PHP Serialize'),
      'unserialize' => $this->t('PHP Unserialize'),
      'json_encode' => $this->t('Json Encode'),
      'json_decode' => $this->t('Json Decode'),
      'base64_encode' => $this->t('Base64 Encode'),
      'base64_decode' => $this->t('Base64 Decode'),
      'yaml_encode' => $this->t('YAML Encode'),
      'yaml_decode' => $this->t('YAML Decode'),
    ];
  }

  /**
   * Defines the description for each available option.
   *
   * @return array
   *   List of option descriptions.
   */
  protected function getOptionsDescriptions(): array {
    return [
      'serialize' => $this->t('Generates a storable representation of a value.'),
      'unserialize' => $this->t('Creates a PHP value from a stored representation.'),
      'json_encode' => $this->t('Creates the JSON representation of a value.'),
      'json_decode' => $this->t('Takes a JSON encoded string and converts it into a PHP value.'),
      'base64_encode' => $this->t('Encodes data with MIME base64.'),
      'base64_decode' => $this->t('Decodes data encoded with MIME base64.'),
      'yaml_encode' => $this->t('Creates the YAML representation of a value.'),
      'yaml_decode' => $this->t('Takes a YAML encoded string and converts it into a PHP value.'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function tamper($data, ?TamperableItemInterface $item = NULL) {
    $mode = $this->getSetting(self::SETTING_MODE);
    switch ($mode) {
      case 'serialize':
      case 'unserialize':
      case 'base64_encode':
      case 'base64_decode':
        if (function_exists($mode)) {
          $data = call_user_func($mode, $data);
        }
        break;

      case 'json_encode':
        $data = Json::encode($data);
        break;

      case 'json_decode':
        $data = Json::decode($data);
        break;

      case 'yaml_encode':
        $data = Yaml::dump($data);
        break;

      case 'yaml_decode':
        $data = Yaml::parse($data);
        break;
    }

    return $data;
  }

}
