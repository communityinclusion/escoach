<?php

namespace Drupal\Tests\memcache\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\memcache\Cache\TimestampCacheTagsChecksum;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Tests the MemcacheBackend with the module's own cache tags checksum service.
 *
 * This is the setup documented in example.services.yml, where cache tag
 * invalidations are tracked in memcache instead of the database.
 *
 * @group memcache
 */
#[Group('memcache')]
#[RunTestsInSeparateProcesses]
class MemcacheBackendTimestampChecksumTest extends MemcacheBackendTest {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    parent::alter($container);

    // Mirror example.services.yml: track tag invalidations in memcache. Use a
    // tolerance of 0 so that tests do not have to wait for the clock skew, and
    // an invalidator that guarantees a distinct timestamp per operation.
    $container->register('memcache.timestamp.invalidator.tag', MonotonicTimestampInvalidator::class)
      ->setArguments([new Reference('memcache.factory'), 'memcache_tag_timestamps', 0]);

    $container->getDefinition('cache_tags.invalidator.checksum')
      ->setClass(TimestampCacheTagsChecksum::class)
      ->setArguments([new Reference('memcache.timestamp.invalidator.tag')]);
  }

}
