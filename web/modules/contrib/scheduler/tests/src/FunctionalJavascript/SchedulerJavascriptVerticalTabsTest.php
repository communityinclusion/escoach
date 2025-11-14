<?php

namespace Drupal\Tests\scheduler\FunctionalJavascript;

/**
 * Tests the JavaScript functionality of vertical tabs summary information.
 *
 * @group scheduler_js
 */
class SchedulerJavascriptVerticalTabsTest extends SchedulerJavascriptTestBase {

  /**
   * Test editing a node.
   */
  public function testEditEntitySummary() {
    $this->drupalLogin($this->schedulerUser);
    /** @var \Drupal\Tests\WebAssert $assert */
    $assert = $this->assertSession();

    // Set the node edit form to use a vertical tab for the Scheduler dates.
    $this->nodetype->setThirdPartySetting('scheduler', 'fields_display_mode', 'vertical_tab')
      ->setThirdPartySetting('scheduler', 'expand_fieldset', 'always')->save();

    // Create a node with a scheduled publishing date.
    $node = $this->drupalCreateNode(['type' => $this->type, 'status' => FALSE, 'publish_on' => strtotime('+2 months')]);
    $this->drupalGet($node->toUrl('edit-form'));
    $assert->pageTextContains('Scheduled for publishing');
    $assert->pageTextNotContains('Scheduled for unpublishing');
    $assert->pageTextNotContains('Not scheduled');

    // Create a node with a scheduled unpublishing date.
    $node = $this->drupalCreateNode(['type' => $this->type, 'unpublish_on' => strtotime('+3 months')]);
    $this->drupalGet($node->toUrl('edit-form'));
    $assert->pageTextNotContains('Scheduled for publishing');
    $assert->pageTextContains('Scheduled for unpublishing');
    $assert->pageTextNotContains('Not scheduled');

    // Fill in a publish_on date and check the summary text.
    $page = $this->getSession()->getPage();
    $page->fillField('edit-publish-on-0-value-date', '05/02/' . (date('Y') + 1));
    $page->fillField('edit-publish-on-0-value-time', '06:00:00pm');
    $assert->waitForText('Scheduled for publishing');
    $assert->pageTextContains('Scheduled for publishing');

    // Remove both date values and check that the summary text is correct.
    // Setting the date and time values to '' only actually removes the first
    // component of each of the fields. But this is enough for drupal.behaviors
    // to update the summary correctly.
    $page->fillField('edit-publish-on-0-value-date', '');
    $page->fillField('edit-publish-on-0-value-time', '');
    $page->fillField('edit-unpublish-on-0-value-date', '');
    $page->fillField('edit-unpublish-on-0-value-time', '');
    $assert->waitForText('Not scheduled');
    $assert->pageTextNotContains('Scheduled for publishing');
    $assert->pageTextNotContains('Scheduled for unpublishing');
    $assert->pageTextContains('Not scheduled');
  }

  /**
   * Test configuring an entity type.
   */
  public function testConfigureEntityTypeSummary() {
    /** @var \Drupal\Tests\WebAssert $assert */
    $assert = $this->assertSession();

    $this->drupalLogin($this->adminUser);
    $this->drupalGet($this->nodetype->toUrl('edit-form'));
    $page = $this->getSession()->getPage();
    // Bring focus to the Scheduler vertical tab.
    $page->clickLink('Scheduler');

    // Both options are enabled by default.
    $assert->pageTextContains('Publishing enabled');
    $assert->pageTextContains('Advanced options');
    $assert->pageTextContains('Unpublishing enabled');

    // Turn off the unpublishing enabled checkbox.
    $page->uncheckField('edit-scheduler-unpublish-enable');
    $this->waitForNoText('Unpublishing enabled');
    $assert->pageTextContains('Publishing enabled');
    $assert->pageTextContains('Advanced options');
    $assert->pageTextNotContains('Unpublishing enabled');

    // Turn off the publishing enabled checkbox.
    $page->uncheckField('edit-scheduler-publish-enable');
    $this->waitForNoText('Publishing enabled');
    $assert->pageTextNotContains('Publishing enabled');
    $assert->pageTextNotContains('Advanced options');

    // Turn on the publishing enabled checkbox.
    $page->checkField('edit-scheduler-publish-enable');
    $assert->waitForText('Publishing enabled');
    $assert->pageTextContains('Publishing enabled');
    $assert->pageTextNotContains('Unpublishing enabled');
    $assert->pageTextContains('Advanced options');

    // Turn on the unpublishing enabled checkbox.
    $page->checkField('edit-scheduler-unpublish-enable');
    $assert->waitForText('Unpublishing enabled');
    $assert->pageTextContains('Unpublishing enabled');

  }

}
