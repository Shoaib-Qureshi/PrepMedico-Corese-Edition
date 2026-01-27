<?php
/**
 * PMCM ASiT Class
 * Handles ASiT coupon management
 *
 * @package PrepMedico_Course_Management
 */

if (!defined('ABSPATH')) {
    exit;
}

class PMCM_ASiT {

    /**
     * Initialize ASiT hooks
     */
    public static function init() {
        add_action('woocommerce_after_checkout_billing_form', [__CLASS__, 'add_membership_field']);
        add_action('woocommerce_checkout_process', [__CLASS__, 'validate_membership_field']);
        add_action('woocommerce_checkout_update_order_review', [__CLASS__, 'apply_coupon_on_checkout']);
        add_action('woocommerce_checkout_create_order', [__CLASS__, 'save_membership_to_order'], 10, 2);
        add_filter('woocommerce_coupon_get_discount_amount', [__CLASS__, 'dynamic_coupon_discount'], 10, 5);
        add_action('wp_footer', [__CLASS__, 'checkout_scripts']);
        add_action('wp_head', [__CLASS__, 'checkout_styles']);
    }

    /**
     * Check if any ASiT eligible course has Early Bird active
     */
    public static function is_early_bird_active() {
        foreach (PMCM_Core::get_asit_eligible_courses() as $slug => $course) {
            $prefix = $course['settings_prefix'];
            $eb_enabled = get_option($prefix . 'early_bird_enabled', 'no');
            $eb_start = get_option($prefix . 'early_bird_start', '');
            $eb_end = get_option($prefix . 'early_bird_end', '');
            $today = current_time('Y-m-d');

            if ($eb_enabled === 'yes' && !empty($eb_end)) {
                $start_ok = empty($eb_start) || strtotime($today) >= strtotime($eb_start);
                $end_ok = strtotime($today) <= strtotime($eb_end);
                if ($start_ok && $end_ok) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Get current ASiT discount percentage
     */
    public static function get_current_discount() {
        if (self::is_early_bird_active()) {
            return get_option('pmcm_asit_discount_early_bird', 5);
        }
        return get_option('pmcm_asit_discount_normal', 10);
    }

    /**
     * Check if cart has ASiT eligible products
     */
    public static function cart_has_eligible_products() {
        if (!WC()->cart) {
            return false;
        }

        $asit_categories = array_keys(PMCM_Core::get_asit_eligible_courses());
        $child_map = PMCM_Core::get_child_to_parent_map();

        foreach (WC()->cart->get_cart() as $cart_item) {
            $product_id = $cart_item['product_id'];
            $categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'slugs']);

            foreach ($categories as $cat_slug) {
                if (in_array($cat_slug, $asit_categories)) {
                    return true;
                }
                if (isset($child_map[$cat_slug]) && in_array($child_map[$cat_slug], $asit_categories)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Add ASiT Membership field to checkout
     */
    public static function add_membership_field($checkout) {
        if (!self::cart_has_eligible_products()) {
            return;
        }

        echo '<div class="asit-membership-section" style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #e0e0e0;">';
        echo '<div class="asit-field-wrapper">';

        woocommerce_form_field('asit_membership_number', array(
            'type' => 'text',
            'class' => array('form-row-first', 'asit-input-field'),
            'label' => __('ASiT Membership Number', 'prepmedico-course-management'),
            'placeholder' => __('Enter your ASiT membership number', 'prepmedico-course-management'),
            'required' => false,
            'maxlength' => 8,
            'custom_attributes' => array(
                'pattern' => '[0-9]{6,8}',
                'title' => 'Please enter membership number'
            ),
        ), $checkout->get_value('asit_membership_number'));

        echo '<p class="form-row form-row-last asit-button-wrapper">
                <button type="button" id="apply_asit_membership" class="button asit-apply-button">Apply</button>
              </p>';
        echo '</div>';

        $current_discount = self::get_current_discount();
        echo '<p class="form-row form-row-wide asit-help-text" style="margin-top: 5px; margin-bottom: 15px; clear: both;">
                <small style="color: #666;">' . sprintf(__('Note: Enter your ASiT membership number to receive %d%% discount for ASiT members. If the number does not match our records, the order will be cancelled without any refund.', 'prepmedico-course-management'), $current_discount) . '</small>
              </p>';
        echo '</div>';
    }

    /**
     * Validate ASiT membership number format
     */
    public static function validate_membership_field() {
        if (!empty($_POST['asit_membership_number'])) {
            $asit_number = sanitize_text_field($_POST['asit_membership_number']);
            if (!preg_match('/^[0-9]{6,8}$/', $asit_number)) {
                wc_add_notice(__('ASiT Membership Number must be 6-8 digits.', 'prepmedico-course-management'), 'error');
            }
        }
    }

    /**
     * Apply ASiT coupon when membership number is entered
     */
    public static function apply_coupon_on_checkout($post_data) {
        parse_str($post_data, $data);

        $asit_number = isset($data['asit_membership_number']) ? sanitize_text_field($data['asit_membership_number']) : '';
        $coupon_code = strtolower(get_option('pmcm_asit_coupon_code', 'ASIT'));

        if (!empty($asit_number) && preg_match('/^[0-9]{6,8}$/', $asit_number)) {
            if (!WC()->cart->has_discount($coupon_code)) {
                WC()->cart->apply_coupon($coupon_code);
            }
            WC()->session->set('asit_membership_number', $asit_number);
        } else {
            if (WC()->cart->has_discount($coupon_code)) {
                WC()->cart->remove_coupon($coupon_code);
            }
            WC()->session->set('asit_membership_number', '');
        }
    }

    /**
     * Save ASiT membership to order
     */
    public static function save_membership_to_order($order, $data) {
        if (!empty($_POST['asit_membership_number'])) {
            $asit_number = sanitize_text_field($_POST['asit_membership_number']);
            if (preg_match('/^[0-9]{6,8}$/', $asit_number)) {
                $order->update_meta_data('_asit_membership_number', $asit_number);
            }
        }
    }

    /**
     * Dynamically adjust ASiT coupon discount based on Early Bird status
     */
    public static function dynamic_coupon_discount($discount, $discounting_amount, $cart_item, $single, $coupon) {
        $asit_coupon_code = strtolower(get_option('pmcm_asit_coupon_code', 'ASIT'));

        if (strtolower($coupon->get_code()) !== $asit_coupon_code) {
            return $discount;
        }

        $discount_percent = self::get_current_discount();

        if ($coupon->get_discount_type() === 'percent') {
            return ($discounting_amount * $discount_percent) / 100;
        }

        return $discount;
    }

    /**
     * ASiT checkout styles
     */
    public static function checkout_styles() {
        if (!is_checkout()) {
            return;
        }
        ?>
        <style>
            .asit-membership-section { margin-top: 25px; }
            .asit-membership-section label { font-weight: 600; color: #333; font-size: 15px; }
            .asit-field-wrapper { display: flex; gap: 10px; align-items: flex-end; }
            .asit-field-wrapper .form-row-first { flex: 1; margin-bottom: 0 !important; }
            .asit-membership-section input[type="text"] { border: 1px solid #d1d5db; background: #fff; padding: 10px; border-radius: 6px; width: 100%; transition: all 0.3s ease; }
            .asit-membership-section input[type="text"]:focus { border-color: #8d2063; box-shadow: 0 0 6px rgba(141, 32, 99, 0.2); outline: none; }
            .asit-apply-button { background: #8d2063 !important; color: #fff !important; border: none; padding: 14px 28px !important; border-radius: 6px !important; cursor: pointer; font-weight: 600; transition: all 0.3s ease; }
            p.form-row.form-row-last.asit-button-wrapper { margin-bottom: 0px !important; }
            .asit-apply-button:hover { background: #7a1c57 !important; transform: translateY(-1px); }
            .asit-apply-button:disabled { background: #ccc !important; cursor: not-allowed; opacity: 0.8; }
            .asit-help-text small { color: #666; font-size: 13px; line-height: 1.5; }
            .cart-discount.coupon-<?php echo esc_attr(strtolower(get_option('pmcm_asit_coupon_code', 'ASIT'))); ?> th { visibility: hidden !important; }
        </style>
        <?php
    }

    /**
     * ASiT checkout scripts
     */
    public static function checkout_scripts() {
        if (!is_checkout()) {
            return;
        }
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            var $asitInput = $('#asit_membership_number');
            var $applyButton = $('#apply_asit_membership');

            if ($asitInput.length === 0) return;

            function validateAsitInput() {
                var asitNumber = $asitInput.val();
                if (asitNumber.length >= 6 && asitNumber.length <= 8 && /^[0-9]+$/.test(asitNumber)) {
                    $applyButton.prop('disabled', false);
                    return true;
                } else {
                    $applyButton.prop('disabled', true);
                    return false;
                }
            }

            validateAsitInput();

            $asitInput.on('input', function() {
                validateAsitInput();
            });

            $applyButton.on('click', function(e) {
                e.preventDefault();
                if (validateAsitInput()) {
                    $applyButton.text('Applying...').prop('disabled', true);
                    $('body').trigger('update_checkout');
                    setTimeout(function() {
                        $applyButton.text('Applied ✓');
                        setTimeout(function() {
                            $applyButton.text('Apply');
                            validateAsitInput();
                        }, 2000);
                    }, 1000);
                }
            });

            $asitInput.on('input', function() {
                if ($(this).val().length === 0) {
                    $('body').trigger('update_checkout');
                }
            });
        });
        </script>
        <?php
    }
}
