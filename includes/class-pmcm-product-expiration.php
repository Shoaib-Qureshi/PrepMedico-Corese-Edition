<?php
/**
 * PMCM Product Expiration Class
 * Handles product-level registration close dates and edition locking
 *
 * @package PrepMedico_Course_Management
 */

if (!defined('ABSPATH')) {
    exit;
}

class PMCM_Product_Expiration {

    /**
     * Initialize hooks
     */
    public static function init() {
        // Admin: Add meta fields to product edit page
        add_action('woocommerce_product_options_general_product_data', [__CLASS__, 'add_product_meta_fields']);
        add_action('woocommerce_process_product_meta', [__CLASS__, 'save_product_meta_fields']);

        // Frontend: Check expiration on product page
        add_action('woocommerce_before_single_product', [__CLASS__, 'check_product_expiration_status']);

        // Frontend: Filter expired products from shop/category pages (optional - can be enabled)
        // add_action('woocommerce_product_query', [__CLASS__, 'filter_expired_products_from_query']);

        // Cart: Validate before adding to cart
        add_filter('woocommerce_add_to_cart_validation', [__CLASS__, 'validate_add_to_cart'], 10, 3);

        // Display expiration info on product page
        add_action('woocommerce_single_product_summary', [__CLASS__, 'display_expiration_notice'], 15);

        // Cron: Daily check for expired products
        add_action('pmcm_daily_expiration_check', [__CLASS__, 'daily_expiration_check']);
    }

    /**
     * Add custom meta fields to product edit page
     */
    public static function add_product_meta_fields() {
        global $post;

        // Check if product is in a managed category
        $categories = wp_get_post_terms($post->ID, 'product_cat', ['fields' => 'slugs']);
        $is_managed = false;
        $parent_course = null;

        foreach ($categories as $cat_slug) {
            $course_data = PMCM_Core::get_course_for_category($cat_slug);
            if ($course_data) {
                $is_managed = true;
                $parent_course = $course_data;
                break;
            }
        }

        if (!$is_managed) {
            return;
        }

        echo '<div class="options_group pmcm-product-options" style="border-top: 1px solid #eee; padding-top: 10px;">';
        echo '<h4 style="padding-left: 12px; color: #8d2063;">' . __('PrepMedico Edition Settings', 'prepmedico-course-management') . '</h4>';

        // Registration Close Date (Expiration Date)
        woocommerce_wp_text_input([
            'id' => '_expiration_date',
            'label' => __('Registration Close Date', 'prepmedico-course-management'),
            'placeholder' => 'YYYY-MM-DD',
            'desc_tip' => true,
            'description' => __('Set the date when registration closes for this product. Product will be marked as "Out of Stock" after this date.', 'prepmedico-course-management'),
            'type' => 'date',
        ]);

        // Edition Number Lock
        $current_edition = '';
        if ($parent_course) {
            $prefix = $parent_course['course']['settings_prefix'];
            $current_edition = get_option($prefix . 'current_edition', 1);
        }

        woocommerce_wp_text_input([
            'id' => '_pmcm_edition_number',
            'label' => __('Lock to Edition #', 'prepmedico-course-management'),
            'placeholder' => $current_edition ? sprintf(__('Current: %d', 'prepmedico-course-management'), $current_edition) : '',
            'desc_tip' => true,
            'description' => __('Optional: Lock this product to a specific edition number. Leave empty to use the current active edition. When locked, product will only be available for that edition.', 'prepmedico-course-management'),
            'type' => 'number',
            'custom_attributes' => ['min' => '1'],
        ]);

        // Edition Lock Toggle
        woocommerce_wp_checkbox([
            'id' => '_pmcm_edition_locked',
            'label' => __('Edition Locked', 'prepmedico-course-management'),
            'description' => __('When checked, this product is only available for the specified edition number above.', 'prepmedico-course-management'),
        ]);

        // Info about parent course
        if ($parent_course) {
            echo '<p class="form-field" style="padding-left: 12px;">';
            echo '<span style="color: #666; font-size: 12px;">';
            echo sprintf(
                __('Parent Course: <strong>%s</strong> | Current Edition: <strong>%s</strong>', 'prepmedico-course-management'),
                esc_html($parent_course['course']['name']),
                esc_html(PMCM_Core::get_ordinal($current_edition))
            );
            echo '</span>';
            echo '</p>';
        }

        echo '</div>';
    }

    /**
     * Save product meta fields
     */
    public static function save_product_meta_fields($post_id) {
        // Expiration Date
        $expiration_date = isset($_POST['_expiration_date']) ? sanitize_text_field($_POST['_expiration_date']) : '';
        if (!empty($expiration_date) && strtotime($expiration_date) === false) {
            $expiration_date = ''; // Invalid date
        }
        update_post_meta($post_id, '_expiration_date', $expiration_date);

        // Edition Number
        $edition_number = isset($_POST['_pmcm_edition_number']) ? absint($_POST['_pmcm_edition_number']) : '';
        update_post_meta($post_id, '_pmcm_edition_number', $edition_number);

        // Edition Locked
        $edition_locked = isset($_POST['_pmcm_edition_locked']) ? 'yes' : 'no';
        update_post_meta($post_id, '_pmcm_edition_locked', $edition_locked);
    }

    /**
     * Check product expiration status on product page
     * Updates stock status based on expiration date
     */
    public static function check_product_expiration_status() {
        global $post;

        if (!$post || $post->post_type !== 'product') {
            return;
        }

        $product_id = $post->ID;
        $expiration_date = get_post_meta($product_id, '_expiration_date', true);

        if (empty($expiration_date)) {
            return;
        }

        $current_timestamp = strtotime(current_time('Y-m-d'));
        $expiration_timestamp = strtotime($expiration_date);

        $product = wc_get_product($product_id);
        if (!$product) {
            return;
        }

        if ($current_timestamp > $expiration_timestamp) {
            // Product has expired - mark out of stock
            if ($product->is_type('variable')) {
                $variations = $product->get_children();
                foreach ($variations as $variation_id) {
                    wc_update_product_stock_status($variation_id, 'outofstock');
                }
            }
            wc_update_product_stock_status($product_id, 'outofstock');
        } else {
            // Product is still valid - ensure it's in stock (if it was marked out due to expiration)
            $was_expired = get_post_meta($product_id, '_pmcm_was_expired', true);
            if ($was_expired === 'yes') {
                if ($product->is_type('variable')) {
                    $variations = $product->get_children();
                    foreach ($variations as $variation_id) {
                        wc_update_product_stock_status($variation_id, 'instock');
                    }
                }
                wc_update_product_stock_status($product_id, 'instock');
                delete_post_meta($product_id, '_pmcm_was_expired');
            }
        }
    }

    /**
     * Validate add to cart - check expiration and edition lock
     */
    public static function validate_add_to_cart($passed, $product_id, $quantity) {
        if (!$passed) {
            return $passed;
        }

        // Check expiration date
        $expiration_date = get_post_meta($product_id, '_expiration_date', true);
        if (!empty($expiration_date)) {
            $current_timestamp = strtotime(current_time('Y-m-d'));
            $expiration_timestamp = strtotime($expiration_date);

            if ($current_timestamp > $expiration_timestamp) {
                wc_add_notice(
                    __('Registration for this product has closed.', 'prepmedico-course-management'),
                    'error'
                );
                return false;
            }
        }

        // Check edition lock
        $edition_locked = get_post_meta($product_id, '_pmcm_edition_locked', true);
        $locked_edition = get_post_meta($product_id, '_pmcm_edition_number', true);

        if ($edition_locked === 'yes' && !empty($locked_edition)) {
            // Get product's parent course
            $categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'slugs']);

            foreach ($categories as $cat_slug) {
                $course_data = PMCM_Core::get_course_for_category($cat_slug);

                if ($course_data) {
                    $parent_slug = $course_data['parent_slug'];

                    // Check which edition user selected (from frontend selector)
                    $selected_slot = isset($_POST['pmcm_selected_edition']) ? sanitize_text_field($_POST['pmcm_selected_edition']) : 'current';
                    $selected_course = isset($_POST['pmcm_selected_course']) ? sanitize_text_field($_POST['pmcm_selected_course']) : '';

                    // Get the edition number for selected slot
                    $prefix = $course_data['course']['settings_prefix'];
                    if ($selected_slot === 'next' && $selected_course === $parent_slug) {
                        $selected_edition = intval(get_option($prefix . 'next_edition', 0));
                    } else {
                        $selected_edition = intval(get_option($prefix . 'current_edition', 1));
                    }

                    // Compare with locked edition
                    if (intval($locked_edition) !== $selected_edition) {
                        $locked_edition_name = PMCM_Core::get_ordinal($locked_edition) . ' ' . $course_data['course']['name'];
                        wc_add_notice(
                            sprintf(
                                __('This product is only available for %s. Please select the correct edition.', 'prepmedico-course-management'),
                                $locked_edition_name
                            ),
                            'error'
                        );
                        return false;
                    }
                    break;
                }
            }
        }

        return $passed;
    }

    /**
     * Display expiration notice on product page
     */
    public static function display_expiration_notice() {
        global $post;

        if (!$post || $post->post_type !== 'product') {
            return;
        }

        $expiration_date = get_post_meta($post->ID, '_expiration_date', true);
        $edition_locked = get_post_meta($post->ID, '_pmcm_edition_locked', true);
        $locked_edition = get_post_meta($post->ID, '_pmcm_edition_number', true);

        $notices = [];

        // Expiration notice
        if (!empty($expiration_date)) {
            $current_timestamp = strtotime(current_time('Y-m-d'));
            $expiration_timestamp = strtotime($expiration_date);
            $days_left = floor(($expiration_timestamp - $current_timestamp) / DAY_IN_SECONDS);

            if ($current_timestamp > $expiration_timestamp) {
                $notices[] = [
                    'type' => 'expired',
                    'message' => __('Registration Closed', 'prepmedico-course-management')
                ];
            } elseif ($days_left <= 7) {
                $notices[] = [
                    'type' => 'warning',
                    'message' => sprintf(
                        _n(
                            'Registration closes in %d day!',
                            'Registration closes in %d days!',
                            $days_left,
                            'prepmedico-course-management'
                        ),
                        $days_left
                    )
                ];
            } else {
                $notices[] = [
                    'type' => 'info',
                    'message' => sprintf(
                        __('Registration closes: %s', 'prepmedico-course-management'),
                        date_i18n(get_option('date_format'), $expiration_timestamp)
                    )
                ];
            }
        }

        // Edition lock notice
        if ($edition_locked === 'yes' && !empty($locked_edition)) {
            $categories = wp_get_post_terms($post->ID, 'product_cat', ['fields' => 'slugs']);
            foreach ($categories as $cat_slug) {
                $course_data = PMCM_Core::get_course_for_category($cat_slug);
                if ($course_data) {
                    $edition_name = PMCM_Core::get_ordinal($locked_edition) . ' ' . $course_data['course']['name'];
                    $notices[] = [
                        'type' => 'edition',
                        'message' => sprintf(
                            __('This product is for %s only', 'prepmedico-course-management'),
                            $edition_name
                        )
                    ];
                    break;
                }
            }
        }

        if (empty($notices)) {
            return;
        }

        echo '<div class="pmcm-product-notices" style="margin: 15px 0;">';
        foreach ($notices as $notice) {
            $bg_color = '#d1ecf1';
            $text_color = '#0c5460';
            $icon = 'info';

            switch ($notice['type']) {
                case 'expired':
                    $bg_color = '#f8d7da';
                    $text_color = '#721c24';
                    $icon = 'warning';
                    break;
                case 'warning':
                    $bg_color = '#fff3cd';
                    $text_color = '#856404';
                    $icon = 'clock';
                    break;
                case 'edition':
                    $bg_color = 'linear-gradient(135deg, #8d2063, #442e8c)';
                    $text_color = '#fff';
                    $icon = 'tag';
                    break;
            }

            $style = "display: flex; align-items: center; gap: 10px; padding: 10px 15px; border-radius: 6px; margin-bottom: 8px; font-size: 14px;";
            if ($notice['type'] === 'edition') {
                $style .= "background: $bg_color; color: $text_color;";
            } else {
                $style .= "background: $bg_color; color: $text_color;";
            }

            echo '<div style="' . esc_attr($style) . '">';
            echo '<span class="dashicons dashicons-' . esc_attr($icon) . '"></span>';
            echo '<span>' . esc_html($notice['message']) . '</span>';
            echo '</div>';
        }
        echo '</div>';
    }

    /**
     * Daily cron check for expired products
     */
    public static function daily_expiration_check() {
        global $wpdb;

        // Find all products with expiration dates that have passed
        $today = current_time('Y-m-d');

        $expired_products = $wpdb->get_col($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta}
             WHERE meta_key = '_expiration_date'
             AND meta_value != ''
             AND meta_value < %s",
            $today
        ));

        foreach ($expired_products as $product_id) {
            $product = wc_get_product($product_id);
            if (!$product) {
                continue;
            }

            // Check if already out of stock
            if ($product->get_stock_status() === 'outofstock') {
                continue;
            }

            // Mark as out of stock
            if ($product->is_type('variable')) {
                $variations = $product->get_children();
                foreach ($variations as $variation_id) {
                    wc_update_product_stock_status($variation_id, 'outofstock');
                }
            }
            wc_update_product_stock_status($product_id, 'outofstock');

            // Mark that it was expired by our system
            update_post_meta($product_id, '_pmcm_was_expired', 'yes');

            PMCM_Core::log_activity('Product #' . $product_id . ' marked out of stock (registration closed)', 'info');
        }
    }

    /**
     * Check if product is available for a specific edition
     */
    public static function is_product_available_for_edition($product_id, $edition_number) {
        // Check expiration
        $expiration_date = get_post_meta($product_id, '_expiration_date', true);
        if (!empty($expiration_date)) {
            if (strtotime(current_time('Y-m-d')) > strtotime($expiration_date)) {
                return false; // Expired
            }
        }

        // Check edition lock
        $edition_locked = get_post_meta($product_id, '_pmcm_edition_locked', true);
        $locked_edition = get_post_meta($product_id, '_pmcm_edition_number', true);

        if ($edition_locked === 'yes' && !empty($locked_edition)) {
            if (intval($locked_edition) !== intval($edition_number)) {
                return false; // Locked to different edition
            }
        }

        return true;
    }

    /**
     * Get product's locked edition number (if any)
     */
    public static function get_product_locked_edition($product_id) {
        $edition_locked = get_post_meta($product_id, '_pmcm_edition_locked', true);
        $locked_edition = get_post_meta($product_id, '_pmcm_edition_number', true);

        if ($edition_locked === 'yes' && !empty($locked_edition)) {
            return intval($locked_edition);
        }

        return null;
    }

    /**
     * Schedule cron event on plugin activation
     */
    public static function schedule_cron() {
        if (!wp_next_scheduled('pmcm_daily_expiration_check')) {
            wp_schedule_event(strtotime('tomorrow 2:00am'), 'daily', 'pmcm_daily_expiration_check');
        }
    }

    /**
     * Unschedule cron on plugin deactivation
     */
    public static function unschedule_cron() {
        $timestamp = wp_next_scheduled('pmcm_daily_expiration_check');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'pmcm_daily_expiration_check');
        }
    }
}
