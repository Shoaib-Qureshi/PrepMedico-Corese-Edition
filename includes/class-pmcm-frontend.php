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
        add_filter('woocommerce_add_to_cart_form_action', [__CLASS__, 'preserve_edition_in_form_action']);
        add_action('wp_footer', [__CLASS__, 'product_form_scripts']);

        // Auto-open the menu cart whenever an item is added
        add_action('woocommerce_add_to_cart', [__CLASS__, 'flag_cart_just_added'], 10, 0);
        add_action('wp_footer', [__CLASS__, 'auto_open_cart_script'], 99);

        // Edition-aware early bird price display on product pages
        add_filter('woocommerce_product_get_sale_price', [__CLASS__, 'edition_aware_sale_price'], 20, 2);
        add_filter('woocommerce_product_get_price',      [__CLASS__, 'edition_aware_price'],      20, 2);
    }

    /**
     * Enqueue frontend scripts and styles
     */
    public static function enqueue_scripts() {
        wp_enqueue_style('pmcm-frontend', PMCM_PLUGIN_URL . 'assets/css/frontend.css', [], PMCM_VERSION);
    }

    /**
     * On a NON-AJAX add-to-cart (form submit that reloads the page), drop a short-lived
     * cookie so the page that loads after the reload knows to open the menu cart. A cookie
     * is used (instead of a session flag) so it survives full-page caches — the JS reads it
     * regardless of whether the HTML was served from cache. AJAX adds are handled by the
     * `added_to_cart` event instead, so we skip them here.
     */
    public static function flag_cart_just_added() {
        if (wp_doing_ajax() || headers_sent()) {
            return;
        }
        setcookie('pmcm_open_cart', '1', time() + 60, defined('COOKIEPATH') ? COOKIEPATH : '/', defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : '');
    }

    /**
     * Open the side cart automatically when an item is added.
     *
     * The theme replaces Elementor's native cart open behaviour with a custom side panel:
     * it moves `.elementor-menu-cart__container` to <body> and reveals it by adding the
     * `pm-cart-open` class (see the theme's "Cart Side Panel" script/CSS). So instead of
     * clicking the Elementor toggle (which fights both Elementor's and the theme's handlers),
     * we drive that same `pm-cart-open` mechanism directly — the deterministic, conflict-free
     * way to open this particular cart.
     *
     *  - Non-AJAX adds: the `pmcm_open_cart` cookie (set in flag_cart_just_added) triggers it
     *    after the page reload, then the cookie is cleared.
     *  - AJAX adds: the `added_to_cart` event triggers it without a reload.
     */
    public static function auto_open_cart_script() {
        if (is_admin()) {
            return;
        }
        /*
         * NOTE: written as vanilla JS with block comments only. The site's performance
         * optimizer minifies inline scripts (collapses newlines) and may delay jQuery, so
         * this must not depend on jQuery for the reload path and must not use // comments.
         */
        ?>
        <script type="text/javascript">
        (function () {
            function getContainer() { return document.querySelector('.elementor-menu-cart__container'); }
            function isCartOpen() {
                var c = getContainer();
                if (!c) { return false; }
                return c.classList.contains('pm-cart-open') || c.getAttribute('aria-hidden') === 'false' || c.classList.contains('elementor-active');
            }
            /* Mirror the theme's own openCart(): move panel to <body>, show it, lock scroll, then add pm-cart-open next frame for the slide-in. */
            function openSideCart() {
                var c = getContainer();
                if (!c) { return false; }
                if (c.parentElement !== document.body) { document.body.appendChild(c); }
                c.style.display = 'block';
                c.setAttribute('aria-hidden', 'false');
                document.body.style.overflow = 'hidden';
                (window.requestAnimationFrame || window.setTimeout)(function () { c.classList.add('pm-cart-open'); }, 16);
                return true;
            }
            function startReopen() {
                var ticks = 0, openStreak = 0;
                var iv = setInterval(function () {
                    ticks++;
                    if (isCartOpen()) { openStreak++; if (openStreak >= 2) { clearInterval(iv); } return; }
                    openStreak = 0;
                    openSideCart();
                    if (ticks >= 16) { clearInterval(iv); }
                }, 200);
            }
            /* AJAX add-to-cart: WooCommerce fires added_to_cart via jQuery — bind only if jQuery is present. */
            if (window.jQuery) {
                window.jQuery(document.body).on('added_to_cart', function () { if (!isCartOpen()) { openSideCart(); } });
            }
            /* Non-AJAX add-to-cart: a cookie was set server-side before the reload. */
            if (document.cookie.indexOf('pmcm_open_cart=1') !== -1) {
                document.cookie = 'pmcm_open_cart=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/';
                if (document.readyState === 'complete') { startReopen(); }
                else { window.addEventListener('load', startReopen); }
            }
        })();
        </script>
        <?php
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
                $url_edition = PMCM_Core::get_requested_edition_number();

                if ($url_edition > 0) {
                    // Edition specified in URL - determine which slot it belongs to
                    $current_edition = intval(get_option($prefix . 'current_edition', 1));
                    $next_enabled = get_option($prefix . 'next_enabled', 'no');
                    $next_edition = intval(get_option($prefix . 'next_edition', 0));

                    $selected_slot = 'current';
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
                    $url_edition = PMCM_Core::get_requested_edition_number();

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

        if (!empty($disabled_courses)) {
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

    /**
     * Determine which edition slot the customer is viewing on the product page.
     * Returns 'current', 'next', or null (not a managed course product).
     * Mirrors the logic in display_edition_selector() lines 99-117.
     */
    private static function get_viewed_edition_slot($product_id) {
        $categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'slugs']);
        $course_data = null;
        foreach ($categories as $cat_slug) {
            $course_data = PMCM_Core::get_course_for_category($cat_slug);
            if ($course_data) break;
        }
        if (!$course_data) return null;

        $prefix      = $course_data['course']['settings_prefix'];
        $url_edition = PMCM_Core::get_requested_edition_number();

        if ($url_edition > 0) {
            $next_enabled = get_option($prefix . 'next_enabled', 'no');
            $next_edition = intval(get_option($prefix . 'next_edition', 0));
            return ($next_enabled === 'yes' && $next_edition === $url_edition) ? 'next' : 'current';
        }
        return 'current';
    }

    /**
     * Preserve the selected edition in the add-to-cart form action URL.
     */
    public static function preserve_edition_in_form_action($action) {
        if (!is_product()) {
            return $action;
        }

        $edition = PMCM_Core::get_requested_edition_number();
        if ($edition <= 0) {
            return $action;
        }

        $base_action = !empty($action) ? $action : get_permalink();
        $base_action = remove_query_arg('edition', $base_action);

        return add_query_arg('edition', $edition, $base_action);
    }

    /**
     * Get the parent course slug for a product ID.
     */
    private static function get_product_course_slug($product_id) {
        $categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'slugs']);
        foreach ($categories as $cat_slug) {
            $course_data = PMCM_Core::get_course_for_category($cat_slug);
            if ($course_data) return $course_data['parent_slug'];
        }
        return null;
    }

    /**
     * Re-stamp the single product add-to-cart form with the selected edition.
     */
    public static function product_form_scripts() {
        if (!is_product()) {
            return;
        }

        global $product;
        if (!$product) {
            return;
        }

        $edition = PMCM_Core::get_requested_edition_number();
        if ($edition <= 0) {
            return;
        }

        $slot = self::get_viewed_edition_slot($product->get_id());
        $course_slug = self::get_product_course_slug($product->get_id());

        if (!$course_slug) {
            return;
        }
        ?>
        <script type="text/javascript">
        (function() {
            var edition = <?php echo (int) $edition; ?>;
            var slot = <?php echo wp_json_encode($slot ?: 'current'); ?>;
            var courseSlug = <?php echo wp_json_encode($course_slug); ?>;

            function upsertHidden(form, name, value) {
                var input = form.querySelector('input[name="' + name + '"]');
                if (!input) {
                    input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = name;
                    form.appendChild(input);
                }
                input.value = value;
            }

            function stampForm(form) {
                if (!form) return;

                var action = form.getAttribute('action') || window.location.href;

                try {
                    var url = new URL(action, window.location.origin);
                    url.searchParams.set('edition', edition);
                    form.setAttribute('action', url.toString());
                } catch (e) {
                    // Leave action unchanged if the URL cannot be parsed.
                }

                upsertHidden(form, 'pmcm_selected_edition', slot);
                upsertHidden(form, 'pmcm_selected_course', courseSlug);
                upsertHidden(form, 'pmcm_edition_number', edition);
            }

            function stampAllForms() {
                document.querySelectorAll('form.cart').forEach(stampForm);
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', stampAllForms);
            } else {
                stampAllForms();
            }

            document.addEventListener('submit', function(e) {
                if (e.target && e.target.matches('form.cart')) {
                    stampForm(e.target);
                }
            }, true);
        })();
        </script>
        <?php
    }

    /**
     * Suppress or return the sale price based on which edition is being viewed.
     * Only acts on single product pages; cart/checkout is handled by PMCM_Cart.
     */
    public static function edition_aware_sale_price($sale_price, $product) {
        if (!is_product() || empty($sale_price)) return $sale_price;

        $slot = self::get_viewed_edition_slot($product->get_id());
        if ($slot === null) return $sale_price;

        $course_slug = self::get_product_course_slug($product->get_id());
        if (!$course_slug) return $sale_price;

        // If Early Bird is not enabled for this course, the sale price is permanent — always show it.
        if (!self::is_early_bird_enabled_for_course($course_slug)) return $sale_price;

        $current_eb = PMCM_Core::is_course_early_bird_active($course_slug);
        $next_eb    = PMCM_Core::is_next_edition_early_bird_active($course_slug);

        if ($slot === 'current' && !$current_eb) return '';
        if ($slot === 'next'    && !$next_eb)    return '';
        return $sale_price;
    }

    /**
     * When sale price is suppressed for the viewed edition, fix the active price
     * so the displayed price is the regular price, not the sale price.
     */
    public static function edition_aware_price($price, $product) {
        if (!is_product()) return $price;

        $sale_price    = $product->get_sale_price('edit'); // raw, unfiltered
        $regular_price = $product->get_regular_price();
        if (empty($sale_price)) return $price;

        $slot = self::get_viewed_edition_slot($product->get_id());
        if ($slot === null) return $price;

        $course_slug = self::get_product_course_slug($product->get_id());
        if (!$course_slug) return $price;

        // If Early Bird is not enabled for this course, the sale price is permanent — always show it.
        if (!self::is_early_bird_enabled_for_course($course_slug)) return $price;

        $current_eb = PMCM_Core::is_course_early_bird_active($course_slug);
        $next_eb    = PMCM_Core::is_next_edition_early_bird_active($course_slug);

        if ($slot === 'current' && !$current_eb) return $regular_price;
        if ($slot === 'next'    && !$next_eb)    return $regular_price;
        return $price;
    }

    /**
     * Returns true if Early Bird is configured/enabled for the given course slug.
     * Used to distinguish permanent sale prices from EB-only sale prices.
     */
    private static function is_early_bird_enabled_for_course($course_slug) {
        $courses = PMCM_Core::get_courses();
        if (!isset($courses[$course_slug])) return false;
        $prefix = $courses[$course_slug]['settings_prefix'];
        return get_option($prefix . 'early_bird_enabled', 'no') === 'yes';
    }
}
