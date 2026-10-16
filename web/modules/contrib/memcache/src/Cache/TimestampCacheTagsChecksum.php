<?php

namespace Drupal\memcache\Cache;

use Drupal\Core\Cache\CacheTagsChecksumInterface;
use Drupal\Core\Cache\CacheTagsChecksumPreloadInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\memcache\Invalidator\TimestampInvalidatorInterface;
use Drupal\memcache\TransactionAwareTrait;

// Core added CacheTagsChecksumPreloadInterface in 11.1; on older cores alias
// the module's empty stand-in so the class below can still be declared.
if (!interface_exists(CacheTagsChecksumPreloadInterface::class)) {
  // @phpstan-ignore-next-line
  class_alias(TimestampCacheTagsChecksumPreloadInterface::class, CacheTagsChecksumPreloadInterface::class);
}

/**
 * Cache tags invalidations checksum implementation by timestamp invalidation.
 *
 * Like \Drupal\Core\Cache\CacheTagsChecksumTrait, tag invalidations that
 * happen while a database transaction is open are delayed until the
 * transaction is committed, so that concurrent requests cannot repopulate the
 * cache with data that has not been committed yet.
 *
 * Implements \Drupal\Core\Cache\CacheTagsChecksumPreloadInterface so that
 * cache backends and the core cache tag preload subscriber can register tags
 * ahead of time; the timestamps of registered tags are then fetched in the
 * same getMulti() as the next checksum lookup instead of one request per item.
 */
class TimestampCacheTagsChecksum implements CacheTagsChecksumInterface, CacheTagsInvalidatorInterface, CacheTagsChecksumPreloadInterface {

  use TransactionAwareTrait;

  /**
   * The timestamp invalidator object.
   *
   * @var \Drupal\memcache\Invalidator\TimestampInvalidatorInterface
   */
  protected $invalidator;

  /**
   * Contains already loaded cache invalidations from the backend.
   *
   * @var array
   */
  protected $tagCache = [];

  /**
   * A list of tags that have already been invalidated in this request.
   *
   * Used to prevent the invalidation of the same cache tag multiple times.
   *
   * @var array
   */
  protected $invalidatedTags = [];

  /**
   * Tags whose invalidation is delayed until the transaction is committed.
   *
   * @var string[]
   */
  protected $delayedTags = [];

  /**
   * Registered cache tags to preload.
   *
   * @var string[]
   */
  protected array $preloadTags = [];

  /**
   * Constructs a TimestampCacheTagsChecksum object.
   *
   * @param \Drupal\memcache\Invalidator\TimestampInvalidatorInterface $invalidator
   *   The timestamp invalidator object.
   */
  public function __construct(TimestampInvalidatorInterface $invalidator) {
    $this->invalidator = $invalidator;
  }

  /**
   * {@inheritdoc}
   */
  public function invalidateTags(array $tags) {
    foreach ($tags as $key => $tag) {
      // @todo Revisit this behavior and determine a better way to handle.
      // Only invalidate tags once per request unless they are written again.
      if (isset($this->invalidatedTags[$tag])) {
        unset($tags[$key]);
      }
      else {
        $this->invalidatedTags[$tag] = TRUE;
        unset($this->tagCache[$tag]);
      }
    }
    if (!$tags) {
      return;
    }

    if ($this->inDatabaseTransaction()) {
      $this->addPostTransactionCallback([$this, 'rootTransactionEndCallback']);
      $this->delayedTags = array_values(array_unique(array_merge($this->delayedTags, $tags)));
    }
    else {
      $this->doInvalidateTags($tags);
    }
  }

  /**
   * Writes new invalidation timestamps for the given tags.
   *
   * @param string[] $tags
   *   The cache tags to invalidate.
   */
  protected function doInvalidateTags(array $tags) {
    foreach ($tags as $tag) {
      $this->tagCache[$tag] = $this->invalidator->invalidateTimestamp($tag);
    }
  }

  /**
   * Callback invoked once the root database transaction has ended.
   *
   * Writes the delayed tag invalidations if the transaction was committed and
   * discards them otherwise.
   *
   * @param bool $success
   *   TRUE if the transaction was committed, FALSE if it was rolled back.
   *
   * @internal
   */
  public function rootTransactionEndCallback($success) {
    $this->postTransactionCallbackRegistered = FALSE;
    $tags = $this->delayedTags;
    $this->delayedTags = [];
    if ($success) {
      $this->doInvalidateTags($tags);
    }
    else {
      // The invalidation never happened, so allow the tags to be invalidated
      // again later in this request.
      foreach ($tags as $tag) {
        unset($this->invalidatedTags[$tag]);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCurrentChecksum(array $tags) {
    // Any cache writes in this request containing cache tags whose invalidation
    // has been delayed due to an open transaction must not be read by any
    // other request, so use a sentinel checksum that can never be valid.
    if ($this->delayedTags && array_intersect($tags, $this->delayedTags)) {
      return CacheTagsChecksumInterface::INVALID_CHECKSUM_WHILE_IN_TRANSACTION;
    }

    // @todo Revisit the invalidatedTags hack.
    // Remove tags that were already invalidated during this request from the
    // static caches so that another invalidation can occur later in the same
    // request. Without that, written cache items would not be invalidated
    // correctly.
    foreach ($tags as $tag) {
      unset($this->invalidatedTags[$tag]);
    }
    // Taking the minimum of the current timestamp and the checksum is used to
    // ensure that items that are not valid yet are identified properly as not
    // valid. The checksum will change continuously until the item is valid,
    // at which point the checksum will match and freeze at that value.
    // Calculate the checksum first: it may create timestamps for tags that
    // are not tracked yet, which must not be newer than the current timestamp.
    $checksum = $this->calculateChecksum($tags);
    return min($this->invalidator->getCurrentTimestamp(), $checksum);
  }

  /**
   * {@inheritdoc}
   */
  public function isValid($checksum, array $tags) {
    // Cache reads involving tags whose invalidation has been delayed must be
    // treated as stale until the transaction is committed.
    if ($this->delayedTags && array_intersect($tags, $this->delayedTags)) {
      return FALSE;
    }

    if (empty($tags)) {
      // If there weren't any tags, the checksum should always be 0 or FALSE.
      return $checksum == 0;
    }
    return $checksum == $this->calculateChecksum($tags);
  }

  /**
   * Calculates the current checksum for a given set of tags.
   *
   * @param array $tags
   *   The array of tags to calculate the checksum for.
   *
   * @return int
   *   The calculated checksum.
   */
  protected function calculateChecksum(array $tags) {

    // If there are registered preload tags, merge them into the lookup set so
    // the backend fetches them all in one batch and populates the static cache.
    // This needs to make sure that it only returns the requested cache tags, so
    // store the combination of requested and preload cache tags separately.
    $tags_with_preload = $tags;
    if ($this->preloadTags) {
      $tags_with_preload = array_unique(array_merge($tags, $this->preloadTags));
      $this->preloadTags = [];
    }

    $query_tags = array_diff($tags_with_preload, array_keys($this->tagCache));
    if ($query_tags) {
      $backend_tags = $this->invalidator->getLastInvalidationTimestamps($query_tags);
      $this->tagCache += $backend_tags;
      $invalid = array_diff($query_tags, array_keys($backend_tags));
      if (!empty($invalid)) {
        // Invalidate any missing tags now. This is necessary because we cannot
        // zero-optimize our tag list -- we can't tell the difference between
        // a tag that has never been invalidated and a tag that was
        // garbage-collected by the backend!
        //
        // This behavioral difference is the main change that allows us to use
        // an unreliable backend to track cache tag invalidation.
        //
        // Invalidating the tag will cause it to start being tracked, so it can
        // be matched against the checksums stored on items.
        // All items cached after that point with the tag will end up with
        // a valid checksum, and all items cached before that point with the tag
        // will have an invalid checksum, because missing invalidations will
        // keep moving forward in time as they get garbage collected and are
        // re-invalidated.
        //
        // The main effect of all this is that a tag going missing
        // will automatically cause the cache items tagged with it to no longer
        // have the correct checksum.
        foreach ($invalid as $invalid_tag) {
          $this->tagCache[$invalid_tag] = $this->invalidator->invalidateTimestamp($invalid_tag);
        }
      }
    }

    // The checksum is equal to the *most recent* invalidation of an applicable
    // tag. If the item is untagged, the checksum is always 0.
    return max([0] + array_intersect_key($this->tagCache, array_flip($tags)));
  }

  /**
   * {@inheritdoc}
   */
  public function registerCacheTagsForPreload(array $cache_tags): void {
    if (empty($cache_tags)) {
      return;
    }
    // A tag with an invalidation pending commit is reported invalid without
    // looking at its timestamp, so there is nothing to preload for it; fetching
    // it would only put the pre-invalidation timestamp into the static cache.
    if ($this->delayedTags) {
      $cache_tags = array_values(array_diff($cache_tags, $this->delayedTags));
    }
    if ($cache_tags) {
      $this->preloadTags = array_merge($this->preloadTags, $cache_tags);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function reset() {
    $this->tagCache = [];
    $this->invalidatedTags = [];
    $this->delayedTags = [];
    $this->preloadTags = [];
  }

}
