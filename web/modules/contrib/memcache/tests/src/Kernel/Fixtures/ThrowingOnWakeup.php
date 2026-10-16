<?php

namespace Drupal\Tests\memcache\Kernel\Fixtures;

use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

/**
 * An object that cannot be unserialized.
 *
 * This stands in for a cached object belonging to an uninstalled module, which
 * throws while being woken up because a service it depends on is gone.
 */
class ThrowingOnWakeup {

  /**
   * Some payload, so the object is not empty.
   *
   * @var string
   */
  public $value = 'payload';

  /**
   * Throws in the same way an orphaned plugin instance does.
   */
  public function __wakeup() {
    throw new ServiceNotFoundException('layout_builder.tempstore_repository');
  }

}
