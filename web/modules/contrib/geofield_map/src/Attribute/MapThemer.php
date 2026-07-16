<?php

namespace Drupal\geofield_map\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a MapThemer item attribute object.
 *
 * @see \Drupal\geofield_map\MapThemerPluginManager
 * @see plugin_api
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class MapThemer extends Plugin {

  /**
   * Constructs a MapThemer attribute.
   */
  public function __construct(
    public readonly string $id,
    public readonly ?TranslatableMarkup $name,
    public readonly ?TranslatableMarkup $description = NULL,
    public readonly array $context = [],
    public readonly int $weight = 0,
    public readonly array $markerIconSelection = [],
    public readonly array $defaultSettings = [],
    public readonly ?string $deriver = NULL,
  ) {
  }

}
