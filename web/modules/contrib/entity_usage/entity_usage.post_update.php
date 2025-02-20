<?php

/**
 * @file
 * Post update functions for the Entity Usage module.
 */

/**
 * Implements hook_removed_post_updates().
 */
function entity_usage_removed_post_updates(): array {
  return [
    'entity_usage_post_update_regenerate_2x' => '8.x-2.0',
  ];
}

/**
 * Clean up entity usage regenerate queue.
 */
function entity_usage_post_update_clean_up_regenerate_queue(array &$sandbox): void {
  $queue = \Drupal::queue('entity_usage_regenerate_queue');
  if ($queue->numberOfItems() > 0) {
    $queue->deleteQueue();
    \Drupal::messenger()->addWarning('There were unprocessed items in the entity_usage_regenerate_queue. Queue processing is no longer an option for the entity-usage:recreate command. Please re-run the command without the --use-queue flag, or visit the UI and trigger the batch update there.');
  }
}
