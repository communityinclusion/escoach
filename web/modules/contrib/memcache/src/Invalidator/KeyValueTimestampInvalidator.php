<?php

namespace Drupal\memcache\Invalidator;

use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;

/**
 * Timestamp invalidator that stores its timestamps in a key-value collection.
 *
 * The default MemcacheTimestampInvalidator keeps the bin deletion timestamps
 * in memcache itself. That is the right place when every web server talks to
 * the same memcached pool, but when each web server runs its own private
 * memcached instance a deleteAll() on one server is invisible to the others.
 *
 * This implementation keeps the timestamps in a key-value collection instead,
 * which by default lives in the database that all web servers share, so a
 * bin deletion on any server is honoured by all of them. It costs one extra
 * key-value read per request for the whole collection.
 *
 * @see \Drupal\memcache\Invalidator\MemcacheTimestampInvalidator
 */
class KeyValueTimestampInvalidator extends TimestampInvalidatorBase {

  /**
   * The key-value store holding the timestamps.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueStoreInterface
   */
  protected KeyValueStoreInterface $keyValueStore;

  /**
   * The key-value collection name.
   *
   * @var string
   */
  protected string $collection;

  /**
   * The timestamps loaded so far, keyed by collection and then by tag.
   *
   * A collection is loaded in full on first access. The cache is static
   * because this service is instantiated more than once per request: once in
   * the bootstrap container that serves cache.container and once in the real
   * container. One read per request per collection is the intent.
   *
   * @var float[][]
   */
  protected static array $timestamps = [];

  /**
   * Constructs a KeyValueTimestampInvalidator.
   *
   * @param \Drupal\Core\KeyValueStore\KeyValueFactoryInterface $key_value_factory
   *   The key-value store factory.
   * @param string $collection
   *   The key-value collection to store the timestamps in.
   * @param float $tolerance
   *   Allowed clock skew between servers, in decimal seconds.
   */
  public function __construct(KeyValueFactoryInterface $key_value_factory, $collection = 'memcache_bin_timestamps', $tolerance = 0.001) {
    parent::__construct($tolerance);
    $this->collection = $collection;
    $this->keyValueStore = $key_value_factory->get($collection);
  }

  /**
   * {@inheritdoc}
   */
  public function invalidateTimestamp($tag) {
    return $this->markAsOutdated($tag);
  }

  /**
   * {@inheritdoc}
   */
  public function getLastInvalidationTimestamp($tag) {
    return $this->loadTimestamps()[$tag] ?? FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getLastInvalidationTimestamps(array $tags) {
    return array_intersect_key($this->loadTimestamps(), array_flip($tags));
  }

  /**
   * {@inheritdoc}
   */
  protected function writeTimestamp($tag, $timestamp) {
    $this->loadTimestamps();
    $this->keyValueStore->set($tag, $timestamp);
    static::$timestamps[$this->collection][$tag] = $timestamp;
    return TRUE;
  }

  /**
   * Returns the timestamps of this collection, reading them on first use.
   *
   * @return float[]
   *   The last invalidation timestamps, keyed by tag.
   */
  protected function loadTimestamps() {
    if (!isset(static::$timestamps[$this->collection])) {
      static::$timestamps[$this->collection] = $this->keyValueStore->getAll();
    }
    return static::$timestamps[$this->collection];
  }

  /**
   * Discards the timestamps read so far, so the next access hits the store.
   *
   * Timestamps written by another process, such as another web server, are
   * only picked up on the next request. Call this to observe them within the
   * same process, which tests need to do.
   */
  public static function resetTimestamps() {
    static::$timestamps = [];
  }

}
