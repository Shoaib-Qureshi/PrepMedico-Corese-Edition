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
     * Display edition selector when multiple editions are active
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

                // Check if customer needs to choose between editions
                if (!PMCM_Core::requires_edition_choice($parent_slug)) {
                    // Only one edition active - add hidden field with current edition info
                    $active_editions = PMCM_Core::get_active_editions($parent_slug);
                    if (!empty($active_editions)) {
                        $edition = $active_editions[0];
                        echo '<input type="hidden" name="pmcm_selected_edition" value="' . esc_attr($edition['slot']) . '">';
                        echo '<input type="hidden" name="pmcm_selected_course" value="' . esc_attr($parent_slug) . '">';
                    }
                    return;
                }

                // Multiple editions active - show selector
                $active_editions = PMCM_Core::get_active_editions($parent_slug);

                if (count($active_editions) > 1) {
                    echo '<div class="pmcm-edition-selector" style="margin: 20px 0; padding: 20px; background: linear-gradient(135deg, #f8f9fa, #e9ecef); border-radius: 8px; border: 2px solid #8d2063;">';
                    echo '<h4 style="margin: 0 0 15px 0; color: #8d2063; font-size: 16px;">';
                    echo '<span class="dashicons dashicons-calendar-alt" style="margin-right: 8px;"></span>';
                    echo __('Select Your Edition', 'prepmedico-course-management');
                    echo '</h4>';

                    echo '<input type="hidden" name="pmcm_selected_course" value="' . esc_attr($parent_slug) . '">';

                    foreach ($active_editions as $index => $edition) {
                        $slot = $edition['slot'];
                        $edition_name = $edition['edition_name'];
                        $start = $edition['edition_start'];
                        $end = $edition['edition_end'];
                        $is_next = ($slot === 'next');

                        $date_range = '';
                        if (!empty($start) && !empty($end)) {
                            $date_range = date('M j, Y', strtotime($start)) . ' - ' . date('M j, Y', strtotime($end));
                        }

                        $input_id = 'pmcm_edition_' . $parent_slug . '_' . $slot;
                        $checked = ($index === 0) ? 'checked' : '';

                        echo '<label for="' . esc_attr($input_id) . '" style="display: flex; align-items: flex-start; padding: 12px 15px; margin-bottom: 10px; background: #fff; border-radius: 6px; cursor: pointer; border: 2px solid ' . ($index === 0 ? '#8d2063' : '#ddd') . '; transition: all 0.2s;" class="pmcm-edition-option">';
                        echo '<input type="radio" id="' . esc_attr($input_id) . '" name="pmcm_selected_edition" value="' . esc_attr($slot) . '" ' . $checked . ' style="margin: 3px 12px 0 0; accent-color: #8d2063;">';
                        echo '<div style="flex: 1;">';
                        echo '<div style="font-weight: 600; font-size: 15px; color: #333;">' . esc_html($edition_name);
                        if ($is_next) {
                            echo ' <span style="background: #28a745; color: #fff; padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: 600; margin-left: 8px;">NEW</span>';
                        }
                        echo '</div>';
                        if ($date_range) {
                            echo '<div style="font-size: 13px; color: #666; margin-top: 4px;">' . esc_html($date_range) . '</div>';
                        }
                        echo '</div>';
                        echo '</label>';
                    }

                    echo '</div>';

                    // Add JavaScript to update border on selection
                    echo '<script>
                    jQuery(document).ready(function($) {
                        $(".pmcm-edition-selector input[type=radio]").on("change", function() {
                            $(".pmcm-edition-option").css("border-color", "#ddd");
                            $(this).closest(".pmcm-edition-option").css("border-color", "#8d2063");
                        });
                    });
                    </script>';
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
}
