<?php

namespace Drupal\memcache\Driver;

use Drupal\memcache\Connection\MemcacheConnection;
use Drupal\memcache\Connection\MemcacheConnectionInterface;
use Drupal\memcache\Connection\MemcachedConnection;
use Drupal\memcache\Connection\ServerAddress;
use Drupal\memcache\MemcacheException;
use Drupal\memcache\MemcacheSettings;
use Drupal\memcache\Statistics\RequestStatistics;

/**
 * Factory class for creation of Memcache objects.
 */
class MemcacheDriverFactory {

  /**
   * Extension value used when memcache is switched off for this environment.
   *
   * Set when both the 'servers' and 'bins' settings are explicitly empty
   * arrays, which lets the module stay enabled on a local or development
   * environment that has no memcache server and possibly neither extension.
   * See "Disabling memcache on an environment" in README.md.
   */
  const EXTENSION_DISABLED = 'disabled';

  /**
   * The key read by checkServer() to verify that a server answers requests.
   *
   * It is never written, so whether it exists is irrelevant: a hit and a miss
   * both prove the server is reachable and speaking the protocol.
   */
  const PROBE_KEY = 'memcache_server_probe';

  /**
   * The settings object.
   *
   * @var \Drupal\memcache\MemcacheSettings
   */
  protected $settings;

  /**
   * The connection class reference.
   *
   * @var string
   */
  protected $connectionClass;

  /**
   * The driver class reference.
   *
   * @var string
   */
  protected $driverClass;

  /**
   * The name of the extension in use, or self::EXTENSION_DISABLED.
   *
   * @var string
   */
  protected $extension;

  /**
   * Whether to connect to memcache using a persistent connection.
   *
   * @var bool
   */
  protected $persistent;

  /**
   * An array of Memcache connections keyed by cluster.
   *
   * @var \Drupal\memcache\Connection\MemcacheConnectionInterface[]
   */
  protected $connections = [];

  /**
   * An array of configured servers.
   *
   * @var array
   */
  protected $servers = [];

  /**
   * An array of configured bins.
   *
   * @var string[]
   */
  protected $bins = [];

  /**
   * The cluster that bins without an explicit mapping are served by.
   *
   * @var string
   */
  protected $defaultCluster = 'default';

  /**
   * An array of failed connections to configured servers keyed by server name.
   *
   * @var bool[]
   */
  protected $failedConnectionCache = [];

  /**
   * The collector that receives per-request statistics.
   *
   * @var \Drupal\memcache\Statistics\RequestStatistics
   */
  protected $statistics;

  /**
   * Whether drivers are wrapped in the statistics decorator.
   *
   * @var bool
   */
  protected $collectStatistics = FALSE;

  /**
   * Constructs a MemcacheDriverFactory object.
   *
   * @param \Drupal\memcache\MemcacheSettings $settings
   *   The settings object.
   * @param \Drupal\memcache\Statistics\RequestStatistics|null $statistics
   *   (optional) The collector that receives per-request statistics when
   *   $settings['memcache']['collect_statistics'] is enabled. Created
   *   internally when omitted.
   */
  public function __construct(MemcacheSettings $settings, ?RequestStatistics $statistics = NULL) {
    $this->settings = $settings;
    $this->statistics = $statistics ?? new RequestStatistics();

    $this->initialize();
  }

  /**
   * Returns a Memcache object based on settings and the bin requested.
   *
   * @param string|null $bin
   *   The bin which is to be used, or NULL for a driver that is not tied to
   *   any bin (memcache_admin uses one to read statistics). A NULL bin maps
   *   to the default cluster.
   * @param bool $flush
   *   Rebuild the bin/server/cache mapping.
   *
   * @return \Drupal\memcache\DrupalMemcacheInterface|bool
   *   A Memcache object. When $settings['memcache']['collect_statistics'] is
   *   enabled it is wrapped in a StatisticsDriverDecorator.
   */
  public function get($bin = NULL, $flush = FALSE) {
    // A NULL bin is keyed under '' in $bins: PHP 8.5 deprecates NULL as an
    // array offset.
    $bin = (string) $bin;

    if ($this->extension === self::EXTENSION_DISABLED) {
      // Memcache is switched off in settings, but something is still asking
      // for a driver. Say so, rather than letting the connection class fail
      // with "Class Memcached not found" or an unreachable-server error.
      throw new MemcacheException(sprintf('Memcache is disabled on this environment because $settings[\'memcache\'][\'servers\'] and $settings[\'memcache\'][\'bins\'] are both empty arrays, so no memcache driver can be created for the "%s" bin. Either remove those settings, or stop sending cache bins, locks or the bootstrap container to memcache in settings.php for this environment.', $bin !== '' ? $bin : 'default'));
    }

    if ($flush) {
      $this->flush();
    }

    // If there is no cluster for this bin in $bins, use the default cluster.
    $cluster = empty($this->bins[$bin]) ? $this->defaultCluster : $this->bins[$bin];

    // Connections are shared by every bin mapped to the same cluster, so only
    // create one when the cluster has not been initialized yet.
    if (empty($this->connections[$cluster])) {
      // Create a new Memcache object. Each cluster gets its own Memcache
      // object.
      /** @var \Drupal\memcache\Connection\MemcacheConnectionInterface $memcache */
      $memcache = new $this->connectionClass($this->settings, $this->persistent ? $this->getPersistentId($cluster) : NULL);

      // A variable to track whether we've connected to the first server.
      $init = FALSE;

      // Link all the servers to this cluster.
      foreach ($this->servers as $s => $c) {
        if ($c == $cluster && !isset($this->failedConnectionCache[$s])) {
          if ($memcache->addServer($s, $this->persistent) && !$init) {
            $init = TRUE;
          }

          if (!$init) {
            $this->failedConnectionCache[$s] = FALSE;
          }
        }
      }

      if (!$init) {
        $attempted = array_keys(array_filter($this->servers, function ($server_cluster) use ($cluster) {
          return $server_cluster == $cluster;
        }));
        $hint = $this->settings->get('ascii_auth') ? ' ASCII authentication is enabled; a server that rejects the configured credentials counts as unreachable, so check $settings[\'memcache\'][\'ascii_auth\'] against the server\'s authfile as well.' : '';
        throw new MemcacheException(sprintf('Failed to connect to any memcache server in the "%s" cluster for the "%s" bin. Server(s) tried: %s. Check that memcached is running and reachable, and review $settings[\'memcache\'][\'servers\'] in settings.php.', $cluster, $bin, $attempted ? implode(', ', $attempted) : '(none configured)') . $hint);
      }

      // Map the cluster to the new Memcache object.
      $this->connections[$cluster] = $memcache;
    }

    $driver = new $this->driverClass($this->settings, $this->connections[$cluster]->getMemcache(), $bin);
    if ($this->collectStatistics) {
      $driver = new StatisticsDriverDecorator($driver, $this->statistics, $bin);
    }

    return $driver;
  }

  /**
   * Returns the collector that receives per-request statistics.
   *
   * It only holds data when $settings['memcache']['collect_statistics'] is
   * enabled; otherwise the drivers are never decorated and nothing is recorded.
   *
   * @return \Drupal\memcache\Statistics\RequestStatistics
   *   The statistics collector.
   */
  public function getRequestStatistics() {
    return $this->statistics;
  }

  /**
   * Initializes memcache settings.
   */
  protected function initialize() {
    // Memcache switched off for this environment: both the "servers" and the
    // "bins" settings are explicitly empty arrays. The module stays enabled but
    // never connects, whether or not an extension is installed, so a local or
    // development environment can be configured from settings.local.php alone.
    if ($this->settings->get('servers', NULL) === []
      && $this->settings->get('bins', NULL) === []) {
      $this->extension = self::EXTENSION_DISABLED;
      return;
    }

    // If an extension is specified in settings.php, use that when available.
    $preferred = $this->settings->get('extension', NULL);

    if (isset($preferred) && class_exists($preferred)) {
      $extension = $preferred;
    }
    // If no extension is set, default to Memcached.
    elseif (class_exists('Memcached')) {
      $extension = \Memcached::class;
    }
    elseif (class_exists('Memcache')) {
      $extension = \Memcache::class;
    }
    elseif (isset($preferred)) {
      throw new MemcacheException(sprintf('The memcache extension "%s" configured in $settings[\'memcache\'][\'extension\'] is not available. Install the matching PHP extension, or remove the setting to use whichever of Memcached or Memcache is installed.', $preferred));
    }
    else {
      throw new MemcacheException('Neither the Memcached nor the Memcache PHP extension is installed, but the memcache module is enabled. Install one of them (Memcached is recommended), uninstall the memcache module, or switch memcache off for this environment by setting both $settings[\'memcache\'][\'servers\'] and $settings[\'memcache\'][\'bins\'] to an empty array.');
    }

    $this->extension = $extension;

    // @todo Make driver class configurable?
    $this->connectionClass = MemcachedConnection::class;
    $this->driverClass = MemcachedDriver::class;

    if ($extension === \Memcache::class) {
      $this->connectionClass = MemcacheConnection::class;
      $this->driverClass = MemcacheDriver::class;
    }

    // Values from settings.php or env variable.
    $host = getenv('MEMCACHED_HOST') ?: '127.0.0.1:11211';
    $this->servers = $this->settings->get('servers', [$host => 'default']);
    $this->bins = $this->settings->get('bins', ['default' => 'default']);
    $this->defaultCluster = $this->resolveDefaultCluster();

    // Indicate whether to connect to memcache using a persistent connection.
    // The Memcache PECL extension does this per server in addServer(); the
    // Memcached PECL extension does it per connection pool, through the id
    // passed to its constructor (see getPersistentId()).
    $this->persistent = $this->settings->get('persistent', FALSE);

    // Per-request statistics cost time and memory on every cache operation, so
    // drivers are only decorated when explicitly asked for in settings.php.
    $this->collectStatistics = (bool) $this->settings->get('collect_statistics', FALSE);
  }

  /**
   * Returns the persistent connection pool id for a cluster.
   *
   * The Memcached PECL extension shares a persistent pool between every
   * request served by the same PHP process, including requests for other
   * sites, and the pool keeps the servers and options it was first configured
   * with. The id therefore identifies the configuration, not the site: it
   * includes the cluster name, so two clusters never merge their server lists,
   * and a hash of the servers, options and SASL settings, so a configuration
   * change starts a new pool instead of inheriting a stale one. Two sites with
   * the same configuration share a pool, which is safe because their keys are
   * told apart by key_prefix, not by the connection.
   *
   * $settings['memcache']['persistent_id'] can be set to label the pool, for
   * example to tell sites apart in process-level diagnostics.
   *
   * @param string $cluster
   *   The cluster name.
   *
   * @return string
   *   The pool id to construct the connection with.
   */
  protected function getPersistentId($cluster) {
    $servers = array_keys(array_filter($this->servers, function ($server_cluster) use ($cluster) {
      return $server_cluster == $cluster;
    }));
    sort($servers);

    $configuration = [
      'servers' => $servers,
      'options' => $this->settings->get('options', []),
      'sasl' => $this->settings->get('sasl', []),
      'ascii_auth' => $this->settings->get('ascii_auth', []),
    ];

    return implode(':', [
      $this->settings->get('persistent_id') ?: 'drupal_memcache',
      $cluster,
      substr(hash('sha256', serialize($configuration)), 0, 16),
    ]);
  }

  /**
   * Returns the configured memcache servers.
   *
   * @return string[]
   *   The cluster name, keyed by server address.
   */
  public function getServers() {
    return $this->servers;
  }

  /**
   * Returns the memcache extension in use.
   *
   * @return string
   *   The extension class name, or self::EXTENSION_DISABLED.
   */
  public function getExtension() {
    return $this->extension;
  }

  /**
   * Returns whether memcache is switched off for this environment.
   *
   * @return bool
   *   TRUE when both the 'servers' and 'bins' settings are empty arrays, in
   *   which case no connection is ever made and get() throws.
   */
  public function isDisabled() {
    return $this->extension === self::EXTENSION_DISABLED;
  }

  /**
   * Checks whether each configured memcache server can be reached.
   *
   * \Memcached::addServer() only registers a server, it never connects, so a
   * host that does not exist at all is accepted without complaint and the
   * failure only shows up later as an unbroken run of cache misses. Reading a
   * key forces a real round trip to the daemon.
   *
   * Intended for diagnostics such as hook_requirements(). This is a blocking
   * network operation and must not be called on the cache hot path.
   *
   * @param int|null $timeout
   *   Connection timeout in milliseconds, or NULL to use the
   *   'status_connect_timeout' setting.
   *
   * @return bool[]
   *   TRUE when the server responded, keyed by configured server address.
   */
  public function checkServers($timeout = NULL) {
    if ($timeout === NULL) {
      $timeout = (int) $this->settings->get('status_connect_timeout', 1000);
    }

    $results = [];
    foreach (array_keys($this->servers) as $server) {
      $results[$server] = $this->checkServer($server, $timeout);
    }

    return $results;
  }

  /**
   * Checks whether a single memcache server responds.
   *
   * Each server is probed over its own connection so that one unreachable
   * server cannot mask the state of the others, and so that the connections
   * serving the rest of the request are left untouched.
   *
   * @param string $server
   *   The server address, as configured.
   * @param int $timeout
   *   Connection timeout in milliseconds.
   *
   * @return bool
   *   TRUE when the server responded.
   */
  protected function checkServer($server, $timeout) {
    if (empty($this->connectionClass)) {
      return FALSE;
    }

    $connection = NULL;
    try {
      /** @var \Drupal\memcache\Connection\MemcacheConnectionInterface $connection */
      $connection = new $this->connectionClass($this->settings);
      $memcache = $connection->getMemcache();

      // Keep an unreachable server from stalling the status report.
      if ($memcache instanceof \Memcached) {
        $memcache->setOption(\Memcached::OPT_CONNECT_TIMEOUT, $timeout);
        $memcache->setOption(\Memcached::OPT_POLL_TIMEOUT, $timeout);
      }

      // Never probe persistently: a health check should not leave a connection
      // behind for the rest of the request to inherit.
      if (!$connection->addServer($server, FALSE)) {
        return FALSE;
      }

      // Probe with a plain read rather than "stats". A proxy such as twemproxy
      // implements the storage and retrieval commands but not the management
      // ones, so "stats" would report a perfectly healthy proxy as dead. A get
      // is also the operation the cache actually depends on: a server that
      // answers it, with a hit or a miss, is serving the site.
      if ($memcache instanceof \Memcached) {
        @$memcache->get(self::PROBE_KEY);
        return in_array($memcache->getResultCode(), [
          \Memcached::RES_SUCCESS,
          \Memcached::RES_NOTFOUND,
        ], TRUE);
      }

      // The Memcache extension cannot tell a miss from a failure, but unlike
      // Memcached it connects eagerly, so the addServer() above has already
      // established that the server accepts connections.
      return TRUE;
    }
    catch (\Throwable $e) {
      // A diagnostic must never be the thing that takes the page down.
      return FALSE;
    }
    finally {
      if ($connection instanceof MemcacheConnectionInterface) {
        try {
          $connection->close();
        }
        catch (\Throwable $e) {
          // Closing is best effort.
        }
      }
    }
  }

  /**
   * Determines the cluster to use for bins without an explicit mapping.
   *
   * Unmapped bins normally fall back to the cluster named 'default', but
   * clusters are named by the site and nothing forces one of them to be called
   * 'default'. When no server is assigned to a 'default' cluster, fall back to
   * the cluster the 'default' bin is mapped to, or, failing that, to the only
   * cluster the servers are spread over.
   *
   * @return string
   *   The name of the cluster serving unmapped bins.
   */
  protected function resolveDefaultCluster() {
    $clusters = array_unique(array_values($this->servers));

    if (in_array('default', $clusters, TRUE)) {
      return 'default';
    }

    $candidates = [];
    if (!empty($this->bins['default'])) {
      $candidates[] = $this->bins['default'];
    }
    if (count($clusters) === 1) {
      $candidates[] = reset($clusters);
    }

    foreach ($candidates as $candidate) {
      if (in_array($candidate, $clusters, TRUE)) {
        return $candidate;
      }
    }

    return 'default';
  }

  /**
   * Retrieves the raw statistics reported by each configured memcache server.
   *
   * Intended for diagnostics such as hook_requirements(), which reports the
   * memory limit and the eviction count. Like checkServers() this is a blocking
   * network operation on a connection of its own and must not be called on the
   * cache hot path.
   *
   * @param int|null $timeout
   *   Connection timeout in milliseconds, or NULL to use the
   *   'status_connect_timeout' setting.
   *
   * @return array
   *   The statistics as returned by the daemon's "stats" command, keyed by
   *   configured server address. A server that could not be reached, or that
   *   does not answer "stats" at all (a proxy such as twemproxy), is FALSE.
   */
  public function getServerStats($timeout = NULL) {
    if ($timeout === NULL) {
      $timeout = (int) $this->settings->get('status_connect_timeout', 1000);
    }

    $results = [];
    foreach (array_keys($this->servers) as $server) {
      $results[$server] = $this->fetchServerStats($server, $timeout);
    }

    return $results;
  }

  /**
   * Checks how each configured server treats ASCII authentication.
   *
   * Intended for hook_requirements(): a blocking network operation on
   * connections of its own, never for the cache hot path.
   *
   * @param int|null $timeout
   *   Connection timeout in milliseconds, or NULL to use the
   *   'status_connect_timeout' setting.
   *
   * @return string[]
   *   One of the following, keyed by configured server address:
   *   - 'authenticated': the server requires authentication and accepted the
   *     configured credentials;
   *   - 'rejected': the server refused the configured credentials;
   *   - 'not_required': the server accepts commands without authenticating,
   *     so it was not started with "-Y" and the credentials protect nothing;
   *   - 'unreachable': the server did not answer at all.
   */
  public function checkAsciiAuthentication($timeout = NULL) {
    if ($timeout === NULL) {
      $timeout = (int) $this->settings->get('status_connect_timeout', 1000);
    }

    $results = [];
    foreach (array_keys($this->servers) as $server) {
      $results[$server] = $this->checkServerAsciiAuthentication($server, $timeout);
    }

    return $results;
  }

  /**
   * Checks how one server treats ASCII authentication.
   *
   * @param string $server
   *   The server address, as configured.
   * @param int $timeout
   *   Connection timeout in milliseconds.
   *
   * @return string
   *   See checkAsciiAuthentication().
   */
  protected function checkServerAsciiAuthentication($server, $timeout) {
    if ($this->connectionClass !== MemcachedConnection::class) {
      return 'unreachable';
    }

    $connection = NULL;
    try {
      // An anonymous client first: a write that succeeds without credentials
      // means the server does not require any.
      $connection = new MemcachedConnection($this->settings);
      $memcache = $connection->getMemcache();
      $memcache->setOption(\Memcached::OPT_CONNECT_TIMEOUT, $timeout);
      $memcache->setOption(\Memcached::OPT_POLL_TIMEOUT, $timeout);
      if (!$connection->addServer($server, FALSE, FALSE)) {
        return 'unreachable';
      }

      $probe = self::PROBE_KEY . '_auth';
      if (@$memcache->set($probe, 1, 1)) {
        @$memcache->delete($probe);
        return 'not_required';
      }
      $code = $memcache->getResultCode();
      if ($code !== \Memcached::RES_CLIENT_ERROR) {
        return 'unreachable';
      }

      // The server wants credentials; see whether it takes ours.
      $authenticator = $connection->getAuthenticator();
      if ($authenticator) {
        [$host, $port] = ServerAddress::parse($server);
        if ($authenticator->authenticateServer($memcache, $host, (int) $port)) {
          return 'authenticated';
        }
      }
      return 'rejected';
    }
    catch (\Throwable $e) {
      // A diagnostic must never be the thing that takes the page down.
      return 'unreachable';
    }
    finally {
      if ($connection instanceof MemcacheConnectionInterface) {
        try {
          $connection->close();
        }
        catch (\Throwable $e) {
          // Closing is best effort.
        }
      }
    }
  }

  /**
   * Retrieves the raw statistics of a single memcache server.
   *
   * @param string $server
   *   The server address, as configured.
   * @param int $timeout
   *   Connection timeout in milliseconds.
   *
   * @return array|false
   *   The statistics keyed by name, or FALSE when the server did not answer.
   */
  protected function fetchServerStats($server, $timeout) {
    if (empty($this->connectionClass)) {
      return FALSE;
    }

    $connection = NULL;
    try {
      /** @var \Drupal\memcache\Connection\MemcacheConnectionInterface $connection */
      $connection = new $this->connectionClass($this->settings);
      $memcache = $connection->getMemcache();

      if ($memcache instanceof \Memcached) {
        $memcache->setOption(\Memcached::OPT_CONNECT_TIMEOUT, $timeout);
        $memcache->setOption(\Memcached::OPT_POLL_TIMEOUT, $timeout);
      }

      if (!$connection->addServer($server, FALSE)) {
        return FALSE;
      }

      $stats = $memcache instanceof \Memcached
        ? @$memcache->getStats()
        : @$memcache->getExtendedStats();

      if (!is_array($stats)) {
        return FALSE;
      }

      // Both extensions key the result by "host:port", but the Memcache
      // extension normalises the host, so do not look the server up by name.
      // Only one server was added, so the first non-empty entry is it.
      foreach ($stats as $server_stats) {
        if (is_array($server_stats) && !empty($server_stats)) {
          return $server_stats;
        }
      }

      return FALSE;
    }
    catch (\Throwable $e) {
      // A diagnostic must never be the thing that takes the page down.
      return FALSE;
    }
    finally {
      if ($connection instanceof MemcacheConnectionInterface) {
        try {
          $connection->close();
        }
        catch (\Throwable $e) {
          // Closing is best effort.
        }
      }
    }
  }

  /**
   * Flushes the memcache bin/server/cache mappings and closes connections.
   */
  protected function flush() {
    foreach ($this->connections as $cluster) {
      $cluster->close();
    }

    $this->connections = [];
  }

}
