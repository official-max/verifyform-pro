<?php
// File includes/vfp-assets.php
if (!defined('ABSPATH')) exit;

/*
|--------------------------------------------------------------------------
| FRONTEND ASSETS
|-------------------------------------------------------------------------- */

add_action('wp_enqueue_scripts', 'vfp_enqueue_front_assets');
function vfp_enqueue_front_assets()
{
    // Enqueue main frontend script
    wp_enqueue_script(
        'vfp-script',
        VFP_URL . 'assets/js/vfp-script.js',
        ['jquery'],
        filemtime(VFP_DIR . 'assets/js/vfp-script.js'),
        true
    );

    // Localize the script with AJAX URL
    wp_localize_script('vfp-script', 'vfp_ajax', [
        'url' => admin_url('admin-ajax.php')
    ]);

    // Get allowed forms from the settings and localize
    $allowed = get_option('vfp_allowed_forms', '');
    $allowed = array_filter(array_map('trim', explode("\n", $allowed)));
    wp_localize_script('vfp-script', 'vfp_allowed_forms', $allowed);
}


/*
|--------------------------------------------------------------------------
| ADMIN ASSETS
|-------------------------------------------------------------------------- */

add_action('admin_enqueue_scripts', 'vfp_enqueue_admin_assets');
function vfp_enqueue_admin_assets($hook)
{
    // Load only on plugin pages
    if (!isset($_GET['page']) || strpos($_GET['page'], 'vfp-') !== 0) {
        return;
    }

    // Enqueue admin style
    wp_enqueue_style(
        'vfp-admin-style',
        VFP_URL . 'assets/css/vfp-style.css',
        [],
        filemtime(VFP_DIR . 'assets/css/vfp-style.css'),
        'all'
    );

    // Enqueue admin script
    wp_enqueue_script(
        'vfp-admin-script',
        VFP_URL . 'assets/js/vfp-script.js',
        ['jquery'],
        filemtime(VFP_DIR . 'assets/js/vfp-script.js'),
        true
    );
}
