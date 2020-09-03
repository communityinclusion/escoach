<?php

namespace Drupal\datetime_extras\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * Plugin implementation of the 'datatime_extras_configurable_list' widget.
 *
 * @FieldWidget(
 *   id = "datatime_extras_configurable_list",
 *   label = @Translation("Configurable list (deprecated)"),
 *   field_types = {
 *     "datetime"
 *   }
 * )
 *
 * @deprecated in datetime_extras:8.x-1.0 and is removed from
 * datetime_extras:8.x-2.0. Use
 * \Drupal\datetime_extras\Plugin\Field\FieldWidget\DateTimeDatelistNoTimeWidget
 * instead.
 * @see https://www.drupal.org/node/2973035
 */
class DateConfigurableListWidget extends DateTimeDatelistNoTimeWidget {

  /**
   * {@inheritdoc}
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    @trigger_error('The ' . __NAMESPACE__ . '\DateConfigurableListWidget is deprecated in datetime_extras:8.x-1.0 and is removed from datetime_extras:8.x-2.0. Use ' . __NAMESPACE__ . '\DateTimeDatelistNoTimeWidget instead. See https://www.drupal.org/node/2973035', E_USER_DEPRECATED);
  }

}
