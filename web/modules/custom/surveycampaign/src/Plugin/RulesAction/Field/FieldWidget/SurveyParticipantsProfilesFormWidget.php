/**
* Plugin implementation of the 'custom_profile_form' widget.
*
* @FieldWidget(
*   id = "survey_partcipants_profiles",
*   label = @Translation("Custom profile form"),
*   field_types = {
*    "text", "entity_reference"
*   }
* )
*/
class SurveyParticipantsProfilesFormWidget extends ProfileFormWidget { 

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    // Override element here
    $element['widget'][0]['entity']['field_survey_first_name']['widget']['#title'] = 'blarg'; 
    return $element;
  }
}