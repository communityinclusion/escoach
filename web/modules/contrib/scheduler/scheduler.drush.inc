<?php

/**
 * @file
 * Drush 8 commands for Scheduler.
 *
 * For Drush 9+ the commands are in src/Commands/SchedulerCommands.php
 * This file is retained Scheduler 8.x-1.x for backwards compatibility with Core
 * versions up to 8.4 but is removed in Scheduler 2.x.
 */

/**
 * Implements hook_drush_command().
 */
function scheduler_drush_command() {
  $items = [];

  $items['scheduler-cron'] = [
    'description' => 'Lightweight cron to process scheduler tasks.',
    'core' => ['8+'],
    'aliases' => ['sch-cron'],
    'category' => 'scheduler',
    'options' => [
      'nomsg' => 'to avoid the "cron completed" message being written to the terminal.',
    ],
  ];

  return $items;
}

/**
 * Run lightweight scheduler cron.
 */
function drush_scheduler_cron() {
  \Drupal::service('scheduler.manager')->runLightweightCron();
  $nomsg = drush_get_option('nomsg', NULL);
  $nomsg ? NULL : \Drupal::messenger()->addMessage(t('Scheduler lightweight cron completed'));
}
