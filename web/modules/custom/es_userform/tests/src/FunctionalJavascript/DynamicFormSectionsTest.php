<?php

namespace Drupal\Tests\es_userform\FunctionalJavascript;

use Drupal\Core\Url;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Functional test of dependent dropdown example.
 *
 * @group es_userform
 *
 * @see Drupal\Tests\es_userform\Functional\DynamicFormSectionsTest
 */
class DynamicFormSectionsTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['es_userform'];

  /**
   * Test the dependent dropdown form with AJAX.
   */
  public function testDynamicFormSections() {
    // Get the Mink stuff.
    $assert = $this->assertSession();
    $page = $this->getSession()->getPage();

    // Get a URL object for the form, specifying no JS.
    $dropdown_url = Url::fromRoute('es_userform.dynamic_form_sections', ['nojs' => 'ajax']);

    // Get the form.
    $this->drupalGet($dropdown_url);
    // Check for the initial state.
    $this->assertEmpty($page->findAll('css', 'div.details-wrapper *'));

    // Cycle through the other dropdown values.
    $question_styles = [
      'Multiple Choice',
      'True/False',
      'Fill-in-the-blanks',
    ];

    // Check expectations against the details wrapper.
    $question_type_dropdown = $page->findField('question_type_select');
    foreach ($question_styles as $question_style) {
      $question_type_dropdown->setValue($question_style);
      $assert->assertWaitOnAjaxRequest();
      $this->assertNotEmpty($page->findAll('css', 'div.details-wrapper *'));
    }
    // Prompt to choose question should remove the question.
    $question_type_dropdown->setValue('Choose question style');
    $assert->assertWaitOnAjaxRequest();
    $this->assertEmpty($page->findAll('css', 'div.details-wrapper *'));

    // Submit the correct answers.
    foreach ($question_styles as $question_style) {
      $this->drupalGet($dropdown_url);
      $question_type_dropdown->setValue($question_style);
      $assert->assertWaitOnAjaxRequest();
      $this->drupalPostForm(NULL, ['question' => 'George Washington'], 'Submit your answer');
      $assert->pageTextContains('You got the right answer: George Washington');
    }
  }

}
