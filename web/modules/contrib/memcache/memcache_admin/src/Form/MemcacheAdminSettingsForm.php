<?php

namespace Drupal\memcache_admin\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\memcache\MemcacheSettings;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Memcache admin settings form.
 */
class MemcacheAdminSettingsForm extends ConfigFormBase {

  /**
   * The memcache settings.
   *
   * @var \Drupal\memcache\MemcacheSettings
   */
  protected $memcacheSettings;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->memcacheSettings = $container->get('memcache.settings');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'memcache_admin_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    if ($this->memcacheSettings instanceof MemcacheSettings && !$this->memcacheSettings->get('collect_statistics')) {
      $form['collect_statistics'] = [
        '#type' => 'item',
        '#markup' => $this->t("Per-request statistics are not being collected, so nothing will be shown. To collect them, add <code>\$settings['memcache']['collect_statistics'] = TRUE;</code> to settings.php."),
      ];
    }

    $form['show_memcache_statistics'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show memcache statistics at the bottom of each page'),
      '#default_value' => $this->config('memcache_admin.settings')->get('show_memcache_statistics'),
      '#description' => $this->t("These statistics will be visible to users with the 'access memcache statistics' permission. Collecting them must be enabled with <code>\$settings['memcache']['collect_statistics'] = TRUE;</code> in settings.php."),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getEditableConfigNames() {
    return ['memcache_admin.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('memcache_admin.settings')
      ->set('show_memcache_statistics', (bool) $form_state->getValue('show_memcache_statistics'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
