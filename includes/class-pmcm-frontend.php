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

        // Email hooks for ASiT member display
        add_action('woocommerce_email_order_meta', [__CLASS__, 'display_asit_in_email'], 10, 3);
        add_action('woocommerce_thankyou', [__CLASS__, 'display_asit_on_thankyou'], 5);
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

    /**
     * Display ASiT member info in WooCommerce emails
     */
    public static function display_asit_in_email($order, $sent_to_admin, $plain_text) {
        // Get ASiT number from order meta
        $asit_number = $order->get_meta('_wcem_asit_number');
        if (empty($asit_number)) {
            $asit_number = $order->get_meta('_asit_membership_number');
        }

        if (empty($asit_number)) {
            return;
        }

        if ($plain_text) {
            echo "\n" . __('ASiT MEMBER', 'prepmedico-course-management') . "\n";
            echo sprintf(__('Membership Number: %s', 'prepmedico-course-management'), $asit_number);
            echo "\n\n";
        } else {
            echo '<div style="margin: 20px 0; padding: 15px; background: linear-gradient(135deg, #8d2063, #442e8c); border-radius: 8px; text-align: center;">';
            echo '<h3 style="margin: 0 0 5px; color: #fff; font-size: 16px;">' . __('ASiT Member', 'prepmedico-course-management') . '</h3>';
            echo '<p style="margin: 0; color: #fff; font-size: 18px; font-weight: 700;">#' . esc_html($asit_number) . '</p>';
            echo '</div>';
        }
    }

    /**
     * Display ASiT member info on thank you page
     */
    public static function display_asit_on_thankyou($order_id) {
        $order = wc_get_order($order_id);

        if (!$order) {
            return;
        }

        // Get ASiT number from order meta
        $asit_number = $order->get_meta('_wcem_asit_number');
        if (empty($asit_number)) {
            $asit_number = $order->get_meta('_asit_membership_number');
        }

        if (empty($asit_number)) {
            return;
        }

        echo '<div class="woocommerce-message" style="background: linear-gradient(135deg, #8d2063, #442e8c); border: none; color: #fff; padding: 15px 20px; margin-bottom: 20px;">';
        echo '<strong>' . __('ASiT Member', 'prepmedico-course-management') . '</strong>';
        echo ' <span style="font-size: 18px; font-weight: 700;">#' . esc_html($asit_number) . '</span>';
        echo '</div>';
    }
}
