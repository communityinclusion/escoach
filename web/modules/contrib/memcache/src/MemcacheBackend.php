<?php

namespace Drupal\memcache;

use Drupal\Component\Assertion\Inspector;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\CacheTagsChecksumInterface;
use Drupal\Core\DependencyInjection\ContainerNotInitializedException;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Site\Settings;
use Drupal\memcache\Invalidator\TimestampInvalidatorInterface;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

/**
 * Defines a Memcache cache backend.
 */
class MemcacheBackend implements CacheBackendInterface {

  use LoggerChannelTrait;
  use TransactionAwareTrait;

  /**
   * The maximum size of an individual cache chunk.
   *
   * Memcached is about balance. With this area of functionality, we need to
   * minimize the number of split items while also considering wasted memory.
   * In Memcached, all slab "pages" contain 1MB of data, by default.  Therefore,
   * when we split items, we want to do to in a manner that comes close to
   * filling a slab page with as little remaining memory as possible, while
   * taking item overhead into consideration.
   *
   * Our tests concluded that Memached slab 39 is a perfect slab to target.
   * Slab 39 contains items roughly between 385-512KB in size.  We are targeting
   * a chunk size of 493568 bytes (482kb) - which will give us enough storage
   * for two split items, leaving as little overhead as possible.
   *
   * Note that the overhead not only includes metadata about each item, but
   * also allows compression "backfiring" (under some circumstances, compression
   * actually enlarges some data objects instead of shrinking them).   */

  const MAX_CHUNK_SIZE = 470000;

  /**
   * The cache bin to use.
   *
   * @var string
   */
  protected $bin;

  /**
   * The (micro)time the bin was last deleted.
   *
   * @var float
   */
  protected $lastBinDeletionTime;

  /**
   * The memcache wrapper object.
   *
   * @var \Drupal\memcache\DrupalMemcacheInterface
   */
  protected $memcache;

  /**
   * The cache tags checksum provider.
   *
   * @var \Drupal\Core\Cache\CacheTagsChecksumInterface|\Drupal\Core\Cache\CacheTagsInvalidatorInterface
   */
  protected $checksumProvider;

  /**
   * The timestamp invalidation provider.
   *
   * @var \Drupal\memcache\Invalidator\TimestampInvalidatorInterface
   */
  protected $timestampInvalidator;

  /**
   * Cache IDs whose deletion is delayed until the transaction is committed.
   *
   * Keyed by cache ID for fast lookups.
   *
   * @var true[]
   */
  protected $delayedDeletions = [];

  /**
   * Whether a deletion of the whole bin is delayed until commit.
   *
   * @var bool
   */
  protected $delayedBinDeletion = FALSE;

  /**
   * Constructs a MemcacheBackend object.
   *
   * @param string $bin
   *   The bin name.
   * @param \Drupal\memcache\DrupalMemcacheInterface $memcache
   *   The memcache object.
   * @param \Drupal\Core\Cache\CacheTagsChecksumInterface $checksum_provider
   *   The cache tags checksum service.
   * @param \Drupal\memcache\Invalidator\TimestampInvalidatorInterface $timestamp_invalidator
   *   The timestamp invalidation provider.
   */
  public function __construct($bin, DrupalMemcacheInterface $memcache, CacheTagsChecksumInterface $checksum_provider, TimestampInvalidatorInterface $timestamp_invalidator) {
    $this->bin = $bin;
    $this->memcache = $memcache;
    $this->checksumProvider = $checksum_provider;
    $this->timestampInvalidator = $timestamp_invalidator;
    $this->ensureBinDeletionTimeIsSet();
  }

  /**
   * Check to see if debug is on. Wrap it in safety for early bootstraps.
   *
   * @returns bool
   */
  private function debug(): bool {
    try {
      // @phpstan-ignore-next-line
      $debug = \Drupal::service('memcache.settings')->get('debug');
      if ($debug) {
        return $debug;
      }
      return FALSE;
    }
    catch (ServiceNotFoundException $e) {
      return FALSE;
    }
    catch (ContainerNotInitializedException $e) {
      return FALSE;
    }
  }

  /**
   * Returns the timestamp for the current request.
   *
   * Delegates to the core time service so that tests (and anything else that
   * swaps the 'datetime.time' service) see the same request time here as in
   * core's own cache backends. The backend also serves the container cache,
   * which is read before the container exists, so fall back to the raw
   * request time in that case.
   *
   * @return int
   *   A Unix timestamp.
   *
   * @see \Drupal\Component\Datetime\Time::getRequestTime()
   */
  public static function getRequestTime() {
    if (\Drupal::hasContainer() && \Drupal::hasService('datetime.time')) {
      return \Drupal::time()->getRequestTime();
    }
    return (int) ($_SERVER['REQUEST_TIME'] ?? time());
  }

  /**
   * {@inheritdoc}
   */
  public function get($cid, $allow_invalid = FALSE) {
    $cids = [$cid];
    $cache = $this->getMultiple($cids, $allow_invalid);
    return reset($cache);
  }

  /**
   * {@inheritdoc}
   */
  public function getMultiple(&$cids, $allow_invalid = FALSE) {
    $cache = $this->memcache->getMulti($cids);
    $fetched = [];

    // Before checking the validity of each item individually, register the
    // cache tags for all returned cache items for preloading, this allows the
    // cache tag service to optimise cache tag lookups.
    if (method_exists($this->checksumProvider, 'registerCacheTagsForPreload')) {
      $tags_for_preload = [];
      foreach ($cache as $result) {
        if (!is_string($result) && !empty($result->tags)) {
          $tags_for_preload[] = $result->tags;
        }
      }
      if ($tags_for_preload) {
        $this->checksumProvider->registerCacheTagsForPreload(array_merge(...$tags_for_preload));
      }
    }

    foreach ($cache as $result) {
      if (is_string($result)) {
        continue;
      }

      if (!$this->timeIsGreaterThanBinDeletionTime($result->created)) {
        continue;
      }

      // Ignore items that are scheduled for deletion once the current
      // database transaction is committed.
      if (isset($this->delayedDeletions[$result->cid])) {
        continue;
      }

      if ($this->valid($result->cid, $result) || $allow_invalid) {

        // If the item is multipart, rebuild the original cache data by fetching
        // children and combining them back into a single item.
        if ($result->data instanceof MultipartItem) {
          $childCIDs = $result->data->getCids();
          $dataParts = $this->memcache->getMulti($childCIDs);
          if (count($dataParts) !== count($childCIDs)) {
            // We're missing a chunk of the original entry. It is not valid.
            continue;
          }
          $result->data = $this->combineItems($dataParts);
        }

        // Add it to the fetched items to diff later.
        $fetched[$result->cid] = $result;
      }
    }

    // Remove items from the referenced $cids array that we are returning,
    // per comment in Drupal\Core\Cache\CacheBackendInterface::getMultiple().
    $cids = array_diff($cids, array_keys($fetched));

    return $fetched;
  }

  /**
   * Determines if the cache item is valid.
   *
   * This also alters the valid property of the cache item itself.
   *
   * @param string $cid
   *   The cache ID.
   * @param \stdClass $cache
   *   The cache item.
   *
   * @return bool
   *   TRUE if valid, FALSE otherwise.
   */
  protected function valid($cid, \stdClass $cache) {
    $cache->valid = TRUE;

    // Items that have expired are invalid.
    if ($cache->expire != CacheBackendInterface::CACHE_PERMANENT && $cache->expire <= static::getRequestTime()) {
      $cache->valid = FALSE;
    }

    // Check if invalidateTags() has been called with any of the items tags.
    if (!$this->checksumProvider->isValid($cache->checksum, $cache->tags)) {
      $cache->valid = FALSE;
    }

    return $cache->valid;
  }

  /**
   * {@inheritdoc}
   */
  public function set($cid, $data, $expire = CacheBackendInterface::CACHE_PERMANENT, array $tags = []) {
    assert(Inspector::assertAllStrings($tags));

    // The per-bin tag only exists to support the deprecated invalidateAll().
    // Skip it when invalidateAll() is handled by deleteAll() instead, so items
    // do not carry (and have to check) a tag that is never invalidated.
    if (!$this->invalidateAllAsDelete()) {
      $tags[] = "memcache:$this->bin";
    }
    $tags = array_unique($tags);
    // Sort the cache tags so that they are stored consistently.
    sort($tags);

    // Create new cache object.
    $cache = new \stdClass();
    $cache->cid = $cid;
    $cache->data = $data;
    $cache->created = round(microtime(TRUE), 3);
    $cache->expire = $expire;
    $cache->tags = $tags;
    $cache->checksum = $this->checksumProvider->getCurrentChecksum($tags);

    // Do not write cache items whose tags have a pending (delayed) invalidation
    // because of an open database transaction. The checksum provider returns
    // a sentinel value in that case: the item could never be read back as
    // valid, and writing it would needlessly clobber a possibly still valid
    // item that other requests can use. Drupal 12 returns the sentinel as a
    // string, older versions as an integer.
    if ((string) $cache->checksum === (string) CacheTagsChecksumInterface::INVALID_CHECKSUM_WHILE_IN_TRANSACTION) {
      return FALSE;
    }

    // Cache all items permanently. We handle expiration in our own logic.
    if ($this->memcache->set($cid, $cache)) {
      return TRUE;
    }

    // Assume that the item is too large.  We need to split it into multiple
    // chunks with a parent entry referencing all the chunks.
    $childKeys = [];
    foreach ($this->splitItem($cache) as $part) {
      // If a single chunk fails to be set, stop trying - we can't reconstitute
      // a value with a missing chunk.
      if (!$this->memcache->set($part->cid, $part)) {
        return FALSE;
      }
      $childKeys[] = $part->cid;
    }

    // Create and write the parent entry referencing all chunks.
    $cache->data = new MultipartItem($childKeys);
    return $this->memcache->set($cid, $cache);
  }

  /**
   * Given a single cache item, split it into multiple child items.
   *
   * @param \stdClass $item
   *   The original cache item, before the split.
   *
   * @return \stdClass[]
   *   An array of child items.
   */
  private function splitItem(\stdClass $item) {
    $data = serialize($item->data);
    $pieces = str_split($data, static::MAX_CHUNK_SIZE);

    // Add a unique identifier each time this function is invoked.  This
    // prevents a race condition where two sets on the same multipart item can
    // clobber each other's children.  With this seed, each time a multipart
    // entry is created, they get a different CID.  The parent (multipart) entry
    // does not inherit this unique identifier, so it is still addressable using
    // the CID it was initially given.
    $seed = Crypt::randomBytesBase64();

    $children = [];

    foreach ($pieces as $i => $chunk) {
      // Child items do not need tags or expire, since that data is carried by
      // the parent.
      $chunkItem = new \stdClass();
      // @todo mention why we added split and picked this order...
      $chunkItem->cid = sprintf('split.%d.%s.%s', $i, $item->cid, $seed);
      $chunkItem->data = $chunk;
      $chunkItem->created = $item->created;
      $children[] = $chunkItem;
    }

    if ($this->debug()) {
      $this->getLogger('memcache')->debug(
        'Split item @cid into @num pieces',
        ['@cid' => $item->cid, '@num' => ($i + 1)]
      );
    }

    return $children;
  }

  /**
   * Given an array of child cache items, recombine into a single value.
   *
   * @param \stdClass[] $items
   *   An array of child cache items.
   *
   * @return mixed
   *   The combined an unserialized value that was originally stored.
   */
  private function combineItems(array $items) {
    // phpcs:disable
    return unserialize(implode(array_column($items, 'data')));
    // phpcs:enable
  }

  /**
   * {@inheritdoc}
   */
  public function setMultiple(array $items) {
    // Register the tags of every item for preloading so that the first set()
    // fetches all of their timestamps in one request instead of one per item.
    if (method_exists($this->checksumProvider, 'registerCacheTagsForPreload')) {
      $tags_for_preload = [];
      foreach ($items as $item) {
        if (!empty($item['tags'])) {
          assert(Inspector::assertAllStrings($item['tags']));
          $tags_for_preload[] = $item['tags'];
        }
      }
      if ($tags_for_preload) {
        $this->checksumProvider->registerCacheTagsForPreload(array_merge(...$tags_for_preload));
      }
    }

    foreach ($items as $cid => $item) {
      $item += [
        'expire' => CacheBackendInterface::CACHE_PERMANENT,
        'tags' => [],
      ];

      $this->set($cid, $item['data'], $item['expire'], $item['tags']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function delete($cid) {
    $this->deleteMultiple([$cid]);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteMultiple(array $cids) {
    if (!$cids) {
      return;
    }
    if ($this->inDatabaseTransaction()) {
      // Defer the deletion until the transaction is committed. Meanwhile the
      // items are hidden from this request in getMultiple(), while other
      // requests keep seeing the (still committed) data.
      $this->addPostTransactionCallback([$this, 'postRootTransactionCommit']);
      $this->delayedDeletions += array_fill_keys($cids, TRUE);
      return;
    }
    $this->doDeleteMultiple($cids);
  }

  /**
   * Deletes items from memcache.
   *
   * @param string[] $cids
   *   The cache IDs to delete.
   */
  protected function doDeleteMultiple(array $cids) {
    foreach ($cids as $cid) {
      $this->memcache->delete($cid);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAll() {
    if ($this->debug()) {
      $this->getLogger('memcache')->debug(
        'Called deleteAll() on bin @bin',
        ['@bin' => $this->bin]
      );
    }

    $this->invalidateBin();
  }

  /**
   * Marks all items in the bin as deleted.
   *
   * Outside of a database transaction the bin deletion timestamp is written to
   * memcache immediately. Inside a transaction only the local timestamp is
   * updated, so that this request no longer sees the items, while the shared
   * timestamp is written once the transaction is committed. Other requests
   * would otherwise repopulate the bin from not yet committed data.
   */
  protected function invalidateBin() {
    if ($this->inDatabaseTransaction()) {
      $this->addPostTransactionCallback([$this, 'postRootTransactionCommit']);
      $this->delayedBinDeletion = TRUE;
      $this->lastBinDeletionTime = $this->timestampInvalidator->getCurrentTimestamp();
      return;
    }
    $this->lastBinDeletionTime = $this->timestampInvalidator->invalidateTimestamp($this->bin);
  }

  /**
   * Callback invoked once the root database transaction has ended.
   *
   * Performs the delayed deletions if the transaction was committed and
   * discards them otherwise.
   *
   * This runs from \Drupal\Core\Database\Transaction::__destruct(), so it must
   * only issue plain memcache commands; in particular it must never serialize
   * cache data, which could trigger arbitrary __sleep() implementations.
   *
   * @param bool $success
   *   Whether the transaction was committed.
   *
   * @internal
   */
  public function postRootTransactionCommit($success) {
    $this->postTransactionCallbackRegistered = FALSE;
    $cids = array_keys($this->delayedDeletions);
    $this->delayedDeletions = [];
    $bin_deletion = $this->delayedBinDeletion;
    $this->delayedBinDeletion = FALSE;

    if ($success) {
      if ($bin_deletion) {
        $this->lastBinDeletionTime = $this->timestampInvalidator->invalidateTimestamp($this->bin);
      }
      $this->doDeleteMultiple($cids);
    }
    elseif ($bin_deletion) {
      // Fall back to the shared timestamp again.
      $this->lastBinDeletionTime = NULL;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function invalidate($cid) {
    $this->invalidateMultiple([$cid]);
  }

  /**
   * Marks cache items as invalid.
   *
   * Invalid items may be returned in later calls to get(), if the
   * $allow_invalid argument is TRUE.
   *
   * @param array $cids
   *   An array of cache IDs to invalidate.
   *
   * @see Drupal\Core\Cache\CacheBackendInterface::deleteMultiple()
   * @see Drupal\Core\Cache\CacheBackendInterface::invalidate()
   * @see Drupal\Core\Cache\CacheBackendInterface::invalidateTags()
   * @see Drupal\Core\Cache\CacheBackendInterface::invalidateAll()
   */
  public function invalidateMultiple(array $cids) {
    // Unlike deletions, invalidations are not delayed until the end of an open
    // database transaction: re-writing the item requires serializing its data
    // again, which is not safe from a post-transaction callback (it runs in a
    // destructor, where arbitrary __sleep() implementations may e.g. start a
    // Fiber). Core does not use invalidate() for entity or render caches, and
    // cache tag invalidation, which those rely on, is delayed.
    foreach ($cids as $cid) {
      if ($item = $this->get($cid)) {
        $item->expire = static::getRequestTime() - 1;
        $this->memcache->set($cid, $item);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function invalidateAll() {
    @trigger_error("CacheBackendInterface::invalidateAll() is deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Use CacheBackendInterface::deleteAll() or cache tag invalidation instead. See https://www.drupal.org/node/3500622", E_USER_DEPRECATED);
    if ($this->debug()) {
      $this->getLogger('memcache')->debug(
        'Called invalidateAll() on bin @bin',
        ['@bin' => $this->bin]
      );
    }

    if ($this->invalidateAllAsDelete()) {
      // Core deprecated invalidateAll() in favour of deleteAll(); treating it
      // as a bin deletion means every item is one tag lighter and one check
      // cheaper on read.
      $this->deleteAll();
    }
    else {
      $this->invalidateTags(["memcache:$this->bin"]);
    }
  }

  /**
   * Whether invalidateAll() is handled as deleteAll().
   *
   * Read from settings rather than the memcache.settings service because
   * this backend also serves the cache.container bin before the container
   * exists.
   *
   * @return bool
   *   TRUE (the default) to delete the bin, FALSE to keep the legacy per-bin
   *   tag invalidation.
   */
  protected function invalidateAllAsDelete(): bool {
    return (bool) (Settings::get('memcache', [])['invalidate_all_as_delete'] ?? TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function invalidateTags(array $tags) {
    if ($this->debug()) {
      $this->getLogger('memcache')->debug(
        'Called invalidateTags() on tags @tags',
        ['@tags' => implode(',', $tags)]
      );
    }

    $this->checksumProvider->invalidateTags($tags);
  }

  /**
   * {@inheritdoc}
   */
  public function removeBin() {
    if ($this->debug()) {
      $this->getLogger('memcache')->debug(
        'Called removeBin() on bin @bin',
        ['@bin' => $this->bin]
      );
    }

    $this->invalidateBin();
  }

  /**
   * {@inheritdoc}
   */
  public function garbageCollection() {
    // Memcache will invalidate items; That items memory allocation is then
    // freed up and reused. So nothing needs to be deleted/cleaned up here.
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    // We do not know so err on the safe side? Not sure if we can know this?
    return TRUE;
  }

  /**
   * Determines if a (micro)time is greater than the last bin deletion time.
   *
   * @param float $item_microtime
   *   A given (micro)time.
   *
   * @internal
   *
   * @return bool
   *   TRUE if the (micro)time is greater than the last bin deletion time, FALSE
   *   otherwise.
   */
  protected function timeIsGreaterThanBinDeletionTime($item_microtime) {
    $last_bin_deletion = $this->getBinLastDeletionTime();

    // If there is time, assume FALSE as there is no previous deletion time
    // to compare with.
    if (!$last_bin_deletion) {
      return FALSE;
    }

    return $item_microtime > $last_bin_deletion;
  }

  /**
   * Gets the last invalidation time for the bin.
   *
   * @internal
   *
   * @return float
   *   The last invalidation timestamp of the tag.
   */
  protected function getBinLastDeletionTime() {
    if (!isset($this->lastBinDeletionTime)) {
      $this->lastBinDeletionTime = $this->timestampInvalidator->getLastInvalidationTimestamp($this->bin);
    }

    return $this->lastBinDeletionTime;
  }

  /**
   * Ensures a last bin deletion time has been set.
   *
   * @internal
   */
  protected function ensureBinDeletionTimeIsSet() {
    if (!$this->getBinLastDeletionTime()) {
      $this->lastBinDeletionTime = $this->timestampInvalidator->invalidateTimestamp($this->bin);
    }
  }

}
