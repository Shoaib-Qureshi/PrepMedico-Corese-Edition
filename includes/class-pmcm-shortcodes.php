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
        add_shortcode('pmcm_edition_number_raw', [__CLASS__, 'edition_number_raw']);
        add_shortcode('pmcm_edition_product_script', [__CLASS__, 'edition_product_script']);

        // New simplified approach - no data attributes needed
        add_shortcode('pmcm_edition_marker', [__CLASS__, 'edition_marker']);
        add_shortcode('pmcm_edition_products_script', [__CLASS__, 'edition_products_script']);
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
            $output .= self::render_registration_status_html($status_data, $course_slug, 'p');
        }

        if ($status_data && $status_data['status'] === 'early_bird') {
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

        $lottie_markup = self::get_registration_status_lottie($status, $course_slug);

        if ($lottie_markup !== '') {
            return '<' . $tag . ' class="wcem-registration-status ' . esc_attr($status_class) . '">'
                . '<span class="wcem-registration-status-inner" style="display:inline-flex;align-items:center;gap:8px;">'
                . $lottie_markup
                . '<span class="wcem-registration-status-label">' . esc_html($status_label) . '</span>'
                . '</span>'
                . '</' . $tag . '>';
        }

        return '<' . $tag . ' class="wcem-registration-status ' . esc_attr($status_class) . '">' . esc_html($status_label) . '</' . $tag . '>';
    }

    /**
     * Get Lottie icon markup for registration status
     */
    private static function get_registration_status_lottie($status, $course_slug) {
        // Keep this targeted to the requested shortcode use case.
        if (strtolower((string) $course_slug) !== 'frcs') {
            return '';
        }

        $file_map = [
            'live' => 'Registration live dot.lottie',
            'early_bird' => 'Early bird dot.lottie',
        ];

        if (!isset($file_map[$status])) {
            return '';
        }

        $file_name = $file_map[$status];
        $file_path = PMCM_PLUGIN_DIR . 'assets/lottie/' . $file_name;

        if (!file_exists($file_path)) {
            return '';
        }

        self::enqueue_lottie_player();

        $file_url = PMCM_PLUGIN_URL . 'assets/lottie/' . rawurlencode($file_name);

        return '<span class="wcem-status-lottie" aria-hidden="true" style="display:inline-flex;width:18px;height:18px;flex-shrink:0;">'
            . '<dotlottie-wc src="' . esc_url($file_url) . '" speed="1" autoplay loop style="width:18px;height:18px;display:block;"></dotlottie-wc>'
            . '</span>';
    }

    /**
     * Enqueue dotLottie web component for frontend rendering
     */
    private static function enqueue_lottie_player() {
        wp_register_script(
            'pmcm-dotlottie-player',
            'https://unpkg.com/@lottiefiles/dotlottie-wc@0.6.16/dist/dotlottie-wc.js',
            [],
            null,
            true
        );
        wp_enqueue_script('pmcm-dotlottie-player');

        // Add type="module" attribute via filter (wp_script_add_data 'type' is unreliable)
        add_filter('script_loader_tag', [__CLASS__, 'add_module_type_to_lottie_script'], 10, 3);
    }

    /**
     * Add type="module" to the dotLottie player script tag
     */
    public static function add_module_type_to_lottie_script($tag, $handle, $src) {
        if ('pmcm-dotlottie-player' !== $handle) {
            return $tag;
        }
        return str_replace('<script ', '<script type="module" ', $tag);
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
                    // Must also be before course start date
                    $before_course = empty($course_start) || $today < strtotime($course_start);

                    if ($eb_start_ok && $eb_end_ok && $before_course) {
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
            // Check current edition early bird
            // Early bird is only valid BEFORE the course starts
            $eb_enabled = get_option($prefix . 'early_bird_enabled', 'no');
            $eb_start = get_option($prefix . 'early_bird_start', '');
            $eb_end = get_option($prefix . 'early_bird_end', '');
            $course_start = get_option($prefix . 'edition_start', '');

            if ($eb_enabled === 'yes' && !empty($eb_end)) {
                $today = strtotime(current_time('Y-m-d'));
                $eb_start_ok = empty($eb_start) || $today >= strtotime($eb_start);
                $eb_end_ok = $today <= strtotime($eb_end);
                // Must also be before course start date
                $before_course = empty($course_start) || $today < strtotime($course_start);

                if ($eb_start_ok && $eb_end_ok && $before_course) {
                    $early_bird_active = true;
                }
            }
        }

        if ($early_bird_active) {
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
            if ($enabled === 'yes') {
                $start = get_option($prefix . 'next_start', '');
                $end = get_option($prefix . 'next_end', '');
            } else {
                $start = '';
                $end = '';
            }
            // If dates not set (either not enabled or dates empty), return dates-tba status
            if (empty($start) || empty($end)) {
                return $output === 'class' ? 'pmcm-dates-tba' : 'dates-tba';
            }
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

        // Determine button state
        $is_closed = !empty($end) && $today_timestamp > strtotime($end);
        $is_upcoming = !empty($start) && $today_timestamp < strtotime($start);

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
        } else {
            $classes[] = 'pmcm-open';
        }

        // Build inline styles for disabled state
        $style = '';
        if ($is_closed || $dates_unavailable) {
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
                // Find all buttons with edition data
                const buttons = document.querySelectorAll('<?php echo $button_selector; ?>');

                buttons.forEach(function(button) {
                    // Skip if already initialized
                    if (button.dataset.pmcmInitialized === 'true') return;
                    button.dataset.pmcmInitialized = 'true';

                    button.addEventListener('click', function(e) {
                        const edition = this.dataset.pmcmEdition;
                        if (!edition) return;

                        // Find the products container
                        const container = findProductContainer(this);
                        if (!container) {
                            console.warn('PMCM: Could not find products container for button', this);
                            return;
                        }

                        // Update links if not already updated with this edition
                        if (updatedContainers.get(container) !== edition) {
                            // Small delay to allow container to become visible
                            setTimeout(function() {
                                updateProductLinks(container, edition);
                            }, 50);
                        }
                    });
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

        // Output hidden marker with edition number
        return '<span class="pmcm-edition-marker" data-edition="' . esc_attr($edition) . '" data-slot="' . esc_attr($slot) . '" style="display:none !important; visibility:hidden; position:absolute; pointer-events:none;"></span>';
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

                // Strategy 3: Find any products container on the page after this element
                const allContainers = document.querySelectorAll('.<?php echo $products_class; ?>, .products.columns-3, .products.columns-4');
                for (let container of allContainers) {
                    // Check if this container comes after the clicked element in DOM order
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
             * Initialize - attach click handlers
             */
            function init() {
                // Listen for clicks on common toggle button selectors
                document.addEventListener('click', function(e) {
                    // Check if clicked element or its ancestors might be a toggle button
                    const target = e.target.closest('a, button, .elementor-button, .e-button, [role="button"], .toggle-btn, .accordion-toggle');
                    if (target) {
                        handleToggleClick({ target: target });
                    }
                }, true);

                // Also watch for markers that become visible (for pre-loaded content)
                const markers = document.querySelectorAll('.pmcm-edition-marker');
                markers.forEach(function(marker) {
                    const edition = marker.getAttribute('data-edition');
                    const slot = marker.getAttribute('data-slot');

                    // If this is a "current" slot marker and it's the first one visible,
                    // pre-apply the edition to any visible products
                    if (slot === 'current' && edition) {
                        const container = findProductsContainer(marker);
                        if (container && !containerEditions.has(container)) {
                            // Check if container is visible
                            if (container.offsetParent !== null) {
                                updateProductLinks(container, edition);
                            }
                        }
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
}
