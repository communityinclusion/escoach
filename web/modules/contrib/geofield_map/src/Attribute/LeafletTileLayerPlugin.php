<?php

namespace Drupal\geofield_map\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a LeafletTileLayerPlugin item attribute object.
 *
 * @see \Drupal\geofield_map\LeafletTileLayerPluginManager
 * @see plugin_api
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class LeafletTileLayerPlugin extends Plugin {

  /**
   * Constructs a LeafletTileLayerPlugin attribute.
   */
  public function __construct(
    public readonly string $id,
    public readonly ?TranslatableMarkup $label,
    public readonly string $url,
    public readonly array $options,
    public readonly ?string $deriver = NULL,
  ) {
  }

}
