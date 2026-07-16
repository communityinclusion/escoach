<?php

namespace Drupal\geofield_map\Plugin\LeafletTileLayerPlugin;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\geofield_map\Attribute\LeafletTileLayerPlugin;
use Drupal\geofield_map\LeafletTileLayerPluginBase;

/**
 * Provides an Stamen_Watercolor Leaflet TileLayer Plugin.
 */
#[LeafletTileLayerPlugin(
  id: "Stamen_Watercolor",
  label: new TranslatableMarkup("Stamen Watercolor"),
  url: "https://tiles.stadiamaps.com/tiles/stamen_watercolor/{z}/{x}/{y}{r}.{ext}",
  options: [
    "minZoom" => 0,
    "maxZoom" => 20,
    "subdomains" => "abcd",
    "attribution" => "&copy; <a href='https://www.stadiamaps.com/'
target='_blank'>Stadia Maps</a> &copy; <a href='https://www.stamen.com/'
target='_blank'>Stamen Design</a> &copy; <a href='https://openmaptiles.org/'
target='_blank''>OpenMapTiles</a> &copy;
<a href='https://www.openstreetmap.org/copyright'>OpenStreetMap</a>
contributors",
    "ext" => "png",
  ],
)]
class StamenWatercolor extends LeafletTileLayerPluginBase {}
