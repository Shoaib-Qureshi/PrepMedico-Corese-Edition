<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package    WooCommerce_Edition_Management
 */

// If uninstall not called from WordPress, exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Course configuration - must match main plugin
 */
$courses = [
    'frcophth-part1' => '_frcophth_p1_',
    'frcophth-part2' => '_frcophth_p2_',
    'frcs' => '_frcs_',
    'frcs-vasc' => '_frcs_vasc_',
    'scfhs' => '_scfhs_'
];

/**
 * Delete plugin options
 */
foreach ($courses as $category_slug => $prefix) {
    delete_option($prefix . 'current_edition');
    delete_option($prefix . 'edition_start');
    delete_option($prefix . 'edition_end');
    delete_option($prefix . 'next_edition');
    delete_option($prefix . 'next_start');
    delete_option($prefix . 'next_end');
}

// Delete edition log
delete_option('wcem_edition_log');

/**
 * Clear scheduled cron events
 */
$timestamp = wp_next_scheduled('wcem_daily_edition_check');
if ($timestamp) {
    wp_unschedule_event($timestamp, 'wcem_daily_edition_check');
}

/**
 * Note: Order meta is NOT deleted to preserve historical data.
 * If you want to delete order meta, uncomment the code below.
 */
/*
global $wpdb;

// Delete order meta
$order_meta_keys = [
    '_course_slug_%',
    '_course_edition_%',
    '_edition_name_%',
    '_edition_start_%',
    '_edition_end_%'
];

foreach ($order_meta_keys as $key) {
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
            $key
        )
    );
}

// Delete order item meta
$item_meta_keys = [
    '_course_slug',
    '_course_edition',
    '_edition_name',
    '_edition_start',
    '_edition_end'
];

foreach ($item_meta_keys as $key) {
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}woocommerce_order_itemmeta WHERE meta_key = %s",
            $key
        )
    );
}
*/
