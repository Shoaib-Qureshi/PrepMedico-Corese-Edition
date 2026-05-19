<?php
/**
 * PMCM Academic Partners Class
 * Unified checkout UI and fee-based discount for ASiT, BOMSS and Rouleaux Club
 *
 * @package PrepMedico_Course_Management
 */

if (!defined('ABSPATH')) {
    exit;
}

class PMCM_Academic_Partners {

    private static $partner_labels = [
        'asit'     => 'ASiT',
        'bomss'    => 'BOMSS',
        'rouleaux' => 'Rouleaux Club',
    ];

    // -------------------------------------------------------------------------
    // Bootstrap
    // -------------------------------------------------------------------------

    public static function init() {
        add_action('woocommerce_after_checkout_billing_form', [__CLASS__, 'add_partner_selector']);
        add_action('woocommerce_checkout_process',             [__CLASS__, 'validate_partner_selection']);
        add_action('woocommerce_checkout_update_order_review', [__CLASS__, 'apply_partner_session']);
        add_action('woocommerce_cart_calculate_fees',          [__CLASS__, 'apply_partner_fee_discount']);
        add_action('woocommerce_checkout_create_order',        [__CLASS__, 'save_partner_to_order'], 10, 2);
        add_action('woocommerce_checkout_order_processed',     [__CLASS__, 'set_membership_pending_status'], 10, 3);

        // Dedicated AJAX: set session immediately before cart recalculation
        add_action('wp_ajax_nopriv_pmcm_apply_partner', [__CLASS__, 'ajax_apply_partner']);
        add_action('wp_ajax_pmcm_apply_partner',        [__CLASS__, 'ajax_apply_partner']);

        // Email registration
        add_filter('woocommerce_email_classes', [__CLASS__, 'register_membership_emails']);

        // Suppress default WC emails for membership-pending orders
        add_filter('woocommerce_email_enabled_new_order',                 [__CLASS__, 'suppress_default_emails_for_pending'], 10, 2);
        add_filter('woocommerce_email_enabled_customer_processing_order', [__CLASS__, 'suppress_default_emails_for_pending'], 10, 2);
        add_filter('woocommerce_email_enabled_customer_on_hold_order',    [__CLASS__, 'suppress_default_emails_for_pending'], 10, 2);

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
            'label_count'               => _n_noop(
                'Membership Pending <span class="count">(%s)</span>',
                'Membership Pending <span class="count">(%s)</span>',
                'prepmedico-course-management'
            ),
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

        $hide = empty($session_partner) ? 'style="display:none;"' : '';
        echo '<div class="pmcm-partner-number-wrap" ' . $hide . '>';
        echo '<div class="pmcm-field-wrapper">';

        woocommerce_form_field('pmcm_partner_number', [
            'type'              => 'text',
            'class'             => ['form-row-first', 'pmcm-partner-input-field'],
            'label'             => __('Membership Number', 'prepmedico-course-management'),
            'placeholder'       => __('Enter your membership number', 'prepmedico-course-management'),
            'required'          => false,
            'maxlength'         => 10,
            'custom_attributes' => [
                'pattern' => '[0-9]{5,10}',
                'title'   => 'Please enter your membership number (5–10 digits)',
            ],
        ], $checkout->get_value('pmcm_partner_number') ?: $session_number);

        echo '<p class="form-row form-row-last pmcm-button-wrapper">
                <button type="button" id="apply_partner_membership" class="pmcm-apply-button">' . esc_html__('Apply', 'prepmedico-course-management') . '</button>
              </p>';
        echo '</div>';

        echo '<p class="form-row form-row-wide pmcm-help-text">
                <small style="color:#666;">' . esc_html__('Note: Discount applies during the Early Bird period. If the membership number does not match our records, the order will be cancelled without refund.', 'prepmedico-course-management') . '</small>
              </p>';
        echo '</div>';
        echo '</div>';
    }

    // -------------------------------------------------------------------------
    // Validation
    // -------------------------------------------------------------------------

    public static function validate_partner_selection() {
        $partner = isset($_POST['pmcm_selected_partner']) ? sanitize_text_field($_POST['pmcm_selected_partner']) : '';
        $number  = isset($_POST['pmcm_partner_number'])   ? sanitize_text_field($_POST['pmcm_partner_number'])   : '';

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
    // Session setter — called on checkout AJAX review update
    // No coupon is applied; the fee hook reads the session instead.
    // -------------------------------------------------------------------------

    public static function apply_partner_session($post_data) {
        parse_str($post_data, $data);

        $partner = isset($data['pmcm_selected_partner']) ? sanitize_text_field($data['pmcm_selected_partner']) : '';
        $number  = isset($data['pmcm_partner_number'])   ? sanitize_text_field($data['pmcm_partner_number'])   : '';

        if (empty($partner) || empty($number) || !preg_match('/^[0-9]{5,10}$/', $number)) {
            if (WC()->session) {
                WC()->session->set('pmcm_selected_partner', '');
                WC()->session->set('pmcm_partner_number', '');
            }
            return;
        }

        WC()->session->set('pmcm_selected_partner', $partner);
        WC()->session->set('pmcm_partner_number', $number);

        // Backwards compat for any code still reading the old ASiT session key
        if ($partner === 'asit') {
            WC()->session->set('asit_membership_number', $number);
        }
    }

    // -------------------------------------------------------------------------
    // Dedicated AJAX endpoint — sets session and confirms eligibility immediately
    // Called by the Apply button BEFORE update_checkout fires, so the fee hook
    // reads a fully-committed session on the very next calculate_totals() call.
    // -------------------------------------------------------------------------

    public static function ajax_apply_partner() {
        check_ajax_referer('pmcm_apply_partner_nonce', 'nonce');

        if (!WC()->session) {
            wp_send_json_error(['message' => 'Session unavailable']);
        }

        $partner = isset($_POST['partner']) ? sanitize_text_field($_POST['partner']) : '';
        $number  = isset($_POST['number'])  ? sanitize_text_field(trim($_POST['number'])) : '';

        // Clear session if inputs are empty or invalid
        if (empty($partner) || empty($number) || !in_array($partner, ['asit', 'bomss', 'rouleaux'], true)) {
            WC()->session->set('pmcm_selected_partner', '');
            WC()->session->set('pmcm_partner_number', '');
            wp_send_json_success(['applied' => false, 'message' => '']);
        }

        // Validate number format (5-10 digits)
        if (!preg_match('/^[0-9]{5,10}$/', $number)) {
            wp_send_json_error(['message' => __('Membership number must be 5–10 digits.', 'prepmedico-course-management')]);
        }

        // Persist in WC session before calculate_totals fires
        WC()->session->set('pmcm_selected_partner', $partner);
        WC()->session->set('pmcm_partner_number', $number);
        if ($partner === 'asit') {
            WC()->session->set('asit_membership_number', $number);
        }

        // Check eligibility to provide immediate feedback
        $eligible = false;
        if (WC()->cart) {
            foreach (WC()->cart->get_cart() as $key => $cart_item) {
                $config = PMCM_Core::get_partner_config_for_product(
                    $cart_item['product_id'],
                    $partner,
                    'current'
                );
                if ($config['is_eligible'] && $config['discount'] > 0) {
                    $eligible = true;
                    break;
                }
            }
        }

        $label = isset(self::$partner_labels[$partner]) ? self::$partner_labels[$partner] : strtoupper($partner);

        if ($eligible) {
            wp_send_json_success([
                'applied'  => true,
                'message'  => sprintf(__('%s discount will be applied.', 'prepmedico-course-management'), $label),
            ]);
        } else {
            wp_send_json_success([
                'applied'  => false,
                'message'  => sprintf(__('%s discount is not available for the current products/period.', 'prepmedico-course-management'), $label),
            ]);
        }
    }

    // -------------------------------------------------------------------------
    // Cart fee discount — secure, session-gated
    // -------------------------------------------------------------------------

    public static function apply_partner_fee_discount() {
        if (!WC()->session || !WC()->cart) {
            return;
        }

        $partner = WC()->session->get('pmcm_selected_partner', '');
        $number  = WC()->session->get('pmcm_partner_number', '');

        if (empty($partner) || !preg_match('/^[0-9]{5,10}$/', $number)) {
            return;
        }

        $total_discount = 0;

        foreach (WC()->cart->get_cart() as $key => $cart_item) {
            $config = PMCM_Core::get_partner_config_for_product(
                $cart_item['product_id'],
                $partner,
                self::get_edition_slot_for_cart_item($key)
            );

            if ($config['is_eligible'] && $config['discount'] > 0) {
                $price           = (float) $cart_item['data']->get_price();
                $total_discount += ($price * $config['discount'] / 100) * $cart_item['quantity'];
            }
        }

        if ($total_discount > 0) {
            $label = isset(self::$partner_labels[$partner]) ? self::$partner_labels[$partner] : strtoupper($partner);
            WC()->cart->add_fee($label . ' Member Discount', -$total_discount, false);
        }
    }

    // -------------------------------------------------------------------------
    // Save to order
    // -------------------------------------------------------------------------

    public static function save_partner_to_order($order, $data) {
        $partner = isset($_POST['pmcm_selected_partner']) ? sanitize_text_field($_POST['pmcm_selected_partner']) : '';
        $number  = isset($_POST['pmcm_partner_number'])   ? sanitize_text_field($_POST['pmcm_partner_number'])   : '';

        // Fall back to session for both values if partner missing from POST
        if (empty($partner) && WC()->session) {
            $partner = WC()->session->get('pmcm_selected_partner', '');
            $number  = WC()->session->get('pmcm_partner_number', '');
        }

        // Also fall back to session for number alone if partner set but number missing/invalid
        if (!empty($partner) && (empty($number) || !preg_match('/^[0-9]{5,10}$/', $number)) && WC()->session) {
            $number = WC()->session->get('pmcm_partner_number', '');
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

        $partner_label = isset(self::$partner_labels[$partner]) ? self::$partner_labels[$partner] : strtoupper($partner);

        // Force WC email system to initialize so our email class hooks are registered
        // before the status change action fires.
        $mailer = WC()->mailer();

        $order->update_status('wc-membership-pending', sprintf(
            __('Order placed with %s membership discount. Membership #%s pending admin verification.', 'prepmedico-course-management'),
            $partner_label,
            $partner_num
        ));

        // Belt-and-suspenders: directly trigger each email if the status action hooks
        // didn't fire (e.g. WC_Emails initialized before our classes were loaded).
        // Each trigger() method sets its own sent flag, so no double-sending occurs.
        if (!$order->get_meta('_pmcm_membership_emails_sent') && isset($mailer->emails['PMCM_Email_Membership_Pending_Admin'])) {
            $mailer->emails['PMCM_Email_Membership_Pending_Admin']->trigger($order->get_id(), $order);
        }
        if (!$order->get_meta('_pmcm_customer_email_sent') && isset($mailer->emails['PMCM_Email_Membership_Pending_Customer'])) {
            $mailer->emails['PMCM_Email_Membership_Pending_Customer']->trigger($order->get_id(), $order);
        }
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
    // Helpers
    // -------------------------------------------------------------------------

    public static function get_eligible_partners_for_cart() {
        if (!WC()->cart) {
            return [];
        }

        $eligible = [];
        foreach (['asit', 'bomss', 'rouleaux'] as $partner) {
            foreach (WC()->cart->get_cart() as $key => $cart_item) {
                $config = PMCM_Core::get_partner_config_for_product(
                    $cart_item['product_id'],
                    $partner,
                    self::get_edition_slot_for_cart_item($key)
                );
                if ($config['show_field']) {
                    $eligible[] = $partner;
                    break;
                }
            }
        }

        return array_unique($eligible);
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

    // -------------------------------------------------------------------------
    // Styles & Scripts
    // -------------------------------------------------------------------------

    public static function checkout_styles() {
        if (!is_checkout()) {
            return;
        }
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
            .pmcm-discount-notice { margin-top: 8px; padding: 8px 12px; border-radius: 4px; font-size: 13px; font-weight: 500; }
            .pmcm-discount-notice.applied  { background: #e8f5e9; color: #1a6b3c; border: 1px solid #a5d6a7; }
            .pmcm-discount-notice.unavailable { background: #fff8e1; color: #7a5c00; border: 1px solid #ffe082; }
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
            var $noticeEl       = null;
            var isApplying      = false;
            var ajaxUrl         = '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
            var applyNonce      = '<?php echo esc_js(wp_create_nonce('pmcm_apply_partner_nonce')); ?>';

            if ($partnerSelect.length === 0) return;

            // Inject notice element after help text
            $numberWrap.append('<div class="pmcm-discount-notice" style="display:none;"></div>');
            $noticeEl = $numberWrap.find('.pmcm-discount-notice');

            function validateNumber() {
                var val = $numberInput.val().trim();
                if (/^[0-9]{5,10}$/.test(val)) {
                    $applyButton.prop('disabled', false);
                    return true;
                }
                $applyButton.prop('disabled', true);
                return false;
            }

            function showNotice(message, type) {
                $noticeEl.removeClass('applied unavailable').addClass(type).text(message).show();
            }

            function hideNotice() {
                $noticeEl.hide().text('');
            }

            $partnerSelect.on('change', function() {
                hideNotice();
                if ($(this).val()) {
                    $numberWrap.show();
                    validateNumber();
                } else {
                    $numberWrap.hide();
                    $numberInput.val('');
                    // Clear session and refresh cart
                    $.post(ajaxUrl, {
                        action: 'pmcm_apply_partner',
                        nonce: applyNonce,
                        partner: '',
                        number: ''
                    }, function() {
                        $('body').trigger('update_checkout');
                    });
                }
            });

            validateNumber();
            $numberInput.on('input', function() {
                hideNotice();
                validateNumber();
                if ($(this).val().trim().length === 0) {
                    $('body').trigger('update_checkout');
                }
            });

            $numberInput.on('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    e.stopPropagation();
                    if (validateNumber()) $applyButton.trigger('click');
                }
            });

            $applyButton.on('click', function(e) {
                e.preventDefault(); e.stopPropagation(); e.stopImmediatePropagation();
                if (isApplying || !validateNumber()) return false;

                isApplying = true;
                $applyButton.text('Applying...').prop('disabled', true);
                hideNotice();

                // Step 1: set session via dedicated AJAX (guarantees session is committed
                //         before WooCommerce's calculate_totals() runs in step 2)
                $.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    data: {
                        action:  'pmcm_apply_partner',
                        nonce:   applyNonce,
                        partner: $partnerSelect.val(),
                        number:  $numberInput.val().trim()
                    },
                    success: function(response) {
                        if (response.success) {
                            var msg  = response.data.message || '';
                            var type = response.data.applied ? 'applied' : 'unavailable';
                            if (msg) showNotice(msg, type);

                            // Step 2: trigger WC cart recalculation — session is already set
                            $('body').trigger('update_checkout');
                        } else {
                            var errMsg = (response.data && response.data.message) ? response.data.message : 'Could not apply discount.';
                            showNotice(errMsg, 'unavailable');
                        }
                        $applyButton.text('Applied ✓');
                        setTimeout(function() {
                            $applyButton.text('Apply');
                            validateNumber();
                            isApplying = false;
                        }, 2000);
                    },
                    error: function() {
                        showNotice('Could not apply discount. Please try again.', 'unavailable');
                        $applyButton.text('Apply');
                        validateNumber();
                        isApplying = false;
                    }
                });

                return false;
            });

            $(document.body).on('updated_checkout', function() { isApplying = false; });
        });
        </script>
        <?php
    }
}
