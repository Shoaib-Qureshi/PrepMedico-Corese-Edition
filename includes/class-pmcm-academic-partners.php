<?php
/**
 * PMCM Academic Partners Class
 * Unified checkout UI and coupon routing for ASiT, BOMSS and Rouleaux Club
 *
 * @package PrepMedico_Course_Management
 */

if (!defined('ABSPATH')) {
    exit;
}

class PMCM_Academic_Partners {

    private static $partner_labels = [
        'asit'    => 'ASiT',
        'bomss'   => 'BOMSS',
        'rouleaux' => 'Rouleaux Club',
    ];

    // -------------------------------------------------------------------------
    // Bootstrap
    // -------------------------------------------------------------------------

    public static function init() {
        add_action('woocommerce_after_checkout_billing_form', [__CLASS__, 'add_partner_selector']);
        add_action('woocommerce_checkout_process',             [__CLASS__, 'validate_partner_selection']);
        add_action('woocommerce_checkout_update_order_review', [__CLASS__, 'apply_partner_coupon']);
        add_action('woocommerce_checkout_create_order',        [__CLASS__, 'save_partner_to_order'], 10, 2);
        add_action('woocommerce_checkout_order_processed',     [__CLASS__, 'set_membership_pending_status'], 10, 3);

        // Email registration
        add_filter('woocommerce_email_classes', [__CLASS__, 'register_membership_emails']);

        // Suppress default WC emails for membership-pending orders
        add_filter('woocommerce_email_enabled_new_order',                      [__CLASS__, 'suppress_default_emails_for_pending'], 10, 2);
        add_filter('woocommerce_email_enabled_customer_processing_order',      [__CLASS__, 'suppress_default_emails_for_pending'], 10, 2);
        add_filter('woocommerce_email_enabled_customer_on_hold_order',         [__CLASS__, 'suppress_default_emails_for_pending'], 10, 2);

        // Coupon bypass filters for BOMSS and ROULEAUX
        add_filter('woocommerce_coupon_is_valid',                              [__CLASS__, 'partner_coupon_is_valid'], 10, 2);
        add_filter('woocommerce_coupon_is_valid_for_product',                  [__CLASS__, 'partner_coupon_is_valid_for_product'], 10, 4);
        add_filter('woocommerce_coupon_is_valid_for_cart',                     [__CLASS__, 'partner_coupon_is_valid_for_cart'], 10, 2);
        add_filter('woocommerce_coupon_get_product_ids',                       [__CLASS__, 'bypass_coupon_product_ids'], 10, 2);
        add_filter('woocommerce_coupon_get_product_categories',                [__CLASS__, 'bypass_coupon_product_categories'], 10, 2);
        add_filter('woocommerce_coupon_get_excluded_product_ids',              [__CLASS__, 'bypass_coupon_excluded_product_ids'], 10, 2);
        add_filter('woocommerce_coupon_get_excluded_product_categories',       [__CLASS__, 'bypass_coupon_excluded_product_categories'], 10, 2);
        add_filter('woocommerce_coupon_get_exclude_sale_items',                [__CLASS__, 'bypass_coupon_exclude_sale_items'], 10, 2);
        add_filter('woocommerce_coupon_get_discount_amount',                   [__CLASS__, 'dynamic_partner_coupon_discount'], 10, 5);
        add_filter('woocommerce_coupon_error',                                 [__CLASS__, 'suppress_empty_coupon_notice'], 10, 3);

        // Frontend scripts/styles
        add_action('wp_head',   [__CLASS__, 'checkout_styles']);
        add_action('wp_footer', [__CLASS__, 'checkout_scripts']);
    }

    // -------------------------------------------------------------------------
    // Custom order status registration
    // -------------------------------------------------------------------------

    public static function register_order_status() {
        register_post_status('wc-membership-pending', [
            'label'                     => _x('Membership Pending', 'Order status', 'prepmedico-course-management'),
            'public'                    => false,
            'exclude_from_search'       => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop('Membership Pending <span class="count">(%s)</span>', 'Membership Pending <span class="count">(%s)</span>', 'prepmedico-course-management'),
        ]);
    }

    public static function add_order_status_to_list($statuses) {
        $statuses['wc-membership-pending'] = _x('Membership Pending Confirmation', 'Order status', 'prepmedico-course-management');
        return $statuses;
    }

    // -------------------------------------------------------------------------
    // Checkout UI
    // -------------------------------------------------------------------------

    public static function add_partner_selector($checkout) {
        $eligible = self::get_eligible_partners_for_cart();
        if (empty($eligible)) {
            return;
        }

        $session_partner = WC()->session ? WC()->session->get('pmcm_selected_partner', '') : '';
        $session_number  = WC()->session ? WC()->session->get('pmcm_partner_number', '') : '';

        echo '<div class="pmcm-partners-section" style="margin-top:20px;padding-top:20px;border-top:1px solid #e0e0e0;">';
        echo '<h3 style="font-size:16px;font-weight:600;color:#333;margin-bottom:12px;">' . esc_html__('Academic Partner Discount', 'prepmedico-course-management') . '</h3>';

        // Partner selector
        echo '<div class="form-row form-row-wide pmcm-partner-row">';
        echo '<label for="pmcm_selected_partner">' . esc_html__('Academic Partner', 'prepmedico-course-management') . '</label>';
        echo '<select id="pmcm_selected_partner" name="pmcm_selected_partner" class="pmcm-partner-select">';
        echo '<option value="">' . esc_html__('— None —', 'prepmedico-course-management') . '</option>';
        foreach ($eligible as $slug) {
            $label = isset(self::$partner_labels[$slug]) ? self::$partner_labels[$slug] : strtoupper($slug);
            printf(
                '<option value="%s"%s>%s</option>',
                esc_attr($slug),
                selected($session_partner, $slug, false),
                esc_html($label)
            );
        }
        echo '</select>';
        echo '</div>';

        // Membership number + apply button (hidden until partner selected)
        $hide = empty($session_partner) ? 'style="display:none;"' : '';
        echo '<div class="pmcm-partner-number-wrap" ' . $hide . '>';
        echo '<div class="pmcm-field-wrapper">';

        woocommerce_form_field('pmcm_partner_number', [
            'type'               => 'text',
            'class'              => ['form-row-first', 'pmcm-partner-input-field'],
            'label'              => __('Membership Number', 'prepmedico-course-management'),
            'placeholder'        => __('Enter your membership number', 'prepmedico-course-management'),
            'required'           => false,
            'maxlength'          => 10,
            'custom_attributes'  => [
                'pattern' => '[0-9]{5,10}',
                'title'   => 'Please enter your membership number (5–10 digits)',
            ],
        ], $checkout->get_value('pmcm_partner_number') ?: $session_number);

        echo '<p class="form-row form-row-last pmcm-button-wrapper">
                <button type="button" id="apply_partner_membership" class="pmcm-apply-button">' . esc_html__('Apply', 'prepmedico-course-management') . '</button>
              </p>';
        echo '</div>'; // .pmcm-field-wrapper

        echo '<p class="form-row form-row-wide pmcm-help-text">
                <small style="color:#666;">' . esc_html__('Note: Discount applies during the Early Bird period. If the membership number does not match our records, the order will be cancelled without refund.', 'prepmedico-course-management') . '</small>
              </p>';
        echo '</div>'; // .pmcm-partner-number-wrap
        echo '</div>'; // .pmcm-partners-section
    }

    // -------------------------------------------------------------------------
    // Validation
    // -------------------------------------------------------------------------

    public static function validate_partner_selection() {
        $partner = isset($_POST['pmcm_selected_partner']) ? sanitize_text_field($_POST['pmcm_selected_partner']) : '';
        $number  = isset($_POST['pmcm_partner_number']) ? sanitize_text_field($_POST['pmcm_partner_number']) : '';

        if (!empty($partner) && !in_array($partner, ['asit', 'bomss', 'rouleaux'], true)) {
            wc_add_notice(__('Invalid academic partner selected.', 'prepmedico-course-management'), 'error');
            return;
        }

        if (!empty($partner) && empty($number)) {
            wc_add_notice(__('Please enter your membership number to use the academic partner discount.', 'prepmedico-course-management'), 'error');
            return;
        }

        if (!empty($partner) && !empty($number) && !preg_match('/^[0-9]{5,10}$/', $number)) {
            wc_add_notice(__('Membership number must be 5–10 digits.', 'prepmedico-course-management'), 'error');
        }
    }

    // -------------------------------------------------------------------------
    // Coupon application (called on checkout AJAX review update)
    // -------------------------------------------------------------------------

    public static function apply_partner_coupon($post_data) {
        parse_str($post_data, $data);

        $partner = isset($data['pmcm_selected_partner']) ? sanitize_text_field($data['pmcm_selected_partner']) : '';
        $number  = isset($data['pmcm_partner_number'])   ? sanitize_text_field($data['pmcm_partner_number'])   : '';

        // Remove all partner coupons first
        self::remove_all_partner_coupons();

        if (empty($partner) || empty($number) || !preg_match('/^[0-9]{5,10}$/', $number)) {
            WC()->session->set('pmcm_selected_partner', '');
            WC()->session->set('pmcm_partner_number', '');
            return;
        }

        WC()->session->set('pmcm_selected_partner', $partner);
        WC()->session->set('pmcm_partner_number', $number);

        // Also keep ASiT session key for backwards compat
        if ($partner === 'asit') {
            WC()->session->set('asit_membership_number', $number);
        }

        $coupon_code = self::get_coupon_code_for_partner($partner);
        if (empty($coupon_code)) {
            return;
        }

        if (self::cart_has_partner_eligible_products($partner) && !WC()->cart->has_discount(strtolower($coupon_code))) {
            WC()->cart->apply_coupon(strtolower($coupon_code));
        }
    }

    // -------------------------------------------------------------------------
    // Save to order
    // -------------------------------------------------------------------------

    public static function save_partner_to_order($order, $data) {
        $partner = isset($_POST['pmcm_selected_partner']) ? sanitize_text_field($_POST['pmcm_selected_partner']) : '';
        $number  = isset($_POST['pmcm_partner_number'])   ? sanitize_text_field($_POST['pmcm_partner_number'])   : '';

        // Fall back to session if POST doesn't have it (block checkout etc.)
        if (empty($partner) && WC()->session) {
            $partner = WC()->session->get('pmcm_selected_partner', '');
            $number  = WC()->session->get('pmcm_partner_number', '');
        }

        if (empty($partner) || !preg_match('/^[0-9]{5,10}$/', $number)) {
            return;
        }

        $order->update_meta_data('_pmcm_academic_partner', $partner);
        $order->update_meta_data('_pmcm_partner_number', $number);

        // Backwards compat
        if ($partner === 'asit') {
            $order->update_meta_data('_asit_membership_number', $number);
        }
    }

    // -------------------------------------------------------------------------
    // Set order to membership-pending after checkout
    // -------------------------------------------------------------------------

    public static function set_membership_pending_status($order_id, $posted_data, $order) {
        if (!is_a($order, 'WC_Order')) {
            $order = wc_get_order($order_id);
        }
        if (!$order) {
            return;
        }

        $partner     = $order->get_meta('_pmcm_academic_partner');
        $partner_num = $order->get_meta('_pmcm_partner_number');

        if (empty($partner)) {
            return;
        }

        $partner_labels = self::$partner_labels;
        $partner_label  = isset($partner_labels[$partner]) ? $partner_labels[$partner] : strtoupper($partner);

        $order->update_status('wc-membership-pending', sprintf(
            __('Order placed with %s membership discount. Membership #%s pending admin verification.', 'prepmedico-course-management'),
            $partner_label,
            $partner_num
        ));
    }

    // -------------------------------------------------------------------------
    // Email classes registration
    // -------------------------------------------------------------------------

    public static function register_membership_emails($email_classes) {
        require_once PMCM_PLUGIN_DIR . 'includes/emails/class-pmcm-email-membership-pending-admin.php';
        require_once PMCM_PLUGIN_DIR . 'includes/emails/class-pmcm-email-membership-pending-customer.php';

        $email_classes['PMCM_Email_Membership_Pending_Admin']    = new PMCM_Email_Membership_Pending_Admin();
        $email_classes['PMCM_Email_Membership_Pending_Customer'] = new PMCM_Email_Membership_Pending_Customer();

        return $email_classes;
    }

    // -------------------------------------------------------------------------
    // Email suppression for membership-pending orders
    // -------------------------------------------------------------------------

    public static function suppress_default_emails_for_pending($enabled, $order) {
        if (!is_a($order, 'WC_Order')) {
            return $enabled;
        }
        if (!empty($order->get_meta('_pmcm_academic_partner'))) {
            return false;
        }
        return $enabled;
    }

    // -------------------------------------------------------------------------
    // Coupon bypass filters (BOMSS + ROULEAUX only — ASIT handled by PMCM_ASiT)
    // -------------------------------------------------------------------------

    private static function is_partner_coupon($coupon) {
        $code = strtolower($coupon->get_code());
        return $code === strtolower(get_option('pmcm_bomss_coupon_code', 'BOMSS'))
            || $code === strtolower(get_option('pmcm_rouleaux_coupon_code', 'ROULEAUX'));
    }

    public static function partner_coupon_is_valid($valid, $coupon) {
        return self::is_partner_coupon($coupon) ? true : $valid;
    }

    public static function partner_coupon_is_valid_for_product($valid, $product, $coupon, $cart_item) {
        return self::is_partner_coupon($coupon) ? true : $valid;
    }

    public static function partner_coupon_is_valid_for_cart($valid, $coupon) {
        return self::is_partner_coupon($coupon) ? true : $valid;
    }

    public static function bypass_coupon_product_ids($product_ids, $coupon) {
        return self::is_partner_coupon($coupon) ? [] : $product_ids;
    }

    public static function bypass_coupon_product_categories($category_ids, $coupon) {
        return self::is_partner_coupon($coupon) ? [] : $category_ids;
    }

    public static function bypass_coupon_excluded_product_ids($product_ids, $coupon) {
        return self::is_partner_coupon($coupon) ? [] : $product_ids;
    }

    public static function bypass_coupon_excluded_product_categories($category_ids, $coupon) {
        return self::is_partner_coupon($coupon) ? [] : $category_ids;
    }

    public static function bypass_coupon_exclude_sale_items($exclude, $coupon) {
        return self::is_partner_coupon($coupon) ? false : $exclude;
    }

    public static function suppress_empty_coupon_notice($error_message, $error_code, $coupon) {
        if ((int) $error_code !== WC_Coupon::E_WC_COUPON_PLEASE_ENTER) {
            return $error_message;
        }
        if (!function_exists('is_checkout') || !is_checkout()) {
            return $error_message;
        }
        $partner = WC()->session ? WC()->session->get('pmcm_selected_partner', '') : '';
        return !empty($partner) ? '' : $error_message;
    }

    // -------------------------------------------------------------------------
    // Dynamic discount for BOMSS / ROULEAUX coupons
    // -------------------------------------------------------------------------

    public static function dynamic_partner_coupon_discount($discount, $discounting_amount, $cart_item, $single, $coupon) {
        $bomss_code   = strtolower(get_option('pmcm_bomss_coupon_code', 'BOMSS'));
        $rouleaux_code = strtolower(get_option('pmcm_rouleaux_coupon_code', 'ROULEAUX'));
        $coupon_code  = strtolower($coupon->get_code());

        if ($coupon_code !== $bomss_code && $coupon_code !== $rouleaux_code) {
            return $discount;
        }

        $partner = $coupon_code === $bomss_code ? 'bomss' : 'rouleaux';

        $product_id = 0;
        if (is_array($cart_item) && isset($cart_item['product_id'])) {
            $product_id = $cart_item['product_id'];
        } elseif (is_object($cart_item) && method_exists($cart_item, 'get_product_id')) {
            $product_id = $cart_item->get_product_id();
        }

        if (!$product_id) {
            return 0;
        }

        $edition_slot = 'current';
        if (WC()->cart) {
            $variation_id = isset($cart_item['variation_id']) ? $cart_item['variation_id'] : 0;
            foreach (WC()->cart->get_cart() as $key => $item) {
                if ($item['product_id'] === $product_id && $item['variation_id'] === $variation_id) {
                    $edition_slot = self::get_edition_slot_for_cart_item($key);
                    break;
                }
            }
        }

        $config = PMCM_Core::get_partner_config_for_product($product_id, $partner, $edition_slot);

        if (!$config['is_eligible'] || $config['discount'] <= 0) {
            return 0;
        }

        if ($coupon->get_discount_type() === 'percent') {
            return ($discounting_amount * $config['discount']) / 100;
        }

        return $discount;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public static function get_eligible_partners_for_cart() {
        if (!WC()->cart) {
            return [];
        }

        $eligible = [];
        foreach (['asit', 'bomss', 'rouleaux'] as $partner) {
            foreach (WC()->cart->get_cart() as $key => $cart_item) {
                $product_id   = $cart_item['product_id'];
                $edition_slot = self::get_edition_slot_for_cart_item($key);
                $config       = PMCM_Core::get_partner_config_for_product($product_id, $partner, $edition_slot);
                if ($config['show_field']) {
                    $eligible[] = $partner;
                    break;
                }
            }
        }

        return array_unique($eligible);
    }

    public static function cart_has_partner_eligible_products($partner) {
        if (!WC()->cart) {
            return false;
        }
        foreach (WC()->cart->get_cart() as $key => $cart_item) {
            $config = PMCM_Core::get_partner_config_for_product(
                $cart_item['product_id'],
                $partner,
                self::get_edition_slot_for_cart_item($key)
            );
            if ($config['is_eligible'] && $config['discount'] > 0) {
                return true;
            }
        }
        return false;
    }

    private static function get_edition_slot_for_cart_item($cart_item_key) {
        if (WC()->session) {
            $edition_data = WC()->session->get('wcem_edition_' . $cart_item_key);
            if ($edition_data && isset($edition_data['edition_slot'])) {
                return $edition_data['edition_slot'];
            }
        }
        return 'current';
    }

    private static function get_coupon_code_for_partner($partner) {
        switch ($partner) {
            case 'asit':    return strtolower(get_option('pmcm_asit_coupon_code', 'ASIT'));
            case 'bomss':   return strtolower(get_option('pmcm_bomss_coupon_code', 'BOMSS'));
            case 'rouleaux': return strtolower(get_option('pmcm_rouleaux_coupon_code', 'ROULEAUX'));
        }
        return '';
    }

    private static function remove_all_partner_coupons() {
        if (!WC()->cart) {
            return;
        }
        $codes = [
            strtolower(get_option('pmcm_asit_coupon_code', 'ASIT')),
            strtolower(get_option('pmcm_bomss_coupon_code', 'BOMSS')),
            strtolower(get_option('pmcm_rouleaux_coupon_code', 'ROULEAUX')),
        ];
        foreach ($codes as $code) {
            if (WC()->cart->has_discount($code)) {
                WC()->cart->remove_coupon($code);
            }
        }
    }

    // -------------------------------------------------------------------------
    // Styles & Scripts
    // -------------------------------------------------------------------------

    public static function checkout_styles() {
        if (!is_checkout()) {
            return;
        }
        $bomss_code   = esc_attr(strtolower(get_option('pmcm_bomss_coupon_code', 'BOMSS')));
        $rouleaux_code = esc_attr(strtolower(get_option('pmcm_rouleaux_coupon_code', 'ROULEAUX')));
        ?>
        <style>
            .pmcm-partners-section { margin-top: 25px; }
            .pmcm-partners-section select { border: 1px solid #d1d5db; background: #fff; padding: 10px; border-radius: 6px; width: 100%; }
            .pmcm-partners-section select:focus { border-color: #8d2063; box-shadow: 0 0 6px rgba(141,32,99,0.2); outline: none; }
            .pmcm-field-wrapper { display: flex; gap: 10px; align-items: flex-end; }
            .pmcm-field-wrapper .form-row-first { flex: 1; margin-bottom: 0 !important; }
            .pmcm-partners-section input[type="text"] { border: 1px solid #d1d5db; background: #fff; padding: 10px; border-radius: 6px; width: 100%; transition: all 0.3s ease; }
            .pmcm-partners-section input[type="text"]:focus { border-color: #8d2063; box-shadow: 0 0 6px rgba(141,32,99,0.2); outline: none; }
            .pmcm-apply-button { background: #8d2063 !important; color: #fff !important; border: none; padding: 14px 28px !important; border-radius: 6px !important; cursor: pointer; font-weight: 600; transition: all 0.3s ease; white-space: nowrap; }
            p.form-row.form-row-last.pmcm-button-wrapper { margin-bottom: 0px !important; }
            .pmcm-apply-button:hover { background: #7a1c57 !important; transform: translateY(-1px); }
            .pmcm-apply-button:disabled { background: #ccc !important; cursor: not-allowed; opacity: 0.8; }
            .cart-discount.coupon-<?php echo $bomss_code; ?> th,
            .cart-discount.coupon-<?php echo $rouleaux_code; ?> th { visibility: hidden !important; }
        </style>
        <?php
    }

    public static function checkout_scripts() {
        if (!is_checkout()) {
            return;
        }
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            var $partnerSelect  = $('#pmcm_selected_partner');
            var $numberWrap     = $('.pmcm-partner-number-wrap');
            var $numberInput    = $('#pmcm_partner_number');
            var $applyButton    = $('#apply_partner_membership');
            var isApplying      = false;

            if ($partnerSelect.length === 0) return;

            function validateNumber() {
                var val = $numberInput.val();
                if (/^[0-9]{5,10}$/.test(val)) {
                    $applyButton.prop('disabled', false);
                    return true;
                }
                $applyButton.prop('disabled', true);
                return false;
            }

            // Show/hide number field based on partner selection
            $partnerSelect.on('change', function() {
                if ($(this).val()) {
                    $numberWrap.show();
                    validateNumber();
                } else {
                    $numberWrap.hide();
                    $numberInput.val('');
                    $('body').trigger('update_checkout');
                }
            });

            validateNumber();
            $numberInput.on('input', validateNumber);

            $numberInput.on('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    e.stopPropagation();
                    if (validateNumber()) $applyButton.trigger('click');
                }
            });

            // Suppress empty coupon form submit
            $(document).on('click', 'form.checkout_coupon button[name="apply_coupon"]', function(e) {
                var $input = $(this).closest('form.checkout_coupon').find('input[name="coupon_code"]');
                if ($input.length && $.trim($input.val()) === '') {
                    e.preventDefault(); e.stopImmediatePropagation(); return false;
                }
            });

            $applyButton.on('click', function(e) {
                e.preventDefault(); e.stopPropagation(); e.stopImmediatePropagation();
                if (isApplying || !validateNumber()) return false;
                isApplying = true;
                $applyButton.text('Applying...').prop('disabled', true);
                $('body').trigger('update_checkout');
                setTimeout(function() {
                    $applyButton.text('Applied ✓');
                    setTimeout(function() {
                        $applyButton.text('Apply');
                        validateNumber();
                        isApplying = false;
                    }, 2000);
                }, 1000);
                return false;
            });

            $numberInput.on('input', function() {
                if ($(this).val().length === 0) $('body').trigger('update_checkout');
            });

            $(document.body).on('updated_checkout', function() { isApplying = false; });
        });
        </script>
        <?php
    }
}
