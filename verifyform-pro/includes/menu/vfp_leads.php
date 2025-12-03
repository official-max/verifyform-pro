<?php
// File includes/vfp-leads.php
if (!defined('ABSPATH')) exit;

global $wpdb;
$table = $wpdb->prefix . 'vfp_submissions';

// Handle Delete Requests
if (!empty($_POST['vfp_delete_nonce']) && wp_verify_nonce($_POST['vfp_delete_nonce'], 'vfp_delete_action')) {

    // --- Single delete ---
    if (!empty($_POST['vfp_delete_selected']) && is_numeric($_POST['vfp_delete_selected'])) {

        $delete_id = intval($_POST['vfp_delete_selected']);
        $wpdb->delete($table, ['id' => $delete_id]);

        echo '<div class="updated"><p><strong>Lead deleted.</strong></p></div>';
    }

    // --- Bulk delete ---
    elseif (!empty($_POST['lead_ids'])) {

        $ids = array_map('intval', $_POST['lead_ids']);
        $ids_list = implode(",", $ids);

        $wpdb->query("DELETE FROM $table WHERE id IN ($ids_list)");

        echo '<div class="updated"><p><strong>Selected leads deleted.</strong></p></div>';
    }
}


// Allowed forms
$allowed_raw = get_option('vfp_allowed_forms', '');
$allowed_forms = array_filter(array_map('trim', explode("\n", $allowed_raw)));

// Filters
$selected_form = isset($_GET['form']) ? sanitize_text_field($_GET['form']) : '';
$search        = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';

// Build WHERE
$where = "WHERE 1=1";
if ($selected_form !== '') {
    $where .= $wpdb->prepare(" AND form_id = %s", $selected_form);
}
if ($search !== '') {
    $where .= $wpdb->prepare(" AND form_data LIKE %s", "%$search%");
}

// Pagination: setup
$limit = 5; // leads per page
$page  = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$offset = ($page - 1) * $limit;

$total_leads = $wpdb->get_var("SELECT COUNT(*) FROM $table $where");
$total_pages = ceil($total_leads / $limit);

// Fetch
$results = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT * FROM $table $where ORDER BY created_at DESC LIMIT %d OFFSET %d",
        $limit,
        $offset
    ),
    ARRAY_A
);

echo '<div class="wrap"><h1>Saved Leads</h1>';
?>

<!-- Filter Form -->
<form method="GET">
    <input type="hidden" name="page" value="vfp-leads">

    <div style="margin-bottom: 15px; display:flex; gap:20px;">

        <div>
            <label><strong>Filter by Form:</strong></label><br>
            <select name="form" onchange="this.form.submit()">
                <option value="">All Forms</option>
                <?php foreach ($allowed_forms as $form): ?>
                    <option value="<?php echo esc_attr($form); ?>" <?php selected($selected_form, $form); ?>>
                        <?php echo esc_html($form); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label><strong>Search:</strong></label><br>
            <input type="text" name="search" placeholder="Name, Email, Phone or any field"
                value="<?php echo esc_attr($search); ?>">
            <button class="button">Search</button>
        </div>

    </div>
</form>

<?php
if (empty($results)) {
    echo '<p>No matching submissions found.</p></div>';
    return;
}
?>

<!-- Bulk Delete Form -->
<form method="POST">
    <?php wp_nonce_field('vfp_delete_action', 'vfp_delete_nonce'); ?>

    <div class="btn-pagination-wrapper">
        <button name="vfp_delete_selected" value="bulk" class="button button-danger"
            onclick="return confirm('Delete selected leads?');">
            Delete Selected
        </button>
        <?php if ($total_pages > 1): ?>
            <div>

                <?php
                // Preserve filters & search
                $base_url = admin_url("admin.php?page=vfp-leads");
                if (!empty($selected_form)) $base_url .= "&form=" . urlencode($selected_form);
                if (!empty($search)) $base_url .= "&search=" . urlencode($search);
                ?>

                <div class="tablenav-pages">

                    <!-- Prev Button -->
                    <?php if ($page > 1): ?>
                        <a class="button" href="<?php echo $base_url . "&paged=" . ($page - 1); ?>">« Prev</a>
                    <?php endif; ?>

                    <!-- Page Numbers -->
                    <?php for ($p = 1; $p <= $total_pages; $p++): ?>

                        <?php if ($p == $page): ?>
                            <span class="button button-primary"><?php echo $p; ?></span>
                        <?php else: ?>
                            <a class="button"
                                href="<?php echo $base_url . "&paged=" . $p; ?>"><?php echo $p; ?></a>
                        <?php endif; ?>

                    <?php endfor; ?>

                    <!-- Next Button -->
                    <?php if ($page < $total_pages): ?>
                        <a class="button" href="<?php echo $base_url . "&paged=" . ($page + 1); ?>">Next »</a>
                    <?php endif; ?>

                </div>
            </div>
        <?php endif; ?>
    </div>


    <table class="widefat striped leads-table">
        <thead>
            <tr>
                <th><input type="checkbox" id="vfp_select_all"></th>
                <th>ID</th>
                <th>Form</th>
                <th>Data</th>
                <th>IP</th>
                <th>User Agent</th>
                <th>Submitted At</th>
                <th>Delete</th>
            </tr>
        </thead>
        <tbody>

            <?php foreach ($results as $row): ?>
                <?php
                $form_data = json_decode($row['form_data'], true);
                $form_data_display = '';
                foreach ($form_data as $key => $value) {
                    $form_data_display .= "<strong>$key:</strong> $value<br>";
                }
                ?>
                <tr>
                    <td><input type="checkbox" name="lead_ids[]" value="<?php echo $row['id']; ?>"></td>

                    <td><?php echo $row['id']; ?></td>
                    <td><?php echo esc_html($row['form_id']); ?></td>
                    <td><?php echo $form_data_display; ?></td>
                    <td><?php echo $row['ip_address']; ?></td>
                    <td><?php echo $row['user_agent']; ?></td>
                    <td><?php echo $row['created_at']; ?></td>

                    <td>
                        <!-- SINGLE DELETE BUTTON -->
                        <button name="vfp_delete_selected" value="<?php echo $row['id']; ?>"
                            class="button-link-delete"
                            onclick="return confirm('Delete this lead?');">
                            🗑️
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>

        </tbody>

    </table>


    <div class="btn-pagination-wrapper">
        <button name="vfp_delete_selected" value="bulk" class="button button-danger"
            onclick="return confirm('Delete selected leads?');">
            Delete Selected
        </button>
        <?php if ($total_pages > 1): ?>
            <div>

                <?php
                // Preserve filters & search
                $base_url = admin_url("admin.php?page=vfp-leads");
                if (!empty($selected_form)) $base_url .= "&form=" . urlencode($selected_form);
                if (!empty($search)) $base_url .= "&search=" . urlencode($search);
                ?>

                <div class="tablenav-pages">

                    <!-- Prev Button -->
                    <?php if ($page > 1): ?>
                        <a class="button" href="<?php echo $base_url . "&paged=" . ($page - 1); ?>">« Prev</a>
                    <?php endif; ?>

                    <!-- Page Numbers -->
                    <?php for ($p = 1; $p <= $total_pages; $p++): ?>

                        <?php if ($p == $page): ?>
                            <span class="button button-primary"><?php echo $p; ?></span>
                        <?php else: ?>
                            <a class="button"
                                href="<?php echo $base_url . "&paged=" . $p; ?>"><?php echo $p; ?></a>
                        <?php endif; ?>

                    <?php endfor; ?>

                    <!-- Next Button -->
                    <?php if ($page < $total_pages): ?>
                        <a class="button" href="<?php echo $base_url . "&paged=" . ($page + 1); ?>">Next »</a>
                    <?php endif; ?>

                </div>
            </div>
        <?php endif; ?>
    </div>


</form>

<script>
    document.getElementById('vfp_select_all').addEventListener('change', function() {
        let checkboxes = document.querySelectorAll('input[name="lead_ids[]"]');
        checkboxes.forEach(cb => cb.checked = this.checked);
    });
</script>

</div>