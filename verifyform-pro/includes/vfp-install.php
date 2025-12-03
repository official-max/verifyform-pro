<?php
if (!defined('ABSPATH')) exit;

function vfp_create_tables()
{
    global $wpdb;
    $table = $wpdb->prefix . 'vfp_submissions';

    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        form_id VARCHAR(100) NOT NULL,
        form_data LONGTEXT NOT NULL,
        ip_address VARCHAR(50) DEFAULT NULL,
        user_agent TEXT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}
