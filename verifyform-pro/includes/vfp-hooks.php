<?php
// File: vfp-hooks.php

/*
|--------------------------------------------------------------------------
| VERIFYFORM PRO: HOOK EXAMPLES
|--------------------------------------------------------------------------
| 1) Action Hook: do_action + add_action
|    - Runs AFTER form is saved
|    - Return value NOT required
|    - Use: send email, logging, webhook, notifications
|    - Trigger in plugin: apply_filters
|    - Developer side: add_filter
|
| 2) Filter Hook: apply_filters + add_filter
|    - Runs BEFORE form is saved
|    - Return value REQUIRED (true/false)
|    - Use: validate, modify, block save
|    - Trigger in plugin: do_action
|    - Developer side: add_action
|
| Notes:
| - Hooks are triggered in vfp-form-handler.php
| - Developers can add their custom logic via add_action/add_filter
|--------------------------------------------------------------------------
*/


if (!defined('ABSPATH')) exit;

// HOOK: Before saving (developers can modify/stop)
add_filter('vfp_before_save', function ($allow = true, $form_id = '', $post = []) {

    if (isset($post['phone']) && empty($post['phone'])) {
        // Stop saving
        return false;
    }

    return $allow;
}, 10, 3);


add_action('vfp_after_save', function ($id, $form_id, $data) {
    // $id => DB me insert hua record ka ID
    // $form_id => kaunsa form submit hua
    // $data => sanitized form data array
    /*
    // Example 1: Send email
    wp_mail('admin@example.com', 'New Form Submission', print_r($data, true));

    // Example 2: Call CRM API
    $url = 'https://example-crm.com/api/save';
    wp_remote_post($url, [
        'body' => $data
    ]);

    // Example 3: Analytics tracking
    do_action('track_form_submission', $form_id, $data); */
}, 10, 3);


// Change redirect for a specific form
add_filter('vfp_redirect_after_submit', function ($default, $insert_id, $form_id, $data) {

    if ($form_id === 'contact_form') {
        return $redirect_url = add_query_arg(
            'submission',
            'success',
            '/?page_id=10/'
        );
    }
    return $default; // otherwise no redirect
}, 10, 4);
