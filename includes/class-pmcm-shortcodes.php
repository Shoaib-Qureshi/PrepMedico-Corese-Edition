<?php
/**
 * PMCM Shortcodes Class
 * Handles all shortcode registrations and rendering
 *
 * @package PrepMedico_Course_Management
 */

if (!defined('ABSPATH')) {
    exit;
}

class PMCM_Shortcodes {

    /**
     * Initialize shortcodes
     */
    public static function init() {
        add_shortcode('current_edition', [__CLASS__, 'current_edition']);
        add_shortcode('edition_info', [__CLASS__, 'edition_info']);
        add_shortcode('edition_number', [__CLASS__, 'edition_number']);
        add_shortcode('registration_status', [__CLASS__, 'registration_status']);
        add_shortcode('early_bird_message', [__CLASS__, 'early_bird_message']);
        add_shortcode('course_registration_info', [__CLASS__, 'course_registration_info']);
        add_shortcode('edition_dates', [__CLASS__, 'edition_dates_frontend']);

        // Elementor table shortcodes for custom edition tables
        add_shortcode('pmcm_edition_url', [__CLASS__, 'edition_url']);
        add_shortcode('pmcm_edition_ordinal', [__CLASS__, 'edition_ordinal']);
        add_shortcode('pmcm_edition_dates', [__CLASS__, 'edition_dates']);
        add_shortcode('pmcm_edition_status', [__CLASS__, 'edition_status']);
        add_shortcode('pmcm_edition_button', [__CLASS__, 'edition_button']);
        add_shortcode('pmcm_edition_number_raw', [__CLASS__, 'edition_number_raw']);
        add_shortcode('pmcm_edition_product_script', [__CLASS__, 'edition_product_script']);

        // New simplified approach - no data attributes needed
        add_shortcode('pmcm_edition_marker', [__CLASS__, 'edition_marker']);
        add_shortcode('pmcm_edition_products_script', [__CLASS__, 'edition_products_script']);

        // Exam dates shortcode
        add_shortcode('pmcm_exam_dates', [__CLASS__, 'exam_dates']);

        // Product-level meta shortcodes (Date, Time & CPD Points) — output nothing when empty
        add_shortcode('course_date', [__CLASS__, 'course_date']);
        add_shortcode('course_time', [__CLASS__, 'course_time']);
        add_shortcode('cpd_points', [__CLASS__, 'cpd_points']);
    }

    /**
     * Shortcode: Registration close date for the current (or specified) product.
     * Usage: [course_date] or [course_date product="123" format="d M Y" before="Closes: "]
     * Outputs nothing if the value is empty (effectively display:none).
     */
    public static function course_date($atts) {
        $atts = shortcode_atts(['product' => '', 'before' => '', 'after' => '', 'format' => ''], $atts, 'course_date');
        return self::render_product_date_value('_expiration_date', $atts);
    }

    /**
     * Shortcode: Course time for the current (or specified) product.
     * Usage: [course_time] or [course_time product="123"]
     * Outputs nothing if the value is empty (effectively display:none).
     */
    public static function course_time($atts) {
        $atts = shortcode_atts(['product' => '', 'before' => '', 'after' => ''], $atts, 'course_time');
        return self::render_product_meta_value('_pmcm_course_time', $atts);
    }

    /**
     * Shortcode: CPD points for the current (or specified) product.
     * Usage: [cpd_points] or [cpd_points product="123" before="CPD Points: " after=" pts"]
     * Outputs nothing if the value is empty (effectively display:none).
     */
    public static function cpd_points($atts) {
        $atts = shortcode_atts(['product' => '', 'before' => '', 'after' => ''], $atts, 'cpd_points');
        return self::render_product_meta_value('_pmcm_cpd_points', $atts);
    }

    /**
     * Resolve a product (by id, slug, or the current page) and return a meta value.
     * Returns '' when the product can't be resolved or the value is empty — so any
     * surrounding label provided via before/after is hidden too.
     */
    private static function render_product_meta_value($meta_key, $atts) {
        $product_id = self::resolve_product_id($atts['product']);
        if (!$product_id) {
            return '';
        }

        $value = get_post_meta($product_id, $meta_key, true);
        if ($value === '' || $value === null) {
            return '';
        }

        return esc_html($atts['before']) . esc_html($value) . esc_html($atts['after']);
    }

    /**
     * Same as render_product_meta_value but formats a stored date (YYYY-MM-DD).
     * Honors the optional `format` attribute, falling back to the site date format.
     */
    private static function render_product_date_value($meta_key, $atts) {
        $product_id = self::resolve_product_id($atts['product']);
        if (!$product_id) {
            return '';
        }

        $value = get_post_meta($product_id, $meta_key, true);
        if ($value === '' || $value === null) {
            return '';
        }

        $ts = strtotime($value);
        if ($ts === false) {
            return '';
        }

        $format = !empty($atts['format']) ? $atts['format'] : get_option('date_format');
        $formatted = date_i18n($format, $ts);

        return esc_html($atts['before']) . esc_html($formatted) . esc_html($atts['after']);
    }

    /**
     * Resolve a product id from a shortcode attribute (id or slug), falling back to
     * the current page. Returns 0 when nothing can be resolved.
     */
    private static function resolve_product_id($product_attr) {
        $product_attr = sanitize_text_field($product_attr);
        $product_id   = 0;

        if (!empty($product_attr)) {
            $product_id = is_numeric($product_attr)
                ? intval($product_attr)
                : intval(PMCM_Core::get_product_id_by_slug(sanitize_title($product_attr)));
        }
        if (!$product_id) {
            $product_id = intval(get_the_ID());
        }

        return $product_id;
    }

    private static function get_registration_override(string $prefix): string
    {
        $val = get_option($prefix . 'registration_override', '');
        if (!in_array($val, ['auto', 'force_open', 'force_closed'], true)) {
            $val = get_option($prefix . 'force_registration_open', 'no') === 'yes' ? 'force_open' : 'auto';
        }

        // Self-cancel: once the real dates catch up, the override is no longer needed.
        // Force Open auto-reverts to Auto when start_date arrives; Force Closed reverts after end_date.
        if ($val === 'force_open' || $val === 'force_closed') {
            $today_ts = strtotime(current_time('Y-m-d'));
            $start    = get_option($prefix . 'edition_start', '');
            $end      = get_option($prefix . 'edition_end', '');
            $reset = false;
            if ($val === 'force_open' && !empty($start) && $today_ts >= strtotime($start)) {
                $reset = true;
            }
            if ($val === 'force_closed' && !empty($end) && $today_ts > strtotime($end)) {
                $reset = true;
            }
            if ($reset) {
                update_option($prefix . 'registration_override', 'auto');
                return 'auto';
            }
        }

        return $val;
    }

    /**
     * Returns the effective option keys for a course prefix.
     * Priority order:
     * 1. next_enabled + shortcode_display_next both 'yes' → next-slot keys (admin-toggled)
     * 2. next_enabled 'yes' + current edition end date has passed → next-slot keys (auto-fallback)
     * 3. Otherwise → current-slot keys
     */
    private static function get_slot_keys($prefix) {
        $next_enabled = get_option($prefix . 'next_enabled', 'no') === 'yes';
        $show_next    = get_option($prefix . 'shortcode_display_next', 'no') === 'yes';

        $next_keys = [
            'edition'    => $prefix . 'next_edition',
            'start'      => $prefix . 'next_start',
            'end'        => $prefix . 'next_end',
            'reg_open'   => $prefix . 'next_registration_open',
            'eb_enabled' => $prefix . 'next_early_bird_enabled',
            'eb_start'   => $prefix . 'next_early_bird_start',
            'eb_end'     => $prefix . 'next_early_bird_end',
        ];

        if ($next_enabled && $show_next) {
            return $next_keys;
        }

        // Auto-fallback: current edition has ended and next is ready — show next data
        if ($next_enabled) {
            $curr_end = get_option($prefix . 'edition_end', '');
            if (!empty($curr_end) && strtotime(current_time('Y-m-d')) > strtotime($curr_end)) {
                return $next_keys;
            }
        }

        return [
            'edition'    => $prefix . 'current_edition',
            'start'      => $prefix . 'edition_start',
            'end'        => $prefix . 'edition_end',
            'reg_open'   => $prefix . 'registration_open',
            'eb_enabled' => $prefix . 'early_bird_enabled',
            'eb_start'   => $prefix . 'early_bird_start',
            'eb_end'     => $prefix . 'early_bird_end',
        ];
    }

    /**
     * Resolve the date registration OPENS (the "Opening soon" gate).
     *
     * Priority:
     *   1. Explicit "Registration Opens" date if set.
     *   2. Early Bird Start (if early bird is enabled) — registration naturally opens
     *      when the early-bird window begins.
     *   3. The edition/course start date as a legacy fallback.
     *
     * Note: edition_start is the COURSE/exam date, so it is NOT used to gate the
     * status unless nothing else is configured.
     *
     * @return string A Y-m-d date string, or '' if registration should be considered
     *                open immediately (no gate).
     */
    private static function resolve_registration_open($explicit_open, $eb_enabled, $eb_start, $course_start) {
        if (!empty($explicit_open)) {
            return $explicit_open;
        }
        if ($eb_enabled === 'yes' && !empty($eb_start)) {
            return $eb_start;
        }
        return $course_start;
    }

    /**
     * Shortcode: Display current edition
     * Format: "12th - Current Edition"
     * Usage: [current_edition course="frcs"]
     */
    public static function current_edition($atts) {
        $atts = shortcode_atts(['course' => '', 'format' => 'full'], $atts, 'current_edition');
        $course_slug = sanitize_text_field($atts['course']);

        if (!isset(PMCM_Core::get_courses()[$course_slug])) {
            return '';
        }

        $course  = PMCM_Core::get_courses()[$course_slug];
        $prefix  = $course['settings_prefix'];
        $keys    = self::get_slot_keys($prefix);
        $edition = get_option($keys['edition'], 1);

        $display_text = PMCM_Core::get_ordinal($edition) . ' - ' . __('Current Edition', 'prepmedico-course-management');

        return '<span class="wcem-current-edition">' . esc_html($display_text) . '</span>';
    }

    /**
     * Shortcode: Display edition info
     * Format: "12th - Current Edition" with enrollment dates
     * Usage: [edition_info course="frcs" show_dates="yes"]
     */
    public static function edition_info($atts) {
        $atts = shortcode_atts(['course' => '', 'show_dates' => 'yes'], $atts, 'edition_info');
        $course_slug = sanitize_text_field($atts['course']);

        if (!isset(PMCM_Core::get_courses()[$course_slug])) {
            return '';
        }

        $course  = PMCM_Core::get_courses()[$course_slug];
        $prefix  = $course['settings_prefix'];
        $keys    = self::get_slot_keys($prefix);
        $edition = get_option($keys['edition'], 1);
        $start   = get_option($keys['start'], '');
        $end     = get_option($keys['end'], '');

        $output = '<div class="wcem-edition-info">';
        $output .= '<p class="wcem-edition-title"><strong>' . esc_html(PMCM_Core::get_ordinal($edition) . ' - ' . __('Current Edition', 'prepmedico-course-management')) . '</strong></p>';

        if ($atts['show_dates'] === 'yes' && $start && $end) {
            $output .= '<p class="wcem-edition-dates">' . __('Enrollment Period:', 'prepmedico-course-management') . ' ' . date('M j, Y', strtotime($start)) . ' - ' . date('M j, Y', strtotime($end)) . '</p>';
        }

        $output .= '</div>';
        return $output;
    }

    /**
     * Shortcode: Display just the edition number
     * Usage: [edition_number course="frcs"]
     */
    public static function edition_number($atts) {
        $atts = shortcode_atts(['course' => ''], $atts, 'edition_number');
        $course_slug = sanitize_text_field($atts['course']);

        if (!isset(PMCM_Core::get_courses()[$course_slug])) {
            return '';
        }

        $course  = PMCM_Core::get_courses()[$course_slug];
        $prefix  = $course['settings_prefix'];
        $keys    = self::get_slot_keys($prefix);
        $edition = get_option($keys['edition'], 1);

        return '<span class="wcem-edition-number">' . esc_html(PMCM_Core::get_ordinal($edition)) . '</span>';
    }

    /**
     * Shortcode: Display enrollment dates
     * Shows current edition dates normally; shows next edition dates when the
     * Frontend Shortcode Display toggle is ON or current edition has ended and next is ready.
     * Usage: [edition_dates course="frcs" format="range|start|end"]
     */
    public static function edition_dates_frontend($atts) {
        $atts = shortcode_atts(['course' => '', 'format' => 'range'], $atts, 'edition_dates');
        $course_slug = sanitize_text_field($atts['course']);
        $format      = sanitize_text_field($atts['format']);

        if (!isset(PMCM_Core::get_courses()[$course_slug])) {
            return '';
        }

        $course = PMCM_Core::get_courses()[$course_slug];
        $prefix = $course['settings_prefix'];

        // Inline slot detection
        $next_enabled = get_option($prefix . 'next_enabled', 'no') === 'yes';
        $show_next    = get_option($prefix . 'shortcode_display_next', 'no') === 'yes';
        $use_next = false;
        if ($next_enabled) {
            if ($show_next) {
                $use_next = true;
            } else {
                $curr_end = get_option($prefix . 'edition_end', '');
                if (!empty($curr_end) && strtotime(current_time('Y-m-d')) > strtotime($curr_end)) {
                    $use_next = true;
                }
            }
        }

        if ($use_next) {
            $start = get_option($prefix . 'next_start', '');
            $end   = get_option($prefix . 'next_end', '');
        } else {
            $start = get_option($prefix . 'edition_start', '');
            $end   = get_option($prefix . 'edition_end', '');
        }

        if ($format === 'start') {
            return '<span class="wcem-edition-date">' . ($start ? date_i18n('F j, Y', strtotime($start)) : __('TBA', 'prepmedico-course-management')) . '</span>';
        } elseif ($format === 'end') {
            return '<span class="wcem-edition-date">' . ($end ? date_i18n('F j, Y', strtotime($end)) : __('TBA', 'prepmedico-course-management')) . '</span>';
        } else {
            if (empty($start) || empty($end)) {
                return '<span class="wcem-edition-date">' . __('TBA', 'prepmedico-course-management') . '</span>';
            }
            return '<span class="wcem-edition-date">' . date_i18n('j M Y', strtotime($start)) . ' – ' . date_i18n('j M Y', strtotime($end)) . '</span>';
        }
    }

    /**
     * Shortcode: Display registration status
     * Usage: [registration_status course="frcs"]
     */
    public static function registration_status($atts) {
        $atts = shortcode_atts(['course' => '', 'slot' => ''], $atts, 'registration_status');
        $course_slug = sanitize_text_field($atts['course']);
        $slot_attr   = sanitize_text_field($atts['slot']); // '', 'current' or 'next' — forces the slot to match the enrol button

        // Auto-detect course from the current product page when no course attr given
        if (empty($course_slug)) {
            $product_id = get_the_ID();
            if ($product_id) {
                $categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'slugs']);
                $child_map  = PMCM_Core::get_child_to_parent_map();
                $courses    = PMCM_Core::get_courses();
                foreach ($categories as $cat_slug) {
                    if (isset($courses[$cat_slug])) {
                        $course_slug = $cat_slug;
                        break;
                    }
                    if (isset($child_map[$cat_slug]) && isset($courses[$child_map[$cat_slug]])) {
                        $course_slug = $child_map[$cat_slug];
                        break;
                    }
                }
            }
        }

        if (!isset(PMCM_Core::get_courses()[$course_slug])) {
            return '';
        }

        $course = PMCM_Core::get_courses()[$course_slug];
        $prefix = $course['settings_prefix'];

        // Per-product check: is the viewed product's category closed for the CURRENT edition?
        // If so, the current slot is not purchasable for this product.
        $page_product_id = get_the_ID();
        $closed_current  = ($page_product_id && get_post_type($page_product_id) === 'product'
            && PMCM_Core::is_product_in_closed_category_current($page_product_id, $course_slug));

        // Determine slot inline — avoids any chain issues with get_registration_status()
        $next_enabled = get_option($prefix . 'next_enabled', 'no') === 'yes';
        $show_next    = get_option($prefix . 'shortcode_display_next', 'no') === 'yes';
        $use_next = false;
        if ($slot_attr === 'next') {
            // Explicitly forced to the next edition (e.g. to match a slot="next" enrol button)
            $use_next = $next_enabled;
        } elseif ($slot_attr === 'current') {
            // Explicitly forced to the current edition
            $use_next = false;
        } elseif ($next_enabled) {
            if ($show_next) {
                $use_next = true;
            } else {
                // Auto-fallback: current edition ended, next is ready
                $curr_end = get_option($prefix . 'edition_end', '');
                if (!empty($curr_end) && strtotime(current_time('Y-m-d')) > strtotime($curr_end)) {
                    $use_next = true;
                }
            }
        }

        // Closed category for the current edition overrides the current-slot status.
        // If the next edition is on sale, show that instead; otherwise show "Registration closed".
        if (!$use_next && $closed_current) {
            if ($next_enabled && $slot_attr !== 'current') {
                $use_next = true;
            } else {
                return self::render_registration_status_html(
                    ['status' => 'closed', 'label' => __('Registration closed', 'prepmedico-course-management'), 'class' => 'wcem-status-closed'],
                    $course_slug,
                    'span'
                );
            }
        }

        if ($use_next) {
            $course_start = get_option($prefix . 'next_start', '');
            $end          = get_option($prefix . 'next_end', '');
            $eb_enabled   = get_option($prefix . 'next_early_bird_enabled', 'no');
            $eb_start     = get_option($prefix . 'next_early_bird_start', '');
            $eb_end       = get_option($prefix . 'next_early_bird_end', '');
            $reg_open     = get_option($prefix . 'next_registration_open', '');
        } else {
            // Registration override (current slot only)
            $override     = self::get_registration_override($prefix);
            $course_start = get_option($prefix . 'edition_start', '');
            $end          = get_option($prefix . 'edition_end', '');
            $eb_enabled   = get_option($prefix . 'early_bird_enabled', 'no');
            $eb_start     = get_option($prefix . 'early_bird_start', '');
            $eb_end       = get_option($prefix . 'early_bird_end', '');
            $reg_open     = get_option($prefix . 'registration_open', '');

            if ($override === 'force_open') {
                // Still show early bird if it is currently active — force_open only overrides closed/coming-soon
                $today_ts   = strtotime(current_time('Y-m-d'));
                $eb_end_ts  = !empty($eb_end) ? strtotime($eb_end) : null;
                $eb_start_ts = !empty($eb_start) ? strtotime($eb_start) : null;
                if ($eb_enabled === 'yes' && $eb_end_ts && (!$eb_start_ts || $today_ts >= $eb_start_ts) && $today_ts <= $eb_end_ts) {
                    return self::render_registration_status_html(['status' => 'early_bird', 'label' => __('Early bird registration open', 'prepmedico-course-management'), 'early_bird_end' => $eb_end, 'class' => 'wcem-status-early-bird'], $course_slug, 'span');
                }
                return self::render_registration_status_html(['status' => 'live', 'label' => __('Registration is live', 'prepmedico-course-management'), 'class' => 'wcem-status-live'], $course_slug, 'span');
            }
            if ($override === 'force_closed') {
                return self::render_registration_status_html(['status' => 'opening_soon', 'label' => __('Coming soon', 'prepmedico-course-management'), 'class' => 'wcem-status-upcoming'], $course_slug, 'span');
            }
        }

        // The "Opening soon" gate is the registration-open date, NOT the course start date.
        $gate_open   = self::resolve_registration_open($reg_open, $eb_enabled, $eb_start, $course_start);
        $status_data = self::compute_registration_status($gate_open, $end, $eb_enabled, $eb_start, $eb_end);
        if (!$status_data) {
            return '';
        }
        return self::render_registration_status_html($status_data, $course_slug, 'span');
    }

    /**
     * Shortcode: Display early bird message
     * Usage: [early_bird_message course="frcs"]
     */
    public static function early_bird_message($atts) {
        $atts = shortcode_atts(['course' => ''], $atts, 'early_bird_message');
        $course_slug = sanitize_text_field($atts['course']);

        if (!isset(PMCM_Core::get_courses()[$course_slug])) {
            return '';
        }

        $course = PMCM_Core::get_courses()[$course_slug];
        $prefix = $course['settings_prefix'];

        // Inline slot detection — same pattern as registration_status()
        $next_enabled = get_option($prefix . 'next_enabled', 'no') === 'yes';
        $show_next    = get_option($prefix . 'shortcode_display_next', 'no') === 'yes';
        $use_next = false;
        if ($next_enabled) {
            if ($show_next) {
                $use_next = true;
            } else {
                $curr_end = get_option($prefix . 'edition_end', '');
                if (!empty($curr_end) && strtotime(current_time('Y-m-d')) > strtotime($curr_end)) {
                    $use_next = true;
                }
            }
        }

        if ($use_next) {
            $eb_enabled = get_option($prefix . 'next_early_bird_enabled', 'no');
            $eb_start   = get_option($prefix . 'next_early_bird_start', '');
            $eb_end     = get_option($prefix . 'next_early_bird_end', '');
        } else {
            $eb_enabled = get_option($prefix . 'early_bird_enabled', 'no');
            $eb_start   = get_option($prefix . 'early_bird_start', '');
            $eb_end     = get_option($prefix . 'early_bird_end', '');
        }

        if ($eb_enabled !== 'yes' || empty($eb_end)) {
            return '';
        }

        $today_ts = strtotime(current_time('Y-m-d'));
        $start_ok = empty($eb_start) || $today_ts >= strtotime($eb_start);
        $end_ok   = $today_ts <= strtotime($eb_end);

        if (!$start_ok || !$end_ok) {
            return '';
        }

        $formatted_date = date('F j, Y', strtotime($eb_end));

        return '<div class="wcem-early-bird-message"><span class="wcem-early-bird-icon">&#127919;</span> ' . sprintf(__('Early Bird Offer Valid Until %s', 'prepmedico-course-management'), '<strong>' . esc_html($formatted_date) . '</strong>') . '</div>';
    }

    /**
     * Shortcode: Display complete course registration info
     * Format: "12th - Current Edition" with registration status below
     * Usage: [course_registration_info course="frcs" show_dates="yes"]
     */
    public static function course_registration_info($atts) {
        $atts = shortcode_atts(['course' => '', 'show_dates' => 'yes'], $atts, 'course_registration_info');
        $course_slug = sanitize_text_field($atts['course']);

        if (!isset(PMCM_Core::get_courses()[$course_slug])) {
            return '';
        }

        $course  = PMCM_Core::get_courses()[$course_slug];
        $prefix  = $course['settings_prefix'];
        $keys    = self::get_slot_keys($prefix);
        $edition = get_option($keys['edition'], 1);
        $start   = get_option($keys['start'], '');
        $end     = get_option($keys['end'], '');

        $status_data = self::get_registration_status($course_slug);

        $output = '<div class="wcem-course-registration-info">';
        $output .= '<p class="wcem-edition-title"><strong>' . esc_html(PMCM_Core::get_ordinal($edition) . ' - ' . __('Current Edition', 'prepmedico-course-management')) . '</strong></p>';

        if ($atts['show_dates'] === 'yes' && $start && $end) {
            $output .= '<p class="wcem-edition-dates">' . date('M j, Y', strtotime($start)) . ' - ' . date('M j, Y', strtotime($end)) . '</p>';
        }

        if ($status_data) {
            $output .= self::render_registration_status_html($status_data, $course_slug, 'p');
        }

        if ($status_data && in_array($status_data['status'], ['early_bird'], true)) {
            $output .= do_shortcode('[early_bird_message course="' . esc_attr($course_slug) . '"]');
        }

        $output .= '</div>';
        return $output;
    }

    /**
     * Render registration status badge with optional Lottie icon
     */
    private static function render_registration_status_html($status_data, $course_slug, $tag = 'span') {
        $allowed_tags = ['span', 'p', 'div'];
        if (!in_array($tag, $allowed_tags, true)) {
            $tag = 'span';
        }

        $status_class = isset($status_data['class']) ? $status_data['class'] : '';
        $status_label = isset($status_data['label']) ? $status_data['label'] : '';
        $status = isset($status_data['status']) ? $status_data['status'] : '';

        $dot_markup = self::get_status_dot($status);

        if ($dot_markup !== '') {
            self::enqueue_dot_css();

            return '<' . $tag . ' class="wcem-registration-status ' . esc_attr($status_class) . '">'
                . '<span class="wcem-registration-status-inner" style="display:inline-flex;align-items:center;gap:14px;">'
                . $dot_markup
                . '<span class="wcem-registration-status-label">' . esc_html($status_label) . '</span>'
                . '</span>'
                . '</' . $tag . '>';
        }

        return '<' . $tag . ' class="wcem-registration-status ' . esc_attr($status_class) . '">' . esc_html($status_label) . '</' . $tag . '>';
    }

    /**
     * Get pulsing dot markup for registration status
     * Renders a solid dot with two expanding ripple rings that fade out
     */
    private static function get_status_dot($status) {
        $colors = [
            'opening_soon' => '#9CA3AF',
            'live'         => '#50c154',
            'course_live'  => '#50c154',
            'early_bird'   => '#C026D3',
            'closed'       => '#ef4444',
        ];

        if (!isset($colors[$status])) {
            return '';
        }

        $color = $colors[$status];
        if ($status === 'early_bird') {
            $type = 'eb';
        } elseif ($status === 'opening_soon') {
            $type = 'grey';
        } elseif ($status === 'closed') {
            $type = 'closed';
        } else {
            $type = 'live';
        }

        // 12px dot; the glow lives on the dot itself via animated box-shadow
        return '<span class="wcem-pulse-dot wcem-pulse-' . $type . '" aria-hidden="true"'
            . ' style="display:inline-flex;align-items:center;justify-content:center;'
            . 'width:12px;height:12px;min-width:12px;flex:0 0 12px;">'
            . '<span class="wcem-pulse-core" style="width:12px;height:12px;border-radius:50%;background:' . $color . ';"></span>'
            . '</span>';
    }

    /**
     * Enqueue inline CSS for the glowing dot animation.
     * The glow is a pulsing box-shadow on the dot itself (not expanding rings).
     */
    private static $dot_css_enqueued = false;

    private static function enqueue_dot_css() {
        if (self::$dot_css_enqueued) {
            return;
        }
        self::$dot_css_enqueued = true;

        add_action('wp_footer', function () {
            echo '<style id="wcem-pulse-dot-css">'
                . '.wcem-pulse-dot .wcem-pulse-core{animation:wcem-glow 1.6s ease-in-out infinite}'
                . '.wcem-pulse-live .wcem-pulse-core{animation-name:wcem-glow-live}'
                . '.wcem-pulse-eb .wcem-pulse-core{animation-name:wcem-glow-eb}'
                . '.wcem-pulse-grey .wcem-pulse-core{animation-name:wcem-glow-grey}'
                . '.wcem-pulse-closed .wcem-pulse-core{animation:none;box-shadow:0 0 3px 0 rgba(239,68,68,0.6)}'
                . '@keyframes wcem-glow-live{'
                    . '0%,100%{box-shadow:0 0 2px 0 rgba(80,193,84,0.5)}'
                    . '50%{box-shadow:0 0 9px 3px rgba(80,193,84,0.9)}'
                . '}'
                . '@keyframes wcem-glow-eb{'
                    . '0%,100%{box-shadow:0 0 2px 0 rgba(192,38,211,0.5)}'
                    . '50%{box-shadow:0 0 9px 3px rgba(192,38,211,0.9)}'
                . '}'
                . '@keyframes wcem-glow-grey{'
                    . '0%,100%{box-shadow:0 0 2px 0 rgba(156,163,175,0.5)}'
                    . '50%{box-shadow:0 0 9px 3px rgba(156,163,175,0.9)}'
                . '}'
                . '</style>';
        }, 99);
    }

    /**
     * Pure status computation — no DB reads.
     * Called by both registration_status() shortcode and get_registration_status().
     *
     * Robust, single-pass state machine. The states are evaluated in strict priority
     * order so they can never contradict each other:
     *
     *   1. No dates configured            → Coming soon
     *   2. today  >  end                  → Coming soon (registration window closed)
     *   3. today  <  start                → Opening soon (registration not open YET —
     *                                       this ALWAYS beats early bird, because early
     *                                       bird is a phase *inside* the open window)
     *   4. start <= today <= end, and within the early-bird sub-window → Early bird open
     *   5. start <= today <= end, otherwise → Registration is live
     *
     * Treats edition_start as the moment registration opens. If you want early bird to
     * be biddable before the edition start, set early_bird_start to that earlier date
     * AND set edition_start to that same earlier date (start gates everything).
     */
    private static function compute_registration_status($start, $end, $eb_enabled, $eb_start, $eb_end) {
        $today_ts    = strtotime(current_time('Y-m-d'));
        $start_ts    = !empty($start)    ? strtotime($start)    : null;
        $end_ts      = !empty($end)      ? strtotime($end)      : null;
        $eb_start_ts = !empty($eb_start) ? strtotime($eb_start) : null;
        $eb_end_ts   = !empty($eb_end)   ? strtotime($eb_end)   : null;

        $coming_soon = ['status' => 'opening_soon', 'label' => __('Coming soon', 'prepmedico-course-management'), 'class' => 'wcem-status-upcoming'];

        // 1. No usable dates at all.
        if (!$start_ts && !$end_ts) {
            return $coming_soon;
        }

        // 2. Registration window has closed (past the end date).
        if ($end_ts && $today_ts > $end_ts) {
            return $coming_soon;
        }

        // 3. Registration has not opened yet — wins over early bird.
        if ($start_ts && $today_ts < $start_ts) {
            return $coming_soon;
        }

        // --- Registration is open here: start <= today <= end ---

        // 4. Early-bird sub-window active?
        if ($eb_enabled === 'yes' && $eb_end_ts) {
            $eb_started = (!$eb_start_ts || $today_ts >= $eb_start_ts);
            $eb_active  = $eb_started && ($today_ts <= $eb_end_ts);
            if ($eb_active) {
                return ['status' => 'early_bird', 'label' => __('Early bird registration open', 'prepmedico-course-management'), 'early_bird_end' => $eb_end, 'class' => 'wcem-status-early-bird'];
            }
        }

        // 5. Open, early bird not active (ended or never configured).
        return ['status' => 'live', 'label' => __('Registration is live', 'prepmedico-course-management'), 'class' => 'wcem-status-live'];
    }

    /**
     * Get registration status for a course — used by course_registration_info() and external callers.
     */
    public static function get_registration_status($course_slug) {
        if (!isset(PMCM_Core::get_courses()[$course_slug])) {
            return null;
        }

        $course = PMCM_Core::get_courses()[$course_slug];
        $prefix = $course['settings_prefix'];
        $keys   = self::get_slot_keys($prefix);

        $eb_enabled = get_option($keys['eb_enabled'], 'no');
        $eb_start   = get_option($keys['eb_start'], '');
        $gate_open  = self::resolve_registration_open(
            get_option($keys['reg_open'], ''),
            $eb_enabled,
            $eb_start,
            get_option($keys['start'], '')
        );

        return self::compute_registration_status(
            $gate_open,
            get_option($keys['end'], ''),
            $eb_enabled,
            $eb_start,
            get_option($keys['eb_end'], '')
        );
    }

    /**
     * =====================================================
     * ELEMENTOR TABLE SHORTCODES
     * For custom edition tables built in Elementor
     * =====================================================
     */

    /**
     * Get edition URL with edition parameter
     * Usage: [pmcm_edition_url course="frcs" slot="current" product="frcs-course"]
     *
     * @param array $atts Shortcode attributes
     * @return string URL with edition parameter
     */
    public static function edition_url($atts) {
        $atts = shortcode_atts([
            'course' => '',
            'slot' => 'current',
            'product' => ''
        ], $atts, 'pmcm_edition_url');

        $course_slug = sanitize_text_field($atts['course']);
        $slot = sanitize_text_field($atts['slot']);
        $product_slug = sanitize_text_field($atts['product']);

        if (empty($course_slug) || empty($product_slug)) {
            return '';
        }

        if (!isset(PMCM_Core::get_courses()[$course_slug])) {
            return '';
        }

        $course = PMCM_Core::get_courses()[$course_slug];
        $prefix = $course['settings_prefix'];

        // Get edition number based on slot
        if ($slot === 'next') {
            $next_enabled = get_option($prefix . 'next_enabled', 'no');
            if ($next_enabled !== 'yes') {
                return ''; // Next slot not enabled
            }
            $edition = intval(get_option($prefix . 'next_edition', 0));
            if ($edition === 0) {
                return '';
            }
        } else {
            $edition = intval(get_option($prefix . 'current_edition', 1));
        }

        // Build URL with edition parameter
        $product_url = home_url('/product/' . $product_slug . '/');
        return esc_url($product_url . '?edition=' . $edition);
    }

    /**
     * Get edition ordinal number (11th, 12th, etc.)
     * Usage: [pmcm_edition_ordinal course="frcs" slot="current"]
     *
     * @param array $atts Shortcode attributes
     * @return string Ordinal edition number
     */
    public static function edition_ordinal($atts) {
        // Support boolean-style attribute: [pmcm_edition_ordinal course="frcs" early_bird]
        // WordPress passes valueless attributes as the key name itself in the $atts array.
        if (is_array($atts)) {
            foreach ($atts as $k => $v) {
                if ($v === 'early_bird' || $k === 'early_bird') {
                    $atts['early_bird'] = '1';
                    if (is_int($k)) unset($atts[$k]);
                    break;
                }
            }
        }

        $atts = shortcode_atts([
            'course'     => '',
            'slot'       => 'current',
            'early_bird' => '1', // '1' = show chip when EB active (default), '0' = never show chip
        ], $atts, 'pmcm_edition_ordinal');

        $course_slug  = sanitize_text_field($atts['course']);
        $slot         = sanitize_text_field($atts['slot']);
        $show_chip    = ($atts['early_bird'] !== '0');

        if (empty($course_slug)) {
            return '';
        }

        if (!isset(PMCM_Core::get_courses()[$course_slug])) {
            return '';
        }

        $course = PMCM_Core::get_courses()[$course_slug];
        $prefix = $course['settings_prefix'];
        $ordinal = '';
        $early_bird_active = false;

        if ($slot === 'next') {
            $next_enabled = get_option($prefix . 'next_enabled', 'no');
            if ($next_enabled === 'yes') {
                $edition = intval(get_option($prefix . 'next_edition', 0));
                if ($edition > 0) {
                    $ordinal = PMCM_Core::get_ordinal($edition);
                } else {
                    $current = intval(get_option($prefix . 'current_edition', 1));
                    $ordinal = PMCM_Core::get_ordinal($current + 1);
                }
                // Check next edition early bird
                // Early bird is only valid BEFORE the course starts
                $eb_enabled = get_option($prefix . 'next_early_bird_enabled', 'no');
                $eb_start = get_option($prefix . 'next_early_bird_start', '');
                $eb_end = get_option($prefix . 'next_early_bird_end', '');
                $course_start = get_option($prefix . 'next_start', '');

                if ($eb_enabled === 'yes' && !empty($eb_end)) {
                    $today = strtotime(current_time('Y-m-d'));
                    $eb_start_ok = empty($eb_start) || $today >= strtotime($eb_start);
                    $eb_end_ok = $today <= strtotime($eb_end);

                    if ($eb_start_ok && $eb_end_ok) {
                        $early_bird_active = true;
                    }
                }
            } else {
                $current = intval(get_option($prefix . 'current_edition', 1));
                $ordinal = PMCM_Core::get_ordinal($current + 1);
            }
        } else {
            $edition = intval(get_option($prefix . 'current_edition', 1));
            $ordinal = PMCM_Core::get_ordinal($edition);
            $eb_enabled = get_option($prefix . 'early_bird_enabled', 'no');
            $eb_start = get_option($prefix . 'early_bird_start', '');
            $eb_end = get_option($prefix . 'early_bird_end', '');

            if ($eb_enabled === 'yes' && !empty($eb_end)) {
                $today = strtotime(current_time('Y-m-d'));
                $eb_start_ok = empty($eb_start) || $today >= strtotime($eb_start);
                $eb_end_ok = $today <= strtotime($eb_end);

                if ($eb_start_ok && $eb_end_ok) {
                    $early_bird_active = true;
                }
            }
        }

        if ($early_bird_active && $show_chip) {
            return $ordinal . ' <span class="pmcm-early-bird-chip">' . __('Early Bird Registration', 'prepmedico-course-management') . '</span>';
        }

        return $ordinal;
    }

    /**
     * Get edition dates
     * Usage: [pmcm_edition_dates course="frcs" slot="current" format="range"]
     *
     * @param array $atts Shortcode attributes
     * @return string Date range or single date
     */
    public static function edition_dates($atts) {
        $atts = shortcode_atts([
            'course' => '',
            'slot' => 'current',
            'format' => 'range' // range, start, end
        ], $atts, 'pmcm_edition_dates');

        $course_slug = sanitize_text_field($atts['course']);
        $slot = sanitize_text_field($atts['slot']);
        $format = sanitize_text_field($atts['format']);

        if (empty($course_slug)) {
            return '';
        }

        if (!isset(PMCM_Core::get_courses()[$course_slug])) {
            return '';
        }

        $course = PMCM_Core::get_courses()[$course_slug];
        $prefix = $course['settings_prefix'];

        if ($slot === 'next') {
            $next_enabled = get_option($prefix . 'next_enabled', 'no');
            if ($next_enabled === 'yes') {
                $start = get_option($prefix . 'next_start', '');
                $end = get_option($prefix . 'next_end', '');
            } else {
                // Next edition not configured - dates are TBA
                $start = '';
                $end = '';
            }
        } else {
            $start = get_option($prefix . 'edition_start', '');
            $end = get_option($prefix . 'edition_end', '');
        }

        if ($format === 'start') {
            return $start ? date_i18n('F j, Y', strtotime($start)) : __('TBA', 'prepmedico-course-management');
        } elseif ($format === 'end') {
            return $end ? date_i18n('F j, Y', strtotime($end)) : __('TBA', 'prepmedico-course-management');
        } else {
            // Range format
            if (empty($start) || empty($end)) {
                return __('TBA', 'prepmedico-course-management');
            }
            return date_i18n('j M Y', strtotime($start)) . ' – ' . date_i18n('j M Y', strtotime($end));
        }
    }

    /**
     * Get edition registration status
     * Usage: [pmcm_edition_status course="frcs" slot="current" output="text"]
     *
     * @param array $atts Shortcode attributes
     * @return string Status text or CSS class
     */
    public static function edition_status($atts) {
        $atts = shortcode_atts([
            'course' => '',
            'slot' => 'current',
            'output' => 'text', // text or class
            'product' => ''
        ], $atts, 'pmcm_edition_status');

        $course_slug = sanitize_text_field($atts['course']);
        $slot = sanitize_text_field($atts['slot']);
        $output = sanitize_text_field($atts['output']);
        $product_slug = sanitize_text_field($atts['product']);

        if (empty($course_slug)) {
            return '';
        }

        if (!isset(PMCM_Core::get_courses()[$course_slug])) {
            return '';
        }

        $course = PMCM_Core::get_courses()[$course_slug];
        $prefix = $course['settings_prefix'];
        $today = current_time('Y-m-d');
        $today_timestamp = strtotime($today);

        // Force closed if product is in a closed category (current slot only)
        if ($slot === 'current' && !empty($product_slug)) {
            $product_id = PMCM_Core::get_product_id_by_slug($product_slug);
            if ($product_id && PMCM_Core::is_product_in_closed_category_current($product_id, $course_slug)) {
                return $output === 'class' ? 'pmcm-closed' : 'closed';
            }
        }

        // Registration override (current slot only)
        if ($slot === 'current') {
            $override = self::get_registration_override($prefix);
            if ($override === 'force_open') {
                return $output === 'class' ? 'pmcm-open' : 'open';
            }
            if ($override === 'force_closed') {
                return $output === 'class' ? 'pmcm-upcoming' : 'upcoming';
            }
        }

        if ($slot === 'next') {
            $enabled = get_option($prefix . 'next_enabled', 'no');
            if ($enabled === 'yes') {
                $start = get_option($prefix . 'next_start', '');
                $end   = get_option($prefix . 'next_end', '');
                $eb_enabled = get_option($prefix . 'next_early_bird_enabled', 'no');
                $eb_start   = get_option($prefix . 'next_early_bird_start', '');
                $eb_end     = get_option($prefix . 'next_early_bird_end', '');
            } else {
                $start = '';
                $end   = '';
                $eb_enabled = 'no';
                $eb_start   = '';
                $eb_end     = '';
            }
            // If dates not set, return dates-tba
            if (empty($start) || empty($end)) {
                return $output === 'class' ? 'pmcm-dates-tba' : 'dates-tba';
            }
        } else {
            $start      = get_option($prefix . 'edition_start', '');
            $end        = get_option($prefix . 'edition_end', '');
            $eb_enabled = get_option($prefix . 'early_bird_enabled', 'no');
            $eb_start   = get_option($prefix . 'early_bird_start', '');
            $eb_end     = get_option($prefix . 'early_bird_end', '');
        }

        // Check status: closed (end date passed)
        if (!empty($end) && $today_timestamp > strtotime($end)) {
            return $output === 'class' ? 'pmcm-closed' : 'closed';
        }

        // Check early bird
        if ($eb_enabled === 'yes' && !empty($eb_end)) {
            $eb_start_ok = empty($eb_start) || $today_timestamp >= strtotime($eb_start);
            $eb_end_ok   = $today_timestamp < strtotime($eb_end);
            if ($eb_start_ok && $eb_end_ok) {
                return $output === 'class' ? 'pmcm-early-bird' : 'early_bird';
            }
        }

        // Check status: upcoming (before start date)
        if (!empty($start) && $today_timestamp < strtotime($start)) {
            return $output === 'class' ? 'pmcm-upcoming' : 'upcoming';
        }

        // Registration is open
        return $output === 'class' ? 'pmcm-open' : 'open';
    }

    /**
     * Get complete edition button with URL and disable state
     * Usage: [pmcm_edition_button course="frcs" slot="current" product="frcs-course" text="Enrol for the course"]
     *
     * @param array $atts Shortcode attributes
     * @return string Button HTML
     */
    public static function edition_button($atts) {
        $atts = shortcode_atts([
            'course' => '',
            'slot' => 'current',
            'product' => '',
            'text' => __('Enrol for the course', 'prepmedico-course-management'),
            'class' => ''
        ], $atts, 'pmcm_edition_button');

        $course_slug = sanitize_text_field($atts['course']);
        $slot = sanitize_text_field($atts['slot']);
        $product_slug = sanitize_text_field($atts['product']);
        $text = sanitize_text_field($atts['text']);
        $custom_class = sanitize_html_class($atts['class']);

        if (empty($course_slug) || empty($product_slug)) {
            return '';
        }

        if (!isset(PMCM_Core::get_courses()[$course_slug])) {
            return '';
        }

        $course = PMCM_Core::get_courses()[$course_slug];
        $prefix = $course['settings_prefix'];
        $today = current_time('Y-m-d');
        $today_timestamp = strtotime($today);

        // Get edition info based on slot
        $dates_unavailable = false;
        if ($slot === 'next') {
            $enabled = get_option($prefix . 'next_enabled', 'no');
            if ($enabled === 'yes') {
                $edition = intval(get_option($prefix . 'next_edition', 0));
                $start = get_option($prefix . 'next_start', '');
                $end = get_option($prefix . 'next_end', '');
            } else {
                // Fallback: current + 1, no dates
                $edition = intval(get_option($prefix . 'current_edition', 1)) + 1;
                $start = '';
                $end = '';
            }
            // Dates not available if start or end is empty
            if (empty($start) || empty($end)) {
                $dates_unavailable = true;
            }
        } else {
            $edition = intval(get_option($prefix . 'current_edition', 1));
            $start = get_option($prefix . 'edition_start', '');
            $end = get_option($prefix . 'edition_end', '');
        }

        // Build URL
        $url = home_url('/product/' . $product_slug . '/?edition=' . $edition);

        // Force closed if product is in a closed category (current slot only)
        $forced_closed = false;
        if ($slot === 'current' && !empty($product_slug)) {
            $product_id = PMCM_Core::get_product_id_by_slug($product_slug);
            if ($product_id && PMCM_Core::is_product_in_closed_category_current($product_id, $course_slug)) {
                $forced_closed = true;
            }
        }

        // Registration override (current slot only)
        $override     = $slot === 'current' ? self::get_registration_override($prefix) : 'auto';
        $force_open   = $override === 'force_open';
        $force_closed = $override === 'force_closed';

        // Determine button state
        $is_closed   = !$force_open && !$force_closed && ($forced_closed || (!empty($end) && $today_timestamp > strtotime($end)));
        $is_upcoming = !$force_open && !$force_closed && !$forced_closed && !empty($start) && $today_timestamp < strtotime($start);

        // Force Closed: show "Opening Soon" regardless of dates
        if ($force_closed) {
            $is_upcoming = true;
            $text        = __('Opening Soon', 'prepmedico-course-management');
        }

        // Build button classes
        $classes = ['pmcm-edition-btn'];
        if ($custom_class) {
            $classes[] = $custom_class;
        }

        if ($dates_unavailable) {
            $classes[] = 'pmcm-dates-tba';
        } elseif ($is_closed) {
            $classes[] = 'pmcm-closed';
        } elseif ($is_upcoming) {
            $classes[] = 'pmcm-upcoming';
            if ($force_closed) {
                $classes[] = 'pmcm-force-closed';
            }
        } else {
            $classes[] = 'pmcm-open';
        }

        // Build inline styles for disabled state
        $style = '';
        if ($is_closed || $dates_unavailable || $force_closed) {
            $style = 'pointer-events: none; opacity: 0.6; cursor: not-allowed;';
        }

        // Generate button HTML
        $output = '<a href="' . esc_url($url) . '" class="' . esc_attr(implode(' ', $classes)) . '"';
        if ($style) {
            $output .= ' style="' . esc_attr($style) . '"';
        }
        $output .= '>' . esc_html($text) . '</a>';

        return $output;
    }

    /**
     * Get raw edition number (without ordinal suffix)
     * Usage: [pmcm_edition_number_raw course="frcs" slot="current"]
     * Output: 11 (just the number, useful for data attributes)
     *
     * @param array $atts Shortcode attributes
     * @return string Raw edition number
     */
    public static function edition_number_raw($atts) {
        $atts = shortcode_atts([
            'course' => '',
            'slot' => 'current'
        ], $atts, 'pmcm_edition_number_raw');

        $course_slug = sanitize_text_field($atts['course']);
        $slot = sanitize_text_field($atts['slot']);

        if (empty($course_slug)) {
            return '';
        }

        if (!isset(PMCM_Core::get_courses()[$course_slug])) {
            return '';
        }

        $course = PMCM_Core::get_courses()[$course_slug];
        $prefix = $course['settings_prefix'];

        if ($slot === 'next') {
            $next_enabled = get_option($prefix . 'next_enabled', 'no');
            if ($next_enabled === 'yes') {
                $edition = intval(get_option($prefix . 'next_edition', 0));
                if ($edition > 0) {
                    return strval($edition);
                }
            }
            // Fallback: current edition + 1
            $current = intval(get_option($prefix . 'current_edition', 1));
            return strval($current + 1);
        } else {
            $edition = intval(get_option($prefix . 'current_edition', 1));
        }

        return strval($edition);
    }

    /**
     * Output JavaScript to handle dynamic edition parameter on product links
     * Place this shortcode ONCE on the page where you have edition toggle buttons
     *
     * Usage: [pmcm_edition_product_script]
     *
     * How it works:
     * 1. Add data-pmcm-edition="11" to your toggle button (use [pmcm_edition_number_raw] for value)
     * 2. Add data-pmcm-products="true" to the container that holds the products
     * 3. When button is clicked, all product links in the container get ?edition=X appended
     *
     * Example Elementor setup:
     * - Toggle button: Add Custom Attribute: data-pmcm-edition|[pmcm_edition_number_raw course="frcs" slot="current"]
     * - Products container: Add class "pmcm-products-container" or data-pmcm-products="true"
     *
     * @param array $atts Shortcode attributes
     * @return string JavaScript code
     */
    public static function edition_product_script($atts) {
        $atts = shortcode_atts([
            'button_selector' => '[data-pmcm-edition]',
            'container_class' => 'pmcm-products-container'
        ], $atts, 'pmcm_edition_product_script');

        $button_selector = esc_js($atts['button_selector']);
        $container_class = esc_js($atts['container_class']);

        ob_start();
        ?>
        <script>
        (function() {
            'use strict';

            // Track which containers have been updated with which edition
            const updatedContainers = new Map();

            function updateProductLinks(container, edition) {
                if (!container || !edition) return;

                // Find all product links in the container
                const links = container.querySelectorAll('a[href*="/product/"]');

                links.forEach(function(link) {
                    let href = link.getAttribute('href');
                    if (!href) return;

                    // Remove existing edition parameter if present
                    const url = new URL(href, window.location.origin);
                    url.searchParams.delete('edition');

                    // Add new edition parameter
                    url.searchParams.set('edition', edition);

                    link.setAttribute('href', url.toString());
                });

                // Mark container as updated with this edition
                updatedContainers.set(container, edition);
            }

            function findProductContainer(button) {
                // Strategy 1: Look for sibling with pmcm-products-container class
                let container = button.parentElement.querySelector('.<?php echo $container_class; ?>');
                if (container) return container;

                // Strategy 2: Look for data-pmcm-products attribute in siblings
                container = button.parentElement.querySelector('[data-pmcm-products]');
                if (container) return container;

                // Strategy 3: Look in parent's siblings
                const parent = button.closest('.e-con, .elementor-element');
                if (parent && parent.nextElementSibling) {
                    container = parent.nextElementSibling.querySelector('.<?php echo $container_class; ?>, [data-pmcm-products]');
                    if (container) return container;

                    // Check if next sibling itself is the container
                    if (parent.nextElementSibling.classList.contains('<?php echo $container_class; ?>') ||
                        parent.nextElementSibling.hasAttribute('data-pmcm-products')) {
                        return parent.nextElementSibling;
                    }
                }

                // Strategy 4: Look for Container_to_Show class (common Elementor pattern)
                container = button.closest('.e-con-inner, .e-con')?.querySelector('.Container_to_Show');
                if (container) return container;

                // Strategy 5: Look for premium-woo-products within the same parent structure
                const grandParent = button.closest('[data-element_type="container"]');
                if (grandParent) {
                    container = grandParent.parentElement.querySelector('.premium-woo-products-inner, .products');
                    if (container) return container.closest('.e-con, [data-element_type="container"]') || container;
                }

                return null;
            }

            function initEditionLinks() {
                // Primary approach: .pmcm-edition-section wrapper contains
                // a .pmcm-edition-marker[data-edition] and a .toggle-container.
                // In Elementor, add class "pmcm-edition-section" to the outer
                // container wrapping each edition row.
                document.querySelectorAll('.pmcm-edition-section').forEach(function(section) {
                    const marker = section.querySelector('.pmcm-edition-marker[data-edition]');
                    if (!marker) return;

                    const edition = marker.getAttribute('data-edition');
                    if (!edition) return;

                    const productsContainer = section.querySelector('.toggle-container');
                    if (!productsContainer) return;

                    // Stamp links immediately on page load
                    updateProductLinks(productsContainer, edition);

                    // Re-stamp on toggle button click (container may animate open after click)
                    const toggleBtn = section.querySelector('.toggle-btn');
                    if (toggleBtn && !toggleBtn.dataset.pmcmInitialized) {
                        toggleBtn.dataset.pmcmInitialized = 'true';
                        toggleBtn.addEventListener('click', function() {
                            setTimeout(function() {
                                updateProductLinks(productsContainer, edition);
                            }, 100);
                        });
                    }
                });

                // Legacy approach: [data-pmcm-edition] attribute on toggle buttons
                const buttons = document.querySelectorAll('<?php echo $button_selector; ?>');
                buttons.forEach(function(button) {
                    if (button.dataset.pmcmInitialized === 'true') return;
                    button.dataset.pmcmInitialized = 'true';

                    var legacyEdition = button.dataset.pmcmEdition;
                    if (!legacyEdition) return;

                    button.addEventListener('click', function(e) {
                        const container = findProductContainer(this);
                        if (!container) return;
                        if (updatedContainers.get(container) !== legacyEdition) {
                            setTimeout(function() {
                                updateProductLinks(container, legacyEdition);
                            }, 50);
                        }
                    });

                    setTimeout(function() {
                        var container = findProductContainer(button);
                        if (container) updateProductLinks(container, legacyEdition);
                    }, 100);
                });
            }

            // Initialize on DOM ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initEditionLinks);
            } else {
                initEditionLinks();
            }

            // Re-initialize for Elementor frontend
            if (typeof jQuery !== 'undefined') {
                jQuery(window).on('elementor/frontend/init', function() {
                    setTimeout(initEditionLinks, 200);
                });
            }

            // Watch for dynamic content
            const observer = new MutationObserver(function(mutations) {
                let shouldInit = false;
                mutations.forEach(function(mutation) {
                    if (mutation.addedNodes.length > 0) {
                        shouldInit = true;
                    }
                });
                if (shouldInit) {
                    setTimeout(initEditionLinks, 100);
                }
            });

            observer.observe(document.body, {
                childList: true,
                subtree: true
            });

            // Expose function globally for manual use
            window.pmcmUpdateProductEdition = updateProductLinks;

        })();
        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * =====================================================
     * SIMPLIFIED APPROACH - NO DATA ATTRIBUTES NEEDED
     * =====================================================
     */

    /**
     * Edition marker - outputs a hidden span with edition number
     * Place this shortcode inside your toggle button's container in Elementor
     *
     * Usage: [pmcm_edition_marker course="frcs" slot="current"]
     *
     * This outputs: <span class="pmcm-edition-marker" data-edition="11" style="display:none;"></span>
     *
     * The JavaScript will find this marker and use the edition when the parent button is clicked.
     *
     * @param array $atts Shortcode attributes
     * @return string Hidden marker span
     */
    public static function edition_marker($atts) {
        $atts = shortcode_atts([
            'course' => '',
            'slot' => 'current'
        ], $atts, 'pmcm_edition_marker');

        $course_slug = sanitize_text_field($atts['course']);
        $slot = sanitize_text_field($atts['slot']);

        if (empty($course_slug)) {
            return '';
        }

        if (!isset(PMCM_Core::get_courses()[$course_slug])) {
            return '';
        }

        $course = PMCM_Core::get_courses()[$course_slug];
        $prefix = $course['settings_prefix'];

        if ($slot === 'next') {
            $next_enabled = get_option($prefix . 'next_enabled', 'no');
            if ($next_enabled === 'yes') {
                $edition = intval(get_option($prefix . 'next_edition', 0));
                if ($edition === 0) {
                    // Next enabled but no edition set - use current + 1
                    $edition = intval(get_option($prefix . 'current_edition', 1)) + 1;
                }
            } else {
                // Fallback: current + 1
                $edition = intval(get_option($prefix . 'current_edition', 1)) + 1;
            }
        } else {
            $edition = intval(get_option($prefix . 'current_edition', 1));
        }

        // Detect closed state: only the 'current' slot is affected by closed_categories_current.
        // The marker is closed when this course's parent category slug is in the closed list.
        $is_closed = false;
        if ($slot === 'current') {
            $closed_cats = PMCM_Core::get_closed_categories_current($course_slug);
            if (in_array($course['category_slug'], $closed_cats, true)) {
                $is_closed = true;
            }
        }

        // Detect force-closed override (shows "Opening Soon" instead of "Registration Closed")
        $is_opening_soon = false;
        if ($slot === 'current' && self::get_registration_override($prefix) === 'force_closed') {
            $is_opening_soon = true;
            $is_closed = false; // don't also add data-closed
        }

        // Output hidden marker with edition number and course slug (data-course used by price CSS)
        return '<span class="pmcm-edition-marker"'
            . ' data-edition="' . esc_attr($edition) . '"'
            . ' data-slot="' . esc_attr($slot) . '"'
            . ' data-course="' . esc_attr($course_slug) . '"'
            . ($is_closed ? ' data-closed="1"' : '')
            . ($is_opening_soon ? ' data-opening-soon="1"' : '')
            . ' style="display:none !important; visibility:hidden; position:absolute; pointer-events:none;"></span>';
    }

    /**
     * Simplified JavaScript for edition product links
     * Works with pmcm_edition_marker shortcode - NO DATA ATTRIBUTES ON BUTTONS NEEDED
     *
     * Usage: [pmcm_edition_products_script]
     *
     * Place this ONCE on your page (e.g., in a HTML widget at the bottom)
     *
     * How it works:
     * 1. Add [pmcm_edition_marker course="frcs" slot="current"] inside your ROW 1 toggle button container
     * 2. Add [pmcm_edition_marker course="frcs" slot="next"] inside your ROW 2 toggle button container
     * 3. Add this script once on the page
     * 4. When a toggle button is clicked, the script finds the nearest marker and updates all product links
     *
     * @param array $atts Shortcode attributes
     * @return string JavaScript code
     */
    public static function edition_products_script($atts) {
        $atts = shortcode_atts([
            'products_class' => 'premium-woo-products-inner'
        ], $atts, 'pmcm_edition_products_script');

        $products_class = esc_js($atts['products_class']);

        ob_start();
        ?>
        <script>
        (function() {
            'use strict';

            // Track which edition each products container should use
            const containerEditions = new Map();

            /**
             * Update all product links in a container with the edition parameter
             */
            function updateProductLinks(container, edition) {
                if (!container || !edition) return;

                const links = container.querySelectorAll('a[href*="/product/"]');
                let updated = 0;

                links.forEach(function(link) {
                    let href = link.getAttribute('href');
                    if (!href) return;

                    try {
                        const url = new URL(href, window.location.origin);
                        url.searchParams.delete('edition');
                        url.searchParams.set('edition', edition);
                        link.setAttribute('href', url.toString());
                        updated++;
                    } catch(e) {
                        // Invalid URL, skip
                    }
                });

                console.log('PMCM: Updated ' + updated + ' product links with edition ' + edition);
                containerEditions.set(container, edition);
            }

            /**
             * Find the edition marker nearest to the clicked element
             */
            function findEditionMarker(clickedElement) {
                // Strategy 1: Check if marker is inside the clicked element
                let marker = clickedElement.querySelector('.pmcm-edition-marker');
                if (marker) return marker;

                // Strategy 2: Check parent containers up to 5 levels
                let parent = clickedElement.parentElement;
                for (let i = 0; i < 5 && parent; i++) {
                    marker = parent.querySelector('.pmcm-edition-marker');
                    if (marker) return marker;
                    parent = parent.parentElement;
                }

                // Strategy 3: Check siblings
                if (clickedElement.parentElement) {
                    const siblings = clickedElement.parentElement.children;
                    for (let sibling of siblings) {
                        if (sibling !== clickedElement) {
                            marker = sibling.querySelector('.pmcm-edition-marker');
                            if (marker) return marker;
                            if (sibling.classList.contains('pmcm-edition-marker')) return sibling;
                        }
                    }
                }

                // Strategy 4: Look in closest Elementor container
                const eContainer = clickedElement.closest('.e-con, .elementor-element, [data-element_type]');
                if (eContainer) {
                    marker = eContainer.querySelector('.pmcm-edition-marker');
                    if (marker) return marker;
                }

                return null;
            }

            /**
             * Find the products container that should be updated
             */
            function findProductsContainer(clickedElement) {
                // Strategy 0: If inside a pmcm-edition-section, find its .toggle-container
                const editionSection = clickedElement.closest('.pmcm-edition-section');
                if (editionSection) {
                    const container = editionSection.querySelector('.toggle-container');
                    if (container) return container;
                }

                // Strategy 1: Look for products class in same parent structure
                let parent = clickedElement.closest('.e-con, .elementor-widget-container, [data-element_type="container"]');

                if (parent) {
                    // Check next siblings at this level
                    let sibling = parent.nextElementSibling;
                    while (sibling) {
                        let container = sibling.querySelector('.<?php echo $products_class; ?>, .products, .woocommerce');
                        if (container) return container;
                        if (sibling.classList.contains('<?php echo $products_class; ?>')) return sibling;
                        sibling = sibling.nextElementSibling;
                    }

                    // Check inside parent's parent
                    if (parent.parentElement) {
                        let container = parent.parentElement.querySelector('.<?php echo $products_class; ?>, .products');
                        if (container) return container;
                    }
                }

                // Strategy 2: Look for Container_to_Show (Elementor toggle pattern)
                const grandParent = clickedElement.closest('.e-con-inner, .e-con');
                if (grandParent) {
                    let container = grandParent.querySelector('.Container_to_Show .<?php echo $products_class; ?>');
                    if (container) return container;

                    container = grandParent.querySelector('.Container_to_Show');
                    if (container) return container;
                }

                // Strategy 3: Find any products container after this element (any column count)
                const allContainers = document.querySelectorAll('.<?php echo $products_class; ?>, .toggle-container, .products');
                for (let container of allContainers) {
                    if (clickedElement.compareDocumentPosition(container) & Node.DOCUMENT_POSITION_FOLLOWING) {
                        return container;
                    }
                }

                return null;
            }

            /**
             * Handle click events on toggle buttons
             */
            function handleToggleClick(e) {
                // Find the edition marker
                const marker = findEditionMarker(e.target);
                if (!marker) {
                    return; // No marker found, not our button
                }

                const edition = marker.getAttribute('data-edition');
                if (!edition) return;

                // Find the products container
                const container = findProductsContainer(e.target);

                if (container) {
                    // Delay to allow the toggle animation to show the container
                    setTimeout(function() {
                        updateProductLinks(container, edition);
                    }, 100);
                } else {
                    // If no container found yet, try again after a longer delay
                    // (for when the container is dynamically shown)
                    setTimeout(function() {
                        const retryContainer = findProductsContainer(e.target);
                        if (retryContainer) {
                            updateProductLinks(retryContainer, edition);
                        }
                    }, 300);
                }
            }

            /**
             * Find the toggle button associated with a marker.
             * Walks up the DOM looking for an ancestor that IS or CONTAINS a toggle button.
             */
            function findToggleForMarker(marker) {
                const SPECIFIC = '.toggle-btn, [data-toggle-target]';
                const GENERIC = SPECIFIC + ', a.elementor-button, button.elementor-button, .e-button, [role="button"], .accordion-toggle';

                let el = marker.parentElement;
                let depth = 0;
                while (el && el !== document.body && depth < 12) {
                    // Marker is literally inside the toggle button
                    if (el.matches && el.matches(SPECIFIC)) return el;
                    // Toggle is a descendant of this ancestor (prefer specific)
                    let inner = el.querySelector(SPECIFIC);
                    if (inner) return inner;
                    // Generic fallback (e.g. plain <a> Enrol button)
                    if (el.matches && el.matches(GENERIC) && el.textContent.trim().length > 0) return el;
                    inner = el.querySelector(GENERIC);
                    if (inner && inner !== marker && !inner.contains(marker.parentElement)) {
                        // Make sure we don't grab a wholly unrelated button further down
                        // by limiting to elements that share a near common ancestor
                        if (inner.textContent && inner.textContent.trim().length > 0) return inner;
                    }
                    el = el.parentElement;
                    depth++;
                }
                return null;
            }

            /**
             * Disable a toggle button that points at a closed slot.
             * labelText: the string to replace the button label with.
             * cssClass: the class to apply (pmcm-toggle-closed or pmcm-toggle-opening-soon).
             */
            function disableClosedToggle(toggle, labelText, cssClass) {
                labelText = labelText || 'Registration Closed';
                cssClass  = cssClass  || 'pmcm-toggle-closed';
                if (!toggle || toggle.dataset.pmcmClosedApplied === '1') return;
                toggle.dataset.pmcmClosedApplied = '1';
                toggle.classList.add(cssClass);
                toggle.setAttribute('aria-disabled', 'true');
                if (toggle.tagName === 'A') {
                    toggle.dataset.pmcmHref = toggle.getAttribute('href') || '';
                    toggle.removeAttribute('href');
                }
                toggle.addEventListener('click', function(ev) {
                    ev.preventDefault();
                    ev.stopImmediatePropagation();
                }, true);
                // Replace inner label text while preserving icon SVGs
                const labelSpan = toggle.querySelector('span:not(.pmcm-edition-marker)');
                if (labelSpan && !labelSpan.dataset.pmcmOriginalLabel) {
                    labelSpan.dataset.pmcmOriginalLabel = labelSpan.textContent;
                    labelSpan.textContent = labelText;
                }
            }

            function applyClosedMarkers() {
                document.querySelectorAll('.pmcm-edition-marker[data-closed="1"]').forEach(function(marker) {
                    const toggle = findToggleForMarker(marker);
                    if (toggle) disableClosedToggle(toggle, 'Registration Closed', 'pmcm-toggle-closed');
                });
                document.querySelectorAll('.pmcm-edition-marker[data-opening-soon="1"]').forEach(function(marker) {
                    const toggle = findToggleForMarker(marker);
                    if (toggle) disableClosedToggle(toggle, 'Opening Soon', 'pmcm-toggle-opening-soon');
                });
            }

            /**
             * Initialize - attach click handlers
             */
            function init() {
                // Disable any toggle buttons whose marker is closed
                applyClosedMarkers();

                // Listen for clicks on common toggle button selectors
                document.addEventListener('click', function(e) {
                    // If the click is inside a disabled toggle, swallow it
                    const closedToggle = e.target.closest('.pmcm-toggle-closed, .pmcm-toggle-opening-soon');
                    if (closedToggle) {
                        e.preventDefault();
                        e.stopImmediatePropagation();
                        return;
                    }
                    // Check if clicked element or its ancestors might be a toggle button
                    const target = e.target.closest('a, button, .elementor-button, .e-button, [role="button"], .toggle-btn, .accordion-toggle');
                    if (target) {
                        handleToggleClick({ target: target });
                    }
                }, true);

                // Pre-apply edition to all product containers on load (current and next)
                document.querySelectorAll('.pmcm-edition-marker').forEach(function(marker) {
                    const edition = marker.getAttribute('data-edition');
                    if (!edition) return;

                    const container = findProductsContainer(marker);
                    if (container && !containerEditions.has(container)) {
                        updateProductLinks(container, edition);
                    }
                });

                // Apply early bird price classes based on chip presence.
                // If [pmcm_edition_ordinal early_bird="1"] rendered a .pmcm-early-bird-chip
                // inside a .pmcm-edition-section, that section gets .pmcm-eb-active (show sale price).
                // Sections without the chip get .pmcm-eb-inactive (regular price only).
                document.querySelectorAll('.pmcm-edition-section').forEach(function(section) {
                    const hasChip = !!section.querySelector('.pmcm-early-bird-chip');
                    const cls     = hasChip ? 'pmcm-eb-active' : 'pmcm-eb-inactive';
                    section.classList.remove('pmcm-eb-active', 'pmcm-eb-inactive');
                    section.classList.add(cls);

                    // Also stamp the inner toggle-container / products grid directly
                    const inner = section.querySelector('.toggle-container, .products');
                    if (inner) {
                        inner.classList.remove('pmcm-eb-active', 'pmcm-eb-inactive');
                        inner.classList.add(cls);
                    }
                });
            }

            // Initialize when DOM is ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', init);
            } else {
                init();
            }

            // Re-initialize for Elementor
            if (typeof jQuery !== 'undefined') {
                jQuery(window).on('elementor/frontend/init', function() {
                    setTimeout(init, 300);
                });
            }

            // Watch for dynamically added markers
            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    mutation.addedNodes.forEach(function(node) {
                        if (node.nodeType === 1) {
                            const markers = node.querySelectorAll ? node.querySelectorAll('.pmcm-edition-marker') : [];
                            if (markers.length > 0 || node.classList?.contains('pmcm-edition-marker')) {
                                // New marker added, might need to update
                                setTimeout(init, 100);
                            }
                        }
                    });
                });
            });

            observer.observe(document.body, { childList: true, subtree: true });

            // Expose for manual use
            window.pmcmApplyEdition = function(edition, containerSelector) {
                const container = document.querySelector(containerSelector);
                if (container) {
                    updateProductLinks(container, edition);
                }
            };

        })();
        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * Get exam dates text for a course slot
     * Usage: [pmcm_exam_dates course="frcs" slot="current|next"]
     * Output: the free-text exam dates string set in admin, or "TBA" if empty
     */
    public static function exam_dates($atts) {
        $atts = shortcode_atts([
            'course' => '',
            'slot'   => 'current',
        ], $atts, 'pmcm_exam_dates');

        $course_slug = sanitize_text_field($atts['course']);
        $slot        = sanitize_text_field($atts['slot']);

        if (empty($course_slug) || !isset(PMCM_Core::get_courses()[$course_slug])) {
            return '';
        }

        $prefix = PMCM_Core::get_courses()[$course_slug]['settings_prefix'];
        $text   = $slot === 'next'
            ? get_option($prefix . 'next_exam_dates', '')
            : get_option($prefix . 'exam_dates', '');

        if (empty($text)) {
            return '<div class="pmcm-exam-dates">' . __('TBA', 'prepmedico-course-management') . '</div>';
        }

        return '<div class="pmcm-exam-dates">' . wp_kses_post($text) . '</div>';
    }
}
