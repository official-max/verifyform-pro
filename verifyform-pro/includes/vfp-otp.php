<?php
// File: includes/vfp-otp.php
if (!defined('ABSPATH')) exit;

// Hook for logged-in & logged-out users
add_action('wp_ajax_vfp_send_otp', 'vfp_send_otp_handler');
add_action('wp_ajax_nopriv_vfp_send_otp', 'vfp_send_otp_handler');

function vfp_send_otp_handler()
{

    // Nonce check
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'vfp_otp_nonce')) {
        wp_send_json_error(['message' => 'Security check failed.']);
    }

    // Type & target
    $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : '';
    $target = isset($_POST['target']) ? sanitize_text_field($_POST['target']) : '';

    if ($type !== 'phone' && $type !== 'email') {
        wp_send_json_error(['message' => 'Invalid type.']);
    }

    // Validate target
    // if ($type === 'phone' && !preg_match('/^[6-9][0-9]{9}$/', $target)) {
    //     wp_send_json_error(['message' => 'Invalid phone number.']);
    // }
    if ($type === 'email' && !is_email($target)) {
        wp_send_json_error(['message' => 'Invalid email address.']);
    }

    // Rate limit
    if (!vfp_can_send_otp($type, $target)) {
        wp_send_json_error(['message' => 'Please wait before requesting another OTP.']);
    }

    // Generate OTP
    $otp_length = intval(get_option('vfp_otp_length', 4));
    $otp = '';
    for ($i = 0; $i < $otp_length; $i++) $otp .= rand(0, 9);

    // API Settings
    if ($type === 'phone') {
        $enabled = get_option('vfp_otp_enable_phone', 0);
        $api_url = get_option('vfp_otp_phone_api_url', '');
        $api_key = get_option('vfp_otp_phone_api_key', '');
        $headers = get_option('vfp_otp_phone_headers', '{}');
        $payload = get_option('vfp_otp_phone_payload', '{}');
    } else {
        $enabled = get_option('vfp_otp_enable_email', 0);
        $api_url = get_option('vfp_otp_email_api_url', '');
        $api_key = get_option('vfp_otp_email_api_key', '');
        $headers = get_option('vfp_otp_email_headers', '{}');
        $payload = get_option('vfp_otp_email_payload', '{}');
    }

    if (!$enabled) wp_send_json_error(['message' => ucfirst($type) . ' OTP is disabled.']);

    // Replace placeholders
    $headers_array = json_decode($headers, true);
    $payload_array = json_decode($payload, true);

    array_walk_recursive($payload_array, function (&$v) use ($otp, $target) {
        $v = str_replace(['{otp}', '{phone}', '{email}', '{target}'], [$otp, $target, $target, $target], $v);
    });
    array_walk_recursive($headers_array, function (&$v) use ($api_key) {
        $v = str_replace('{api_key}', $api_key, $v);
    });

    // Send OTP API
    $args = ['timeout' => 30, 'headers' => $headers_array, 'body' => wp_json_encode($payload_array)];
    $response = wp_remote_post($api_url, $args);

    if (is_wp_error($response)) wp_send_json_error(['message' => 'Failed to send OTP.']);

    // Store OTP transient
    $otp_expiry = intval(get_option('vfp_otp_expiry', 2)) * MINUTE_IN_SECONDS;
    $transient_key = 'vfp_otp_' . $type . '_' . md5($target);
    set_transient($transient_key, $otp, $otp_expiry);

    // Update last sent time for rate limit
    vfp_update_otp_timestamp($type, $target);

    wp_send_json_success(['message' => 'OTP sent successfully.', 'otp' => $otp]); // Remove OTP in production
}

// Rate limit check
function vfp_can_send_otp($type, $target)
{
    $transient_key = 'vfp_otp_last_' . $type . '_' . md5($target);
    $last_sent = get_transient($transient_key);
    return !$last_sent || (time() - $last_sent) >= 60;
}

function vfp_update_otp_timestamp($type, $target)
{
    $transient_key = 'vfp_otp_last_' . $type . '_' . md5($target);
    set_transient($transient_key, time(), 10 * MINUTE_IN_SECONDS);
}
