<?php

namespace Drupal\Tests\es_userform\FunctionalJavascript;

use Drupal\Core\Url;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Tests the behavior of the submit-driven AJAX example.
 *
 * @group es_userform
 */
class SubmitDrivenTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['es_userform'];

  /**
   * Test the behavior of the submit-driven AJAX example.
   *
   * Behaviors to test:
   * - GET the route es_userform.submit_driven_ajax.
   * - Examine the DOM to make sure our change hasn't happened yet.
   * - Submit the form.
   * - Wait for the AJAX request to complete.
   * - Examine the DOM to see if our expected change happened.
   */
  public function testSubmitDriven() {
    // Get the session assertion object.
    $assert = $this->assertSession();
    // Get the page.
    $this->drupalGet(Url::fromRoute('es_userform.submit_driven_ajax'));
    // Examine the DOM to make sure our change hasn't happened yet.
    $assert->pageTextNotContains('Clicked submit (Submit):');
    // Submit the form.
    $this->submitForm([], 'Submit');
    // Wait on the AJAX request.
    $assert->assertWaitOnAjaxRequest();
    // Compare DOM to our expectations.
    $assert->pageTextContains('Clicked submit (Submit):');
  }

}
