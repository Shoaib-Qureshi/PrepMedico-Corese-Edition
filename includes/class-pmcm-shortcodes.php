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

        // Elementor table shortcodes for custom edition tables
        add_shortcode('pmcm_edition_url', [__CLASS__, 'edition_url']);
        add_shortcode('pmcm_edition_ordinal', [__CLASS__, 'edition_ordinal']);
        add_shortcode('pmcm_edition_dates', [__CLASS__, 'edition_dates']);
        add_shortcode('pmcm_edition_status', [__CLASS__, 'edition_status']);
        add_shortcode('pmcm_edition_button', [__CLASS__, 'edition_button']);
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

        $course = PMCM_Core::get_courses()[$course_slug];
        $prefix = $course['settings_prefix'];
        $edition = get_option($prefix . 'current_edition', 1);

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

        $course = PMCM_Core::get_courses()[$course_slug];
        $prefix = $course['settings_prefix'];

        $edition = get_option($prefix . 'current_edition', 1);
        $start = get_option($prefix . 'edition_start', '');
        $end = get_option($prefix . 'edition_end', '');

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

        $course = PMCM_Core::get_courses()[$course_slug];
        $prefix = $course['settings_prefix'];
        $edition = get_option($prefix . 'current_edition', 1);

        return '<span class="wcem-edition-number">' . esc_html(PMCM_Core::get_ordinal($edition)) . '</span>';
    }

    /**
     * Shortcode: Display registration status
     * Usage: [registration_status course="frcs"]
     */
    public static function registration_status($atts) {
        $atts = shortcode_atts(['course' => ''], $atts, 'registration_status');
        $course_slug = sanitize_text_field($atts['course']);

        $status_data = self::get_registration_status($course_slug);

        if (!$status_data) {
            return '';
        }

        return '<span class="wcem-registration-status ' . esc_attr($status_data['class']) . '">' . esc_html($status_data['label']) . '</span>';
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

        $early_bird_enabled = get_option($prefix . 'early_bird_enabled', 'no');
        $early_bird_start = get_option($prefix . 'early_bird_start', '');
        $early_bird_end = get_option($prefix . 'early_bird_end', '');

        if ($early_bird_enabled !== 'yes' || empty($early_bird_end)) {
            return '';
        }

        $today = current_time('Y-m-d');
        $today_timestamp = strtotime($today);

        $start_ok = empty($early_bird_start) || $today_timestamp >= strtotime($early_bird_start);
        $end_ok = $today_timestamp <= strtotime($early_bird_end);

        if (!$start_ok || !$end_ok) {
            return '';
        }

        $formatted_date = date('F j, Y', strtotime($early_bird_end));

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

        $course = PMCM_Core::get_courses()[$course_slug];
        $prefix = $course['settings_prefix'];

        $edition = get_option($prefix . 'current_edition', 1);
        $start = get_option($prefix . 'edition_start', '');
        $end = get_option($prefix . 'edition_end', '');

        $status_data = self::get_registration_status($course_slug);

        $output = '<div class="wcem-course-registration-info">';
        $output .= '<p class="wcem-edition-title"><strong>' . esc_html(PMCM_Core::get_ordinal($edition) . ' - ' . __('Current Edition', 'prepmedico-course-management')) . '</strong></p>';

        if ($atts['show_dates'] === 'yes' && $start && $end) {
            $output .= '<p class="wcem-edition-dates">' . date('M j, Y', strtotime($start)) . ' - ' . date('M j, Y', strtotime($end)) . '</p>';
        }

        if ($status_data) {
            $output .= '<p class="wcem-registration-status ' . esc_attr($status_data['class']) . '">' . esc_html($status_data['label']) . '</p>';
        }

        if ($status_data && $status_data['status'] === 'early_bird') {
            $output .= do_shortcode('[early_bird_message course="' . esc_attr($course_slug) . '"]');
        }

        $output .= '</div>';
        return $output;
    }

    /**
     * Get registration status for a course
     * Logic:
     * - End Date passed → "Registration Opening Soon" (cron auto-increments edition)
     * - Early Bird enabled & within dates → "Early Bird Registration Open"
     * - Early Bird End == Today (and course end date still far) → "Registration is Live"
     * - After Early Bird but before course end → "Registration is Live"
     * - Before start date → "Registration Opening Soon"
     * - Within start and end dates → "Registration is Live"
     */
    public static function get_registration_status($course_slug) {
        if (!isset(PMCM_Core::get_courses()[$course_slug])) {
            return null;
        }

        $course = PMCM_Core::get_courses()[$course_slug];
        $prefix = $course['settings_prefix'];

        $today = current_time('Y-m-d');
        $today_timestamp = strtotime($today);

        $start = get_option($prefix . 'edition_start', '');
        $end = get_option($prefix . 'edition_end', '');
        $early_bird_enabled = get_option($prefix . 'early_bird_enabled', 'no');
        $early_bird_start = get_option($prefix . 'early_bird_start', '');
        $early_bird_end = get_option($prefix . 'early_bird_end', '');

        $start_timestamp = !empty($start) ? strtotime($start) : null;
        $end_timestamp = !empty($end) ? strtotime($end) : null;
        $early_bird_start_timestamp = !empty($early_bird_start) ? strtotime($early_bird_start) : null;
        $early_bird_end_timestamp = !empty($early_bird_end) ? strtotime($early_bird_end) : null;

        // 1. Check if Current Edition End Date has passed → Registration Opening Soon
        // (Cron will auto-increment the edition number)
        if ($end_timestamp && $today_timestamp > $end_timestamp) {
            return [
                'status' => 'opening_soon',
                'label' => __('Registration Opening Soon', 'prepmedico-course-management'),
                'class' => 'wcem-status-upcoming'
            ];
        }

        // 2. Check Early Bird
        if ($early_bird_enabled === 'yes' && $early_bird_end_timestamp) {
            $eb_start_ok = !$early_bird_start_timestamp || $today_timestamp >= $early_bird_start_timestamp;
            $eb_end_ok = $today_timestamp < $early_bird_end_timestamp;

            // Within Early Bird period
            if ($eb_start_ok && $eb_end_ok) {
                return [
                    'status' => 'early_bird',
                    'label' => __('Early Bird Registration Open', 'prepmedico-course-management'),
                    'early_bird_end' => $early_bird_end,
                    'class' => 'wcem-status-early-bird'
                ];
            }

            // Early Bird End Date == Today OR Early Bird has ended but course end date is still far
            // → Registration is Live (early bird closed, course edition still active)
            if ($today_timestamp >= $early_bird_end_timestamp && $end_timestamp && $today_timestamp < $end_timestamp) {
                return [
                    'status' => 'live',
                    'label' => __('Registration is Live', 'prepmedico-course-management'),
                    'class' => 'wcem-status-live'
                ];
            }
        }

        // 3. If dates not set (after increment), show Opening Soon
        if (!$start_timestamp || !$end_timestamp) {
            return [
                'status' => 'opening_soon',
                'label' => __('Registration Opening Soon', 'prepmedico-course-management'),
                'class' => 'wcem-status-upcoming'
            ];
        }

        // 4. Check if before Current Edition Start Date
        if ($today_timestamp < $start_timestamp) {
            return [
                'status' => 'opening_soon',
                'label' => __('Registration Opening Soon', 'prepmedico-course-management'),
                'class' => 'wcem-status-upcoming'
            ];
        }

        // 5. Registration is Live (within start and end dates)
        return [
            'status' => 'live',
            'label' => __('Registration is Live', 'prepmedico-course-management'),
            'class' => 'wcem-status-live'
        ];
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
        $atts = shortcode_atts([
            'course' => '',
            'slot' => 'current'
        ], $atts, 'pmcm_edition_ordinal');

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
            if ($next_enabled !== 'yes') {
                return '';
            }
            $edition = intval(get_option($prefix . 'next_edition', 0));
            if ($edition === 0) {
                return '';
            }
        } else {
            $edition = intval(get_option($prefix . 'current_edition', 1));
        }

        return PMCM_Core::get_ordinal($edition);
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
            if ($next_enabled !== 'yes') {
                return '';
            }
            $start = get_option($prefix . 'next_start', '');
            $end = get_option($prefix . 'next_end', '');
        } else {
            $start = get_option($prefix . 'edition_start', '');
            $end = get_option($prefix . 'edition_end', '');
        }

        if ($format === 'start') {
            return $start ? date_i18n('F j, Y', strtotime($start)) : '';
        } elseif ($format === 'end') {
            return $end ? date_i18n('F j, Y', strtotime($end)) : '';
        } else {
            // Range format
            if (empty($start) || empty($end)) {
                return __('Dates TBA', 'prepmedico-course-management');
            }
            return date_i18n('F j, Y', strtotime($start)) . ' - ' . date_i18n('F j, Y', strtotime($end));
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
            'output' => 'text' // text or class
        ], $atts, 'pmcm_edition_status');

        $course_slug = sanitize_text_field($atts['course']);
        $slot = sanitize_text_field($atts['slot']);
        $output = sanitize_text_field($atts['output']);

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

        if ($slot === 'next') {
            $enabled = get_option($prefix . 'next_enabled', 'no');
            if ($enabled !== 'yes') {
                return $output === 'class' ? 'pmcm-disabled' : 'disabled';
            }
            $start = get_option($prefix . 'next_start', '');
            $end = get_option($prefix . 'next_end', '');
        } else {
            $start = get_option($prefix . 'edition_start', '');
            $end = get_option($prefix . 'edition_end', '');
        }

        // Check status: closed (end date passed)
        if (!empty($end) && $today_timestamp > strtotime($end)) {
            return $output === 'class' ? 'pmcm-closed' : 'closed';
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
        if ($slot === 'next') {
            $enabled = get_option($prefix . 'next_enabled', 'no');
            if ($enabled !== 'yes') {
                return ''; // Next slot not enabled, don't show button
            }
            $edition = intval(get_option($prefix . 'next_edition', 0));
            $start = get_option($prefix . 'next_start', '');
            $end = get_option($prefix . 'next_end', '');
        } else {
            $edition = intval(get_option($prefix . 'current_edition', 1));
            $start = get_option($prefix . 'edition_start', '');
            $end = get_option($prefix . 'edition_end', '');
        }

        // Build URL
        $url = home_url('/product/' . $product_slug . '/?edition=' . $edition);

        // Determine button state
        $is_closed = !empty($end) && $today_timestamp > strtotime($end);
        $is_upcoming = !empty($start) && $today_timestamp < strtotime($start);

        // Build button classes
        $classes = ['pmcm-edition-btn'];
        if ($custom_class) {
            $classes[] = $custom_class;
        }

        if ($is_closed) {
            $classes[] = 'pmcm-closed';
        } elseif ($is_upcoming) {
            $classes[] = 'pmcm-upcoming';
        } else {
            $classes[] = 'pmcm-open';
        }

        // Build inline styles for disabled state
        $style = '';
        if ($is_closed) {
            $style = 'pointer-events: none; opacity: 0.5; cursor: not-allowed;';
        }

        // Generate button HTML
        $output = '<a href="' . esc_url($url) . '" class="' . esc_attr(implode(' ', $classes)) . '"';
        if ($style) {
            $output .= ' style="' . esc_attr($style) . '"';
        }
        $output .= '>' . esc_html($text) . '</a>';

        return $output;
    }
}
