<?php

namespace Drupal\token_custom\Form;

use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Entity\EntityDeleteFormTrait;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a confirmation form for deleting a custom token type entity.
 */
class TokenCustomTypeDeleteForm extends EntityConfirmFormBase {

  use EntityDeleteFormTrait;

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $tokens = $this->entityTypeManager->getStorage('token_custom')->getQuery()
      ->condition('type', $this->entity->id())
      ->execute();
    if (!empty($tokens)) {
      $caption = '<p>' . $this->formatPlural(count($tokens), '%label is used by 1 custom token on your site. You can not remove this custom token type until you have removed all of the %label custom tokens.', '%label is used by @count custom tokens on your site. You may not remove %label until you have removed all of the %label custom tokens.', [
        '%label' => $this->entity->label(),
      ]) . '</p>';
      $form['description'] = ['#markup' => $caption];
      return $form;
    }
    else {
      return parent::buildForm($form, $form_state);
    }
  }

}
