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

        // Edition selector on product page (when multiple editions are active)
        add_action('woocommerce_before_add_to_cart_button', [__CLASS__, 'display_edition_selector'], 10);

        // Product title filters
        add_filter('the_title', [__CLASS__, 'add_edition_to_title'], 10, 2);
        add_filter('woocommerce_product_title', [__CLASS__, 'add_edition_to_wc_title'], 10, 2);
        add_filter('woocommerce_cart_item_name', [__CLASS__, 'add_edition_to_cart_item_name'], 10, 3);
        add_filter('woocommerce_order_item_name', [__CLASS__, 'add_edition_to_order_item_name'], 10, 2);

        // Email hooks for ASiT member display
        add_action('woocommerce_email_order_meta', [__CLASS__, 'display_asit_in_email'], 10, 3);
        add_action('woocommerce_thankyou', [__CLASS__, 'display_asit_on_thankyou'], 5);

        // Dynamic CSS for disabling enrol buttons when dates unavailable
        add_action('wp_head', [__CLASS__, 'output_dynamic_enrol_css']);
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
     * Display edition info and capture edition from URL parameter
     * Edition selection happens on the course page table, NOT on product page
     * URL format: /product/frcs-course/?edition=11
     */
    public static function display_edition_selector() {
        global $product;

        if (!$product) {
            return;
        }

        $categories = wp_get_post_terms($product->get_id(), 'product_cat', ['fields' => 'slugs']);

        foreach ($categories as $category_slug) {
            $course_data = PMCM_Core::get_course_for_category($category_slug);

            if ($course_data) {
                $parent_slug = $course_data['parent_slug'];
                $course = $course_data['course'];

                // Check if this course has edition management enabled
                if (empty($course['edition_management']) || !$course['edition_management']) {
                    continue;
                }

                $prefix = $course['settings_prefix'];

                // Check if edition is passed via URL parameter
                $url_edition = isset($_GET['edition']) ? intval($_GET['edition']) : 0;

                if ($url_edition > 0) {
                    // Edition specified in URL - determine which slot it belongs to
                    $current_edition = intval(get_option($prefix . 'current_edition', 1));
                    $next_enabled = get_option($prefix . 'next_enabled', 'no');
                    $next_edition = intval(get_option($prefix . 'next_edition', 0));

                    $selected_slot = 'current';
                    $selected_edition_number = $url_edition;

                    // Check if URL edition matches next slot
                    if ($next_enabled === 'yes' && $next_edition === $url_edition) {
                        $selected_slot = 'next';
                    } elseif ($current_edition !== $url_edition) {
                        // URL edition doesn't match current or next - might be a future/past edition
                        // Still capture it for the order, use current slot settings for dates
                        $selected_slot = 'current';
                    }

                    // Add hidden fields for cart capture
                    echo '<input type="hidden" name="pmcm_selected_edition" value="' . esc_attr($selected_slot) . '">';
                    echo '<input type="hidden" name="pmcm_selected_course" value="' . esc_attr($parent_slug) . '">';
                    echo '<input type="hidden" name="pmcm_edition_number" value="' . esc_attr($url_edition) . '">';

                    // Show selected edition info badge
                    $ordinal = PMCM_Core::get_ordinal($url_edition);
                    echo '<div class="pmcm-edition-selected" style="margin: 15px 0; padding: 12px 16px; background: linear-gradient(135deg, #8d2063, #442e8c); border-radius: 6px; color: #fff;">';
                    echo '<div style="display: flex; align-items: center; gap: 10px;">';
                    echo '<span style="display: flex; align-items: center; justify-content: center; width: 28px; height: 28px; background: rgba(255,255,255,0.2); border-radius: 50%;">';
                    echo '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>';
                    echo '</span>';
                    echo '<div>';
                    echo '<div style="font-size: 12px; opacity: 0.9;">' . __('Selected Edition', 'prepmedico-course-management') . '</div>';
                    echo '<div style="font-size: 16px; font-weight: 600;">' . esc_html($ordinal . ' ' . $course['name']) . '</div>';
                    echo '</div>';
                    echo '</div>';
                    echo '</div>';
                } else {
                    // No edition in URL - use current edition by default
                    $active_editions = PMCM_Core::get_active_editions($parent_slug);
                    if (!empty($active_editions)) {
                        $edition = $active_editions[0];
                        echo '<input type="hidden" name="pmcm_selected_edition" value="' . esc_attr($edition['slot']) . '">';
                        echo '<input type="hidden" name="pmcm_selected_course" value="' . esc_attr($parent_slug) . '">';
                    }
                }

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
     * Uses the edition stored in cart session (selected by customer), not the current edition
     */
    public static function add_edition_to_cart_item_name($name, $cart_item, $cart_item_key) {
        // Check for edition data in cart session
        if (WC()->session) {
            $edition_data = WC()->session->get('wcem_edition_' . $cart_item_key);
            if ($edition_data && !empty($edition_data['edition_name'])) {
                // Use the edition name from cart session (already formatted with ordinal)
                $edition_number = $edition_data['edition_number'];
                if ($edition_number && !preg_match('/^\d+(st|nd|rd|th)\s+-\s+/', $name)) {
                    return PMCM_Core::get_ordinal($edition_number) . ' - ' . $name;
                }
                return $name;
            }
        }

        // Fallback to default behavior
        $product_id = $cart_item['product_id'];
        return self::prepend_edition_to_title($name, $product_id);
    }

    /**
     * Add edition to order item name (for order confirmation and emails)
     * Reads the saved edition from order item meta first, then falls back to current edition
     */
    public static function add_edition_to_order_item_name($name, $item) {
        // Check if edition was saved to order item meta (from cart session at checkout)
        $saved_edition_number = $item->get_meta('_course_edition');
        if (!empty($saved_edition_number)) {
            $edition_number = intval($saved_edition_number);
            if ($edition_number > 0 && !preg_match('/^\d+(st|nd|rd|th)\s+-\s+/', $name)) {
                return PMCM_Core::get_ordinal($edition_number) . ' - ' . $name;
            }
            return $name;
        }

        // Fallback to current edition from database
        $product_id = $item->get_product_id();
        return self::prepend_edition_to_title($name, $product_id);
    }

    /**
     * Helper: Prepend edition number to title
     * Only for parent courses with edition_management enabled
     * Child categories and courses without edition_management show just the title
     *
     * Priority for edition number:
     * 1. URL parameter (?edition=12) - when on product page
     * 2. Current edition from database
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

                    // Check for URL parameter first (when customer selected edition from table)
                    $url_edition = isset($_GET['edition']) ? intval($_GET['edition']) : 0;

                    if ($url_edition > 0) {
                        $edition = $url_edition;
                    } else {
                        // Default to current edition
                        $edition = get_option($prefix . 'current_edition', 1);
                    }

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
            echo "\n✓ " . __('ASiT MEMBER VERIFIED', 'prepmedico-course-management') . "\n";
            echo sprintf(__('Membership Number: %s', 'prepmedico-course-management'), $asit_number);
            echo "\n\n";
        } else {
            echo '<table cellpadding="0" cellspacing="0" border="0" style="margin: 20px 0; width: 100%;">';
            echo '<tr><td style="background: linear-gradient(135deg, #8d2063, #442e8c); border-radius: 8px; padding: 20px;">';
            echo '<table cellpadding="0" cellspacing="0" border="0" width="100%">';
            echo '<tr>';
            echo '<td width="50" style="vertical-align: middle;">';
            echo '<div style="width: 40px; height: 40px; background: rgba(255,255,255,0.2); border-radius: 50%; text-align: center; line-height: 40px;">';
            echo '<span style="color: #fff; font-size: 20px; font-weight: bold;">✓</span>';
            echo '</div>';
            echo '</td>';
            echo '<td style="vertical-align: middle; padding-left: 15px;">';
            echo '<div style="color: rgba(255,255,255,0.9); font-size: 13px; margin-bottom: 2px;">' . __('ASiT Member Verified', 'prepmedico-course-management') . '</div>';
            echo '<div style="color: #fff; font-size: 20px; font-weight: 700;">#' . esc_html($asit_number) . '</div>';
            echo '</td>';
            echo '</tr>';
            echo '</table>';
            echo '</td></tr>';
            echo '</table>';
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

        echo '<div style="background: linear-gradient(135deg, #8d2063, #442e8c); border-radius: 8px; color: #fff; padding: 20px 25px; margin-bottom: 20px; display: flex; align-items: center; gap: 15px;">';
        echo '<span style="display: flex; align-items: center; justify-content: center; width: 40px; height: 40px; background: rgba(255,255,255,0.2); border-radius: 50%;">';
        echo '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>';
        echo '</span>';
        echo '<div>';
        echo '<div style="font-size: 14px; opacity: 0.9; margin-bottom: 2px;">' . __('ASiT Member Verified', 'prepmedico-course-management') . '</div>';
        echo '<div style="font-size: 22px; font-weight: 700;">#' . esc_html($asit_number) . '</div>';
        echo '</div>';
        echo '</div>';
    }

    /**
     * Output dynamic CSS to disable .enrol_btn_course buttons
     * when next edition dates are not available (TBA)
     */
    public static function output_dynamic_enrol_css() {
        $courses = PMCM_Core::get_courses();
        $disabled_courses = [];

        foreach ($courses as $slug => $course) {
            if (!isset($course['edition_management']) || !$course['edition_management']) {
                continue;
            }

            $prefix = $course['settings_prefix'];
            $next_enabled = get_option($prefix . 'next_enabled', 'no');

            $has_next_dates = false;
            if ($next_enabled === 'yes') {
                $next_start = get_option($prefix . 'next_start', '');
                $next_end = get_option($prefix . 'next_end', '');
                if (!empty($next_start) && !empty($next_end)) {
                    $has_next_dates = true;
                }
            }

            if (!$has_next_dates) {
                $disabled_courses[] = $slug;
            }
        }

        if (empty($disabled_courses)) {
            return;
        }

        echo '<style id="pmcm-enrol-btn-css">';
        foreach ($disabled_courses as $slug) {
            echo '.pmcm-next-' . esc_attr($slug) . ' .enrol_btn_course,';
            echo '.enrol_btn_course.pmcm-next-' . esc_attr($slug) . ',';
        }
        echo '.pmcm-dates-tba .enrol_btn_course,';
        echo '.enrol_btn_course.pmcm-dates-tba';
        echo '{ pointer-events: none !important; opacity: 0.5 !important; cursor: not-allowed !important; }';
        echo '</style>';
    }
}
