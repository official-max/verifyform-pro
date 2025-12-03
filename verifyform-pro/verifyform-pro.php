<?php
/*
Plugin Name: VerifyForm Pro
Plugin URI: https://example.com/
Description: A universal form plugin with OTP verification, email notifications, and data saving.
Version: 1.0
Author: Jatin
Author URI: https://example.com/
License: GPL2
*/

// Exit if accessed directly
if (!defined('ABSPATH')) exit;

// Define plugin paths
define('VFP_DIR', plugin_dir_path(__FILE__));
define('VFP_URL', plugin_dir_url(__FILE__));

/*
|--------------------------------------------------------------------------
| Plugin Activation Hook
|--------------------------------------------------------------------------
*/
register_activation_hook(__FILE__, 'vfp_on_activate');

function vfp_on_activate()
{
    // Example: Create database tables
    require_once VFP_DIR . 'includes/vfp-install.php';
    vfp_create_tables();
}

/*
|--------------------------------------------------------------------------
| Plugin Deactivation Hook
|--------------------------------------------------------------------------
*/
register_deactivation_hook(__FILE__, 'vfp_on_deactivate');

function vfp_on_deactivate()
{
    flush_rewrite_rules();
}

// Load plugin core files
require_once VFP_DIR . 'includes/vfp-loader.php';
