<?php

namespace Drupal\Tests\memcache\Kernel;

use Drupal\Core\Cache\CacheTagsChecksumPreloadInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\memcache\Cache\TimestampCacheTagsChecksum;
use Drupal\memcache\Invalidator\MemcacheTimestampInvalidator;
use Drupal\memcache\Invalidator\TimestampInvalidatorInterface;
use Drupal\memcache\MemcacheBackend;

/**
 * Tests cache tag preloading through the backend and the checksum service.
 *
 * @group memcache
 *
 * @see https://www.drupal.org/project/memcache/issues/3592346
 */
class MemcacheCacheTagsPreloadTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'memcache'];

  /**
   * The checksum service under test, wired to a real memcached.
   *
   * @var \Drupal\memcache\Cache\TimestampCacheTagsChecksum
   */
  protected $checksum;

  /**
   * The backend under test, using $checksum as its checksum provider.
   *
   * @var \Drupal\memcache\MemcacheBackend
   */
  protected $backend;

  /**
   * Every set of tags the checksum service asked the invalidator for.
   *
   * One entry per getLastInvalidationTimestamps() call.
   *
   * @var string[][]
   */
  public $lookups = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $host = getenv('MEMCACHED_HOST') ?: '127.0.0.1:11211';
    $this->setSetting('memcache', [
      'servers' => [$host => 'default'],
      'bins' => ['default' => 'default'],
      'key_prefix' => $this->databasePrefix,
    ]);

    $factory = $this->container->get('memcache.factory');
    $invalidator = new MemcacheTimestampInvalidator($factory, 'memcache_tag_timestamps', 0);
    $this->checksum = new TimestampCacheTagsChecksum($this->recordingInvalidator($invalidator));
    $this->backend = new MemcacheBackend('preload_test', $factory->get('preload_test'), $this->checksum, $this->container->get('memcache.timestamp.invalidator.bin'));
    // A freshly built backend treats items written in the same instant as
    // deleted; let that window pass.
    usleep(10000);
  }

  /**
   * Wraps an invalidator so the test can see each batch of tags looked up.
   *
   * @param \Drupal\memcache\Invalidator\TimestampInvalidatorInterface $inner
   *   The real invalidator.
   *
   * @return \Drupal\memcache\Invalidator\TimestampInvalidatorInterface
   *   The recording wrapper.
   */
  protected function recordingInvalidator(TimestampInvalidatorInterface $inner): TimestampInvalidatorInterface {
    $test = $this;
    return new class($inner, $test) implements TimestampInvalidatorInterface {

      public function __construct(
        protected TimestampInvalidatorInterface $inner,
        protected MemcacheCacheTagsPreloadTest $test,
      ) {}

      /**
       * {@inheritdoc}
       */
      public function invalidateTimestamp($tag) {
        return $this->inner->invalidateTimestamp($tag);
      }

      /**
       * {@inheritdoc}
       */
      public function getLastInvalidationTimestamp($tag) {
        return $this->inner->getLastInvalidationTimestamp($tag);
      }

      /**
       * {@inheritdoc}
       */
      public function getLastInvalidationTimestamps(array $tags) {
        $this->test->lookups[] = array_values($tags);
        return $this->inner->getLastInvalidationTimestamps($tags);
      }

      /**
       * {@inheritdoc}
       */
      public function getCurrentTimestamp($offset = 0.0) {
        return $this->inner->getCurrentTimestamp($offset);
      }

    };
  }

  /**
   * Makes sure tags exist in memcache and forgets them again locally.
   *
   * An item written with a tag that memcache has never seen gets checksum 0
   * and only becomes valid once the tag has been invalidated once, so tests
   * create their tags up front to keep that out of the way.
   *
   * @param string[] $tags
   *   The tags to create.
   */
  protected function warmTags(array $tags): void {
    $this->checksum->invalidateTags($tags);
    $this->checksum->reset();
  }

  /**
   * Tests that the checksum service advertises preload support to core.
   */
  public function testImplementsPreloadInterface(): void {
    // Core's CacheTagPreloadSubscriber and DatabaseBackend only preload when
    // this holds. On cores without the interface the module aliases its own,
    // so the check is valid everywhere the module is supported.
    $this->assertInstanceOf(CacheTagsChecksumPreloadInterface::class, $this->checksum);
  }

  /**
   * Tests that getMultiple() fetches the tags of all hits in one lookup.
   */
  public function testGetMultipleBatchesTagLookups(): void {
    $this->warmTags(['tag1', 'tag2', 'tag3', 'tag4', 'tag5']);
    $cids = [];
    for ($i = 1; $i <= 5; $i++) {
      $this->backend->set("item$i", $i, -1, ["tag$i"]);
      $cids[] = "item$i";
    }
    // getMultiple() removes hits from the passed array, so use copies.
    $requested = $cids;
    $this->assertCount(5, $this->backend->getMultiple($requested));

    // Forget everything the checksum service learned while setting the items,
    // as a new request would, and read them back in one go.
    $this->checksum->reset();
    $this->lookups = [];
    $requested = $cids;
    $items = $this->backend->getMultiple($requested);
    $this->assertCount(5, $items);
    foreach ($items as $item) {
      $this->assertTrue($item->valid);
    }

    $this->assertCount(1, $this->lookups, 'All tags of the fetched items were looked up in a single request.');
    // Items carry only their own tags: the per-bin tag that used to support
    // invalidateAll() is no longer added by default.
    $expected = ['tag1', 'tag2', 'tag3', 'tag4', 'tag5'];
    $this->assertEqualsCanonicalizing($expected, $this->lookups[0]);
  }

  /**
   * Tests that setMultiple() fetches the tags of all items in one lookup.
   */
  public function testSetMultipleBatchesTagLookups(): void {
    $items = [];
    for ($i = 1; $i <= 5; $i++) {
      $items["item$i"] = ['data' => $i, 'tags' => ["tag$i"]];
    }
    $this->warmTags(['tag1', 'tag2', 'tag3', 'tag4', 'tag5']);
    $this->lookups = [];

    $this->backend->setMultiple($items);

    $this->assertCount(1, $this->lookups, 'All tags of the written items were looked up in a single request.');
    // Items carry only their own tags: the per-bin tag that used to support
    // invalidateAll() is no longer added by default.
    $expected = ['tag1', 'tag2', 'tag3', 'tag4', 'tag5'];
    $this->assertEqualsCanonicalizing($expected, $this->lookups[0]);

    $cids = array_keys($items);
    $this->assertCount(5, $this->backend->getMultiple($cids));
  }

  /**
   * Tests that preloaded tags do not leak into the checksum of other tags.
   */
  public function testPreloadDoesNotChangeChecksums(): void {
    $this->warmTags(['tag_a']);
    $this->backend->set('item', 'value', -1, ['tag_a']);
    $this->assertNotFalse($this->backend->get('item'));

    // Invalidate an unrelated tag, then register it for preloading before the
    // next lookup. Its newer timestamp must not make item look stale.
    $this->checksum->invalidateTags(['tag_b']);
    $this->checksum->reset();
    $this->checksum->registerCacheTagsForPreload(['tag_b']);
    $this->assertNotFalse($this->backend->get('item'));

    // A registered tag that is invalidated after registration still applies.
    $this->checksum->reset();
    $this->checksum->registerCacheTagsForPreload(['tag_a']);
    $this->checksum->invalidateTags(['tag_a']);
    $this->assertFalse($this->backend->get('item'));
  }

  /**
   * Tests that the invalidation of a preloaded tag is honoured.
   */
  public function testPreloadedTagInvalidation(): void {
    $this->warmTags(['tag_a']);
    $this->backend->set('item', 'value', -1, ['tag_a']);
    $this->assertNotFalse($this->backend->get('item'));

    // Another process invalidates tag_a. This request has not seen the tag
    // yet; preloading it must return the invalidated timestamp.
    $other = new TimestampCacheTagsChecksum(new MemcacheTimestampInvalidator($this->container->get('memcache.factory'), 'memcache_tag_timestamps', 0));
    usleep(10000);
    $other->invalidateTags(['tag_a']);

    $this->checksum->reset();
    $this->checksum->registerCacheTagsForPreload(['tag_a']);
    $this->assertFalse($this->backend->get('item'));
  }

}
