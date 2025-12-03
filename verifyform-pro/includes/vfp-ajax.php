<?php
// File: includes/vfp-ajax.php
if (!defined('ABSPATH')) exit;

add_action('wp_ajax_nopriv_vfp_send_otp', 'vfp_ajax_send_otp');
add_action('wp_ajax_vfp_send_otp', 'vfp_ajax_send_otp');

function vfp_is_phone($phone)
{
    return preg_match('/^[6-9][0-9]{9}$/', $phone);
}


function vfp_ajax_send_otp()
{
    // check nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'vfp_otp_nonce')) {
        wp_send_json_error(['message' => 'Security check failed.']);
    }

    $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : '';
    $target = isset($_POST['target']) ? sanitize_text_field($_POST['target']) : '';

    /* Example: 
    <input type="text" name="phone">
    <button id="send-phone-otp">Send OTP</button>

    <input type="email" name="email">
    <button id="send-email-otp">Send OTP</button>
    */

    if ($type !== 'phone' && $type !== 'email') {
        wp_send_json_error(['message' => 'Invalid type.']);
    }

    // validate target
    if ($type === 'phone') {
        if (!vfp_is_phone($target)) {
            wp_send_json_error(['message' => 'Invalid phone number.']);
        }
    } else {
        if (!is_email($target)) {
            wp_send_json_error(['message' => 'Invalid email address.']);
        }
    }

    // rate limit
    if (!vfp_can_send_otp($type, $target)) {
        wp_send_json_error(['message' => 'Please wait before requesting another OTP.']);
    }

    // generate otp
    $length = intval(get_option('vfp_otp_length', 4));
    $otp = vfp_generate_otp($length);

    // Build provider request using settings (phone/email)
    if ($type === 'phone') {
        $api_url   = get_option('vfp_otp_phone_api_url', '');
        $api_key   = get_option('vfp_otp_phone_api_key', '');
        $headers_s = get_option('vfp_otp_phone_headers', '{}');
        $payload_s = get_option('vfp_otp_phone_payload', '{}');
    } else {
        $api_url   = get_option('vfp_otp_email_api_url', '');
        $api_key   = get_option('vfp_otp_email_api_key', '');
        $headers_s = get_option('vfp_otp_email_headers', '{}');
        $payload_s = get_option('vfp_otp_email_payload', '{}');
    }

    if (empty($api_url)) {
        wp_send_json_error(['message' => 'OTP provider not configured.']);
    }

    // Allow raw JSON string or array saved — try decode, else treat as string template
    $headers = json_decode($headers_s, true);
    if (!is_array($headers)) {
        $headers = [];
    }

    // prepare payload - if it's JSON string -> decode to array, else try parse placeholders in string
    $payload_arr = json_decode($payload_s, true);
    $is_payload_string = false;
    if (!is_array($payload_arr)) {
        // treat payload_s as a raw string template (e.g. JSON text). We'll do simple placeholder replace later.
        $is_payload_string = true;
        $payload_text = $payload_s;
    }

    // prepare replacements
    $ip = '';
    if (isset($_SERVER['HTTP_X_ORIGINAL_FORWARDED_FOR'])) {
        $ips = explode(':', $_SERVER['HTTP_X_ORIGINAL_FORWARDED_FOR']);
        $ip = $ips[0];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    }

    // Replace placeholders in headers
    array_walk_recursive($headers, function (&$v) use ($target, $otp, $api_key) {
        if (!is_string($v)) return;
        $v = str_replace(['{phone}', '{email}', '{otp}', '{api_key}'], [$target, $target, $otp, $api_key], $v);
    });

    // Build request body
    if ($is_payload_string) {
        $body_text = str_replace(
            ['{phone}', '{email}', '{otp}', '{ip}', '{api_key}'],
            [$target, $target, $otp, $ip, $api_key],
            $payload_text
        );
        // try to decode to check whether it's JSON text; if it's not JSON, send as text
        $maybe_json = json_decode($body_text, true);
        if (is_array($maybe_json)) {
            $body = wp_json_encode($maybe_json);
            if (!isset($headers['Content-Type'])) $headers['Content-Type'] = 'application/json';
        } else {
            $body = $body_text;
        }
    } else {
        // payload_arr is an array — replace placeholders recursively
        array_walk_recursive($payload_arr, function (&$v) use ($target, $otp, $ip, $api_key) {
            if (!is_string($v)) return;
            $v = str_replace(['{phone}', '{email}', '{otp}', '{ip}', '{api_key}'], [$target, $target, $otp, $ip, $api_key], $v);
        });
        $body = wp_json_encode($payload_arr);
        if (!isset($headers['Content-Type'])) $headers['Content-Type'] = 'application/json';
    }

    // convert headers array to proper format for wp_remote_post (assoc)
    $request_headers = [];
    foreach ($headers as $k => $v) {
        $request_headers[$k] = $v;
    }

    // allow filter to modify request before sending
    $request_args = apply_filters('vfp_otp_request_args', [
        'headers' => $request_headers,
        'body'    => $body,
        'timeout' => 20,
    ], $type, $target, $otp);

    // perform request
    $response = wp_remote_post($api_url, $request_args);

    if (is_wp_error($response)) {
        wp_send_json_error(['message' => $response->get_error_message()]);
    }

    $code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);
    $response_json = json_decode($response_body);

    // Determine success: default: HTTP 2xx => success.
    $success = ($code >= 200 && $code < 300);

    // BUT for Apollo API your original code checked response->errorMessage === "Success"
    // Provide a filter so integrator can customize success check if needed
    $success = apply_filters('vfp_otp_response_success', $success, $response_json, $response_body, $code, $type);

    if ($success) {
        // store hashed OTP
        vfp_store_otp($type, $target, $otp);

        /**
         * DO NOT LOG $otp in plain text in production.
         * For development you may send it back in response (NOT recommended on live)
         */
        do_action('vfp_after_otp_sent', $type, $target, $otp);

        wp_send_json_success(['message' => 'OTP sent successfully.']);
    } else {
        // try to extract message
        $msg = 'Provider error';
        if (is_object($response_json) && isset($response_json->Message)) {
            $msg = $response_json->Message;
        } elseif (!empty($response_body)) {
            $msg = wp_trim_words(strip_tags($response_body), 40, '...');
        }
        wp_send_json_error(['message' => $msg]);
    }
}
