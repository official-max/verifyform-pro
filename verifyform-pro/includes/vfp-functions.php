<?php


/**
 * Get submissions for a form
 *
 * @param string $form_id Optional. Agar blank ho toh sab forms ke submissions return honge.
 * @param int $limit Optional. Kitni entries chahiye. Default: 100
 * @return array
 */
function vfp_get_submissions($form_id = '', $limit = 100)
{
    global $wpdb;
    $table = $wpdb->prefix . 'vfp_submissions';

    $query = "SELECT * FROM $table";
    $params = [];

    if (!empty($form_id)) {
        $query .= " WHERE form_id = %s";
        $params[] = $form_id;
    }

    $query .= " ORDER BY created_at DESC LIMIT %d";
    $params[] = $limit;

    $results = $wpdb->get_results($wpdb->prepare($query, ...$params), ARRAY_A);

    // Decode form_data JSON
    foreach ($results as $k => $row) {
        $results[$k]['form_data'] = json_decode($row['form_data'], true);
    }

    return $results;
}
