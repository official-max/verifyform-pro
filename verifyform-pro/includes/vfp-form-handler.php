<?php
if (!defined('ABSPATH')) exit;

function vfp_get_client_ip()
{

    $keys = [
        'HTTP_X_REAL_IP',
        'HTTP_CLIENT_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_FORWARDED',
        'HTTP_X_CLUSTER_CLIENT_IP',
        'HTTP_FORWARDED_FOR',
        'HTTP_FORWARDED',
        'REMOTE_ADDR'
    ];

    foreach ($keys as $key) {
        if (array_key_exists($key, $_SERVER)) {

            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
    }

    return '0.0.0.0';
}

function vfp_upload_error_message($code)
{
    $errors = [
        UPLOAD_ERR_OK         => 'No error, file uploaded successfully.',
        UPLOAD_ERR_INI_SIZE   => 'File exceeds the upload_max_filesize limit.',
        UPLOAD_ERR_FORM_SIZE  => 'File exceeds the MAX_FILE_SIZE limit.',
        UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
        UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
        UPLOAD_ERR_EXTENSION  => 'File upload stopped by extension.'
    ];
    return isset($errors[$code]) ? $errors[$code] : 'Unknown upload error.';
}

function vfp_file_error_message($code)
{
    $messages = [
        1 => "File size exceeds server limit.",
        2 => "File size exceeds form limit.",
        3 => "File was only partially uploaded.",
        4 => "No file was uploaded.",
        6 => "Missing temporary folder.",
        7 => "Failed to write file to disk."
    ];

    return isset($messages[$code]) ? $messages[$code] : "Unknown upload error.";
}




add_action('wp_ajax_vfp_submit', 'vfp_ajax_submit_form');
add_action('wp_ajax_nopriv_vfp_submit', 'vfp_ajax_submit_form');

function vfp_ajax_submit_form()
{

    // Nonce validation
    if (!isset($_POST['vfp_nonce']) || !wp_verify_nonce($_POST['vfp_nonce'], 'vfp_form_action')) {
        wp_send_json([
            'success' => false,
            'errors' => ['form' => 'Security check failed!']
        ]);
    }

    $form_id = sanitize_text_field($_POST['form_id']);
    $ip_address = vfp_get_client_ip();

    // Allowed forms
    $allowed = get_option('vfp_allowed_forms', '');
    $allowed = array_filter(array_map('trim', explode("\n", $allowed)));

    if (!empty($allowed) && !in_array($form_id, $allowed)) {
        wp_send_json([
            'success' => false,
            'errors'  => ['form' => 'This form is not allowed.']
        ]);
    }

    // Required fields from JS
    $required_fields = json_decode(stripslashes($_POST['required_fields']), true);
    if (!is_array($required_fields)) $required_fields = [];

    // Validation example
    $errors = [];

    // ------------------------------
    // TEXT FIELD VALIDATION
    // ------------------------------
    foreach ($required_fields as $field) {
        // If it's file input, skip here
        if (isset($_FILES[$field])) continue;

        if (!isset($_POST[$field]) || trim($_POST[$field]) === '') {
            $errors[$field] = 'This field is required.';
        }
    }

    /*
    if (empty($_POST['name'])) {
        $errors['name'] = 'Name is required';
    }

    if (empty($_POST['phone'])) {
        $errors['phone'] = 'Phone number required';
    }*/

    if (!empty($errors)) {
        wp_send_json([
            'success' => false,
            'errors' => $errors
        ]);
    }


    // FILE UPLOAD HANDLING SECTION
    $uploaded_files = [];

    if (!empty($_FILES)) {
        require_once ABSPATH . 'wp-admin/includes/file.php';

        foreach ($_FILES as $field_name => $file) {

            $is_required_file = in_array($field_name, $required_fields);

            // Required but missing file
            if ($is_required_file && $file['error'] === UPLOAD_ERR_NO_FILE) {
                wp_send_json([
                    'success' => false,
                    'errors'  => [$field_name => 'This file is required.']
                ]);
            }

            // Developer filter
            $file = apply_filters('vfp_before_file_upload', $file, $form_id, $field_name);

            // If developer blocked upload (return FALSE)
            if ($file === false) {
                wp_send_json([
                    'success' => false,
                    'errors'  => [$field_name => 'Upload blocked by filter.']
                ]);
            }

            // Skip empty optional file
            if ($file['error'] === UPLOAD_ERR_NO_FILE) continue;

            // Handling file error
            if (!empty($file['error']) && $file['error'] !== UPLOAD_ERR_OK) {
                wp_send_json([
                    'success' => false,
                    'errors'  => [$field_name => vfp_upload_error_message($file['error'])]
                ]);
            }

            // Must be an uploaded file
            if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
                wp_send_json([
                    'success' => false,
                    'errors'  => [$field_name => 'File missing or invalid upload.']
                ]);
            }

            $upload = wp_handle_upload($file, ['test_form' => false]);

            if (!empty($upload['error'])) {
                wp_send_json([
                    'success' => false,
                    'errors'  => [$field_name => $upload['error']]
                ]);
            }

            // Store file URL
            $uploaded_files[$field_name] = $upload['url'];
        }
    }

    // ----------- SAVE ALL FIELDS AUTOMATICALLY -----------

    $clean_data = [];

    // FILTER HOOK (Data modify karne ke liye)
    $filtered_data = apply_filters('vfp_before_save', $_POST, $form_id);
    if ($filtered_data === false) {
        wp_send_json([
            'success' => false,
            'errors' => ['form' => 'Submission blocked by filter.']
        ]);
    }

    foreach ($filtered_data as $key => $value) {
        if (in_array($key, ['action', 'vfp_nonce', 'form_id', 'required_fields'])) continue;
        $clean_data[$key] = sanitize_text_field($value);
    }

    // Add uploaded file URLs
    if (!empty($uploaded_files)) {
        foreach ($uploaded_files as $field => $url) {
            $clean_data[$field] = esc_url_raw($url);
        }
    }


    global $wpdb;
    $table = $wpdb->prefix . 'vfp_submissions';

    $wpdb->insert($table, [
        'form_id' => $form_id,
        'form_data' => wp_json_encode($clean_data),
        'ip_address' => $_SERVER['REMOTE_ADDR'],
        'user_agent' => $_SERVER['HTTP_USER_AGENT'],
        'created_at' => current_time('mysql')
    ]);

    $insert_id = $wpdb->insert_id;

    // ACTION HOOK: Trigger after save
    do_action('vfp_after_save', $insert_id, $form_id, $clean_data);


    $default_redirect = null;
    // Allow developer to override
    $redirect_url = apply_filters('vfp_redirect_after_submit', $default_redirect, $insert_id, $form_id, $clean_data);

    wp_send_json([
        'success'  => true,
        'message'  => 'Form submitted successfully',
        'redirect' => $redirect_url
    ]);

    /*
    wp_send_json([
        'success' => true,
        'redirect' => add_query_arg('submission', 'success', get_permalink())
    ]);
    */
}

/*
add_action('init', 'vfp_handle_form_submission');
function vfp_handle_form_submission()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    if (!isset($_POST['form_id'])) return;

    $ip_address = vfp_get_client_ip();

    // Validate nonce
    if (
        !isset($_POST['vfp_nonce']) ||
        !wp_verify_nonce($_POST['vfp_nonce'], 'vfp_form_action')
    ) {
        wp_die('Security check failed!');
    }

    $form_id = sanitize_text_field($_POST['form_id']);

    // Allowed forms from settings
    $allowed = get_option('vfp_allowed_forms', '');

    // Convert textarea → array
    $allowed = array_filter(array_map('trim', explode("\n", $allowed)));

    // Validate only allowed forms [if allowed form blank so all form data save]
    // if (!empty($allowed) && !in_array($form_id, $allowed, true)) return;

    if (empty($allowed)) {
        return;
    }
    if (!in_array($form_id, $allowed, true)) {
        return;
    }

    // Internal fields to skip
    $exclude = [
        'form_id',
        'vfp_nonce',
        'vfp_submit_form',
        'action'
    ];


    // Allow devs to block saving
    $submission_pre_process = apply_filters('vfp_before_save_submission', true, $form_id, $_POST);
    if (!$submission_pre_process) return;

    // Run validation
    $validation_errors = apply_filters('vfp_validate_form_submission', [], $form_id, $_POST);

    $clean_data = [];

    foreach ($_POST as $key => $value) {

        if (in_array($key, $exclude, true)) continue;

        // Sanitize dynamic fields
        $clean_data[$key] = sanitize_text_field($value);
    }

    $json_data = wp_json_encode($clean_data);

    global $wpdb;
    $table = $wpdb->prefix . 'vfp_submissions';

    // Insert DB
    $wpdb->insert(
        $table,
        [
            'form_id'     => $form_id,
            'form_data'   => $json_data,
            // 'ip_address'  => $_SERVER['REMOTE_ADDR'] ?? '',
            'ip_address'  => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent'  => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'created_at'  => current_time('mysql'),
        ]
    );
    $insert_id = $wpdb->insert_id;

    // HOOK: After saving submission
    do_action('vfp_after_save_submission', $form_id, $_POST, $insert_id);

    // Allow developer to override redirect
    $default_redirect = add_query_arg('submission', 'success', remove_query_arg(array_keys($_POST)));
    $redirect_url = apply_filters('vfp_redirect_url_after_submission', $default_redirect, $form_id, $_POST, $insert_id);

    if ($redirect_url) {
        wp_redirect($redirect_url);
        exit;
    }
}
*/
