<?php

/**
 * Plugin Name: PrepMedico Course Management
 * Description: Manages course editions for WooCommerce products with FluentCRM integration, ASiT membership discounts, and Early Bird offers. Tracks which edition a customer purchased and enables precise segmentation.
 * Version: 2.1.0
 * Author: Shoaib Qureshi - Tier2 Digital
 * Author URI: https://tier2.digital
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 * Text Domain: prepmedico-course-management
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('PMCM_VERSION', '2.1.0');
define('PMCM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PMCM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PMCM_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Keep old constants for backwards compatibility
define('WCEM_VERSION', PMCM_VERSION);
define('WCEM_PLUGIN_DIR', PMCM_PLUGIN_DIR);
define('WCEM_PLUGIN_URL', PMCM_PLUGIN_URL);
define('WCEM_PLUGIN_BASENAME', PMCM_PLUGIN_BASENAME);

/**
 * Load plugin files
 */
function pmcm_load_files()
{
    // Load class files
    require_once PMCM_PLUGIN_DIR . 'includes/class-pmcm-core.php';
    require_once PMCM_PLUGIN_DIR . 'includes/class-pmcm-shortcodes.php';
    require_once PMCM_PLUGIN_DIR . 'includes/class-pmcm-admin.php';
    require_once PMCM_PLUGIN_DIR . 'includes/class-pmcm-fluentcrm.php';
    require_once PMCM_PLUGIN_DIR . 'includes/class-pmcm-asit.php';
    require_once PMCM_PLUGIN_DIR . 'includes/class-pmcm-cart.php';
    require_once PMCM_PLUGIN_DIR . 'includes/class-pmcm-frontend.php';
    require_once PMCM_PLUGIN_DIR . 'includes/class-pmcm-cron.php';
    require_once PMCM_PLUGIN_DIR . 'includes/class-pmcm-product-expiration.php';
}

/**
 * Initialize the plugin
 */
function pmcm_init()
{
    // Check if WooCommerce is active
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p>' . __('PrepMedico Course Management requires WooCommerce to be installed and active.', 'prepmedico-course-management') . '</p></div>';
        });
        return;
    }

    // Load plugin files
    pmcm_load_files();

    // Run migration if not done yet (for existing sites)
    if (!PMCM_Core::is_migrated()) {
        PMCM_Core::migrate_to_database();
    }

    // Initialize all components
    PMCM_Shortcodes::init();
    PMCM_Admin::init();
    PMCM_FluentCRM::init();
    PMCM_ASiT::init();
    PMCM_Cart::init();
    PMCM_Frontend::init();
    PMCM_Cron::init();
    PMCM_Product_Expiration::init();
}
add_action('plugins_loaded', 'pmcm_init', 20);

/**
 * Plugin activation
 */
function pmcm_activate()
{
    // Load files first
    pmcm_load_files();

    // Migrate hardcoded courses to database (runs once)
    PMCM_Core::migrate_to_database();

    // Set default options for all courses
    foreach (PMCM_Core::get_courses() as $category_slug => $course) {
        $prefix = $course['settings_prefix'];

        if (get_option($prefix . 'current_edition') === false) {
            add_option($prefix . 'current_edition', 1);
        }
        if (get_option($prefix . 'edition_start') === false) {
            add_option($prefix . 'edition_start', '');
        }
        if (get_option($prefix . 'edition_end') === false) {
            add_option($prefix . 'edition_end', '');
        }
        if (get_option($prefix . 'early_bird_enabled') === false) {
            add_option($prefix . 'early_bird_enabled', 'no');
        }
        if (get_option($prefix . 'early_bird_start') === false) {
            add_option($prefix . 'early_bird_start', '');
        }
        if (get_option($prefix . 'early_bird_end') === false) {
            add_option($prefix . 'early_bird_end', '');
        }
    }

    // ASiT Coupon settings
    if (get_option('pmcm_asit_coupon_code') === false) {
        add_option('pmcm_asit_coupon_code', 'ASIT');
    }
    if (get_option('pmcm_asit_discount_early_bird') === false) {
        add_option('pmcm_asit_discount_early_bird', 5);
    }
    if (get_option('pmcm_asit_discount_normal') === false) {
        add_option('pmcm_asit_discount_normal', 10);
    }

    // Schedule cron
    PMCM_Cron::schedule_cron();
    PMCM_Product_Expiration::schedule_cron();

    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'pmcm_activate');

/**
 * Plugin deactivation
 */
function pmcm_deactivate()
{
    // Load files first
    pmcm_load_files();

    // Unschedule cron
    PMCM_Cron::unschedule_cron();
    PMCM_Product_Expiration::unschedule_cron();
}
register_deactivation_hook(__FILE__, 'pmcm_deactivate');

/**
 * Helper function to get course edition
 * @param string $course_slug The course slug
 * @return array|null Course edition data
 */
function pmcm_get_course_edition($course_slug)
{
    if (!class_exists('PMCM_Core')) {
        pmcm_load_files();
    }
    return PMCM_Core::get_course_edition($course_slug);
}

// Backwards compatibility alias
function wcem_get_course_edition($course_slug)
{
    return pmcm_get_course_edition($course_slug);
}
