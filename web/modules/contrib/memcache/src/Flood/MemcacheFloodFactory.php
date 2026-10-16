<?php

namespace Drupal\memcache\Flood;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\memcache\Driver\MemcacheDriverFactory;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The memcache flood backend factory.
 *
 * Point the core "flood" service at this factory to keep flood control in
 * memcache instead of the database:
 * @code
 * flood:
 *   class: Drupal\Core\Flood\FloodInterface
 *   factory: ['@memcache.flood.factory', get]
 * @endcode
 */
class MemcacheFloodFactory {

  /**
   * The bin name for flood events.
   *
   * @var string
   */
  protected $bin = 'flood';

  /**
   * The memcache factory.
   *
   * @var \Drupal\memcache\Driver\MemcacheDriverFactory
   */
  protected $factory;

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
   * Constructs a new MemcacheFloodFactory.
   *
   * @param \Drupal\memcache\Driver\MemcacheDriverFactory $memcache_factory
   *   The memcache factory.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(MemcacheDriverFactory $memcache_factory, RequestStack $request_stack, TimeInterface $time) {
    $this->factory = $memcache_factory;
    $this->requestStack = $request_stack;
    $this->time = $time;
  }

  /**
   * Gets a flood backend instance.
   *
   * @return \Drupal\Core\Flood\FloodInterface
   *   A memcache flood backend instance.
   */
  public function get() {
    return new MemcacheFloodBackend($this->factory->get($this->bin), $this->requestStack, $this->time);
  }

}
