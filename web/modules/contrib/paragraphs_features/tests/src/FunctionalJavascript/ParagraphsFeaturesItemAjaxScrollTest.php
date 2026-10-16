<?php

namespace Drupal\Tests\paragraphs_features\FunctionalJavascript;

use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * Tests ScrollToElementCommand dispatch on paragraph edit/remove AJAX.
 *
 * @group paragraphs_features
 */
class ParagraphsFeaturesItemAjaxScrollTest extends ParagraphsFeaturesJavascriptTestBase {

  /**
   * Edit and Remove dispatch ScrollToElementCommand for the affected row.
   */
  public function testItemAjaxDispatchesScrollToElement() {
    $content_type = 'test_item_ajax_scroll';
    $this->createTestConfiguration($content_type, 1);

    // Trim the dropdown actions to just Remove and promote it to a visible
    // button via paragraphs_features' single-option behavior.
    $this->config('core.entity_form_display.node.' . $content_type . '.default')
      ->set('content.field_paragraphs.settings.features.duplicate', 0)
      ->set('content.field_paragraphs.settings.features.convert', 0)
      ->save();
    $this->config('paragraphs_features.settings')
      ->set('dropdown_to_button', TRUE)
      ->save();

    // Create a node with three paragraphs so deltas 0, 1, 2 render on edit.
    $paragraphs = [];
    for ($i = 0; $i < 3; $i++) {
      $paragraph = Paragraph::create(['type' => 'test_1']);
      $paragraph->save();
      $paragraphs[] = $paragraph;
    }
    $node = Node::create([
      'type' => $content_type,
      'title' => 'Test',
      'field_paragraphs' => $paragraphs,
    ]);
    $node->save();

    $this->drupalGet($node->toUrl('edit-form'));

    // Instrument the client-side command to capture dispatches.
    $this->getSession()->executeScript($this->instrumentScript());

    // Collapse the middle paragraph — target should be the same delta.
    $this->scrollClick('xpath', '//input[@data-drupal-selector="field-paragraphs-1-collapse"]');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $calls = json_decode($this->getSession()->evaluateScript('JSON.stringify(window.__scrollToElementCalls)'), TRUE) ?: [];
    $this->assertNotEmpty($calls, 'ScrollToElementCommand dispatched after Collapse.');
    $this->assertStringContainsString('field-paragraphs-1', end($calls)['drupalElementSelector']);

    // Reset the capture, then remove the last paragraph. With sparse
    // deltas the clicked delta is gone, so the fallback picks the last
    // surviving row.
    $this->getSession()->executeScript($this->instrumentScript());
    $this->getSession()->getPage()->pressButton('field_paragraphs_2_remove');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $calls = json_decode($this->getSession()->evaluateScript('JSON.stringify(window.__scrollToElementCalls)'), TRUE) ?: [];
    $this->assertNotEmpty($calls, 'ScrollToElementCommand dispatched after Remove.');
    $this->assertMatchesRegularExpression('/field-paragraphs-\d+/', end($calls)['drupalElementSelector']);
  }

  /**
   * JS snippet that captures scrollToElement command dispatches.
   */
  protected function instrumentScript(): string {
    return <<<'JS'
      window.__scrollToElementCalls = [];
      const originalScrollTo = Drupal.AjaxCommands.prototype.scrollToElement;
      Drupal.AjaxCommands.prototype.scrollToElement = function (ajax, response, status) {
        window.__scrollToElementCalls.push({
          drupalElementSelector: response.drupalElementSelector,
          drupalParentSelector: response.drupalParentSelector,
        });
        return originalScrollTo.call(this, ajax, response, status);
      };
    JS;
  }

}
