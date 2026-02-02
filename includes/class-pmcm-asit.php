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
     * @deprecated Use PMCM_Core::is_course_early_bird_active() for per-course check
     */
    public static function is_early_bird_active() {
        foreach (PMCM_Core::get_asit_eligible_courses() as $slug => $course) {
            if (PMCM_Core::is_course_early_bird_active($slug)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get current ASiT discount percentage for display (max discount in cart)
     * This is used for displaying the discount percentage to users
     */
    public static function get_current_discount() {
        if (!WC()->cart) {
            // Fallback to checking all eligible courses
            $max_discount = 0;
            foreach (PMCM_Core::get_asit_eligible_courses() as $slug => $course) {
                $config = PMCM_Core::get_asit_discount_for_course($slug);
                if ($config['discount'] > $max_discount) {
                    $max_discount = $config['discount'];
                }
            }
            return $max_discount > 0 ? $max_discount : 5; // Default 5%
        }

        // Get the max discount from cart products
        $max_discount = 0;
        foreach (WC()->cart->get_cart() as $cart_item) {
            $product_id = $cart_item['product_id'];
            $config = PMCM_Core::get_asit_config_for_product($product_id);
            if ($config['discount'] > $max_discount) {
                $max_discount = $config['discount'];
            }
        }

        return $max_discount > 0 ? $max_discount : 5; // Default 5%
    }

    /**
     * Check if cart has products that should show the ASiT field
     * This is different from eligibility - some products show field but may not have discount
     */
    public static function cart_has_eligible_products() {
        if (!WC()->cart) {
            return false;
        }

        foreach (WC()->cart->get_cart() as $cart_item) {
            $product_id = $cart_item['product_id'];
            $config = PMCM_Core::get_asit_config_for_product($product_id);

            // Show field if configured to show
            if ($config['show_field']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if cart has any products with actual ASiT discount available
     */
    public static function cart_has_discount_eligible_products() {
        if (!WC()->cart) {
            return false;
        }

        foreach (WC()->cart->get_cart() as $cart_item) {
            $product_id = $cart_item['product_id'];
            $config = PMCM_Core::get_asit_config_for_product($product_id);

            // Has discount if eligible and discount > 0
            if ($config['is_eligible'] && $config['discount'] > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get ASiT discount info message for checkout
     */
    public static function get_discount_info_message() {
        if (!WC()->cart) {
            return '';
        }

        $messages = [];
        $checked_courses = [];

        foreach (WC()->cart->get_cart() as $cart_item) {
            $product_id = $cart_item['product_id'];
            $config = PMCM_Core::get_asit_config_for_product($product_id);

            if (!$config['show_field']) {
                continue;
            }

            // Get course name for this product
            $categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'slugs']);
            $child_map = PMCM_Core::get_child_to_parent_map();
            $courses = PMCM_Core::get_courses();
            $course_name = '';

            foreach ($categories as $cat_slug) {
                if (isset($courses[$cat_slug])) {
                    $course_name = $courses[$cat_slug]['name'];
                    $course_slug = $cat_slug;
                    break;
                }
                if (isset($child_map[$cat_slug]) && isset($courses[$child_map[$cat_slug]])) {
                    $course_name = $courses[$child_map[$cat_slug]]['name'];
                    $course_slug = $child_map[$cat_slug];
                    break;
                }
            }

            if (empty($course_name) || in_array($course_slug, $checked_courses)) {
                continue;
            }
            $checked_courses[] = $course_slug;

            if ($config['mode'] === 'always' && $config['discount'] > 0) {
                $messages[] = sprintf('%s: %d%% discount', $course_name, $config['discount']);
            } elseif ($config['mode'] === 'early_bird_only') {
                if ($config['is_eligible'] && $config['discount'] > 0) {
                    $messages[] = sprintf('%s: %d%% Early Bird discount', $course_name, $config['discount']);
                } else {
                    $messages[] = sprintf('%s: No discount (Early Bird period ended)', $course_name);
                }
            }
        }

        return implode(' | ', $messages);
    }

    /**
     * Add ASiT Membership field to checkout
     */
    public static function add_membership_field($checkout) {
        if (!self::cart_has_eligible_products()) {
            return;
        }

        // Get detailed discount info for cart products
        $discount_info = self::get_discount_info_message();
        $has_any_discount = self::cart_has_discount_eligible_products();

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

        // Show discount info based on cart products
        if (!empty($discount_info)) {
            echo '<p class="form-row form-row-wide asit-discount-info" style="margin-top: 5px; margin-bottom: 10px; clear: both; padding: 10px; background: #f8f9fa; border-radius: 6px; border-left: 3px solid #8d2063;">';
            echo '<small style="color: #333; font-weight: 500;">' . esc_html($discount_info) . '</small>';
            echo '</p>';
        }

        if ($has_any_discount) {
            echo '<p class="form-row form-row-wide asit-help-text" style="margin-top: 5px; margin-bottom: 15px; clear: both;">
                    <small style="color: #666;">' . __('Note: Enter your ASiT membership number to receive the discount. If the number does not match our records, the order will be cancelled without any refund.', 'prepmedico-course-management') . '</small>
                  </p>';
        } else {
            echo '<p class="form-row form-row-wide asit-help-text" style="margin-top: 5px; margin-bottom: 15px; clear: both;">
                    <small style="color: #999;">' . __('Note: ASiT discount is not available for the products in your cart at this time. You can still enter your membership number for our records.', 'prepmedico-course-management') . '</small>
                  </p>';
        }
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
     * Dynamically adjust ASiT coupon discount based on per-product settings
     * Each product gets its own discount percentage based on course configuration
     */
    public static function dynamic_coupon_discount($discount, $discounting_amount, $cart_item, $single, $coupon) {
        $asit_coupon_code = strtolower(get_option('pmcm_asit_coupon_code', 'ASIT'));

        if (strtolower($coupon->get_code()) !== $asit_coupon_code) {
            return $discount;
        }

        // Get the product ID from cart item
        $product_id = 0;
        if (is_array($cart_item) && isset($cart_item['product_id'])) {
            $product_id = $cart_item['product_id'];
        } elseif (is_object($cart_item) && method_exists($cart_item, 'get_product_id')) {
            $product_id = $cart_item->get_product_id();
        }

        if (!$product_id) {
            return $discount;
        }

        // Get per-product ASiT discount configuration
        $config = PMCM_Core::get_asit_config_for_product($product_id);

        // If product is not eligible for ASiT discount, return 0
        if (!$config['is_eligible'] || $config['discount'] <= 0) {
            return 0;
        }

        $discount_percent = $config['discount'];

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
