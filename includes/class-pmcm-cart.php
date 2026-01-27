<?php
/**
 * PMCM Cart Class
 * Handles cart and order operations
 *
 * @package PrepMedico_Course_Management
 */

if (!defined('ABSPATH')) {
    exit;
}

class PMCM_Cart {

    /**
     * Initialize cart hooks
     */
    public static function init() {
        add_action('woocommerce_add_to_cart', [__CLASS__, 'save_edition_to_cart'], 10, 6);
        add_filter('woocommerce_get_item_data', [__CLASS__, 'display_edition_in_cart'], 10, 2);
        add_action('woocommerce_checkout_create_order_line_item', [__CLASS__, 'save_edition_to_order_item'], 10, 4);
        add_action('woocommerce_checkout_order_processed', [__CLASS__, 'save_edition_to_order'], 10, 3);
        add_action('woocommerce_store_api_checkout_order_processed', [__CLASS__, 'save_edition_to_order_block'], 10, 1);
        add_action('woocommerce_admin_order_data_after_billing_address', [__CLASS__, 'display_edition_in_admin_order']);
    }

    /**
     * Save edition to cart when product is added
     */
    public static function save_edition_to_cart($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data) {
        $categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'slugs']);

        foreach ($categories as $category_slug) {
            $course_data = PMCM_Core::get_course_for_category($category_slug);

            if ($course_data) {
                $course = $course_data['course'];
                $parent_slug = $course_data['parent_slug'];
                $prefix = $course['settings_prefix'];
                $is_child = $course_data['is_child'];

                $edition_number = get_option($prefix . 'current_edition', 1);

                // Check if course has edition management
                $has_edition_management = isset($course['edition_management']) && $course['edition_management'] === true;

                // For backend storage: all courses with edition_management get full edition name
                // For Library Subscription (no edition_management): just the course name
                $edition_name = $has_edition_management
                    ? PMCM_Core::get_ordinal($edition_number) . ' ' . $course['name']
                    : $course['name'];

                // Store edition number for all courses with edition_management (including sub-categories)
                // Only Library Subscription (edition_management: false) gets null
                $edition_number_to_store = $has_edition_management ? $edition_number : null;

                $edition_data = [
                    'course_slug' => $parent_slug,
                    'original_category' => $category_slug,
                    'is_child_category' => $is_child,
                    'course_name' => $course['name'],
                    'edition_number' => $edition_number_to_store,
                    'edition_name' => $edition_name,
                    'edition_start' => get_option($prefix . 'edition_start', ''),
                    'edition_end' => get_option($prefix . 'edition_end', '')
                ];

                WC()->session->set('wcem_edition_' . $cart_item_key, $edition_data);
                break;
            }
        }
    }

    /**
     * Display edition info in cart
     */
    public static function display_edition_in_cart($item_data, $cart_item) {
        $cart_item_key = $cart_item['key'] ?? '';

        if ($cart_item_key && WC()->session) {
            $edition_data = WC()->session->get('wcem_edition_' . $cart_item_key);

            if ($edition_data) {
                $item_data[] = [
                    'key' => __('Edition', 'prepmedico-course-management'),
                    'value' => $edition_data['edition_name']
                ];
            }
        }

        return $item_data;
    }

    /**
     * Save edition to order line item
     */
    public static function save_edition_to_order_item($item, $cart_item_key, $values, $order) {
        if (WC()->session) {
            $edition_data = WC()->session->get('wcem_edition_' . $cart_item_key);

            if ($edition_data) {
                $item->add_meta_data('_course_slug', $edition_data['course_slug']);
                $item->add_meta_data('_course_edition', $edition_data['edition_number']);
                $item->add_meta_data('_edition_name', $edition_data['edition_name']);
                $item->add_meta_data('_edition_start', $edition_data['edition_start']);
                $item->add_meta_data('_edition_end', $edition_data['edition_end']);
            }
        }
    }

    /**
     * Save edition information to order meta on checkout
     */
    public static function save_edition_to_order($order_id, $posted_data = null, $order = null) {
        if (!$order) {
            $order = wc_get_order($order_id);
        }

        if (!$order) {
            return;
        }

        $courses_in_order = [];
        $processed_parents = [];

        foreach ($order->get_items() as $item_id => $item) {
            $product_id = $item->get_product_id();
            $categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'slugs']);

            foreach ($categories as $category_slug) {
                $course_data = PMCM_Core::get_course_for_category($category_slug);

                if ($course_data) {
                    $parent_slug = $course_data['parent_slug'];

                    if (in_array($parent_slug, $processed_parents)) {
                        continue;
                    }

                    $course = $course_data['course'];
                    $prefix = $course['settings_prefix'];
                    $is_child = $course_data['is_child'];

                    $edition_number = get_option($prefix . 'current_edition', 1);

                    // Check if course has edition management
                    $has_edition_management = isset($course['edition_management']) && $course['edition_management'] === true;

                    // For backend storage: all courses with edition_management get full edition name
                    // For Library Subscription (no edition_management): just the course name
                    $edition_name = $has_edition_management
                        ? PMCM_Core::get_ordinal($edition_number) . ' ' . $course['name']
                        : $course['name'];

                    // Store edition number for all courses with edition_management (including sub-categories)
                    // Only Library Subscription (edition_management: false) gets null
                    $edition_number_to_save = $has_edition_management ? $edition_number : null;

                    $edition_start = get_option($prefix . 'edition_start', '');
                    $edition_end = get_option($prefix . 'edition_end', '');

                    $order->update_meta_data('_course_slug_' . $parent_slug, $parent_slug);
                    $order->update_meta_data('_course_edition_' . $parent_slug, $edition_number_to_save);
                    $order->update_meta_data('_edition_name_' . $parent_slug, $edition_name);
                    $order->update_meta_data('_edition_start_' . $parent_slug, $edition_start);
                    $order->update_meta_data('_edition_end_' . $parent_slug, $edition_end);
                    $order->update_meta_data('_original_category_' . $parent_slug, $category_slug);

                    wc_update_order_item_meta($item_id, '_course_slug', $parent_slug);
                    wc_update_order_item_meta($item_id, '_original_category', $category_slug);
                    wc_update_order_item_meta($item_id, '_course_edition', $edition_number_to_save);
                    wc_update_order_item_meta($item_id, '_edition_name', $edition_name);
                    wc_update_order_item_meta($item_id, '_edition_start', $edition_start);
                    wc_update_order_item_meta($item_id, '_edition_end', $edition_end);

                    $courses_in_order[] = [
                        'slug' => $parent_slug,
                        'original_category' => $category_slug,
                        'is_child' => $course_data['is_child'],
                        'course' => $course,
                        'edition' => $edition_number_to_save,
                        'edition_name' => $edition_name
                    ];

                    $processed_parents[] = $parent_slug;
                    break;
                }
            }
        }

        if (!empty($courses_in_order)) {
            $order->update_meta_data('_wcem_needs_fluentcrm_sync', 'yes');
            $order->update_meta_data('_wcem_courses_data', json_encode($courses_in_order));
        }

        $order->save();

        PMCM_Core::log_activity('Order #' . $order_id . ' processed with courses: ' . implode(', ', array_column($courses_in_order, 'edition_name')), 'success');
    }

    /**
     * Save edition for block checkout
     */
    public static function save_edition_to_order_block($order) {
        self::save_edition_to_order($order->get_id(), null, $order);
    }

    /**
     * Display edition info in admin order page
     */
    public static function display_edition_in_admin_order($order) {
        $has_edition_data = false;
        $has_course_products = false;

        // Check if order has any course products
        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            $categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'slugs']);
            foreach ($categories as $category_slug) {
                if (PMCM_Core::get_course_for_category($category_slug)) {
                    $has_course_products = true;
                    break 2;
                }
            }
        }

        if (!$has_course_products) {
            return;
        }

        $output = '<div class="wcem-order-editions" style="margin-top:20px;padding:15px;background:#f6f7f7;border:1px solid #dcdcde;"><h3 style="margin:0 0 10px;">' . __('Course Edition Information', 'prepmedico-course-management') . '</h3>';

        foreach (PMCM_Core::get_courses() as $category_slug => $course) {
            $edition_name = $order->get_meta('_edition_name_' . $category_slug);

            if ($edition_name) {
                $has_edition_data = true;
                $edition_number = $order->get_meta('_course_edition_' . $category_slug);
                $edition_start = $order->get_meta('_edition_start_' . $category_slug);
                $edition_end = $order->get_meta('_edition_end_' . $category_slug);

                $output .= '<p style="margin:5px 0;"><strong>' . esc_html($course['name']) . ':</strong> ' . esc_html($edition_name);
                if ($edition_number) {
                    $output .= ' <span style="color:#666;">(Edition #' . esc_html($edition_number) . ')</span>';
                }
                if ($edition_start && $edition_end) {
                    $output .= ' <em style="color:#666;">(' . date('M j, Y', strtotime($edition_start)) . ' - ' . date('M j, Y', strtotime($edition_end)) . ')</em>';
                }
                $output .= '</p>';
            }
        }

        $synced = $order->get_meta('_wcem_fluentcrm_synced');
        $sync_time = $order->get_meta('_wcem_fluentcrm_sync_time');

        // Show sync status
        if ($synced === 'yes') {
            $output .= '<p style="color:green;margin:10px 0 5px;"><span class="dashicons dashicons-yes"></span> FluentCRM synced: ' . esc_html($sync_time) . '</p>';
        } else if ($has_edition_data) {
            $output .= '<p style="color:orange;margin:10px 0 5px;"><span class="dashicons dashicons-warning"></span> FluentCRM not synced</p>';
        }

        // Action buttons
        $output .= '<div style="margin-top:10px; display:flex; gap:10px; flex-wrap:wrap;">';

        // Update Edition Data button (recalculates based on current settings)
        $output .= '<button type="button" class="button button-small wcem-update-edition" data-order="' . $order->get_id() . '" title="Recalculate edition data based on current settings">';
        $output .= __('Update Edition Data', 'prepmedico-course-management') . '</button>';

        // Sync to FluentCRM button
        if ($has_edition_data || $has_course_products) {
            $output .= '<button type="button" class="button button-small wcem-sync-order" data-order="' . $order->get_id() . '">';
            $output .= ($synced === 'yes' ? __('Re-sync to FluentCRM', 'prepmedico-course-management') : __('Sync to FluentCRM', 'prepmedico-course-management')) . '</button>';
        }

        $output .= '</div>';

        $output .= '<script>
            jQuery(document).ready(function($) {
                $(".wcem-sync-order").on("click", function() {
                    var btn = $(this);
                    var orderId = btn.data("order");
                    btn.prop("disabled", true).text("Syncing...");
                    $.post(ajaxurl, {
                        action: "wcem_sync_order",
                        order_id: orderId,
                        nonce: "' . wp_create_nonce('wcem_admin_nonce') . '"
                    }, function(response) {
                        if (response.success) {
                            alert("Synced successfully!");
                            location.reload();
                        } else {
                            alert("Error: " + response.data.message);
                            btn.prop("disabled", false).text("Sync to FluentCRM");
                        }
                    });
                });

                $(".wcem-update-edition").on("click", function() {
                    var btn = $(this);
                    var orderId = btn.data("order");
                    if (!confirm("This will update edition data based on current settings. Continue?")) {
                        return;
                    }
                    btn.prop("disabled", true).text("Updating...");
                    $.post(ajaxurl, {
                        action: "wcem_update_order_edition",
                        order_id: orderId,
                        nonce: "' . wp_create_nonce('wcem_admin_nonce') . '"
                    }, function(response) {
                        if (response.success) {
                            alert(response.data.message);
                            location.reload();
                        } else {
                            alert("Error: " + response.data.message);
                            btn.prop("disabled", false).text("Update Edition Data");
                        }
                    });
                });
            });
        </script>';

        $output .= '</div>';

        echo $output;
    }
}
