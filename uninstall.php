<?php
/**
 * Uninstall script for Eskim Views Counter
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

$tables = [
    $wpdb->prefix . 'eskim_views_counter_visitors',
    $wpdb->prefix . 'eskim_views_counter_countries',
    $wpdb->prefix . 'eskim_views_counter_referers',
];

foreach ($tables as $table) {
    $wpdb->query("DROP TABLE IF EXISTS $table");
}
