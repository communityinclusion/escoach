<?php

namespace Drupal\geofield_map\Plugin\LeafletTileLayerPlugin;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\geofield_map\Attribute\LeafletTileLayerPlugin;
use Drupal\geofield_map\LeafletTileLayerPluginBase;

/**
 * Provides a GoogleMaps Roads Leaflet TileLayer Plugin.
 */
#[LeafletTileLayerPlugin(
  id: "GoogleMaps_Roads",
  label: new TranslatableMarkup("GoogleMaps Roads"),
  url: "https://mt{s}.googleapis.com/vt?x={x}&y={y}&z={z}",
  options: [
    "attribution" => "Map data &copy; <a href='https://googlemaps.com'>Google</a>",
    'detectRetina' => FALSE,
    'subdomains' => [0, 1, 2, 3],
  ],
)]
class GoogleMapsRoads extends LeafletTileLayerPluginBase {}
