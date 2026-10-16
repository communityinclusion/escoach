<?php

namespace Drupal\Tests\memcache\Kernel;

use Drupal\Core\Site\Settings;
use Drupal\KernelTests\KernelTestBase;
use Drupal\memcache\Cache\TimestampCacheTagsChecksum;
use Drupal\memcache\Driver\MemcacheDriverFactory;
use Drupal\memcache\Invalidator\MemcacheTimestampInvalidator;
use Drupal\memcache\MemcacheSettings;

/**
 * Tests that an invalidation always moves the stored timestamp forward.
 *
 * The timestamps are shared between every web server through memcache, but
 * each web server stamps them from its own clock. A server whose clock runs
 * ahead therefore writes timestamps that are in the future as far as the other
 * servers are concerned, and an invalidation raised on one of those other
 * servers must still take effect.
 *
 * @group memcache
 *
 * @see \Drupal\memcache\Invalidator\TimestampInvalidatorBase::markAsOutdated()
 */
class MemcacheTimestampInvalidatorTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'memcache'];

  /**
   * The invalidator under test.
   *
   * @var \Drupal\memcache\Invalidator\MemcacheTimestampInvalidator
   */
  protected $invalidator;

  /**
   * The driver backing the invalidator, used to plant and read timestamps.
   *
   * @var \Drupal\memcache\DrupalMemcacheInterface
   */
  protected $driver;

  /**
   * The tolerance the invalidator is built with.
   */
  const TOLERANCE = 0.001;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->setSetting('memcache', [
      'servers' => [getenv('MEMCACHED_HOST') ?: '127.0.0.1:11211' => 'default'],
      'bins' => ['default' => 'default'],
      'key_prefix' => $this->databasePrefix,
    ]);

    $factory = new MemcacheDriverFactory(new MemcacheSettings(Settings::getInstance()));
    $this->invalidator = new MemcacheTimestampInvalidator($factory, 'timestamp_test', self::TOLERANCE);
    $this->driver = $factory->get('timestamp_test');
  }

  /**
   * Returns a tag name unique to the calling test.
   *
   * @return string
   *   The tag.
   */
  protected function tag() {
    return 'node:1:' . uniqid('', TRUE);
  }

  /**
   * Tests that invalidating a tag nobody has touched records a timestamp.
   */
  public function testFirstInvalidationIsRecorded() {
    $tag = $this->tag();
    $before = $this->invalidator->getCurrentTimestamp();

    $returned = $this->invalidator->invalidateTimestamp($tag);

    $this->assertGreaterThanOrEqual($before, $returned);
    $this->assertSame($returned, $this->invalidator->getLastInvalidationTimestamp($tag));
  }

  /**
   * Tests that a second invalidation moves the timestamp on again.
   */
  public function testRepeatedInvalidationAdvances() {
    $tag = $this->tag();

    $first = $this->invalidator->invalidateTimestamp($tag);
    $second = $this->invalidator->invalidateTimestamp($tag);

    $this->assertGreaterThan($first, $second);
    $this->assertSame($second, $this->invalidator->getLastInvalidationTimestamp($tag));
  }

  /**
   * Tests that a timestamp written by a faster server is still advanced.
   *
   * Without this, the invalidation is silently discarded and the items the
   * faster server cached stay valid.
   */
  public function testInvalidationIsNotDroppedByFasterServer() {
    $tag = $this->tag();

    // Another web server, five seconds ahead of this one, invalidated the tag.
    $future = $this->invalidator->getCurrentTimestamp(5.0);
    $this->driver->set($tag, $future);

    $returned = $this->invalidator->invalidateTimestamp($tag);

    $this->assertGreaterThan($future, $returned, 'The invalidation moved the timestamp past the future one.');
    $this->assertSame($returned, $this->invalidator->getLastInvalidationTimestamp($tag));
  }

  /**
   * Tests that an invalidation never moves a timestamp backwards.
   */
  public function testInvalidationNeverMovesBackwards() {
    $tag = $this->tag();

    $future = $this->invalidator->getCurrentTimestamp(5.0);
    $this->driver->set($tag, $future);

    $this->assertGreaterThan($future, $this->invalidator->invalidateTimestamp($tag));
  }

  /**
   * Tests that an item cached by a faster server is invalidated.
   *
   * This is the symptom the timestamp behaviour produces once it reaches the
   * checksum layer: an edit on one web server leaves content cached by another
   * web server in place, so the site serves old and new content depending on
   * which server answers.
   */
  public function testItemCachedByFasterServerIsInvalidated() {
    $tag = $this->tag();

    // The faster server invalidated the tag, then cached an item. Stamping
    // both from its own clock leaves the item carrying the tag's timestamp as
    // its checksum, which is what makes the item valid there.
    $future = $this->invalidator->getCurrentTimestamp(5.0);
    $this->driver->set($tag, $future);
    $checksum = $future;

    $checksum_provider = new TimestampCacheTagsChecksum($this->invalidator);
    $this->assertTrue($checksum_provider->isValid($checksum, [$tag]), 'The item starts out valid.');

    // An edit on this server, whose clock is behind, invalidates the tag.
    $checksum_provider->reset();
    $checksum_provider->invalidateTags([$tag]);

    $checksum_provider->reset();
    $this->assertFalse($checksum_provider->isValid($checksum, [$tag]), 'The item cached by the faster server is no longer valid.');
  }

}
