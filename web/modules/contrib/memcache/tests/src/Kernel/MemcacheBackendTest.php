<?php

namespace Drupal\Tests\memcache\Kernel;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;
use Drupal\Core\Site\Settings;
use Drupal\KernelTests\Core\Cache\GenericCacheBackendUnitTestBase;
use Drupal\memcache\Driver\StatisticsDriverDecorator;
use Drupal\memcache\MemcacheBackend;
use Drupal\memcache\MemcacheBackendFactory;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the MemcacheBackend.
 *
 * @group memcache
 */
#[Group('memcache')]
#[RunTestsInSeparateProcesses]
class MemcacheBackendTest extends GenericCacheBackendUnitTestBase implements ServiceModifierInterface {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['system', 'memcache'];

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    $service_definition = $container->getDefinition('memcache.timestamp.invalidator.bin');
    // Set tolerance to 0 for timestamp invalidator.
    $service_definition->setArgument(2, 0);
  }

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $host = getenv('MEMCACHED_HOST') ?: '127.0.0.1:11211';
    $this->setSetting('memcache', [
      'servers' => [$host => 'default'],
      'bin' => ['default' => 'default'],
      'debug' => TRUE,
      // Memcache is shared between all tests and test runs, so isolate the
      // keys of each test the same way the database tables are isolated.
      'key_prefix' => $this->databasePrefix,
    ]);
  }

  /**
   * Creates a new instance of DatabaseBackend.
   *
   * @return \Drupal\memcache\MemcacheBackend
   *   A new MemcacheBackend object.
   */
  protected function createCacheBackend($bin): CacheBackendInterface {
    $factory = new MemcacheBackendFactory(
      $this->container->get('memcache.factory'),
      $this->container->get('cache_tags.invalidator.checksum'),
      $this->container->get('memcache.timestamp.invalidator.bin'),
    );
    return $factory->get($bin);
  }

  /**
   * Tests that per-request statistics are not collected by default.
   *
   * Statistics collection was unintentionally always on, costing time and
   * memory on every cache operation.
   *
   * @see https://www.drupal.org/project/memcache/issues/3621859
   */
  public function testStatisticsNotCollectedByDefault(): void {
    $backend = $this->getCacheBackend();
    $backend->set('stats_test', 'value');
    $backend->get('stats_test');
    $cids = ['stats_test', 'stats_missing'];
    $backend->getMultiple($cids);
    $backend->delete('stats_test');

    $memcache = $this->container->get('memcache.factory')->get('default');
    $this->assertNotInstanceOf(StatisticsDriverDecorator::class, $memcache);
    $this->assertSame(['all' => [], 'ops' => []], $memcache->requestStats());
    $this->assertTrue($this->container->get('memcache.request_statistics')->isEmpty());
  }

  /**
   * Tests that a driver can be requested without a bin.
   *
   * The memcache_admin module asks for a bin-less driver to read statistics.
   * The NULL bin must map to the 'default' cluster and must not be used as an
   * array offset, which PHP 8.5 deprecates.
   *
   * @see https://www.drupal.org/project/memcache/issues/3576305
   */
  public function testGetDriverWithoutBin(): void {
    $factory = $this->container->get('memcache.factory');
    $driver = $factory->get(NULL, TRUE);
    $this->assertNotFalse($driver);
    $this->assertSame($driver->getMemcache(), $factory->get(NULL)->getMemcache());
    $this->assertSame($driver->getMemcache(), $factory->get('default')->getMemcache());
  }

  /**
   * Gets a backend to test; this will get a shared instance set in the object.
   *
   * @return \Drupal\Core\Cache\CacheBackendInterface
   *   Cache backend to test.
   */
  protected function getCacheBackend($bin = NULL) {
    $backend = parent::getCacheBackend($bin);
    usleep(10000);
    return $backend;
  }

  /**
   * Tests that set() is skipped while a tag invalidation is pending commit.
   *
   * Setting a cache item whose tags overlap with a pending (delayed) tag
   * invalidation must not write to the backend at all, since the item would
   * be immediately rejected as invalid anyway. This is verified by attempting
   * the write during the transaction and then, after rolling back the
   * transaction (which discards the pending invalidation), confirming via
   * get() that the pre-transaction value is still present unchanged - proving
   * the overwrite was never actually persisted to storage.
   */
  public function testSetDuringTransactionSkippedForPendingTagInvalidation(): void {
    $backend = $this->getCacheBackend();
    $tags = ['test_tag_pending'];

    $backend->set('test1', 'original', Cache::PERMANENT, $tags);
    $cached = $backend->get('test1');
    $this->assertIsObject($cached);
    $this->assertSame('original', $cached->data);

    $transaction = \Drupal::database()->startTransaction();

    // Invalidate the tag inside the transaction. The actual invalidation is
    // delayed until commit, but the tag must be treated as pending for the
    // remainder of the transaction.
    $backend->invalidateTags($tags);

    // Attempting to write a new value carrying the pending tag must be
    // skipped.
    $this->assertFalse($backend->set('test1', 'attempted overwrite', Cache::PERMANENT, $tags), 'set() reported failure while the tag invalidation was pending.');

    // Roll back the transaction, discarding the pending tag invalidation.
    $transaction->rollBack();
    unset($transaction);

    // If the set() above had actually written to the backend, the value would
    // now be 'attempted overwrite' regardless of the rollback, since the
    // backend storage itself is not part of the database transaction. Getting
    // the original value back proves the write was correctly skipped.
    $cached = $backend->get('test1');
    $this->assertIsObject($cached, 'Original item is still present.');
    $this->assertSame('original', $cached->data, 'set() did not overwrite the item while the tag invalidation was pending.');
  }

  /**
   * Tests that delete() and deleteMultiple() are deferred until commit.
   *
   * Deletions performed during a database transaction must not be applied to
   * the backend immediately; they should only occur once the transaction
   * commits successfully, and be discarded entirely if the transaction rolls
   * back.
   */
  public function testDeleteDuringTransactionIsDeferred(): void {
    $backend = $this->getCacheBackend();

    $backend->set('test1', 'original');
    $this->assertIsObject($backend->get('test1'));

    $transaction = \Drupal::database()->startTransaction();
    $backend->delete('test1');

    // Reads through the backend must not surface an item pending deletion.
    $this->assertFalse($backend->get('test1'), 'Item is not returned while its deletion is pending commit.');

    // Another request (simulated by a separate backend instance for the same
    // bin) must still see the item until the transaction is committed.
    $other_request = $this->createCacheBackend($this->getTestBin());
    $this->assertIsObject($other_request->get('test1'), 'Item is still visible to other requests while the deletion is pending commit.');

    // Rolling back must discard the deferred deletion. If delete() had
    // actually removed the item from the backend, it could not reappear here,
    // since the backend storage is not part of the database transaction.
    $transaction->rollBack();
    unset($transaction);

    $cached = $backend->get('test1');
    $this->assertIsObject($cached, 'Item was not physically deleted because deletion is deferred until commit.');
    $this->assertSame('original', $cached->data);

    // For completeness, verify that a delete() still takes effect once the
    // transaction actually commits.
    $transaction = \Drupal::database()->startTransaction();
    $backend->delete('test1');
    unset($transaction);

    $this->assertFalse($backend->get('test1'), 'Item was deleted once the transaction committed.');

    // Verify that deleteMultiple() defers in exactly the same way.
    $backend->set('test2', 'original');
    $backend->set('test3', 'original');
    $this->assertIsObject($backend->get('test2'));
    $this->assertIsObject($backend->get('test3'));

    $transaction = \Drupal::database()->startTransaction();
    $backend->deleteMultiple(['test2', 'test3']);

    // Reads through the backend must not surface items pending deletion.
    $this->assertFalse($backend->get('test2'), 'Item is not returned while its deletion is pending commit.');
    $this->assertFalse($backend->get('test3'), 'Item is not returned while its deletion is pending commit.');

    // Rolling back must discard the deferred deletions. If deleteMultiple()
    // had actually removed the items from the backend, they could not
    // reappear here, since the backend storage is not part of the database
    // transaction.
    $transaction->rollBack();
    unset($transaction);

    $cached = $backend->get('test2');
    $this->assertIsObject($cached, 'Item was not physically deleted because deletion is deferred until commit.');
    $this->assertSame('original', $cached->data);
    $cached = $backend->get('test3');
    $this->assertIsObject($cached, 'Item was not physically deleted because deletion is deferred until commit.');
    $this->assertSame('original', $cached->data);

    // For completeness, verify that a deleteMultiple() still takes effect
    // once the transaction actually commits.
    $transaction = \Drupal::database()->startTransaction();
    $backend->deleteMultiple(['test2', 'test3']);
    unset($transaction);

    $this->assertFalse($backend->get('test2'), 'Item was deleted once the transaction committed.');
    $this->assertFalse($backend->get('test3'), 'Item was deleted once the transaction committed.');
  }

  /**
   * Tests that invalidateTags() is deferred until a transaction commits.
   *
   * Tag invalidations performed during a database transaction must not be
   * applied to the backend immediately; they should only take effect once the
   * transaction commits successfully, and be discarded entirely if the
   * transaction rolls back.
   */
  public function testInvalidateTagsDuringTransactionIsDeferred(): void {
    $backend = $this->getCacheBackend();
    $tags = ['test_tag_deferred'];

    $backend->set('test1', 'original', Cache::PERMANENT, $tags);
    $this->assertIsObject($backend->get('test1'));

    $transaction = \Drupal::database()->startTransaction();
    $backend->invalidateTags($tags);

    // The tag must appear invalidated for the remainder of the transaction,
    // so that this and other requests do not read stale data.
    $this->assertFalse($backend->get('test1'), 'Item is not returned while its tag invalidation is pending commit.');

    // Rolling back must discard the pending invalidation entirely. If it had
    // actually been written to the backend, the item could not become valid
    // again here, since the backend storage is not part of the database
    // transaction.
    $transaction->rollBack();
    unset($transaction);

    $cached = $backend->get('test1');
    $this->assertIsObject($cached, 'Item remained valid because the tag invalidation was deferred and then discarded on rollback.');
    $this->assertSame('original', $cached->data);

    // For completeness, verify that invalidateTags() still takes effect once
    // the transaction actually commits. A different tag/item is used here, as
    // invalidating the same tag twice in one request is otherwise a no-op.
    // @todo This may be a bug in core CacheTagsChecksumTrait which does not
    //   rollback changes to invalidatedTags when a transaction is rolled
    //   back. Thus subsequent invalidations after a captured rollback do
    //   not take effect. This test works around that for now.
    $committed_tags = ['test_tag_deferred_committed'];
    $backend->set('test2', 'original', Cache::PERMANENT, $committed_tags);
    $this->assertIsObject($backend->get('test2'));

    $transaction = \Drupal::database()->startTransaction();
    $backend->invalidateTags($committed_tags);
    unset($transaction);

    $this->assertFalse($backend->get('test2'), 'Item was invalidated once the transaction committed.');
  }

  /**
   * Tests that deletions are deferred until the root transaction ends.
   *
   * Nested transactions are savepoints; the deletion must only be applied once
   * the outermost transaction is committed.
   */
  public function testDeleteInNestedTransactionIsDeferredUntilRootCommit(): void {
    $backend = $this->getCacheBackend();
    $other_request = $this->createCacheBackend($this->getTestBin());

    $backend->set('test1', 'original');
    $backend->set('test2', 'original');

    $outer = \Drupal::database()->startTransaction();
    $inner = \Drupal::database()->startTransaction('inner_savepoint');
    $backend->delete('test1');
    // Releasing the savepoint must not trigger the deletion.
    unset($inner);
    $this->assertFalse($backend->get('test1'), 'Item is hidden from the current request.');
    $this->assertIsObject($other_request->get('test1'), 'Item is still visible to other requests after the savepoint was released.');

    // A deletion inside a rolled back savepoint is still applied once the root
    // transaction commits: deleting too much is always safe.
    $inner = \Drupal::database()->startTransaction('inner_savepoint');
    $backend->delete('test2');
    $inner->rollBack();
    unset($inner);
    $this->assertIsObject($other_request->get('test2'), 'Item is still visible to other requests after the savepoint was rolled back.');

    unset($outer);
    $this->assertFalse($backend->get('test1'), 'Item was deleted once the root transaction committed.');
    $this->assertFalse($other_request->get('test1'), 'Item is gone for other requests once the root transaction committed.');
    $this->assertFalse($other_request->get('test2'), 'Item deleted in a rolled back savepoint is gone once the root transaction committed.');
  }

  /**
   * Tests that deleteAll() and removeBin() are deferred until commit.
   *
   * The current request must immediately stop seeing the items, other requests
   * must keep seeing them until the transaction is committed, and a rollback
   * must leave the bin untouched.
   */
  public function testDeleteAllDuringTransactionIsDeferred(): void {
    foreach (['deleteAll', 'removeBin'] as $method) {
      $backend = $this->getCacheBackend();
      $other_request = $this->createCacheBackend($this->getTestBin());

      $backend->set('test1', 'original');
      $this->assertIsObject($backend->get('test1'));
      $this->assertIsObject($other_request->get('test1'));

      $transaction = \Drupal::database()->startTransaction();
      $backend->$method();
      $this->assertFalse($backend->get('test1'), "Item is hidden from the current request after $method().");
      $this->assertIsObject($other_request->get('test1'), "Item is still visible to other requests while $method() is pending commit.");

      $transaction->rollBack();
      unset($transaction);
      $this->assertIsObject($backend->get('test1'), "Item is visible again after the transaction containing $method() was rolled back.");
      $this->assertIsObject($other_request->get('test1'), "Item is still visible to other requests after the rollback of $method().");

      $transaction = \Drupal::database()->startTransaction();
      $backend->$method();
      $this->assertIsObject($other_request->get('test1'), "Item is still visible to other requests while $method() is pending commit.");
      unset($transaction);
      $this->assertFalse($backend->get('test1'), "Item is gone after the transaction containing $method() was committed.");
      // The bin deletion time is only read once per request, so a request
      // started after the commit is represented by a new backend instance.
      $other_request = $this->createCacheBackend($this->getTestBin());
      $this->assertFalse($other_request->get('test1'), "Item is gone for other requests after the transaction containing $method() was committed.");
    }
  }

  /**
   * Tests that a stale item written by another request is removed on commit.
   *
   * This is the race the deferral exists for: request A deletes an item (or
   * the whole bin) inside a transaction while the cache is cold, request B
   * misses the cache, rebuilds it from the database, which still holds the old
   * uncommitted data, and writes it back. Without deferral the deletion is a
   * no-op on a cold cache (or, for deleteAll(), writes a bin deletion time
   * that predates request B's item) and the stale item survives the commit.
   * Untagged items such as the entity persistent cache cannot rely on tag
   * invalidation to correct this, so the deletion itself must happen after
   * the commit.
   */
  public function testStaleWriteDuringTransactionIsRemovedOnCommit(): void {
    // delete(): the item is deleted once the transaction commits, even though
    // it did not exist when delete() was called.
    $backend = $this->getCacheBackend();
    $other_request = $this->createCacheBackend($this->getTestBin());
    $this->assertFalse($backend->get('test1'));

    $transaction = \Drupal::database()->startTransaction();
    $backend->delete('test1');
    $other_request->set('test1', 'stale');
    $this->assertIsObject($other_request->get('test1'), 'Other requests can still use the stale item while the deletion is pending commit.');
    unset($transaction);

    $this->assertFalse($backend->get('test1'), 'Stale item written during the transaction is gone for the current request after commit.');
    $this->assertFalse($other_request->get('test1'), 'Stale item written during the transaction is gone for other requests after commit.');

    // deleteAll(): the bin deletion time is written on commit, so it is newer
    // than the stale item written during the transaction.
    $backend = $this->getCacheBackend();
    $other_request = $this->createCacheBackend($this->getTestBin());
    $this->assertFalse($backend->get('test2'));

    $transaction = \Drupal::database()->startTransaction();
    $backend->deleteAll();
    // Make sure the stale item is written measurably later than deleteAll()
    // was called, so that an immediate bin deletion time would not cover it.
    usleep(10000);
    $other_request->set('test2', 'stale');
    $this->assertIsObject($other_request->get('test2'), 'Other requests can still use the stale item while deleteAll() is pending commit.');
    usleep(10000);
    unset($transaction);

    $this->assertFalse($backend->get('test2'), 'Stale item written during the transaction is gone for the current request after deleteAll() committed.');
    // The bin deletion time is only read once per request, so a request
    // started after the commit is represented by a new backend instance.
    $other_request = $this->createCacheBackend($this->getTestBin());
    $this->assertFalse($other_request->get('test2'), 'Stale item written during the transaction is gone for other requests after deleteAll() committed.');
  }

  /**
   * Tests that expiry follows the core time service.
   *
   * The backend used to read $_SERVER['REQUEST_TIME'] directly, so a test
   * that swapped the 'datetime.time' service to fake the clock would see
   * core's cache backends expire items while memcache kept serving them.
   *
   * @see https://www.drupal.org/project/memcache/issues/2999614
   */
  public function testRequestTimeUsesTimeService(): void {
    $real_time = $this->container->get('datetime.time')->getRequestTime();
    $this->assertSame($real_time, MemcacheBackend::getRequestTime());

    $backend = $this->getCacheBackend();
    $backend->set('expiring', 'value', $real_time + 100);
    $this->assertSame('value', $backend->get('expiring')->data);

    // Move the clock forward past the expiry and check the backend notices.
    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn($real_time + 200);
    $time->method('getCurrentTime')->willReturn($real_time + 200);
    $this->container->set('datetime.time', $time);

    $this->assertSame($real_time + 200, MemcacheBackend::getRequestTime());
    $this->assertFalse($backend->get('expiring'));
    $invalid = $backend->get('expiring', TRUE);
    $this->assertFalse($invalid->valid);
    $this->assertSame('value', $invalid->data);
  }

  /**
   * Tests Drupal\Core\Cache\CacheBackendInterface::invalidateAll().
   *
   * @group legacy
   */
  #[IgnoreDeprecations]
  public function testInvalidateAll(): void {
    // This test is copied from the parent, as we need to undo the default
    // optimization for it to work and D12 will remove it.
    $this->setSetting('memcache', ['invalidate_all_as_delete' => FALSE] + Settings::get('memcache', []));

    $backend_a = $this->getCacheBackend();
    $backend_b = $this->getCacheBackend('bootstrap');

    // Set both expiring and permanent keys.
    $backend_a->set('test1', 1, Cache::PERMANENT);
    $backend_a->set('test2', 3, time() + 1000);
    $backend_b->set('test3', 4, Cache::PERMANENT);

    $this->expectDeprecation('CacheBackendInterface::invalidateAll() is deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Use CacheBackendInterface::deleteAll() or cache tag invalidation instead. See https://www.drupal.org/node/3500622');
    $backend_a->invalidateAll();

    $this->assertFalse($backend_a->get('test1'), 'First key has been invalidated.');
    $this->assertFalse($backend_a->get('test2'), 'Second key has been invalidated.');
    $this->assertNotEmpty($backend_b->get('test3'), 'Item in other bin is preserved.');
    $this->assertNotEmpty($backend_a->get('test1', TRUE), 'First key has not been deleted.');
    $this->assertNotEmpty($backend_a->get('test2', TRUE), 'Second key has not been deleted.');
  }

  /**
   * Tests that invalidateAll() deletes the bin by default.
   *
   * @group legacy
   */
  #[IgnoreDeprecations]
  public function testInvalidateAllAsDelete(): void {
    $backend_a = $this->getCacheBackend();
    $backend_b = $this->getCacheBackend('bootstrap');

    $backend_a->set('test1', 1, Cache::PERMANENT, ['foo']);
    $backend_a->set('test2', 3, time() + 1000);
    $backend_b->set('test3', 4, Cache::PERMANENT);

    // Items no longer carry the per-bin tag that invalidateAll() used to rely
    // on, so untagged items stay untagged.
    $this->assertSame(['foo'], $backend_a->get('test1')->tags);
    $this->assertSame([], $backend_a->get('test2')->tags);

    $this->expectDeprecation('CacheBackendInterface::invalidateAll() is deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Use CacheBackendInterface::deleteAll() or cache tag invalidation instead. See https://www.drupal.org/node/3500622');
    $backend_a->invalidateAll();

    $this->assertFalse($backend_a->get('test1'), 'First key has been deleted.');
    $this->assertFalse($backend_a->get('test2'), 'Second key has been deleted.');
    $this->assertFalse($backend_a->get('test1', TRUE), 'First key is not retrievable as invalid.');
    $this->assertFalse($backend_a->get('test2', TRUE), 'Second key is not retrievable as invalid.');
    $this->assertNotEmpty($backend_b->get('test3'), 'Item in other bin is preserved.');
  }

}
