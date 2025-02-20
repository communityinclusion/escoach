<?php

namespace Drupal\entity_usage;

use Drupal\Core\Url;

/**
 * Interface for a service to determine if a URL references an entity.
 */
interface UrlToEntityInterface {

  /**
   * The entity route regex pattern.
   */
  const ENTITY_ROUTE_PATTERN = '/^entity\.([a-z_]*)\./';

  /**
   * Try to retrieve entity information from a URL string.
   *
   * @param string $url
   *   A URL string.
   *
   * @return string[]|null
   *   An array with two values, the entity type and entity ID, or NULL if no
   *   entity could be retrieved.
   */
  public function findEntityIdByUrl(string $url): ?array;

  /**
   * Try to retrieve entity information from a URL object.
   *
   * @param \Drupal\Core\Url $url
   *   A URL object.
   *
   * @return string[]|null
   *   An array with two values, the entity type and entity ID, or NULL if no
   *   entity could be retrieved.
   */
  public function findEntityIdByRoutedUrl(Url $url): ?array;

}
