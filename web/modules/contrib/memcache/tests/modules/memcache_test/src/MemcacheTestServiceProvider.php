<?php

namespace Drupal\memcache_test;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;
use Drupal\memcache_test\Driver\StatslessMemcacheDriverFactory;

/**
 * Serves every memcache connection through a "stats"-less proxy double.
 */
class MemcacheTestServiceProvider implements ServiceModifierInterface {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    if ($container->hasDefinition('memcache.factory')) {
      $container->getDefinition('memcache.factory')
        ->setClass(StatslessMemcacheDriverFactory::class);
    }
  }

}
