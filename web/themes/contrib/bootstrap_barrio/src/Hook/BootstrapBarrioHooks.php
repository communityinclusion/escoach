<?php

namespace Drupal\bootstrap_barrio\Hook;

use Drupal\Component\Utility\DeprecationHelper;
use Drupal\Core\Extension\ThemeSettingsProvider;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Link;
use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\Xss;
use Drupal\node\Entity\Node;
use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Hook\Attribute\Hook;
/**
 * Hook implementations for bootstrap_barrio.
 */
class BootstrapBarrioHooks
{
    /**
     * Implements hook_preprocess_HOOK() for HTML document templates.
     *
     * Adds body classes if certain regions have content.
     */
    #[Hook('preprocess_html')]
    public function preprocessHtml(&$variables)
    {
        if (!isset($variables['attributes']['class'])) {
            $variables['attributes']['class'] = [
            ];
        }
        // Color settings
        if (DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('bootstrap_barrio_enable_color'), fn() => theme_get_setting('bootstrap_barrio_enable_color'))) {
            $variables['#attached']['library'][] = 'bootstrap_barrio/colors';
            // CSS variables
            $gray200 = '#e9ecef';
            $gray800 = '#343a40';
            $white = '#fff';
            $pc = DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('bootstrap_barrio_base_primary_color'), fn() => theme_get_setting('bootstrap_barrio_base_primary_color')) ?? '#2F3C7E';
            $pcrgb = _hex2rgb($pc);
            $pcd = _color_luminance($pc, -0.15);
            $bc = '#000';
            $pct = _getContrastColor($pc, $white, $bc);
            $sc = DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('bootstrap_barrio_base_secondary_color'), fn() => theme_get_setting('bootstrap_barrio_base_secondary_color')) ?? '#FBEAEB';
            $scrgb = _hex2rgb($sc);
            $scd = _color_luminance($sc, -0.15);
            $sct = _getContrastColor($sc, $white, $gray800);
            $colors = [
                'white' => $white,
                'black' => '#000',
                'gray-200' => $gray200,
                'gray-800' => $gray800,
                'primary' => $pc,
                'secondary' => $sc,
            ];
            $color = _get_color(DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('bootstrap_barrio_body_color'), fn() => theme_get_setting('bootstrap_barrio_body_color')) ?? 'gray-800', $colors);
            $colors['base'] = $color;
            $bg = _get_color(DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('bootstrap_barrio_body_bg_color'), fn() => theme_get_setting('bootstrap_barrio_body_bg_color')) ?? 'white', $colors);
            $h1 = _get_color(DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('bootstrap_barrio_h1_color'), fn() => theme_get_setting('bootstrap_barrio_h1_color')) ?? 'base', $colors);
            $h2 = _get_color(DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('bootstrap_barrio_h2_color'), fn() => theme_get_setting('bootstrap_barrio_h2_color')) ?? 'base', $colors);
            $h3 = _get_color(DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('bootstrap_barrio_h3_color'), fn() => theme_get_setting('bootstrap_barrio_h3_color')) ?? 'base', $colors);
            $styles = [
                "--bs-primary:{$pc};",
                "--bb-primary-dark:{$pcd};",
                "--bb-primary-light:{$pct};",
                "--bs-primary-rgb:{$pcrgb};",
                "--bs-secondary:{$sc};",
                "--bb-secondary-dark:{$scd};",
                "--bs-secondary-rgb:{$scrgb};",
                "--bs-link-color:{$pc};",
                "--bs-link-hover-color:{$pcd};",
                "--bs-body-color:{$color};",
                "--bs-body-bg:{$bg};",
                "--bb-h1-color:{$h1};",
                "--bb-h2-color:{$h2};",
                "--bb-h3-color:{$h3};",
            ];
            $variables['html_attributes']->setAttribute('style', implode('', $styles));
        }
        // Add information about the number of sidebars.
        if (!empty($variables['page']['sidebar_first']) && !empty($variables['page']['sidebar_second'])) {
            $variables['attributes']['class'][] = 'layout-two-sidebars';
        } elseif (!empty($variables['page']['sidebar_first'])) {
            $variables['attributes']['class'][] = 'layout-one-sidebar';
            $variables['attributes']['class'][] = 'layout-sidebar-first';
        } elseif (!empty($variables['page']['sidebar_second'])) {
            $variables['attributes']['class'][] = 'layout-one-sidebar';
            $variables['attributes']['class'][] = 'layout-sidebar-second';
        } else {
            $variables['attributes']['class'][] = 'layout-no-sidebars';
        }
        if (!empty($variables['page']['featured_top'])) {
            $variables['attributes']['class'][] = 'has-featured-top';
        }
        // Scroll Spy info added to body.
        if ($scroll_spy = DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('bootstrap_barrio_scroll_spy'), fn() => theme_get_setting('bootstrap_barrio_scroll_spy'))) {
            $variables['attributes']['data-bs-target'] = $scroll_spy;
            $variables['attributes']['data-bs-spy'] = "scroll";
        }
        // Page specific info.
        $variables['path_info']['args'] = FALSE;
        $path = \Drupal::service('path.current')->getPath();
        $path = ltrim($path, '/');
        $path_args = explode('/', $path);
        if (isset($path_args[0]) && isset($path_args[1]) && $path_args[0] == 'node' && is_numeric($path_args[1])) {
            $variables['attributes']['class'][] = 'page-node-' . $path_args[1];
        }
        if (isset($path_args[0]) && isset($path_args[1]) && $path_args[0] == 'user') {
            $variables['attributes']['class'][] = 'page-user-' . $path_args[1];
        }
        if (isset($path_args[0]) && isset($path_args[1]) && isset($path_args[2]) && $path_args[0] == 'taxonomy' && $path_args[1] == 'term') {
            $variables['attributes']['class'][] = 'page-taxonomy-term-' . $path_args[2];
            $term = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($path_args[2]);
            if (!empty($term)) {
                $vocabulary_id = $term->bundle();
                $variables['attributes']['class'][] = 'page-vocabulary-' . Html::cleanCssIdentifier($vocabulary_id);
            }
        }
        $view_id = \Drupal::routeMatch()->getParameter('view_id');
        if ($view_id) {
            $variables['attributes']['class'][] = 'page-view-' . Html::cleanCssIdentifier($view_id);
        }
        // SEO Information.
        $http_equiv = [
            '#type' => 'html_tag',
            '#tag' => 'meta',
            '#attributes' => [
                'http-equiv' => 'x-ua-compatible',
                'content' => 'ie=edge',
            ],
        ];
        $variables['page']['#attached']['html_head'][] = [
            $http_equiv,
            'http-equiv',
        ];
    }
    /**
     * Implements hook_page_attachments_alter() for page templates.
     */
    #[Hook('page_attachments_alter')]
    public function pageAttachmentsAlter(&$page)
    {
        $viewport = [
            '#type' => 'html_tag',
            '#tag' => 'meta',
            '#attributes' => [
                'name' => 'viewport',
                'content' => 'width=device-width, initial-scale=1, shrink-to-fit=no',
            ],
        ];
        $page['#attached']['html_head'][] = [
            $viewport,
            'viewport',
        ];
    }
    /**
     * Implements hook_preprocess_HOOK() for page templates.
     */
    #[Hook('preprocess_page')]
    public function preprocessPage(&$variables)
    {
        if (\Drupal::moduleHandler()->moduleExists('bootstrap_library')) {
            $library = DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('bootstrap_barrio_library'), fn() => theme_get_setting('bootstrap_barrio_library'));
            switch ($library) {
                case 'cdn':
                    $variables['#attached']['library'][] = 'bootstrap_library/bootstrap-cdn';
                    break;
                case 'production':
                    $variables['#attached']['library'][] = 'bootstrap_library/bootstrap';
                    break;
                case 'development':
                    $variables['#attached']['library'][] = 'bootstrap_library/bootstrap-dev';
                    break;
            }
        } else {
            $source = DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('bootstrap_barrio_source'), fn() => theme_get_setting('bootstrap_barrio_source'));
            if ($source) {
                $variables['#attached']['library'][] = $source;
            }
        }
        $img_fluid = DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('bootstrap_barrio_image_fluid'), fn() => theme_get_setting('bootstrap_barrio_image_fluid'));
        if ($img_fluid) {
            $variables['#attached']['library'][] = 'bootstrap_barrio/image_fluid';
        }
        $flyout = DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('bootstrap_barrio_navbar_flyout'), fn() => theme_get_setting('bootstrap_barrio_navbar_flyout'));
        if ($flyout) {
            $variables['#attached']['library'][] = 'bootstrap_barrio/navigation';
        }
        $slide = DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('bootstrap_barrio_navbar_slide'), fn() => theme_get_setting('bootstrap_barrio_navbar_slide'));
        if ($slide) {
            $variables['#attached']['library'][] = 'bootstrap_barrio/slide_nav';
        }
        switch (DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('bootstrap_barrio_tabs_style'), fn() => theme_get_setting('bootstrap_barrio_tabs_style'))) {
            case 'full':
                $variables['#attached']['library'][] = 'bootstrap_barrio/tabs_full';
                break;
        }
        $fonts = DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('bootstrap_barrio_google_fonts'), fn() => theme_get_setting('bootstrap_barrio_google_fonts'));
        if ($fonts) {
            $variables['#attached']['library'][] = 'bootstrap_barrio/' . $fonts;
        }
        $icons = DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('bootstrap_barrio_bootstrap_icons'), fn() => theme_get_setting('bootstrap_barrio_bootstrap_icons'));
        if ($icons) {
            $variables['#attached']['library'][] = 'bootstrap_barrio/bootstrap-icons';
        }
        $icons = DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('bootstrap_barrio_icons'), fn() => theme_get_setting('bootstrap_barrio_icons'));
        if ($icons) {
            $variables['#attached']['library'][] = 'bootstrap_barrio/' . $icons;
        }
        $system_messages = DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('bootstrap_barrio_system_messages'), fn() => theme_get_setting('bootstrap_barrio_system_messages'));
        if ($system_messages) {
            $variables['#attached']['library'][] = 'bootstrap_barrio/' . $system_messages;
        }
        $content_width = 'col';
        $sidebar_first_width = 'col-md-' . DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('bootstrap_barrio_sidebar_first_width'), fn() => theme_get_setting('bootstrap_barrio_sidebar_first_width'));
        $sidebar_second_width = 'col-md-' . DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('bootstrap_barrio_sidebar_second_width'), fn() => theme_get_setting('bootstrap_barrio_sidebar_second_width'));
        // Define default classes for containers.
        $variables['navbar_top_attributes'] = [
            'class' => [
                'navbar',
            ],
            'id' => [
                'navbar-top',
            ],
        ];
        $variables['navbar_attributes'] = [
            'class' => [
                'navbar',
            ],
            'id' => [
                'navbar-main',
            ],
        ];
        $variables['content_attributes'] = [
            'class' => [
                'main-content',
                $content_width,
            ],
            'id' => [
                'content',
            ],
            'role' => [
                'main',
            ],
        ];
        $variables['sidebar_first_attributes'] = [
            'class' => [
                'sidebar_first',
                'sidebar',
                $sidebar_first_width,
            ],
            'id' => [
                'sidebar_first',
            ],
        ];
        $variables['sidebar_second_attributes'] = [
            'class' => [
                'sidebar_second',
                'sidebar',
                $sidebar_second_width,
            ],
            'id' => [
                'sidebar_second',
            ],
        ];
        // Get custom Navbar classes from config and add them to Navbars.
        if (DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('bootstrap_barrio_navbar_top_class'), fn() => theme_get_setting('bootstrap_barrio_navbar_top_class')) !== NULL) {
            $variables['navbar_top_attributes']['class'][] = DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('bootstrap_barrio_navbar_top_class'), fn() => theme_get_setting('bootstrap_barrio_navbar_top_class'));
        }
        if (DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('bootstrap_barrio_navbar_class'), fn() => theme_get_setting('bootstrap_barrio_navbar_class')) !== NULL) {
            $variables['navbar_attributes']['class'][] = DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('bootstrap_barrio_navbar_class'), fn() => theme_get_setting('bootstrap_barrio_navbar_class'));
        }
        switch (DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('bootstrap_barrio_navbar_offcanvas'), fn() => theme_get_setting('bootstrap_barrio_navbar_offcanvas'))) {
            case 'offcanvas-collapse':
                $variables['navbar_offcanvas'] = TRUE;
                $variables['navbar_collapse_class'] = "offcanvas offcanvas-end";
                $variables['navbar_collapse_btn_data'] = "offcanvas";
                break;
            default:
                $variables['navbar_offcanvas'] = FALSE;
                $variables['navbar_collapse_class'] = 'collapse navbar-collapse justify-content-end';
                $variables['navbar_collapse_btn_data'] = "collapse";
        }
        // Affix.
        $affix_navbar_top = DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('bootstrap_barrio_navbar_top_affix'), fn() => theme_get_setting('bootstrap_barrio_navbar_top_affix'));
        $affix_navbar = DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('bootstrap_barrio_navbar_affix'), fn() => theme_get_setting('bootstrap_barrio_navbar_affix'));
        $affix_sidebar_first = DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('bootstrap_barrio_sidebar_first_affix'), fn() => theme_get_setting('bootstrap_barrio_sidebar_first_affix'));
        $affix_sidebar_second = DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('bootstrap_barrio_sidebar_second_affix'), fn() => theme_get_setting('bootstrap_barrio_sidebar_second_affix'));
        if ($affix_navbar_top || $affix_navbar || $affix_sidebar_first || $affix_sidebar_second) {
            $variables['#attached']['library'][] = 'bootstrap_barrio/affix';
            if ($affix_navbar_top) {
                $variables['navbar_top_attributes']['data-toggle'] = 'affix';
            }
            if ($affix_navbar) {
                $variables['navbar_attributes']['data-toggle'] = 'affix';
            }
            if ($affix_sidebar_first) {
                $variables['sidebar_first_attributes']['data-toggle'] = 'affix';
            }
            if ($affix_sidebar_second) {
                $variables['sidebar_second_attributes']['data-toggle'] = 'affix';
            }
        }
        // Calculate widths.
        if (DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('bootstrap_barrio_content_offset'), fn() => theme_get_setting('bootstrap_barrio_content_offset'))) {
            $variables['content_attributes']['class'][] = 'offset-md-' . DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('bootstrap_barrio_content_offset'), fn() => theme_get_setting('bootstrap_barrio_content_offset'));
        }
        if (DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('bootstrap_barrio_sidebar_first_offset'), fn() => theme_get_setting('bootstrap_barrio_sidebar_first_offset'))) {
            $variables['sidebar_first_attributes']['class'][] = 'offset-md-' . DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('bootstrap_barrio_sidebar_first_offset'), fn() => theme_get_setting('bootstrap_barrio_sidebar_first_offset'));
        }
        if (DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('bootstrap_barrio_sidebar_second_offset'), fn() => theme_get_setting('bootstrap_barrio_sidebar_second_offset'))) {
            $variables['sidebar_second_attributes']['class'][] = 'offset-md-' . DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('bootstrap_barrio_sidebar_second_offset'), fn() => theme_get_setting('bootstrap_barrio_sidebar_second_offset'));
        }
        // Define column layout classes.
        switch (DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('bootstrap_barrio_sidebar_position'), fn() => theme_get_setting('bootstrap_barrio_sidebar_position'))) {
            case 'left':
                $variables['sidebar_first_attributes']['class'][] = 'order-first';
                $variables['content_attributes']['class'][] = 'order-last';
                break;
            case 'both':
                $variables['sidebar_first_attributes']['class'][] = 'order-first';
                $variables['sidebar_second_attributes']['class'][] = 'order-last';
                break;
            case 'right':
                $variables['content_attributes']['class'][] = 'order-first';
                $variables['sidebar_second_attributes']['class'][] = 'order-last';
                break;
        }
        // Define navigation position.
        $variables['navbar_top_attributes']['class'][] = DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('bootstrap_barrio_navbar_top_position'), fn() => theme_get_setting('bootstrap_barrio_navbar_top_position'));
        $variables['navbar_attributes']['class'][] = DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('bootstrap_barrio_navbar_position'), fn() => theme_get_setting('bootstrap_barrio_navbar_position'));
        // Define navigation color scheme.
        $variables['navbar_top_attributes']['class'][] = DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('bootstrap_barrio_navbar_top_color'), fn() => theme_get_setting('bootstrap_barrio_navbar_top_color'));
        $variables['navbar_attributes']['class'][] = DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('bootstrap_barrio_navbar_color'), fn() => theme_get_setting('bootstrap_barrio_navbar_color'));
        // Define navigation background classes.
        $variables['navbar_top_attributes']['class'][] = DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('bootstrap_barrio_navbar_top_background'), fn() => theme_get_setting('bootstrap_barrio_navbar_top_background'));
        $variables['navbar_attributes']['class'][] = DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('bootstrap_barrio_navbar_background'), fn() => theme_get_setting('bootstrap_barrio_navbar_background'));
        // Define navigation toggle size.
        switch (DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('bootstrap_barrio_navbar_toggle'), fn() => theme_get_setting('bootstrap_barrio_navbar_toggle'))) {
            case 'navbar-toggleable-xl':
                $variables['navbar_attributes']['class'][] = 'navbar-expand-xl';
                break;
            case 'navbar-toggleable-lg':
                $variables['navbar_attributes']['class'][] = 'navbar-expand-lg';
                break;
            case 'navbar-toggleable-md':
                $variables['navbar_attributes']['class'][] = 'navbar-expand-md';
                break;
            case 'navbar-toggleable-sm':
                $variables['navbar_attributes']['class'][] = 'navbar-expand-sm';
                break;
            case 'navbar-toggleable-xs':
                $variables['navbar_attributes']['class'][] = 'navbar-expand-xs';
                break;
            case 'navbar-toggleable-all':
                $variables['navbar_attributes']['class'][] = '';
                break;
            default:
                $variables['navbar_attributes']['class'][] = 'navbar-expand-md';
                break;
        }
        if (DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('bootstrap_barrio_sidebar_collapse'), fn() => theme_get_setting('bootstrap_barrio_sidebar_collapse'))) {
            $variables['content_attributes']['class'][] = 'sidebar-offcanvas';
            $variables['sidebar_collapse'] = TRUE;
            $variables['#attached']['library'][] = 'bootstrap_barrio/sidebar_collapse';
        }
        // Declare region attributes.
        $variables['navbar_top_attributes'] = new Attribute($variables['navbar_top_attributes']);
        $variables['navbar_attributes'] = new Attribute($variables['navbar_attributes']);
        $variables['content_attributes'] = new Attribute($variables['content_attributes']);
        $variables['sidebar_first_attributes'] = new Attribute($variables['sidebar_first_attributes']);
        $variables['sidebar_second_attributes'] = new Attribute($variables['sidebar_second_attributes']);
        // Declare container layout.
        $variables['container_navbar'] = DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('bootstrap_barrio_navbar_container'), fn() => theme_get_setting('bootstrap_barrio_navbar_container'));
        $variables['container'] = DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('bootstrap_barrio_container'), fn() => theme_get_setting('bootstrap_barrio_container')) ?? (DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('bootstrap_barrio_fluid_container'), fn() => theme_get_setting('bootstrap_barrio_fluid_container')) ? 'container-fluid' : 'container');
    }
    /**
     * Implements hook_preprocess_HOOK() for page templates.
     */
    #[Hook('preprocess_page_title')]
    public function preprocessPageTitle(&$variables)
    {
        // Since the title and the shortcut link are both block level elements,
        // positioning them next to each other is much simpler with a wrapper div.
        if (!empty($variables['title_suffix']['add_or_remove_shortcut']) && $variables['title']) {
            // Add a wrapper div using the title_prefix and title_suffix render
            // elements.
            $variables['title_prefix']['shortcut_wrapper'] = [
                '#markup' => '<div class="shortcut-wrapper clearfix">',
                '#weight' => 100,
            ];
            $variables['title_suffix']['shortcut_wrapper'] = [
                '#markup' => '</div>',
                '#weight' => -99,
            ];
            // Make sure the shortcut link is the first item in title_suffix.
            $variables['title_suffix']['add_or_remove_shortcut']['#weight'] = -100;
        }
    }
    /**
     * Implements hook_preprocess_HOOK() for maintenance-page.html.twig.
     */
    #[Hook('preprocess_maintenance_page')]
    public function preprocessMaintenancePage(&$variables)
    {
        // By default, site_name is set to Drupal if no db connection is available
        // or during site installation. Setting site_name to an empty string makes
        // the site and update pages look cleaner.
        // @see template_preprocess_maintenance_page
        if (!$variables['db_is_active']) {
            $variables['site_name'] = '';
        }
        // Bootstrap Barrio has custom styling for the maintenance page.
        $variables['#attached']['library'][] = 'bootstrap_barrio/maintenance_page';
    }
    /**
     * Implements hook_preprocess_HOOK() for node.html.twig.
     */
    #[Hook('preprocess_node')]
    public function preprocessNode(&$variables)
    {
        // Remove the "Add new comment" link on teasers or when the comment form is
        // displayed on the page.
        // The "teaser" variable was removed from core node preprocessing;
        // use view_mode instead (@see NodeThemeHooks::preprocessNode()).
        if ($variables['view_mode'] === 'teaser' || !empty($variables['content']['comments']['comment_form'])) {
            unset($variables['content']['links']['comment']['#links']['comment-add']);
        }
        // Hide label on all nodes
        if (DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('bootstrap_barrio_hide_node_label'), fn() => theme_get_setting('bootstrap_barrio_hide_node_label')) != NULL) {
            unset($variables['label']);
        }
    }
    /**
     * Implements hook_preprocess_HOOK() for region.html.twig.
     */
    #[Hook('preprocess_region')]
    public function preprocessRegion(&$variables)
    {
        $nowrap = [
            'breadcrumb',
            'highlighted',
            'content',
            'primary_menu',
            'header',
            'sidebar_first',
            'sidebar_second',
        ];
        if (DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('bootstrap_barrio_region_class_' . $variables['elements']['#region']), fn() => theme_get_setting('bootstrap_barrio_region_class_' . $variables['elements']['#region'])) !== NULL) {
            $variables['attributes']['class'][] = DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('bootstrap_barrio_region_class_' . $variables['elements']['#region']), fn() => theme_get_setting('bootstrap_barrio_region_class_' . $variables['elements']['#region']));
        } elseif (!in_array($variables['elements']['#region'], $nowrap)) {
            $variables['attributes']['class'][] = 'row';
        }
        if (DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('bootstrap_barrio_region_clean_' . $variables['elements']['#region']), fn() => theme_get_setting('bootstrap_barrio_region_clean_' . $variables['elements']['#region'])) !== NULL) {
            $variables['clean'] = DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('bootstrap_barrio_region_clean_' . $variables['elements']['#region']), fn() => theme_get_setting('bootstrap_barrio_region_clean_' . $variables['elements']['#region']));
        } else {
            $variables['clean'] = FALSE;
        }
        $variables['container'] = DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('bootstrap_barrio_container'), fn() => theme_get_setting('bootstrap_barrio_container')) ?? (DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('bootstrap_barrio_fluid_container'), fn() => theme_get_setting('bootstrap_barrio_fluid_container')) ? 'container-fluid' : 'container');
    }
    /**
     * Implements hook_preprocess_HOOK() for block.html.twig.
     */
    #[Hook('preprocess_block')]
    public function preprocessBlock(&$variables)
    {
        if ($variables['base_plugin_id'] == 'system_menu_block' && isset($variables['attributes']['id'])) {
            $variables['content']['#attributes']['id'] = $variables['attributes']['id'];
        }
        // Add a clearfix class to system branding blocks.
        if ($variables['plugin_id'] == 'system_branding_block') {
            $variables['attributes']['class'][] = 'clearfix';
        }
        $block = $variables['elements'];
        if (!empty($block['#configuration']['provider']) && $block['#configuration']['provider'] == "block_content") {
            if (isset($block['content']['#block_content']) && $block['content']['#block_content'] !== null) {
                $bundle = $block['content']['#block_content']->bundle();
                $variables['attributes']['class'][] = 'block-content-' . $bundle;
            }
        }
    }
    /**
     * Implements hook_preprocess_HOOK() for form elements.
     */
    #[Hook('preprocess_form_element')]
    public function preprocessFormElement(&$variables)
    {
        if (isset($variables['label']['#title'])) {
            if (is_object($variables['label']['#title']) && !$variables['label']['#title'] instanceof MarkupInterface) {
                $variables['input_title'] = NULL;
            } elseif (is_object($variables['label']['#title'])) {
                $variables['input_title'] = $variables['label']['#title'];
            } else {
                $variables['input_title'] = $variables['label']['#title'];
            }
        }
        if (DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('bootstrap_barrio_float_label'), fn() => theme_get_setting('bootstrap_barrio_float_label')) && $variables['element']['#type'] != 'radio' && $variables['element']['#type'] != 'checkbox' && $variables['element']['#type'] != 'date' && $variables['element']['#type'] != 'time' && $variables['element']['#type'] != 'search' && $variables['element']['#type'] != 'select' && $variables['element']['#type'] != 'textarea' && $variables['element']['#type'] != 'managed_file') {
            $variables['float_label'] = DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('bootstrap_barrio_float_label'), fn() => theme_get_setting('bootstrap_barrio_float_label'));
            $variables['label_display'] = 'after';
        }
        if (isset($variables['element']['#attributes'])) {
            $variables['input_attributes'] = new Attribute($variables['element']['#attributes']);
        }
        if (isset($variables['element']['#type']) && ($variables['element']['#type'] == 'radio' || $variables['element']['#type'] == 'checkbox')) {
            switch (DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('bootstrap_barrio_checkbox'), fn() => theme_get_setting('bootstrap_barrio_checkbox'))) {
                case 'switch':
                    $variables['checkbox_style'] = 'form-switch';
                    break;
                case 'button':
                    $variables['checkbox_style'] = 'form-button';
                    break;
            }
        }
    }
    /**
     * Implements hook_preprocess_HOOK() for form-element-label.html.twig.
     */
    #[Hook('preprocess_form_element_label')]
    public function preprocessFormElementLabel(&$variables)
    {
        $element = $variables['element'];
    }
    /**
     * Implements hook_preprocess_HOOK() for menu.html.twig.
     */
    #[Hook('preprocess_menu')]
    public function preprocessMenu(&$variables)
    {
        $variables['attributes']['class'][] = 'clearfix';
        // MenuLinkTree::buildItems() keys items by plugin ID, producing
        // associative arrays that json_encode() (and thus the SDC prop
        // validator) treats as objects instead of lists. Bootstrap Barrio's
        // menu components (menu_main, menu_columns) declare "items" as a
        // JSON schema array, so re-index the tree recursively.
        if (!empty($variables['items'])) {
            $variables['items'] = $this->reindexMenuTree($variables['items']);
        }
    }

    /**
     * Recursively re-indexes a menu tree so every level is a sequential list.
     */
    private function reindexMenuTree(array $items): array
    {
        $items = array_values($items);
        foreach ($items as &$item) {
            if (!empty($item['below']) && is_array($item['below'])) {
                $item['below'] = $this->reindexMenuTree($item['below']);
            }
        }
        return $items;
    }
    /**
     * Implements hook_preprocess_HOOK() for menu-local-task.html.twig.
     */
    #[Hook('preprocess_menu_local_task')]
    public function preprocessMenuLocalTask(&$variables)
    {
        $link = $variables['element']['#link'];
        $url = $link['url'];
        $options = $url->getOptions();
        if (isset($link) && isset($link['localized_options'])) {
            $url->setOptions($options + $link['localized_options']);
        } else {
            $url->setOptions($options);
        }
        $variables['item'] = Link::fromTextAndUrl($link['title'], $url);
    }
    /**
     * Implements hook_preprocess_HOOK() for menu-local-tasks.html.twig.
     */
    #[Hook('preprocess_menu_local_tasks')]
    public function preprocessMenuLocalTasks(&$variables)
    {
        switch (DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('bootstrap_barrio_tabs_style'), fn() => theme_get_setting('bootstrap_barrio_tabs_style'))) {
            case 'pills':
                $variables['nav_style'] = 'nav-pills';
                break;
            default:
                $variables['nav_style'] = 'nav-tabs';
                break;
        }
    }
    /**
     * Implements hook_theme_suggestions_HOOK_alter().
     */
    #[Hook('theme_suggestions_menu_alter')]
    public function themeSuggestionsMenuAlter(array &$suggestions, array $variables)
    {
        // Taken from http://kristiankaa.dk/article/drupal8-region-specific-menu-theme-hook-suggestion
        // bootstrap_barrio_preprocess_block(&$variables)
        if (isset($variables['attributes']['block'])) {
            $hook = str_replace([
                'block-',
                '-',
            ], [
                '',
                '_',
            ], $variables['attributes']['block']);
            $suggestions[] = $variables['theme_hook_original'] . '__' . $hook;
        }
    }
    /**
     * Implements hook_preprocess_HOOK() for menu-local-action.html.twig.
     */
    #[Hook('preprocess_menu_local_action')]
    public function preprocessMenuLocalAction(&$variables)
    {
        $link = $variables['element']['#link'];
        $link += [
            'localized_options' => [
            ],
        ];
        $link['localized_options']['attributes']['class'][] = 'btn';
        $link['localized_options']['attributes']['class'][] = 'btn-secondary';
        $link['localized_options']['set_active_class'] = TRUE;
        $variables['link'] = [
            '#type' => 'link',
            '#title' => $link['title'],
            '#options' => $link['localized_options'],
            '#url' => $link['url'],
        ];
    }
    /**
     * Implements hook_theme_suggestions_HOOK_alter() for region templates.
     */
    #[Hook('theme_suggestions_region_alter')]
    public function themeSuggestionsRegionAlter(array &$suggestions, array $variables)
    {
        $nowrap = [
            'breadcrumb',
            'highlighted',
            'content',
            'primary_menu',
            'header',
            'sidebar_first',
            'sidebar_second',
        ];
        if (DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('bootstrap_barrio_region_clean_' . $variables['elements']['#region']), fn() => theme_get_setting('bootstrap_barrio_region_clean_' . $variables['elements']['#region'])) !== NULL) {
            $region_clean = DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('bootstrap_barrio_region_clean_' . $variables['elements']['#region']), fn() => theme_get_setting('bootstrap_barrio_region_clean_' . $variables['elements']['#region']));
        } else {
            $region_clean = in_array($variables['elements']['#region'], $nowrap);
        }
        $region_width = DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('bootstrap_barrio_region_width_' . $variables['elements']['#region']), fn() => theme_get_setting('bootstrap_barrio_region_width_' . $variables['elements']['#region']));
        if ($region_width) {
            $suggestions[] = 'region__full_width';
        } elseif ($region_clean) {
            $suggestions[] = 'region__nowrap';
        }
    }
    /**
     * Implements hook_theme_suggestions_HOOK_alter().
     */
    #[Hook('theme_suggestions_block_alter')]
    public function themeSuggestionsBlockAlter(array &$suggestions, array $variables)
    {
        $block = $variables['elements'];
        if (!empty($block['#configuration']['provider']) && $block['#configuration']['provider'] == "block_content") {
            if (isset($block['content']['#block_content']) && $block['content']['#block_content'] !== null) {
                $bundle = $block['content']['#block_content']->bundle();
                array_splice($suggestions, 2, 0, 'block__block_' . $bundle);
            }
        }
    }
    /**
     * Implements hook_theme_suggestions_HOOK_alter().
     */
    #[Hook('theme_suggestions_user_alter')]
    public function themeSuggestionsUserAlter(array &$suggestions, array $variables, $hook)
    {
        // Define the view mode.
        $sanitized_view_mode = strtr($variables['elements']['#view_mode'], '.', '_');
        $user_id = \Drupal::service('current_user')->id();
        $suggestions[] = 'user__' . $sanitized_view_mode;
        $suggestions[] = 'user__' . $user_id;
        $suggestions[] = 'user__' . $user_id . '__' . $sanitized_view_mode;
        return $suggestions;
    }
    /**
     * Implements hook_theme_suggestions_HOOK_alter() for page template.
     */
    #[Hook('theme_suggestions_page_alter')]
    public function themeSuggestionsPageAlter(array &$suggestions, array $variables)
    {
        // Add a template suggestions for node type.
        /** @var \Drupal\node\NodeInterface $node */
        if ($node = \Drupal::routeMatch()->getParameter('node')) {
            if (is_numeric($node)) {
                $node = Node::load($node);
            }
            $suggestions[] = 'page__node__' . $node->bundle();
        }
    }
    /**
     * Implements hook_theme_suggestions_HOOK_alter() for links templates.
     */
    #[Hook('theme_suggestions_links_alter')]
    public function themeSuggestionsLinksAlter(array &$suggestions, array $variables)
    {
        // ToDo get from core a variable to flag inline links.
        if (isset($variables['attributes']['class'])) {
            if (in_array('inline', $variables['attributes']['class'])) {
                $suggestions[] = 'links__inline';
            }
        }
    }
    /**
     * Implements hook_theme_suggestions_HOOK_alter() for status messages templates.
     */
    #[Hook('theme_suggestions_status_messages_alter')]
    public function themeSuggestionsStatusMessagesAlter(array &$suggestions, array $variables)
    {
        switch (DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('bootstrap_barrio_messages_widget'), fn() => theme_get_setting('bootstrap_barrio_messages_widget'))) {
            case 'toasts':
                $suggestions[] = 'status_messages__toasts';
                break;
            case 'alerts':
                $suggestions[] = 'status_messages__alerts';
                break;
        }
    }
    /**
     * Implements hook_preprocess_HOOK() for breadcrumb.
     */
    #[Hook('preprocess_breadcrumb')]
    public function preprocessBreadcrumb(&$variables)
    {
        if (DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('bootstrap_barrio_breadcrumb_divider'), fn() => theme_get_setting('bootstrap_barrio_breadcrumb_divider')) && DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('bootstrap_barrio_breadcrumb_divider'), fn() => theme_get_setting('bootstrap_barrio_breadcrumb_divider')) != '') {
            $variables['breadcrumb_divider'] = sprintf("--bs-breadcrumb-divider: '%s';", DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('bootstrap_barrio_breadcrumb_divider'), fn() => theme_get_setting('bootstrap_barrio_breadcrumb_divider')));
        }
    }
    /**
     * Implements hook_preprocess_HOOK() for status_messages.
     */
    #[Hook('preprocess_status_messages')]
    public function preprocessStatusMessages(&$variables)
    {
        $variables['title_ids'] = [
        ];
        if (isset($variables['message_list']) && is_array($variables['message_list'])) {
            foreach ($variables['message_list'] as $message_type => $messages) {
                $variables['title_ids'][$message_type] = Html::getUniqueId("message-{$message_type}-title");
            }
        }
        if (DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('bootstrap_barrio_messages_widget'), fn() => theme_get_setting('bootstrap_barrio_messages_widget')) === 'toasts') {
            $variables['attributes']['data-bs-delay'] = DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('bootstrap_barrio_messages_widget_toast_delay'), fn() => theme_get_setting('bootstrap_barrio_messages_widget_toast_delay')) ?? 10000;
        }
    }
    /**
     * Implements hook_theme_suggestions_HOOK_alter() for form templates.
     */
    #[Hook('theme_suggestions_form_alter')]
    public function themeSuggestionsFormAlter(array &$suggestions, array $variables)
    {
        if ($variables['element']['#form_id'] == 'search_block_form') {
            $suggestions[] = 'form__search_block_form';
        }
        if ($variables['element']['#form_id'] == 'node_form') {
            $suggestions[] = 'node_edit_form';
        }
    }
    /**
     * Implements hook_theme_suggestions_HOOK_alter() for form templates.
     */
    #[Hook('theme_suggestions_input_alter')]
    public function themeSuggestionsInputAlter(array &$suggestions, array $variables)
    {
        if ($variables['element']['#type'] == 'submit' && DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('bootstrap_barrio_button'), fn() => theme_get_setting('bootstrap_barrio_button'))) {
            $suggestions[] = 'input__submit_button';
        }
    }
    /**
     * Implements hook_form_alter() to add classes to the search form.
     */
    /*
     * function bootstrap_barrio_form_alter(
     * &$form, \Drupal\Core\Form\FormStateInterface $form_state, $form_id) {
     *  if (in_array($form_id, ['search_block_form', 'search_form'])) {
     *    $key = ($form_id == 'search_block_form') ? 'actions' : 'basic';
     *    if (!isset($form[$key]['submit']['#attributes'])) {
     *      $form[$key]['submit']['#attributes'] = new Attribute();
     *    }
     *    $outline = theme_get_setting('bootstrap_barrio_button_outline') ?
     *    'btn-outline-primary' : 'btn-primary';
     * If use the $outline variable, HTML crashes. Even if use the value directly
     *    $form[$key]['submit']['#attributes']->addClass(
     *    ['btn', 'btn-primary', 'btn-sm']);
     *  }
     * }
     */
    /**
     * Implements hook_preprocess().
     */
    #[Hook('preprocess')]
    public function preprocess(&$variables, $hook)
    {
        if (is_array($variables['attributes'] ?? NULL) && isset($variables['attributes']['class'])) {
            $class = 'is-invalid';
            if (is_object($variables['attributes']['class'])) {
                if (in_array('error', $variables['attributes']['class']->value())) {
                    $variables['attributes']->addClass($class);
                    // If there is a wrapper, add class too.
                    if (isset($variables["wrapper_attributes"])) {
                        $variables['wrapper_attributes']->addClass($class);
                    }
                }
            } elseif (is_array($variables['attributes']['class'])) {
                if (in_array('error', $variables['attributes']['class'])) {
                    $variables['attributes']['class'][] = $class;
                }
            }
        }
    }
    /**
     * Implements hook_preprocess_input() for adding classes to inputs.
     */
    #[Hook('preprocess_input')]
    public function preprocessInput(&$variables)
    {
        if ($variables['element']['#type'] == 'submit') {
            $button_size = DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('bootstrap_barrio_button_size'), fn() => theme_get_setting('bootstrap_barrio_button_size'));
            $button_types = [
                'btn-primary',
                'btn-secondary',
                'btn-success',
                'btn-warning',
                'btn-danger',
                'btn-link',
            ];
            $button_sizes = [
                'btn-sm',
                'btn-lg',
            ];
            $button_is_outline = DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('bootstrap_barrio_button_outline'), fn() => theme_get_setting('bootstrap_barrio_button_outline'));
            $button_type = DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('bootstrap_barrio_button_type'), fn() => theme_get_setting('bootstrap_barrio_button_type')) ?? 'primary';
            if (is_object($variables['attributes']['class'])) {
                \Drupal::logger('bootstrap_barrio')->notice(implode($variables['attributes']['class']->value()));
                if (!in_array('btn', $variables['attributes']['class']->value())) {
                    $variables['attributes']['class']->addClass('btn');
                }
                if (empty(array_intersect($button_types, $variables['attributes']['class']->value()))) {
                    if ($button_is_outline) {
                        $variables['attributes']['class']->addClass('btn-outline-' . $button_type);
                    } else {
                        $variables['attributes']['class']->addClass('btn-' . $button_type);
                    }
                }
                if ($button_size && empty(array_intersect($button_sizes, $variables['attributes']['class']->value()))) {
                    $variables['attributes']['class']->addClass($button_size);
                }
            } else {
                if (!in_array('btn', $variables['attributes']['class'])) {
                    $variables['attributes']['class'][] = 'btn';
                }
                if (empty(array_intersect($button_types, $variables['attributes']['class']))) {
                    if ($button_is_outline) {
                        $variables['attributes']['class'][] = 'btn-outline-' . $button_type;
                    } else {
                        $variables['attributes']['class'][] = 'btn-' . $button_type;
                    }
                }
                if ($button_size && empty(array_intersect($button_sizes, $variables['attributes']['class']))) {
                    $variables['attributes']['class'][] = $button_size;
                }
                $variables['attributes'] = new Attribute($variables['attributes']);
            }
            $variables['safe_value_label'] = Xss::filter($variables['attributes']['value'] ?? '');
        }
        if ($variables['element']['#type'] != 'radio' && $variables['element']['#type'] != 'checkbox' && $variables['element']['#type'] != 'date' && $variables['element']['#type'] != 'time' && $variables['element']['#type'] != 'managed_file' && $variables['element']['#type'] != 'select' && $variables['element']['#type'] != 'search' && $variables['element']['#type'] != 'textarea' && DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('bootstrap_barrio_float_label'), fn() => theme_get_setting('bootstrap_barrio_float_label')) && !isset($variables['attributes']['placeholder'])) {
            $variables['attributes']['placeholder'] = isset($variables['element']['#title']) ? $variables['element']['#title'] : '';
        }
    }
    /**
     * Implements hook_preprocess_select() for adding classes to select boxes.
     */
    #[Hook('preprocess_select')]
    public function preprocessSelect(&$variables)
    {
        if (isset($variables['attributes']['class'])) {
            if (is_object($variables['attributes']['class'])) {
                if (in_array('error', $variables['attributes']['class']->value())) {
                    $variables['attributes']['class']->addClass('is-invalid');
                }
            } elseif (is_array($variables['attributes']['class'])) {
                if (in_array('error', $variables['attributes']['class'])) {
                    $variables['attributes']['class'][] = 'is-invalid';
                }
            }
        }
    }
}
