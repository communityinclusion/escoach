<?php

namespace Drupal\geofield_map\Services;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\key\KeyRepositoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides Google Maps API configuration and localization.
 *
 * This service handles the retrieval of the Google Maps API key from
 * configuration and provides localized API URLs based on the request region.
 */
class GoogleMapsService {

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The Module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Key repository object.
   *
   * @var \Drupal\key\KeyRepositoryInterface
   */
  protected $keyRepository;

  /**
   * The Gmap Api Key.
   *
   * @var string
   */
  protected $gmapApiKey;

  /**
   * The Gmap Api Key.
   *
   * @var array
   */
  public $gmapApiLocalization = [
    'default' => 'maps.googleapis.com/maps/api/js',
    'china' => 'maps.google.cn/maps/api/js',
  ];

  /**
   * Set the module related Gmap API Key.
   *
   * @return string
   *   The GmapApiKey
   */
  protected function setGmapApiKey() {
    $setting = $this->config->get('geofield_map.settings')->get('gmap_api_key');
    // If api key is the id of a key stored in the key module, load that.
    if ($this->moduleHandler->moduleExists('key') && isset($this->keyRepository)) {
      $key = $this->keyRepository->getKey($setting);
      if ($key) {
        $setting = $key->getKeyValue();
      }
    }
    return $setting;
  }

  /**
   * Constructs a new GoogleMapsService object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   A config factory for retrieving required config objects.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\key\KeyRepositoryInterface|null $key_repository
   *   The Key repository object.
   */
  public function __construct(
    ConfigFactoryInterface   $config_factory,
    LanguageManagerInterface $language_manager,
    RequestStack             $request_stack,
    ModuleHandlerInterface   $module_handler,
    ?KeyRepositoryInterface  $key_repository = NULL,
  ) {
    $this->config = $config_factory;
    $this->languageManager = $language_manager;
    $this->moduleHandler = $module_handler;
    $this->requestStack = $request_stack;
    $this->keyRepository = $key_repository;
    $this->gmapApiKey = $this->setGmapApiKey();
  }

  /**
   * Get the module related Gmap API Key.
   *
   * @return string
   *   The GmapApiKey
   */
  public function getGmapApiKey() {
    return $this->gmapApiKey;
  }

  /**
   * Get the localized Gmap API Library.
   *
   * @param string $index
   *   The index parameter.
   *
   * @return string
   *   The Gmap Api library base Url
   */
  public function getGmapApiLocalization($index = 'default') {

    // In case of China, the google maps api should be called as not secure,
    // and this is possible only for not ssl web requests.
    $web_protocol = 'https://';
    if ($index == 'china' && !$this->requestStack->getCurrentRequest()->isSecure()) {
      $web_protocol = 'http://';
    }
    return isset($this->gmapApiLocalization[$index]) ? $web_protocol . $this->gmapApiLocalization[$index] : $web_protocol . $this->gmapApiLocalization['default'];
  }

}
