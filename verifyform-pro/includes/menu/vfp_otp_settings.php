<?php
// FILE: includes/menu/vfp_otp_settings.php
if (!defined('ABSPATH')) exit;

/*
|--------------------------------------------------------------------------
| SAVE OTP SETTINGS
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vfp_save_otp_settings'])) {

    check_admin_referer('vfp_save_otp_settings_action', 'vfp_save_otp_settings_nonce');

    // Basic OTP rules
    update_option('vfp_otp_enable_phone', isset($_POST['vfp_otp_enable_phone']) ? 1 : 0);
    update_option('vfp_otp_enable_email', isset($_POST['vfp_otp_enable_email']) ? 1 : 0);
    update_option('vfp_otp_required_for', sanitize_text_field($_POST['vfp_otp_required_for']));
    update_option('vfp_otp_length', intval($_POST['vfp_otp_length']));
    update_option('vfp_otp_expiry', intval($_POST['vfp_otp_expiry']));

    // Phone OTP API
    update_option('vfp_otp_phone_api_url', esc_url_raw($_POST['vfp_otp_phone_api_url']));
    update_option('vfp_otp_phone_api_key', sanitize_text_field($_POST['vfp_otp_phone_api_key']));
    update_option('vfp_otp_phone_headers', wp_unslash($_POST['vfp_otp_phone_headers']));
    update_option('vfp_otp_phone_payload', wp_unslash($_POST['vfp_otp_phone_payload']));

    /* update_option('vfp_otp_phone_headers', wp_json_encode($_POST['vfp_otp_phone_headers']));
    update_option('vfp_otp_phone_payload', wp_json_encode($_POST['vfp_otp_phone_payload'])); */

    // Email OTP API
    update_option('vfp_otp_email_api_url', esc_url_raw($_POST['vfp_otp_email_api_url']));
    update_option('vfp_otp_email_api_key', sanitize_text_field($_POST['vfp_otp_email_api_key']));
    update_option('vfp_otp_email_headers', wp_unslash($_POST['vfp_otp_email_headers']));
    update_option('vfp_otp_email_payload', wp_unslash($_POST['vfp_otp_email_payload']));

    /*update_option('vfp_otp_email_headers', wp_json_encode($_POST['vfp_otp_email_headers']));
    update_option('vfp_otp_email_payload', wp_json_encode($_POST['vfp_otp_email_payload']));*/

    echo '<div class="updated"><p>OTP Settings Saved Successfully.</p></div>';
}

/*
|--------------------------------------------------------------------------
| GET SAVED VALUES
|--------------------------------------------------------------------------
*/
$enable_phone = get_option('vfp_otp_enable_phone', 0);
$enable_email = get_option('vfp_otp_enable_email', 0);
$required_for = get_option('vfp_otp_required_for', 'phone');
$otp_length   = get_option('vfp_otp_length', 6);
$otp_expiry   = get_option('vfp_otp_expiry', 5);

// phone settings
$phone_api_url = get_option('vfp_otp_phone_api_url', '');
$phone_api_key = get_option('vfp_otp_phone_api_key', '');
$phone_headers_json = get_option('vfp_otp_phone_headers', '{}');
$phone_payload_json = get_option('vfp_otp_phone_payload', '{}');

// email settings
$email_api_url = get_option('vfp_otp_email_api_url', '');
$email_api_key = get_option('vfp_otp_email_api_key', '');
$email_headers_json = get_option('vfp_otp_email_headers', '{}');
$email_payload_json = get_option('vfp_otp_email_payload', '{}');

?>
<div class="wrap">
    <h1>OTP Settings</h1>

    <form method="post">
        <?php wp_nonce_field('vfp_save_otp_settings_action', 'vfp_save_otp_settings_nonce'); ?>

        <h2>General OTP Settings</h2>
        <table class="form-table">

            <tr>
                <th>Enable Phone OTP</th>
                <td>
                    <label>
                        <input type="checkbox" name="vfp_otp_enable_phone" <?php checked($enable_phone, 1); ?>>
                        Enable OTP for Phone numbers
                    </label>
                </td>
            </tr>

            <tr>
                <th>Enable Email OTP</th>
                <td>
                    <label>
                        <input type="checkbox" name="vfp_otp_enable_email" <?php checked($enable_email, 1); ?>>
                        Enable OTP for Email ID
                    </label>
                </td>
            </tr>

            <tr>
                <th>OTP Required For</th>
                <td>
                    <select name="vfp_otp_required_for">
                        <option value="phone" <?php selected($required_for, 'phone'); ?>>Phone Only</option>
                        <option value="email" <?php selected($required_for, 'email'); ?>>Email Only</option>
                        <option value="both" <?php selected($required_for, 'both'); ?>>Both (Phone & Email)</option>
                    </select>
                </td>
            </tr>

            <tr>
                <th>OTP Length</th>
                <td>
                    <input type="number" name="vfp_otp_length" value="<?php echo esc_attr($otp_length); ?>" min="3" max="10">
                </td>
            </tr>

            <tr>
                <th>OTP Expiry (minutes)</th>
                <td>
                    <input type="number" name="vfp_otp_expiry" value="<?php echo esc_attr($otp_expiry); ?>" min="1" max="30">
                </td>
            </tr>

        </table>


        <hr>
        <h2>Phone OTP API Settings</h2>

        <table class="form-table">
            <tr>
                <th>API URL</th>
                <td><input type="text" name="vfp_otp_phone_api_url" value="<?php echo esc_attr($phone_api_url); ?>" class="regular-text"></td>
            </tr>

            <tr>
                <th>API Key</th>
                <td><input type="text" name="vfp_otp_phone_api_key" value="<?php echo esc_attr($phone_api_key); ?>" class="regular-text"></td>
            </tr>

            <tr>
                <th>Headers (JSON)</th>
                <td><textarea name="vfp_otp_phone_headers" rows="4" class="large-text"><?php echo esc_textarea($phone_headers_json); ?></textarea></td>
            </tr>

            <tr>
                <th>Payload Template (JSON)</th>
                <td>
                    <textarea name="vfp_otp_phone_payload" rows="6" class="large-text"><?php echo esc_textarea($phone_payload_json); ?></textarea>
                    <p class="description">Use variables: {otp}, {phone}</p>
                </td>
            </tr>
        </table>


        <hr>
        <h2>Email OTP API Settings</h2>

        <table class="form-table">
            <tr>
                <th>API URL</th>
                <td><input type="text" name="vfp_otp_email_api_url" value="<?php echo esc_attr($email_api_url); ?>" class="regular-text"></td>
            </tr>

            <tr>
                <th>API Key</th>
                <td><input type="text" name="vfp_otp_email_api_key" value="<?php echo esc_attr($email_api_key); ?>" class="regular-text"></td>
            </tr>

            <tr>
                <th>Headers (JSON)</th>
                <td><textarea name="vfp_otp_email_headers" rows="4" class="large-text"><?php echo esc_textarea($email_headers_json); ?></textarea></td>
            </tr>

            <tr>
                <th>Payload Template (JSON)</th>
                <td>
                    <textarea name="vfp_otp_email_payload" rows="6" class="large-text"><?php echo esc_textarea($email_payload_json); ?></textarea>
                    <p class="description">Use variables: {otp}, {email}</p>
                </td>
            </tr>
        </table>

        <p><button class="button button-primary" name="vfp_save_otp_settings">Save Settings</button></p>
    </form>
</div>