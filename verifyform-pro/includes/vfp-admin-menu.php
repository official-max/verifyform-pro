<?php
// File includes/vfp-admin-menu.php

if (!defined('ABSPATH')) exit;

function vfp_admin_menu()
{
    add_menu_page(
        'VerifyForm Pro',
        'VerifyForm Pro',
        'manage_options',
        'vfp-dashboard',
        'vfp_dashboard_page',
        'dashicons-shield-alt',
        26
    );

    add_submenu_page(
        'vfp-dashboard',
        'General Settings',
        'General Settings',
        'manage_options',
        'vfp-general',
        'vfp_general_settings_page'
    );
    /*
    add_submenu_page(
        'vfp-dashboard',
        'OTP Settings',
        'OTP Settings',
        'manage_options',
        'vfp-otp-settings',
        'vfp_otp_settings_page'
    );
    */
    add_submenu_page(
        'vfp-dashboard',
        'Saved Leads',
        'Saved Leads',
        'manage_options',
        'vfp-leads',
        'vfp_leads_page'
    );
}

add_action('admin_menu', 'vfp_admin_menu');


// Dashboard Page
function vfp_dashboard_page()
{
    echo '<div class="wrap"><h1>VerifyForm Pro</h1><p>Welcome to your form & OTP management plugin.</p></div>';
}


// General Settings Page
function vfp_general_settings_page()
{
    require_once VFP_DIR . 'includes/menu/vfp_general_settings.php';
}


// OTP Settings Page
function vfp_otp_settings_page()
{
    require_once VFP_DIR . 'includes/menu/vfp_otp_settings.php';
}


// Leads Page
function vfp_leads_page()
{
    require_once VFP_DIR . 'includes/menu/vfp_leads.php';
}
