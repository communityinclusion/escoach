<?php

namespace Drupal\tamper\Plugin\Tamper;

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
      '#title' => $this->t('Serialization mode:'),
      '#options' => $this->getOptions(),
      '#default_value' => $this->getSetting(self::SETTING_MODE),
    ];

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
  protected function getOptions() {
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
   * {@inheritdoc}
   */
  public function tamper($data, ?TamperableItemInterface $item = NULL) {
    $function = $this->getSetting(self::SETTING_MODE);

    if (function_exists($function)) {
      $data = call_user_func($function, $data);
    }
    elseif ($function === 'yaml_encode') {
      $data = Yaml::dump($data);
    }
    elseif ($function === 'yaml_decode') {
      $data = Yaml::parse($data);
    }

    return $data;
  }

}
