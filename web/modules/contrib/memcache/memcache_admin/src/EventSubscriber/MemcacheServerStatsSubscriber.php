<?php

namespace Drupal\memcache_admin\EventSubscriber;

use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\memcache\Driver\MemcacheDriverFactory;
use Drupal\memcache\Driver\StatisticsDriverDecorator;
use Drupal\memcache_admin\Event\MemcacheStatsEvent;
use Drupal\memcache_admin\Stats\MemcacheStatsObject;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Adds memcache server specific details to the stats array.
 */
class MemcacheServerStatsSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;
  use MessengerTrait;

  /**
   * The memcache driver factory.
   *
   * @var \Drupal\memcache\Driver\MemcacheDriverFactory
   */
  protected $memcacheFactory;

  /**
   * Constructs a MemcacheServerStatsSubscriber.
   *
   * @param \Drupal\memcache\Driver\MemcacheDriverFactory|null $memcache_factory
   *   The memcache driver factory, used to tell a server that is down from one
   *   that merely does not report statistics.
   */
  public function __construct(?MemcacheDriverFactory $memcache_factory = NULL) {
    // @phpcs:ignore
    $this->memcacheFactory = $memcache_factory ?? \Drupal::service('memcache.factory'); // @phpstan-ignore-line
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events = [];
    $events[MemcacheStatsEvent::BUILD_MEMCACHE_STATS][] = [
      'onPopulateStats',
      100,
    ];
    $events[MemcacheStatsEvent::REPORT_MEMCACHE_STATS][] = [
      'onReportStats',
      100,
    ];
    return $events;
  }

  /**
   * Populates the Memcache Server Stats.
   *
   * @param \Drupal\memcache_admin\Event\MemcacheStatsEvent $event
   *   The event being dispatched.
   *
   * @throws \Exception
   */
  public function onPopulateStats(MemcacheStatsEvent $event) {
    $raw_stats = $event->getRawStats();
    $bin = $event->getCacheBin();

    // No cache bin data, return.
    if (!isset($raw_stats[$bin])) {
      return;
    }
    // No servers found, return.
    if (!is_array($raw_stats[$bin])) {
      return;
    }
    $servers = array_keys($raw_stats[$bin]);
    foreach ($servers as $server) {
      // The aggregate totals carry the libevent version too when every server
      // reports the same one; they are not a server.
      if ($server == 'total') {
        continue;
      }
      // Memcache servers report libevent version, use that for detecting stats.
      if (isset($raw_stats[$bin][$server]['libevent'])) {
        $event->updateFormattedStats('memcache', $bin, $server, new MemcacheStatsObject($raw_stats[$bin][$server]));
        $event->updateServers($server);
      }
    }
    if (isset($raw_stats[$bin]['total'])) {
      $event->updateTotals([$bin => new MemcacheStatsObject($raw_stats[$bin]['total'])]);
    }
  }

  /**
   * Populates the reporting of a stored set of stats.
   *
   * @param \Drupal\memcache_admin\Event\MemcacheStatsEvent $event
   *   Memcache stats event.
   */
  public function onReportStats(MemcacheStatsEvent $event) {
    $stats = $event->getFormattedStats('memcache');
    $bin = $event->getCacheBin();

    // A server that did not answer "stats" has nothing to format and would
    // otherwise disappear from the report without a trace. Say whether it is
    // down or merely does not report statistics.
    $raw_stats = $event->getRawStats();
    $reported = FALSE;
    if (isset($raw_stats[$bin]) && is_array($raw_stats[$bin])) {
      $missing = [];
      foreach ($raw_stats[$bin] as $server => $server_stats) {
        if ($server !== 'total' && (!is_array($server_stats) || empty($server_stats))) {
          $missing[] = $server;
        }
      }
      if ($missing) {
        $this->reportServersWithoutStats($missing);
        $reported = TRUE;
      }
    }

    // No cache bin data, return.
    if (empty($stats[$bin])) {
      if (!$reported) {
        $this->reportMissingStats($event);
      }
      return;
    }
    // No servers found, return.
    if (!is_array($stats[$bin])) {
      return;
    }

    // Only the Memcached PECL extension can say whether its connections are
    // persistent; the Memcache PECL extension has no equivalent.
    $memcache = $event->getMemcache()->getMemcache();
    $persistent = $memcache instanceof \Memcached ? $memcache->isPersistent() : NULL;

    $data = [];
    foreach ($stats[$bin] as $server => $statistics) {
      if (empty($statistics->getUptime())) {
        $this->messenger()
          ->addError($this->t('Failed to connect to server at @address.', ['@address' => $server]));
      }
      else {
        $data['server_overview'][$server] = $this->t('v@version running @uptime', [
          '@version' => $statistics->getVersion(),
          '@uptime' => $statistics->getUptime(),
        ]);
        $data['server_time'][$server] = $statistics->getServerTime();
        $data['server_connections'][$server] = $statistics->getConnections();
        if ($persistent !== NULL) {
          $data['is_persistent_connection'][$server] = $persistent ? $this->t('Yes') : $this->t('No');
        }
        $data['cache_sets'][$server] = $statistics->getSets();
        $data['cache_gets'][$server] = $statistics->getGets();
        $data['cache_counters'][$server] = $statistics->getCounters();
        $data['cache_transfer'][$server] = $statistics->getTransferred();
        $data['cache_average'][$server] = $statistics->getConnectionAvg();
        $data['memory_available'][$server] = $statistics->getMemory();
        $data['memory_evictions'][$server] = $statistics->getEvictions();
      }
    }

    // Build a custom report array.
    $report = [
      'uptime' => [
        'uptime' => [
          'label' => $this->t('Uptime'),
          'servers' => $data['server_overview'],
        ],
        'time' => [
          'label' => $this->t('Time'),
          'servers' => $data['server_time'],
        ],
        'connections' => [
          'label' => $this->t('Connections'),
          'servers' => $data['server_connections'],
        ],
      ],
      'stats' => [
        'sets' => [
          'label' => $this->t('Sets'),
          'servers' => $data["cache_sets"],
        ],
        'gets' => [
          'label' => $this->t('Gets'),
          'servers' => $data["cache_gets"],
        ],
        'counters' => [
          'label' => $this->t('Counters'),
          'servers' => $data["cache_counters"],
        ],
        'transfer' => [
          'label' => $this->t('Transferred'),
          'servers' => $data["cache_transfer"],
        ],
        'average' => [
          'label' => $this->t('Per-connection average'),
          'servers' => $data["cache_average"],
        ],
      ],
      'memory' => [
        'memory' => [
          'label' => $this->t('Available memory'),
          'servers' => $data['memory_available'],
        ],
        'evictions' => [
          'label' => $this->t('Evictions'),
          'servers' => $data['memory_evictions'],
        ],
      ],
    ];
    if (isset($data['is_persistent_connection'])) {
      $report['uptime']['persistent'] = [
        'label' => $this->t('Persistent connections'),
        'servers' => $data['is_persistent_connection'],
      ];
    }

    // Don't display aggregate totals if there's only one server.
    if (count($stats[$bin]) > 1) {
      /** @var \Drupal\memcache_admin\Stats\MemcacheStatsObject $totals */
      $totals = $event->getTotals();
      $report['uptime']['uptime']['total'] = $this->t('n/a');
      $report['uptime']['time']['total'] = $this->t('n/a');
      $report['uptime']['connections']['total'] = $totals[$bin]->getConnections();
      if (isset($report['uptime']['persistent'])) {
        $report['uptime']['persistent']['total'] = $this->t('n/a');
      }
      $report['stats']['sets']['total'] = $totals[$bin]->getSets();
      $report['stats']['gets']['total'] = $totals[$bin]->getGets();
      $report['stats']['counters']['total'] = $totals[$bin]->getCounters();
      $report['stats']['transfer']['total'] = $totals[$bin]->getTransferred();
      $report['stats']['average']['total'] = $totals[$bin]->getConnectionAvg();
      $report['memory']['memory']['total'] = $totals[$bin]->getMemory();
      $report['memory']['evictions']['total'] = $totals[$bin]->getEvictions();
    }

    $event->updateReport($report);
  }

  /**
   * Explains why no statistics could be shown for a bin.
   *
   * Statistics come from the memcached "stats" command, which is not part of
   * the storage protocol: a proxy such as twemproxy answers every get and set
   * but rejects "stats", so the cache works while this report stays empty. Do
   * not describe that as a broken configuration. Probe the servers instead and
   * say which of them are actually down.
   *
   * @param \Drupal\memcache_admin\Event\MemcacheStatsEvent $event
   *   Memcache stats event.
   */
  protected function reportMissingStats(MemcacheStatsEvent $event) {
    $bin = $event->getCacheBin();
    $memcache = $event->getMemcache();

    // With per-request statistics enabled the driver is wrapped in a decorator
    // that forwards getBins() through __call(), which method_exists() cannot
    // see, so look at the driver underneath.
    while ($memcache instanceof StatisticsDriverDecorator) {
      $memcache = $memcache->getDecoratedDriver();
    }

    $servers = [];
    if (method_exists($memcache, 'getBins')) {
      $cluster = $memcache->getBins()[$bin] ?? 'default';
      $servers = array_keys($this->memcacheFactory->getServers(), $cluster, TRUE);
    }

    if (!$servers) {
      $this->messenger()->addError($this->t('No statistics could be retrieved for the %bin bin. There may be a problem with your Memcache configuration; please review @readme for more information.', [
        '%bin' => $bin,
        '@readme' => 'README.md',
      ]));
      return;
    }

    $this->reportServersWithoutStats($servers);
  }

  /**
   * Reports servers that returned no statistics, probing which are down.
   *
   * @param string[] $servers
   *   The addresses of the servers that did not answer "stats".
   */
  protected function reportServersWithoutStats(array $servers) {
    $reachable = $this->memcacheFactory->checkServers();
    $down = array_filter($servers, function ($server) use ($reachable) {
      return empty($reachable[$server]);
    });
    $up = array_diff($servers, $down);

    if ($down) {
      $this->messenger()->addError($this->t('Failed to connect to the memcache server(s) at %servers. Cache reads and writes to these servers fail silently; see the <a href=":status">status report</a> for details.', [
        '%servers' => implode(', ', $down),
        ':status' => Url::fromRoute('system.status')->toString(),
      ]));
    }

    if ($up) {
      $this->messenger()->addWarning($this->t('The memcache server(s) at %servers answer cache reads and writes but do not answer the "stats" command, so no statistics can be shown for them. This is expected behind a proxy such as twemproxy, which does not implement "stats"; the cache itself is unaffected. See the Memcache Admin section of @readme.', [
        '%servers' => implode(', ', $up),
        '@readme' => 'README.md',
      ]));
    }
  }

}
