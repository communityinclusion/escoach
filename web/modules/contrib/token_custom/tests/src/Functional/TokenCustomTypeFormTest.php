<?php

namespace Drupal\Tests\token_custom\Functional;

use Drupal\Tests\BrowserTestBase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tests the functionality of the custom token type form.
 *
 * @group token_custom
 */
class TokenCustomTypeFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'token_custom',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->config('system.site')->set('page.front', '/test-page')->save();
    $this->drupalLogin($this->rootUser);
  }

  /**
   * Tests the functionality of the custom token type form.
   */
  public function testCustomTokenTypeForm(): void {
    // Go to the settings page and create a custom token type.
    $this->drupalGet('admin/structure/token-custom/type/add');
    $session = $this->assertSession();
    $session->statusCodeEquals(Response::HTTP_OK);
    $page = $this->getSession()->getPage();
    $page->fillField('name', 'test_token_type');
    $page->fillField('machineName', 'test-token-type-machine-name');
    $page->fillField('description', 'Test Token type description.');
    $page->pressButton('op');
    $session->statusCodeEquals(Response::HTTP_OK);
    // Check on the list page that the token type has been created properly.
    $this->drupalGet('admin/structure/token-custom/type');
    $session->statusCodeEquals(Response::HTTP_OK);
    $session->pageTextContainsOnce('test_token_type');
    $session->pageTextContainsOnce('Test Token type description.');
    // Create a new custom token using the custom token type.
    $this->drupalGet('admin/structure/token-custom/add');
    $session->statusCodeEquals(Response::HTTP_OK);
    $page->selectFieldOption('type', 'test_token_type');
    $page->fillField('name[0][value]', 'test_token');
    $page->fillField('machine_name[0][value]', 'test-token-machine-name');
    $page->fillField('description[0][value]', 'Test Token description.');
    $page->fillField('content[0][value]', 'This is a test token content.');
    $page->pressButton('op');
    $session->statusCodeEquals(Response::HTTP_OK);
    // Check on the list page that the token has been created properly.
    $this->drupalGet('admin/structure/token-custom');
    $session->statusCodeEquals(Response::HTTP_OK);
    $session->pageTextContainsOnce('test_token');
    $session->pageTextContainsOnce('test-token-type-machine-name');
    $session->pageTextContainsOnce('test-token-machine-name');
    $session->pageTextContainsOnce('Test Token description.');
    $session->pageTextContainsOnce('This is a test token content.');
  }

}
