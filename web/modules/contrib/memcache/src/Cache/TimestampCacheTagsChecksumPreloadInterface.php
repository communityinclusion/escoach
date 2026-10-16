<?php

namespace Drupal\memcache\Cache;

/**
 * Stand-in for the core cache tag preload interface on cores without it.
 *
 * \Drupal\Core\Cache\CacheTagsChecksumPreloadInterface was added in Drupal
 * 11.1. Declaring it as an implemented interface on older cores is a fatal
 * error, so TimestampCacheTagsChecksum aliases this empty interface to the
 * core name when core does not define it. Nothing in core calls
 * registerCacheTagsForPreload() there, so the alias only keeps the class
 * loadable.
 *
 * @internal
 */
interface TimestampCacheTagsChecksumPreloadInterface {}
