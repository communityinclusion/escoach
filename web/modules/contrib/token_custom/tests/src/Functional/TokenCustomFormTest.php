<?php

namespace Drupal\Tests\token_custom\Functional;

use Drupal\Tests\BrowserTestBase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tests the functionality of the custom token form.
 *
 * @group token_custom
 */
class TokenCustomFormTest extends BrowserTestBase {

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
   * Tests the functionality of the custom token form.
   */
  public function testCustomTokenForm(): void {
    // Go to the settings page and create a custom token.
    $this->drupalGet('admin/structure/token-custom/add');
    $session = $this->assertSession();
    $session->statusCodeEquals(Response::HTTP_OK);
    $page = $this->getSession()->getPage();
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
    $session->pageTextContainsOnce('test-token-machine-name');
    $session->pageTextContainsOnce('Test Token description.');
    $session->pageTextContainsOnce('This is a test token content.');
  }

}
