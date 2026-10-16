<?php

namespace Drupal\memcache\Logger;

use Psr\Log\AbstractLogger;

/**
 * Logs to the PHP error log for use before the container is available.
 *
 * The container cache bin is read and written while the kernel is still
 * building the container, so no logger service exists yet. Anything worth
 * logging at that point goes to error_log() instead, which lands in the web
 * server's error log where a fatal error would otherwise have appeared.
 *
 * @see \Drupal\memcache\Driver\DriverBase::getLogger()
 */
class ErrorLogLogger extends AbstractLogger {

  /**
   * The channel name to prefix each message with.
   *
   * @var string
   */
  protected $channel;

  /**
   * Constructs an ErrorLogLogger.
   *
   * @param string $channel
   *   The channel name, e.g. 'memcache'.
   */
  public function __construct($channel) {
    $this->channel = $channel;
  }

  /**
   * {@inheritdoc}
   */
  public function log($level, $message, array $context = []): void {
    // Replace the Drupal-style @, % and : placeholders that callers use with
    // the logger channel, so the message reads the same in either log.
    $replacements = [];
    foreach ($context as $key => $value) {
      if (is_string($key) && $key !== '' && strpbrk($key[0], '@%:') !== FALSE && is_scalar($value)) {
        $replacements[$key] = (string) $value;
      }
    }
    error_log(sprintf('[%s] %s: %s', $this->channel, $level, strtr((string) $message, $replacements)));
  }

}
