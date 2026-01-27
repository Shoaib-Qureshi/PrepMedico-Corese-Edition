<?php
/**
 * PMCM Frontend Class
 * Handles frontend display and scripts
 *
 * @package PrepMedico_Course_Management
 */

if (!defined('ABSPATH')) {
    exit;
}

class PMCM_Frontend {

    /**
     * Initialize frontend hooks
     */
    public static function init() {
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
        add_action('woocommerce_single_product_summary', [__CLASS__, 'display_edition_on_product'], 25);

        // Product title filters
        add_filter('the_title', [__CLASS__, 'add_edition_to_title'], 10, 2);
        add_filter('woocommerce_product_title', [__CLASS__, 'add_edition_to_wc_title'], 10, 2);
        add_filter('woocommerce_cart_item_name', [__CLASS__, 'add_edition_to_cart_item_name'], 10, 3);
        add_filter('woocommerce_order_item_name', [__CLASS__, 'add_edition_to_order_item_name'], 10, 2);
    }

    /**
     * Enqueue frontend scripts and styles
     */
    public static function enqueue_scripts() {
        if (is_product() || is_cart() || is_checkout()) {
            wp_enqueue_style('pmcm-frontend', PMCM_PLUGIN_URL . 'assets/css/frontend.css', [], PMCM_VERSION);
        }
    }

    /**
     * Display edition on product page
     */
    public static function display_edition_on_product() {
        global $product;

        if (!$product) {
            return;
        }

        $categories = wp_get_post_terms($product->get_id(), 'product_cat', ['fields' => 'slugs']);

        foreach ($categories as $category_slug) {
            $course_data = PMCM_Core::get_course_for_category($category_slug);

            if ($course_data) {
                echo do_shortcode('[course_registration_info course="' . esc_attr($course_data['parent_slug']) . '"]');
                break;
            }
        }
    }

    /**
     * Add edition number to product title (for the_title filter)
     */
    public static function add_edition_to_title($title, $post_id = null) {
        if (is_admin() || !$post_id) {
            return $title;
        }

        if (get_post_type($post_id) !== 'product') {
            return $title;
        }

        if (!is_singular('product') && !is_shop() && !is_product_category() && !is_product_tag()) {
            return $title;
        }

        return self::prepend_edition_to_title($title, $post_id);
    }

    /**
     * Add edition number to WooCommerce product title
     */
    public static function add_edition_to_wc_title($title, $product) {
        if (is_admin()) {
            return $title;
        }

        $product_id = is_object($product) ? $product->get_id() : $product;
        return self::prepend_edition_to_title($title, $product_id);
    }

    /**
     * Add edition to cart item name
     */
    public static function add_edition_to_cart_item_name($name, $cart_item, $cart_item_key) {
        $product_id = $cart_item['product_id'];
        return self::prepend_edition_to_title($name, $product_id);
    }

    /**
     * Add edition to order item name (for emails)
     */
    public static function add_edition_to_order_item_name($name, $item) {
        $product_id = $item->get_product_id();
        return self::prepend_edition_to_title($name, $product_id);
    }

    /**
     * Helper: Prepend edition number to title
     * Only for parent courses with edition_management enabled
     * Child categories and courses without edition_management show just the title
     */
    private static function prepend_edition_to_title($title, $product_id) {
        if (strpos($title, ' - ') === false || !preg_match('/^\d+(st|nd|rd|th)\s+-\s+/', $title)) {
            $categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'slugs']);

            foreach ($categories as $category_slug) {
                $course_data = PMCM_Core::get_course_for_category($category_slug);

                if ($course_data) {
                    $course = $course_data['course'];
                    $is_child = $course_data['is_child'];

                    // Only add edition for parent courses with edition_management enabled
                    $has_edition_management = isset($course['edition_management']) && $course['edition_management'] === true;

                    // Skip edition for child categories and courses without edition_management
                    if (!$has_edition_management || $is_child) {
                        return $title;
                    }

                    $prefix = $course['settings_prefix'];
                    $edition = get_option($prefix . 'current_edition', 1);

                    if (!preg_match('/^\d+(st|nd|rd|th)\s+-\s+/', $title)) {
                        return PMCM_Core::get_ordinal($edition) . ' - ' . $title;
                    }
                    break;
                }
            }
        }

        return $title;
    }
}
