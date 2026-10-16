<?php

namespace Drupal\memcache;

/**
 * Helpers for deferring cache operations until a database transaction ends.
 *
 * Memcache is not part of the database transaction. If a cache item is deleted
 * or a cache tag is invalidated while the transaction that changed the
 * underlying data is still open, a concurrent request can miss the cache,
 * rebuild the item from the not yet committed (old) data and write it back.
 * Once the transaction commits, memcache then holds stale data indefinitely.
 *
 * The database connection is deliberately looked up lazily instead of being
 * injected: constructing the connection service opens a database connection,
 * which would defeat the purpose of serving e.g. page cache hits without ever
 * touching the database.
 */
trait TransactionAwareTrait {

  /**
   * Whether a post-transaction callback has been registered.
   *
   * @var bool
   */
  protected $postTransactionCallbackRegistered = FALSE;

  /**
   * Returns the database connection if one is available.
   *
   * @return \Drupal\Core\Database\Connection|null
   *   The default database connection, or NULL if the container or the
   *   database service is not available (yet).
   */
  protected function getTransactionConnection(): ?object {
    // phpcs:disable DrupalPractice.Objects.GlobalDrupal.GlobalDrupal
    if (!\Drupal::hasContainer() || !\Drupal::hasService('database')) {
      return NULL;
    }
    return \Drupal::database();
    // phpcs:enable
  }

  /**
   * Determines whether a database transaction is currently active.
   *
   * @return bool
   *   TRUE if the default database connection is in a transaction.
   */
  protected function inDatabaseTransaction(): bool {
    $connection = $this->getTransactionConnection();
    return $connection !== NULL && $connection->inTransaction();
  }

  /**
   * Registers a callback to run when the root database transaction ends.
   *
   * The callback receives a single boolean argument: TRUE if the transaction
   * was committed, FALSE if it was rolled back. Callbacks run from within
   * \Drupal\Core\Database\Transaction::__destruct(), so they must only perform
   * cheap, side-effect free work: no rendering, no serialization of arbitrary
   * objects (which may trigger __sleep() and start a Fiber, something PHP
   * before 8.4 does not allow inside a destructor).
   *
   * Only one callback is registered per transaction; subsequent calls until
   * the callback has run are ignored.
   *
   * @param callable $callback
   *   The callback to invoke once the root transaction has ended.
   */
  protected function addPostTransactionCallback(callable $callback): void {
    if ($this->postTransactionCallbackRegistered) {
      return;
    }
    $connection = $this->getTransactionConnection();
    if ($connection === NULL) {
      return;
    }
    // The transaction manager was introduced in Drupal 10.2, but a database
    // driver that has not implemented one yet makes transactionManager()
    // return FALSE rather than throw, so fall back to the legacy callback in
    // that case as well.
    $manager = method_exists($connection, 'transactionManager') ? $connection->transactionManager() : NULL;
    if ($manager) {
      $manager->addPostTransactionCallback($callback);
    }
    else {
      // @phpstan-ignore-next-line
      $connection->addRootTransactionEndCallback($callback);
    }
    $this->postTransactionCallbackRegistered = TRUE;
  }

}
