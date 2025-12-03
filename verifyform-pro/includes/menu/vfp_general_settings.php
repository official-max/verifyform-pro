<?php // File includes/function vfp_general_settings.php 
if (!defined('ABSPATH')) exit;

$option_name = 'vfp_allowed_forms';
// Save form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vfp_save_settings'])) {

    $raw = sanitize_textarea_field($_POST[$option_name]);

    $lines = preg_split('/\r\n|\r|\n/', trim($raw));
    $clean = array_filter(array_map('trim', $lines)); // Clean array

    $final = [];
    $used  = [];

    foreach ($clean as $id) {

        $original = $id;
        $counter = 1;

        // If already exists → add suffix
        while (in_array($id, $used, true)) {
            $id = $original . "_" . $counter;
            $counter++;
        }

        $used[]  = $id;
        $final[] = $id;
    }

    // Sanitize and save
    update_option($option_name, implode("\n", $final));

    echo '<div class="updated"><p><strong>Settings Saved!</strong></p></div>';
}

// Get saved value
$saved_value = get_option($option_name, '');
?>

<div class="wrap">
    <h1>General Settings</h1>

    <form method="post">

        <table class="form-table">

            <tr>
                <td>
                    <label for="vfp_textarea">Allowed Form Names</label>
                    <p>Enter one <strong>form_name</strong> per line. Only these forms will be saved.</p>
                    <textarea name="vfp_allowed_forms"
                        id="vfp_textarea_value"
                        rows="10"
                        cols="5"
                        class="large-text"><?php echo esc_textarea($saved_value); ?></textarea>
                </td>
            </tr>

        </table>

        <input type="submit"
            name="vfp_save_settings"
            class="button button-primary"
            value="Save Settings">
    </form>
</div>