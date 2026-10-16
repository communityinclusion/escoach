<?php

namespace Drupal\Tests\memcache\Unit;

use Drupal\Core\Cache\CacheTagsChecksumInterface;
use Drupal\Core\Database\Transaction\TransactionManagerInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\memcache\Cache\TimestampCacheTagsChecksum;
use Drupal\memcache\Invalidator\TimestampInvalidatorInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * @coversDefaultClass \Drupal\memcache\Cache\TimestampCacheTagsChecksum
 * @group memcache
 */
#[Group('memcache')]
#[CoversClass(TimestampCacheTagsChecksum::class)]
class TimestampCacheTagsChecksumTest extends UnitTestCase {

  /**
   * The mock timestamp invalidator.
   *
   * @var \Drupal\memcache\Invalidator\TimestampInvalidatorInterface|\PHPUnit\Framework\MockObject\MockObject|\PHPUnit\Framework\MockObject\Stub
   */
  protected TimestampInvalidatorInterface $invalidator;

  /**
   * The object under test.
   */
  protected TimestampCacheTagsChecksum $checksum;

  /**
   * The mock transaction manager shared by the mock database connections.
   *
   * @var \Drupal\Core\Database\Transaction\TransactionManagerInterface|\PHPUnit\Framework\MockObject\MockObject|\PHPUnit\Framework\MockObject\Stub|null
   */
  protected $transactionManager = NULL;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->invalidator = $this->createStub(TimestampInvalidatorInterface::class);
    $this->invalidator->method('getCurrentTimestamp')->willReturn(9999.0);
    $this->checksum = new TimestampCacheTagsChecksum($this->invalidator);
  }

  /**
   * Replaces the stub invalidator with a mock that accepts expectations.
   */
  private function mockInvalidator(): void {
    $this->invalidator = $this->createMock(TimestampInvalidatorInterface::class);
    $this->invalidator->method('getCurrentTimestamp')->willReturn(9999.0);
    $this->checksum = new TimestampCacheTagsChecksum($this->invalidator);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    parent::tearDown();
    \Drupal::unsetContainer();
  }

  /**
   * Sets up a mock database container simulating in/out-of-transaction state.
   *
   * @param bool $in_transaction
   *   Whether the mock database reports being in a transaction.
   */
  private function setUpDatabaseContainer(bool $in_transaction): void {
    $tx_manager = $this->transactionManager ??= $this->createStub(TransactionManagerInterface::class);

    // Use an anonymous class so that method_exists() finds the methods.
    $database = new class($tx_manager, $in_transaction) {

      public function __construct(
        private mixed $txManager,
        private bool $inTx,
      ) {}

      /**
       * Mimics Connection::inTransaction().
       */
      public function inTransaction(): bool {
        return $this->inTx;
      }

      /**
       * Mimics Connection::transactionManager().
       */
      public function transactionManager(): mixed {
        return $this->txManager;
      }

    };

    $container = new ContainerBuilder();
    $container->set('database', $database);
    \Drupal::setContainer($container);
  }

  /**
   * Reads a protected property from the object under test.
   */
  private function getProperty(string $name): mixed {
    return (new \ReflectionProperty(TimestampCacheTagsChecksum::class, $name))
      ->getValue($this->checksum);
  }

  /**
   * Writes a protected property on the object under test.
   */
  private function setProperty(string $name, mixed $value): void {
    (new \ReflectionProperty(TimestampCacheTagsChecksum::class, $name))
      ->setValue($this->checksum, $value);
  }

  /**
   * @covers ::invalidateTags
   */
  public function testInvalidateTagsOutsideTransactionInvalidatesImmediately(): void {
    $this->mockInvalidator();
    $this->invalidator->expects($this->once())
      ->method('invalidateTimestamp')
      ->with('tag_a')
      ->willReturn(100.0);

    $this->setUpDatabaseContainer(in_transaction: FALSE);
    $this->checksum->invalidateTags(['tag_a']);

    $this->assertEquals(['tag_a' => 100.0], $this->getProperty('tagCache'));
    $this->assertEmpty($this->getProperty('delayedTags'));
  }

  /**
   * @covers ::invalidateTags
   */
  public function testInvalidateTagsInsideTransactionDelaysInvalidation(): void {
    $this->mockInvalidator();
    $this->invalidator->expects($this->never())->method('invalidateTimestamp');

    $this->setUpDatabaseContainer(in_transaction: TRUE);
    $this->checksum->invalidateTags(['tag_a', 'tag_b']);

    $this->assertEqualsCanonicalizing(
      ['tag_a', 'tag_b'],
      $this->getProperty('delayedTags'),
    );
    $this->assertEmpty($this->getProperty('tagCache'));
  }

  /**
   * @covers ::invalidateTags
   */
  public function testTagAlreadyInvalidatedThisRequestIsNotDelayedAgain(): void {
    $this->mockInvalidator();
    // First call — outside transaction — marks tag_a as invalidated.
    $this->invalidator->expects($this->once())
      ->method('invalidateTimestamp')
      ->with('tag_a')
      ->willReturn(100.0);

    $this->setUpDatabaseContainer(in_transaction: FALSE);
    $this->checksum->invalidateTags(['tag_a']);

    // Second call — simulate entering a transaction — should skip tag_a.
    $this->setUpDatabaseContainer(in_transaction: TRUE);
    $this->checksum->invalidateTags(['tag_a']);

    $this->assertEmpty(
      $this->getProperty('delayedTags'),
      'Already-invalidated tag was not re-added to delayedTags.',
    );
  }

  /**
   * @covers ::getCurrentChecksum
   */
  public function testGetCurrentChecksumReturnsSentinelForDelayedTags(): void {
    $this->setProperty('delayedTags', ['delayed_tag']);

    $result = $this->checksum->getCurrentChecksum(['delayed_tag']);

    $this->assertSame(
      CacheTagsChecksumInterface::INVALID_CHECKSUM_WHILE_IN_TRANSACTION,
      $result,
    );
  }

  /**
   * @covers ::getCurrentChecksum
   */
  public function testGetCurrentChecksumDoesNotReturnSentinelForUnrelatedTags(): void {
    $this->setProperty('delayedTags', ['delayed_tag']);

    $this->invalidator->method('getLastInvalidationTimestamps')
      ->willReturnCallback(function (array $tags) {
        return array_fill_keys($tags, 10.0);
      });

    $result = $this->checksum->getCurrentChecksum(['other_tag']);

    $this->assertNotSame(
      CacheTagsChecksumInterface::INVALID_CHECKSUM_WHILE_IN_TRANSACTION,
      $result,
    );
  }

  /**
   * @covers ::isValid
   */
  public function testIsValidReturnsFalseForDelayedTags(): void {
    $this->setProperty('delayedTags', ['delayed_tag']);

    $this->assertFalse($this->checksum->isValid(0, ['delayed_tag']));
  }

  /**
   * @covers ::isValid
   */
  public function testIsValidUnaffectedByUnrelatedDelayedTags(): void {
    $this->setProperty('delayedTags', ['delayed_tag']);
    $this->setProperty('tagCache', ['other_tag' => 5.0]);

    $this->assertTrue($this->checksum->isValid(5, ['other_tag']));
  }

  /**
   * @covers ::rootTransactionEndCallback
   */
  public function testRootTransactionEndCallbackOnCommitInvalidatesTags(): void {
    $this->mockInvalidator();
    $this->invalidator->expects($this->exactly(2))
      ->method('invalidateTimestamp')
      ->willReturnCallback(fn(string $tag) => match ($tag) {
        'tag_a' => 100.0,
        'tag_b' => 200.0,
      });

    $this->setProperty('delayedTags', ['tag_a', 'tag_b']);
    $this->checksum->rootTransactionEndCallback(TRUE);

    $this->assertEmpty($this->getProperty('delayedTags'));
    $this->assertEquals(
      ['tag_a' => 100.0, 'tag_b' => 200.0],
      $this->getProperty('tagCache'),
    );
  }

  /**
   * @covers ::rootTransactionEndCallback
   */
  public function testRootTransactionEndCallbackOnRollbackDiscardsTags(): void {
    $this->mockInvalidator();
    $this->invalidator->expects($this->never())->method('invalidateTimestamp');

    $this->setProperty('delayedTags', ['tag_a', 'tag_b']);
    $this->checksum->rootTransactionEndCallback(FALSE);

    $this->assertEmpty($this->getProperty('delayedTags'));
    $this->assertEmpty($this->getProperty('tagCache'));
  }

  /**
   * @covers ::rootTransactionEndCallback
   */
  public function testRootTransactionEndCallbackOnRollbackAllowsReinvalidation(): void {
    $this->mockInvalidator();
    // The tag is delayed inside the transaction and never written.
    $this->setUpDatabaseContainer(in_transaction: TRUE);
    $this->checksum->invalidateTags(['tag_a']);
    $this->assertSame(['tag_a'], $this->getProperty('delayedTags'));
    $this->assertSame(['tag_a' => TRUE], $this->getProperty('invalidatedTags'));

    // After a rollback the tag must be invalidatable again in this request.
    $this->checksum->rootTransactionEndCallback(FALSE);
    $this->assertEmpty($this->getProperty('invalidatedTags'));

    $this->invalidator->expects($this->once())
      ->method('invalidateTimestamp')
      ->with('tag_a')
      ->willReturn(100.0);
    $this->setUpDatabaseContainer(in_transaction: FALSE);
    $this->checksum->invalidateTags(['tag_a']);
    $this->assertEquals(['tag_a' => 100.0], $this->getProperty('tagCache'));
  }

  /**
   * @covers ::invalidateTags
   */
  public function testCallbackIsRegisteredOncePerTransaction(): void {
    $this->transactionManager = $this->createMock(TransactionManagerInterface::class);
    $this->setUpDatabaseContainer(in_transaction: TRUE);
    $this->transactionManager->expects($this->once())
      ->method('addPostTransactionCallback')
      ->with([$this->checksum, 'rootTransactionEndCallback']);

    $this->checksum->invalidateTags(['tag_a']);
    $this->checksum->invalidateTags(['tag_b']);
    $this->assertEqualsCanonicalizing(['tag_a', 'tag_b'], $this->getProperty('delayedTags'));

    // Once the transaction has ended a new callback may be registered.
    $this->checksum->rootTransactionEndCallback(TRUE);
    $this->assertFalse($this->getProperty('postTransactionCallbackRegistered'));
  }

  /**
   * @covers ::reset
   */
  public function testResetClearsAllState(): void {
    $this->setProperty('tagCache', ['tag_a' => 100.0]);
    $this->setProperty('invalidatedTags', ['tag_a' => TRUE]);
    $this->setProperty('delayedTags', ['tag_c']);
    $this->setProperty('preloadTags', ['tag_d']);

    $this->checksum->reset();

    $this->assertEmpty($this->getProperty('tagCache'));
    $this->assertEmpty($this->getProperty('invalidatedTags'));
    $this->assertEmpty($this->getProperty('delayedTags'));
    $this->assertEmpty($this->getProperty('preloadTags'));
  }

  /**
   * @covers ::registerCacheTagsForPreload
   * @covers ::calculateChecksum
   */
  public function testPreloadTagsBatchedAndConsumedAfterCalculation(): void {
    // Preload tags must be merged into the SAME backend call as the requested
    // tags, and must be cleared afterwards so they are not re-fetched.
    $this->mockInvalidator();
    $this->invalidator->expects($this->once())
      ->method('getLastInvalidationTimestamps')
      ->with($this->callback(function (array $tags): bool {
        sort($tags);
        return $tags === ['preload_b', 'preload_c', 'tag_a'];
      }))
      ->willReturn(['tag_a' => 100.0, 'preload_b' => 200.0, 'preload_c' => 300.0]);

    $this->checksum->registerCacheTagsForPreload(['preload_b', 'preload_c']);
    // First call: all three tags fetched in a single backend request.
    $this->checksum->getCurrentChecksum(['tag_a']);
    // Second call: tag_a is already in tagCache — mock's expects($this->once())
    // enforces that no second backend call happens.
    $this->checksum->getCurrentChecksum(['tag_a']);

    $this->assertEmpty($this->getProperty('preloadTags'));
  }

  /**
   * @covers ::calculateChecksum
   */
  public function testPreloadDoesNotAffectReturnedChecksum(): void {
    $this->invalidator->method('getLastInvalidationTimestamps')
      ->willReturn(['tag_a' => 100.0, 'preload_b' => 500.0]);

    $this->checksum->registerCacheTagsForPreload(['preload_b']);
    $result = $this->checksum->getCurrentChecksum(['tag_a']);

    // Checksum is min(getCurrentTimestamp=9999, calculateChecksum=100) = 100.
    $this->assertEquals(100.0, $result);
  }

  /**
   * @covers ::registerCacheTagsForPreload
   */
  public function testDelayedTagsAreNotPreloaded(): void {
    // A tag whose invalidation is waiting for the transaction to commit is
    // treated as invalid regardless of its stored timestamp, so fetching that
    // timestamp would be wasted, and could cache a stale value for later.
    $this->setUpDatabaseContainer(in_transaction: TRUE);
    $this->checksum->invalidateTags(['tag_a']);

    $this->checksum->registerCacheTagsForPreload(['tag_a', 'tag_b']);

    $this->assertSame(['tag_b'], $this->getProperty('preloadTags'));
  }

  /**
   * @covers ::registerCacheTagsForPreload
   */
  public function testMultiplePreloadRegistrationsAreMerged(): void {
    $this->checksum->registerCacheTagsForPreload(['tag_a', 'tag_b']);
    $this->checksum->registerCacheTagsForPreload(['tag_c']);

    $this->assertEqualsCanonicalizing(
      ['tag_a', 'tag_b', 'tag_c'],
      $this->getProperty('preloadTags'),
    );
  }

}
