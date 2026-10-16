<?php

namespace Drupal\memcache\Driver;

use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\memcache\Connection\MemcachedConnection;
use Drupal\memcache\DrupalMemcacheInterface;
use Drupal\memcache\Logger\ErrorLogLogger;
use Drupal\memcache\MemcacheSettings;

/**
 * Defines driver base abstract class.
 */
abstract class DriverBase implements DrupalMemcacheInterface {

  use LoggerChannelTrait {
    getLogger as traitGetLogger;
  }

  /**
   * The memcache config object.
   *
   * @var \Drupal\memcache\MemcacheSettings
   */
  protected $settings;

  /**
   * The memcache object.
   *
   * @var \Memcache|\Memcached
   *   E.g. \Memcache|\Memcached
   */
  protected $memcache;

  /**
   * The hash algorithm to pass to hash(). Defaults to 'sha1'.
   *
   * @var string
   */
  protected $hashAlgorithm;

  /**
   * The prefix memcache key for all keys.
   *
   * @var string
   */
  protected $prefix;

  /**
   * Stats for the entire request.
   *
   * No longer written to by the drivers: statistics are collected by
   * \Drupal\memcache\Driver\StatisticsDriverDecorator into the
   * memcache.request_statistics service. Kept for subclasses that still call
   * statsWrite().
   *
   * @var array
   */
  protected static $stats = [
    'all' => [],
    'ops' => [],
  ];

  /**
   * Whether a failed memcache operation has been logged during this request.
   *
   * @var bool
   */
  protected static $failureLogged = FALSE;

  /**
   * Whether a failure is being logged right now.
   *
   * Logging goes through services that may themselves read the cache, and
   * with the server down those reads fail too; without this flag each of them
   * would start logging again until the stack overflows.
   *
   * @var bool
   */
  protected static $loggingFailure = FALSE;

  /**
   * Constructs a DriverBase object.
   *
   * @param \Drupal\memcache\MemcacheSettings $settings
   *   The memcache config object.
   * @param \Memcached|\Memcache $memcache
   *   An existing memcache connection object.
   * @param string $bin
   *   The class instance specific cache bin to use.
   */
  public function __construct(MemcacheSettings $settings, $memcache, $bin = NULL) {
    $this->settings = $settings;
    $this->memcache = $memcache;
    $this->hashAlgorithm = $this->settings->get('key_hash_algorithm', 'sha1');
    $prefix = $this->settings->get('key_prefix', '');
    if ($prefix) {
      $this->prefix = $prefix . ':';
    }
    if ($bin) {
      $this->prefix .= $bin . ':';
    }
  }

  /**
   * Gets the logger for a specific channel.
   *
   * The container cache bin is used while the kernel is still building the
   * container, before \Drupal::setContainer() has run, so a failed operation
   * on it has no logger service to report to. Asking for one at that point
   * throws, which turns a failed cache write into a fatal error for the whole
   * request. Fall back to the PHP error log until the container exists.
   *
   * @param string $channel
   *   The name of the channel.
   *
   * @return \Psr\Log\LoggerInterface
   *   The logger for the given channel.
   */
  protected function getLogger($channel) {
    // @phpstan-ignore-next-line
    if (!\Drupal::hasContainer()) {
      return new ErrorLogLogger($channel);
    }
    return $this->traitGetLogger($channel);
  }

  /**
   * Wrapper settings method for config vs settings object.
   *
   * @param string $key
   *   The setting to be retrieved.
   *
   * @return array|null
   *   The settings for a specified parameter.
   */
  protected function getMemcacheSetting($key) {
    // @phpstan-ignore-next-line
    $setting = \Drupal::configFactory()->getEditable('memcache.settings')->get('memcache_' . $key);
    if ($setting !== NULL) {
      @trigger_error('Memcache settings set via configuration is deprecated in memcache:8.x-2.6 and is removed from memcache:3.0.0. Use settings.php to set memcache variables. See https://www.drupal.org/node/3460878', E_USER_DEPRECATED);
      return $setting;
    }
    return $this->settings->get($key);
  }

  /**
   * {@inheritdoc}
   */
  public function get($key) {
    $full_key = $this->key($key);
    try {
      $result = $this->memcache->get($full_key);
    }
    catch (\Throwable $e) {
      $this->discardUnserializableItem($full_key, $e);
      return FALSE;
    }

    if ($result === FALSE) {
      $this->checkResult('get', $full_key);
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function key($key) {
    $full_key = urlencode($this->prefix . '-' . $key);

    // Memcache only supports key lengths up to 250 bytes.  If we have generated
    // a longer key, we shrink it to an acceptable length with a configurable
    // hashing algorithm. Sha1 was selected as the default as it performs
    // quickly with minimal collisions.
    if (strlen($full_key) > 250) {
      $full_key = urlencode($this->prefix . '-' . hash($this->hashAlgorithm, $key));
      $full_key .= '-' . substr(urlencode($key), 0, (250 - 1) - strlen($full_key) - 1);
    }

    return $full_key;
  }

  /**
   * Fetches keys one at a time, dropping any that cannot be unserialized.
   *
   * A single unreadable item aborts an entire multi-get, which would otherwise
   * turn one poisoned entry into a total cache outage for every request.
   *
   * @param string[] $full_keys
   *   Prefixed memcache keys, keyed by cache ID.
   *
   * @return array
   *   The values that could be read, keyed by their prefixed memcache key.
   */
  protected function getMultiFallback(array $full_keys) {
    $results = [];

    foreach ($full_keys as $full_key) {
      try {
        // The extension emits a warning of its own before handing us the
        // exception; we report the failure ourselves below.
        $result = @$this->memcache->get($full_key);
      }
      catch (\Throwable $e) {
        $this->discardUnserializableItem($full_key, $e);
        continue;
      }

      if ($result !== FALSE) {
        $results[$full_key] = $result;
      }
    }

    return $results;
  }

  /**
   * Removes an item whose stored value could not be unserialized.
   *
   * Objects belonging to code that is no longer installed, or that reference a
   * service which no longer exists, throw while being woken up. Such an item
   * can never be read again, so delete it rather than let it break every
   * subsequent request until it expires or the server is flushed.
   *
   * @param string $full_key
   *   The prefixed memcache key of the offending item.
   * @param \Throwable $e
   *   The error raised while unserializing the item.
   */
  protected function discardUnserializableItem($full_key, \Throwable $e) {
    $this->memcache->delete($full_key);

    try {
      $this->getLogger('memcache')->warning('Deleted cache item @key because its stored value could not be unserialized: @message. This usually means the item holds an object from code that is no longer installed.', [
        '@key' => $full_key,
        '@message' => $e->getMessage(),
      ]);
    }
    catch (\Throwable $ignored) {
      // Cache reads happen before the container is available, so logging is
      // best effort. Removing the item is what actually matters.
    }
  }

  /**
   * {@inheritdoc}
   */
  public function delete($key) {
    $full_key = $this->key($key);
    $result = $this->memcache->delete($full_key, 0);

    if (!$result) {
      $this->checkResult('delete', $full_key);
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function flush() {
    $result = $this->memcache->flush();

    if (!$result) {
      $this->checkResult('flush', '');
    }

    return $result;
  }

  /**
   * Reacts to a memcache operation that returned FALSE.
   *
   * The base implementation does nothing: the Memcache PECL extension cannot
   * tell a miss from a failed server, and it raises a PHP notice of its own
   * when a server fails. Drivers that can tell the difference should call
   * logFailure() for anything that is not a normal outcome.
   *
   * @param string $operation
   *   The operation that returned FALSE, e.g. 'set'.
   * @param string $key
   *   The full memcache key involved, or '' when there is none.
   */
  protected function checkResult($operation, $key) {
    // Nothing to do by default; see MemcachedDriver::checkResult().
  }

  /**
   * Logs a failed memcache operation, at most once per request.
   *
   * A server that is down makes every operation on it fail, so logging each
   * one would flood the log with hundreds of identical entries per request.
   * The first failure in a request is logged at error level and the rest are
   * dropped. When APCu is available the log is also throttled across
   * requests, to one entry per $settings['memcache']['failure_log_interval']
   * seconds (default 60; 0 logs once per request regardless of APCu). With
   * $settings['memcache']['debug'] set, every failure is logged.
   *
   * @param string $operation
   *   The operation that failed, e.g. 'set'.
   * @param string $key
   *   The full memcache key involved, or '' when there is none.
   * @param string $reason
   *   The reason reported by the extension, if any.
   */
  protected function logFailure($operation, $key, $reason = '') {
    if (static::$loggingFailure) {
      return;
    }

    $debug = $this->settings->get('debug');
    $interval = 0;
    if (!$debug) {
      if (static::$failureLogged) {
        return;
      }
      // Set the flag before logging: the logger itself may hit the cache,
      // fail again and end up back here.
      static::$failureLogged = TRUE;

      $interval = (int) $this->settings->get('failure_log_interval', 60);
      // apcu_add() is atomic and fails while the entry exists, so it doubles
      // as a lock shared by every process in the pool.
      if ($interval > 0 && function_exists('apcu_enabled') && apcu_enabled()) {
        if (!apcu_add('memcache:failure_logged:' . $this->settings->get('key_prefix', ''), TRUE, $interval)) {
          return;
        }
      }
      else {
        $interval = 0;
      }
    }

    $servers = [];
    if ($this->memcache instanceof \Memcached) {
      foreach ($this->memcache->getServerList() as $server) {
        $servers[] = $server['host'] . ':' . $server['port'];
      }
    }

    if ($debug) {
      $throttle = '';
    }
    elseif ($interval > 0) {
      $throttle = 'Further failures are not logged for @interval seconds.';
    }
    else {
      $throttle = 'Further failures in this request are not logged.';
    }

    static::$loggingFailure = TRUE;
    try {
      $this->getLogger('memcache')->error('Memcache @operation failed for key @key on server(s) @servers: @reason. The site is running without this cache while the server is unreachable; the status report lists which servers are affected. ' . $throttle, [
        '@operation' => $operation,
        '@key' => $key === '' ? '(none)' : $key,
        '@servers' => $servers ? implode(', ', $servers) : '(unknown)',
        '@reason' => $reason === '' ? 'no reason reported' : $reason,
        '@interval' => $interval,
      ]);
    }
    finally {
      static::$loggingFailure = FALSE;
    }
  }

  /**
   * Retrieves statistics recorded during memcache operations.
   *
   * @param string $stats_bin
   *   The bin to retrieve statistics for.
   * @param string $stats_type
   *   The type of statistics to retrieve when using the Memcache extension.
   * @param bool $aggregate
   *   Whether to add a "total" entry to the servers of the bin, summing the
   *   statistics of all servers. See ::aggregateStats() for what is summed.
   */
  public function stats($stats_bin = 'cache', $stats_type = 'default', $aggregate = FALSE) {

    // The stats_type can be over-loaded with an integer slab id, if doing a
    // cachedump.  We know we're doing a cachedump if $slab is non-zero.
    $slab = (int) $stats_type;
    $stats = [];

    foreach ($this->getBins() as $bin => $target) {
      if ($stats_bin == $bin) {
        if (isset($this->memcache)) {
          if ($this->memcache instanceof \Memcached) {
            $stats[$bin] = $this->memcache->getStats();

            // The Memcached extension gives up on the whole pool as soon as
            // one server does not answer, which hides the servers that do.
            if (!is_array($stats[$bin])) {
              $stats[$bin] = $this->memcachedStatsPerServer();
            }
          }

          // The PHP Memcache extension 3.x version throws an error if the stats
          // type is NULL or not in {reset, malloc, slabs, cachedump, items,
          // sizes}. If $stats_type is 'default', then no parameter should be
          // passed to the Memcache memcache_get_extended_stats() function.
          elseif ($this->memcache instanceof \Memcache) {
            if ($stats_type == 'default' || $stats_type == '') {
              $stats[$bin] = $this->memcache->getExtendedStats();
            }

            // If $slab isn't zero, then we are dumping the contents of a
            // specific cache slab.
            elseif (!empty($slab)) {
              $stats[$bin] = $this->memcache->getStats('cachedump', $slab);
            }
            else {
              $stats[$bin] = $this->memcache->getExtendedStats($stats_type);
            }
          }
        }
      }
    }

    // Optionally calculate a sum-total for all servers in the current bin.
    if ($aggregate) {
      foreach ($stats as $bin => $servers) {
        if (is_array($servers)) {
          $total = $this->aggregateStats($servers);
          if ($total) {
            $stats[$bin]['total'] = $total;
          }
        }
      }
    }

    return $stats;
  }

  /**
   * Collects statistics from each server of a Memcached pool separately.
   *
   * \Memcached::getStats() returns FALSE for the entire pool when any one
   * server fails to answer, so a single dead server makes the statistics of
   * the live ones unavailable. Asking every server over its own connection
   * keeps the healthy servers reporting and marks the dead ones as FALSE.
   *
   * This is a blocking network operation and is only meant for the
   * statistics report, never for the cache hot path.
   *
   * @return array
   *   The statistics keyed by "host:port", or FALSE for a server that did not
   *   answer.
   */
  protected function memcachedStatsPerServer() {
    $stats = [];
    $timeout = (int) $this->settings->get('status_connect_timeout', 1000);

    foreach ($this->memcache->getServerList() as $server) {
      $address = $server['host'] . ':' . $server['port'];
      $stats[$address] = FALSE;

      $connection = $this->createStatsConnection();
      $memcache = $connection->getMemcache();
      // Keep a dead server from stalling the report.
      $memcache->setOption(\Memcached::OPT_CONNECT_TIMEOUT, $timeout);
      $memcache->setOption(\Memcached::OPT_POLL_TIMEOUT, $timeout);

      // Unix sockets are listed with the path as the host and a port of 0.
      $path = empty($server['port']) ? 'unix://' . $server['host'] : $address;
      if ($connection->addServer($path, FALSE)) {
        $server_stats = @$memcache->getStats();
        if (is_array($server_stats)) {
          // The single server answers under whatever name the extension gives
          // it, so take the one non-empty entry rather than guess the key.
          foreach ($server_stats as $answer) {
            if (is_array($answer) && !empty($answer)) {
              $stats[$address] = $answer;
              break;
            }
          }
        }
      }
      $connection->close();
    }

    return $stats;
  }

  /**
   * Creates the throwaway connection used to query one server's statistics.
   *
   * Separate from the pool so that a single dead server cannot poison it, and
   * overridable so that a test double can stand in for the real extension.
   *
   * @return \Drupal\memcache\Connection\MemcachedConnection
   *   A connection with no servers added yet.
   */
  protected function createStatsConnection() {
    return new MemcachedConnection($this->settings);
  }

  /**
   * Sums the statistics of several servers into one set of totals.
   *
   * Only values that are meaningful as a sum are added up: counters and gauges
   * such as hits, misses, items, bytes and pages. Properties of a server or of
   * a slab class are not sums of anything: the pid or version of a server, or
   * the chunk size of slab class 2, which is per server and not necessarily
   * the same on every server of a cluster. Those are carried into the totals
   * only when every server reports the same value, and left out otherwise.
   *
   * @param array $servers
   *   The statistics of each server, keyed by server address, as returned by
   *   the extension. Entries that are not arrays (unreachable servers) are
   *   ignored.
   *
   * @return array
   *   The totals, keyed by statistic name. Empty when no server responded.
   */
  protected function aggregateStats(array $servers) {
    $total = [];
    $properties = [];
    $responded = 0;

    foreach ($servers as $server) {
      if (!is_array($server)) {
        continue;
      }
      $responded++;

      foreach ($server as $key => $value) {
        if (!$this->isAggregatableStat($key)) {
          $properties[$key][] = $value;
        }
        elseif (is_numeric($value)) {
          if (isset($total[$key])) {
            $total[$key] += $value;
          }
          else {
            $total[$key] = $value;
          }
        }
      }
    }

    foreach ($properties as $key => $values) {
      if (count($values) === $responded && count(array_unique($values)) === 1) {
        $total[$key] = reset($values);
      }
    }

    return $total;
  }

  /**
   * Determines whether a statistic is a sum of anything across servers.
   *
   * @param string $key
   *   The statistic name, e.g. "get_hits" or "2:chunk_size".
   *
   * @return bool
   *   TRUE if the values of several servers add up to a meaningful total,
   *   FALSE if the statistic describes a server or a slab class instead.
   */
  protected function isAggregatableStat($key) {
    $patterns = [
      // Server properties, from the default statistics.
      '/^(pid|time|version|libevent|pointer_size|accepting_conns|listen_disabled_num|slab_reassign_last_busy_status)$/',
      // Slab class properties, from the "slabs" statistics.
      '/^\d+:(chunk_size|chunks_per_page)$/',
      // Item ages and eviction times, from the "items" statistics.
      '/^items:\d+:(age|age_hot|age_warm|evicted_time)$/',
    ];

    foreach ($patterns as $pattern) {
      if (preg_match($pattern, (string) $key)) {
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * Helper function to get the bins.
   */
  public function getBins() {
    $memcache_bins = $this->getMemcacheSetting('bins');
    if (!isset($memcache_bins)) {
      $memcache_bins = ['cache' => 'default'];
    }

    return $memcache_bins;
  }

  /**
   * Helper function to get the servers.
   */
  public function getServers() {
    $memcache_servers = $this->getMemcacheSetting('servers');
    $host = getenv('MEMCACHED_HOST') ?: '127.0.0.1:11211';
    if (!isset($memcache_servers)) {
      $memcache_servers = [$host => 'default'];
    }

    return $memcache_servers;
  }

  /**
   * Helper function to get memcache.
   */
  public function getMemcache() {
    return $this->memcache;
  }

  /**
   * Returns the legacy per-request statistics array.
   *
   * The drivers no longer record anything here. Per-request statistics are
   * collected by \Drupal\memcache\Driver\StatisticsDriverDecorator into the
   * memcache.request_statistics service when
   * $settings['memcache']['collect_statistics'] is enabled; read them from
   * that service, or from MemcacheDriverFactory::getRequestStatistics().
   *
   * @return array
   *   The static statistics array, empty unless a subclass calls statsWrite().
   */
  public function requestStats() {
    return self::$stats;
  }

  /**
   * Returns an array of available statistics types.
   */
  public function statsTypes() {
    if ($this->memcache instanceof \Memcache) {
      // @todo Determine which versions of the PECL memcache extension have
      // these other stats types: 'malloc', 'maps', optionally detect this
      // version and expose them.  These stats are "subject to change without
      // warning" unfortunately.
      return ['default', 'slabs', 'items', 'sizes'];
    }
    else {
      // The Memcached PECL extension only offers the default statistics.
      return ['default'];
    }
  }

  /**
   * Legacy hook that used to decide whether an operation was recorded.
   *
   * The drivers no longer call this; statistics collection lives in
   * \Drupal\memcache\Driver\StatisticsDriverDecorator. Kept so that
   * subclasses overriding it keep working.
   *
   * @return bool
   *   Always FALSE.
   */
  protected function statsInit() {
    return FALSE;
  }

  /**
   * Legacy method that records an operation into the static statistics array.
   *
   * The drivers no longer call this; statistics collection lives in
   * \Drupal\memcache\Driver\StatisticsDriverDecorator. Kept so that
   * subclasses calling it keep working.
   *
   * @param string $action
   *   The action being performed (get, set, etc...).
   * @param string $bin
   *   The memcache bin the action is being performed in.
   * @param array $keys
   *   Keyed array in the form (string)$cid => (bool)$success. The keys the
   *   action is being performed on, and whether or not it was a success.
   */
  protected function statsWrite($action, $bin, array $keys) {
    // The 'dmemcache' timer this used to read was never started, so no elapsed
    // time was ever recorded here. The decorator times operations itself.
    $time = 0;

    // Build the 'all' and 'ops' arrays displayed by memcache_admin.module.
    foreach ($keys as $key => $success) {
      self::$stats['all'][] = [
        number_format($time, 2),
        $action,
        $bin,
        $key,
        $success ? 'hit' : 'miss',
      ];
      if (!isset(self::$stats['ops'][$action])) {
        self::$stats['ops'][$action] = [$action, 0, 0, 0];
      }
      self::$stats['ops'][$action][1] += $time;
      if ($success) {
        self::$stats['ops'][$action][2]++;
      }
      else {
        self::$stats['ops'][$action][3]++;
      }
    }
  }

}
