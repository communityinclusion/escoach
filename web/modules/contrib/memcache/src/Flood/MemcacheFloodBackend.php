<?php

namespace Drupal\memcache\Flood;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Flood\PrefixFloodInterface;
use Drupal\memcache\Driver\DriverBase;
use Drupal\memcache\Driver\StatisticsDriverDecorator;
use Drupal\memcache\DrupalMemcacheInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Defines a memcache flood backend.
 *
 * Every (event, identifier) pair is stored as one memcache item holding the
 * list of registered events, each with the time it was registered and the
 * time it expires. Items are written with a memcache expiration matching the
 * newest event, so expired events disappear on their own and no cron based
 * garbage collection is needed. Expired events are also ignored when reading,
 * so unlike the core database backend an event stops counting as soon as its
 * window has passed rather than when cron next runs.
 *
 * With the Memcached PHP extension the list is updated with a compare-and-swap
 * so that concurrent requests never lose each other's events. The older
 * Memcache extension has no such primitive; there the update is a plain read
 * and write, and two requests registering the same event at the same instant
 * may count as one.
 *
 * Identifiers of the form "prefix-rest" (such as the "uid-ip" identifier used
 * for failed user logins) are additionally recorded in a per prefix index so
 * that clearByPrefix() can find them, since memcache cannot list keys.
 *
 * @see \Drupal\Core\Flood\DatabaseBackend
 */
class MemcacheFloodBackend implements FloodInterface, PrefixFloodInterface {

  /**
   * How often to retry a compare-and-swap update that lost a race.
   */
  const MAX_CAS_ATTEMPTS = 10;

  /**
   * Largest relative expiration memcache accepts, in seconds (30 days).
   *
   * Anything larger is interpreted by memcached as an absolute timestamp.
   */
  const MAX_RELATIVE_EXPIRE = 2592000;

  /**
   * The memcache wrapper object.
   *
   * @var \Drupal\memcache\DrupalMemcacheInterface
   */
  protected $memcache;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Constructs a new MemcacheFloodBackend.
   *
   * @param \Drupal\memcache\DrupalMemcacheInterface $memcache
   *   The memcache wrapper object for the flood bin.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack used to retrieve the current request.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(DrupalMemcacheInterface $memcache, RequestStack $request_stack, TimeInterface $time) {
    $this->memcache = $memcache;
    $this->requestStack = $request_stack;
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   */
  public function register($name, $window = 3600, $identifier = NULL) {
    $identifier = $this->getIdentifier($identifier);
    $now = $this->time->getRequestTime();
    $expire = $now + $window;

    $this->update($this->getEventKey($name, $identifier), function (array $events) use ($now, $expire) {
      $events[] = ['time' => $now, 'expire' => $expire];
      return $events;
    });

    // Record the identifier under its prefix so clearByPrefix() can find it.
    $prefix = $this->getPrefix($identifier);
    if ($prefix !== NULL) {
      $this->update($this->getPrefixKey($name, $prefix), function (array $identifiers) use ($identifier, $expire) {
        $current = $identifiers[$identifier]['expire'] ?? 0;
        $identifiers[$identifier] = ['expire' => max($current, $expire)];
        return $identifiers;
      });
    }
  }

  /**
   * {@inheritdoc}
   */
  public function clear($name, $identifier = NULL) {
    $identifier = $this->getIdentifier($identifier);
    $this->memcache->delete($this->getEventKey($name, $identifier));
  }

  /**
   * {@inheritdoc}
   */
  public function clearByPrefix(string $name, string $prefix): void {
    $key = $this->getPrefixKey($name, $prefix);
    foreach (array_keys($this->read($key)) as $identifier) {
      $this->memcache->delete($this->getEventKey($name, $identifier));
    }
    $this->memcache->delete($key);
  }

  /**
   * {@inheritdoc}
   */
  public function isAllowed($name, $threshold, $window = 3600, $identifier = NULL) {
    $identifier = $this->getIdentifier($identifier);
    $now = $this->time->getRequestTime();
    $limit = $now - $window;

    $number = 0;
    foreach ($this->read($this->getEventKey($name, $identifier)) as $event) {
      if ($event['expire'] > $now && $event['time'] > $limit) {
        $number++;
      }
    }

    return $number < $threshold;
  }

  /**
   * {@inheritdoc}
   */
  public function garbageCollection() {
    // Nothing to do: every item is stored with a memcache expiration equal to
    // its newest event, and expired events are skipped when reading.
  }

  /**
   * Returns the identifier to use, defaulting to the client IP address.
   *
   * @param string|null $identifier
   *   The identifier passed by the caller, or NULL.
   *
   * @return string
   *   The identifier.
   */
  protected function getIdentifier($identifier) {
    if (!isset($identifier)) {
      $identifier = $this->requestStack->getCurrentRequest()->getClientIp();
    }
    return (string) $identifier;
  }

  /**
   * Returns the prefix of an identifier, if it has one.
   *
   * @param string $identifier
   *   The identifier.
   *
   * @return string|null
   *   The part of the identifier before the first "-", or NULL when the
   *   identifier is not prefixed.
   *
   * @see \Drupal\Core\Flood\FloodInterface::register()
   */
  protected function getPrefix($identifier) {
    $position = strpos($identifier, '-');
    if ($position === FALSE || $position === 0) {
      return NULL;
    }
    return substr($identifier, 0, $position);
  }

  /**
   * Returns the memcache key holding the events of an identifier.
   *
   * @param string $name
   *   The event name.
   * @param string $identifier
   *   The identifier.
   *
   * @return string
   *   The key.
   */
  protected function getEventKey($name, $identifier) {
    return 'flood:' . $name . ':' . $identifier;
  }

  /**
   * Returns the memcache key indexing the identifiers sharing a prefix.
   *
   * @param string $name
   *   The event name.
   * @param string $prefix
   *   The identifier prefix.
   *
   * @return string
   *   The key.
   */
  protected function getPrefixKey($name, $prefix) {
    return 'flood_prefix:' . $name . ':' . $prefix;
  }

  /**
   * Reads a list from memcache.
   *
   * @param string $key
   *   The key.
   *
   * @return array
   *   The stored list, or an empty array when there is none.
   */
  protected function read($key) {
    $items = $this->memcache->get($key);
    return is_array($items) ? $items : [];
  }

  /**
   * Atomically updates a list of expiring items in memcache.
   *
   * @param string $key
   *   The key.
   * @param callable $callback
   *   Receives the current list (an empty array when there is none) and
   *   returns the new one. Every entry must be an array with an 'expire'
   *   timestamp; expired entries are dropped before the list is written and
   *   the item's memcache expiration is set to the newest entry.
   */
  protected function update($key, callable $callback) {
    $now = $this->time->getRequestTime();
    $client = $this->getClient();

    if ($client instanceof \Memcached) {
      $full_key = $this->memcache->key($key);

      for ($attempt = 0; $attempt < static::MAX_CAS_ATTEMPTS; $attempt++) {
        $result = $client->get($full_key, NULL, \Memcached::GET_EXTENDED);
        $exists = is_array($result) && isset($result['cas']);
        $current = $exists && is_array($result['value']) ? $result['value'] : [];

        [$items, $expire] = $this->prepare($callback($current), $now);

        if ($items === []) {
          if (!$exists) {
            return;
          }
          $client->delete($full_key);
          return;
        }

        if (!$exists) {
          if ($client->add($full_key, $items, $expire)) {
            return;
          }
          // Another request created the item first: re-read and retry.
          if ($client->getResultCode() === \Memcached::RES_NOTSTORED) {
            continue;
          }
          break;
        }

        if ($client->cas($result['cas'], $full_key, $items, $expire)) {
          return;
        }
        // Another request changed the item in the meantime: retry. Any other
        // failure is a server problem the plain write below will report.
        if ($client->getResultCode() !== \Memcached::RES_DATA_EXISTS) {
          break;
        }
      }
    }

    // Plain read-modify-write: used with the Memcache extension, which has no
    // compare-and-swap, and as a last resort when the atomic update keeps
    // losing races or the server refuses it.
    [$items, $expire] = $this->prepare($callback($this->read($key)), $now);
    if ($items === []) {
      $this->memcache->delete($key);
    }
    else {
      $this->memcache->set($key, $items, $expire);
    }
  }

  /**
   * Drops expired entries from a list and works out its memcache expiration.
   *
   * @param array $items
   *   The list; every entry is an array with an 'expire' timestamp.
   * @param int $now
   *   The current request time.
   *
   * @return array
   *   A two element array: the pruned list and the expiration to store it
   *   with, as seconds from now or, beyond 30 days, an absolute timestamp.
   */
  protected function prepare(array $items, $now) {
    $latest = $now;
    foreach ($items as $index => $item) {
      if (!is_array($item) || !isset($item['expire']) || $item['expire'] <= $now) {
        unset($items[$index]);
        continue;
      }
      $latest = max($latest, (int) $item['expire']);
    }

    $expire = $latest - $now;
    if ($expire > static::MAX_RELATIVE_EXPIRE) {
      $expire = $latest;
    }

    return [$items, $expire];
  }

  /**
   * Returns the underlying extension client, when it can be reached.
   *
   * @return \Memcached|\Memcache|null
   *   The client, or NULL when the wrapper does not expose one.
   */
  protected function getClient() {
    $driver = $this->memcache;
    // With per-request statistics enabled the driver is wrapped; the client
    // lives on the driver underneath.
    while ($driver instanceof StatisticsDriverDecorator) {
      $driver = $driver->getDecoratedDriver();
    }
    return $driver instanceof DriverBase ? $driver->getMemcache() : NULL;
  }

}
