<?php

namespace Drupal\field_display_label\Hook;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Config\Entity\ThirdPartySettingsInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for field_display_label.
 */
class FieldDisplayLabelHooks {
  use StringTranslationTrait;

  /**
   * Implements hook_form_FORM_ID_alter() for field_config_edit_form().
   */
  #[Hook('form_field_config_edit_form_alter')]
  public function formFieldConfigEditFormAlter(&$form, FormStateInterface $form_state): void {
    $field = $form_state->getFormObject()->getEntity();
    if (!isset($field)) {
      return;
    }
    $form['display_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Display label'),
      '#description' => $this->t('A separate label for viewing this field. Leave blank to use the default field label.'),
      '#weight' => isset($form['label']['#weight']) ? $form['label']['#weight'] + 1 : 0,
      '#default_value' => !empty($field->getThirdPartySetting('field_display_label', 'display_label')) ? $field->getThirdPartySetting('field_display_label', 'display_label') : '',
    ];
    $form['actions']['submit']['#submit'][] = 'field_display_label_form_field_config_edit_form_submit';
  }

  /**
   * Implements hook_preprocess_field().
   */
  #[Hook('preprocess_field')]
  public static function preprocessField(&$variables): void {
    $element = $variables['element'];
    $entity = $element['#object'];
    if (empty($entity)) {
      return;
    }
    $field_definition = $entity->getFieldDefinition($element['#field_name']);
    if ($field_definition instanceof ThirdPartySettingsInterface) {
      $definition = $field_definition->getThirdPartySetting('field_display_label', 'display_label');
      if (!empty($definition)) {
        $variables['label'] = $definition;
      }
    }
  }

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help($route_name, RouteMatchInterface $route_match) {
    if ($route_name == 'help.page.field_display_label') {
      $output = '<p>' . $this->t('The Field Display Label module allows you to change the display label of a field on a per-bundle basis. This can be useful for changing the label of a field without needing to alter the field itself or override the label using a template.') . '</p>';
      $output .= '<h3>' . $this->t('Using the Field Display Label module') . '</h3>';
      $output .= '<p>' . $this->t('To use the Field Display Label module, navigate to the manage display page for a content type or entity bundle and click on the gear icon for the field you want to modify. From there, you can enter a custom label for the field and choose which view modes it applies to.') . '</p>';
      return $output;
    }
  }

}
