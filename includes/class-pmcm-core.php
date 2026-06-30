<?php
/**
 * PMCM Core Class
 * Contains course definitions and helper methods
 * Now supports dynamic course configuration from database
 *
 * @package PrepMedico_Course_Management
 */

if (!defined('ABSPATH')) {
    exit;
}

class PMCM_Core {

    /**
     * Cache for courses to avoid repeated database calls
     */
    private static $courses_cache = null;
    private static $child_map_cache = null;

    /**
     * ASiT / partner discount modes
     */
    const ASIT_MODE_NONE = 'none';
    const ASIT_MODE_EARLY_BIRD_ONLY = 'early_bird_only';
    const ASIT_MODE_ALWAYS = 'always';

    /**
     * Supported academic partners
     */
    const PARTNERS = ['asit', 'bomss', 'rouleaux'];

    /**
     * Default courses - used as fallback and for initial migration
     */
    private static $default_courses = [
        'frcophth-part-1' => [
            'name' => 'FRCOphth Part 1',
            'category_slug' => 'frcophth-part-1',
            'settings_prefix' => '_frcophth_p1_',
            'fluentcrm_tag' => 'FRCOphth-Part1',
            'fluentcrm_field' => 'frcophth_p1_edition',
            'edition_management' => true,
            'asit_eligible' => false,
            'asit_discount_mode' => 'none',
            'asit_early_bird_discount' => 0,
            'asit_normal_discount' => 0,
            'asit_show_field' => false,
            'children' => ['frcophth-individual-weekend-viva-part-1']
        ],
        'frcophth-part-2' => [
            'name' => 'FRCOphth Part 2',
            'category_slug' => 'frcophth-part-2',
            'settings_prefix' => '_frcophth_p2_',
            'fluentcrm_tag' => 'FRCOphth-Part2',
            'fluentcrm_field' => 'frcophth_p2_edition',
            'edition_management' => true,
            'asit_eligible' => false,
            'asit_discount_mode' => 'none',
            'asit_early_bird_discount' => 0,
            'asit_normal_discount' => 0,
            'asit_show_field' => false,
            'children' => ['frcophth-individual-weekend-viva-part-2']
        ],
        'frcs' => [
            'name' => 'FRCS',
            'category_slug' => 'frcs',
            'settings_prefix' => '_frcs_',
            'fluentcrm_tag' => 'FRCS',
            'fluentcrm_field' => 'frcs_edition',
            'edition_management' => true,
            'asit_eligible' => true,
            'asit_discount_mode' => 'early_bird_only',
            'asit_edition_scope' => 'both',
            'asit_early_bird_discount' => 5,
            'asit_normal_discount' => 0,
            'asit_show_field' => true,
            'bomss_eligible' => true,
            'bomss_discount_mode' => 'early_bird_only',
            'bomss_early_bird_discount' => 10,
            'bomss_normal_discount' => 0,
            'bomss_show_field' => true,
            'rouleaux_eligible' => false,
            'rouleaux_discount_mode' => 'none',
            'rouleaux_early_bird_discount' => 0,
            'rouleaux_normal_discount' => 0,
            'rouleaux_show_field' => false,
            'children' => ['frcs-rapid-review-lecture-series', 'mock-viva', 'sba-q-bank', 'speciality-based-weekend-viva-sessions']
        ],
        'frcs-vasc' => [
            'name' => 'FRCS VASC',
            'category_slug' => 'frcs-vasc',
            'settings_prefix' => '_frcs_vasc_',
            'fluentcrm_tag' => 'FRCS-VASC',
            'fluentcrm_field' => 'frcs_vasc_edition',
            'edition_management' => true,
            'asit_eligible' => true,
            'asit_discount_mode' => 'early_bird_only',
            'asit_edition_scope' => 'both',
            'asit_early_bird_discount' => 5,
            'asit_normal_discount' => 0,
            'asit_show_field' => true,
            'bomss_eligible' => false,
            'bomss_discount_mode' => 'none',
            'bomss_early_bird_discount' => 0,
            'bomss_normal_discount' => 0,
            'bomss_show_field' => false,
            'rouleaux_eligible' => true,
            'rouleaux_discount_mode' => 'early_bird_only',
            'rouleaux_early_bird_discount' => 10,
            'rouleaux_normal_discount' => 0,
            'rouleaux_show_field' => true,
            'children' => ['frcs-vasc-individual-weekend-viva-sessions', 'frcs-vasc-library-subscription']
        ],
        'library-subscription' => [
            'name' => 'Library Subscription',
            'category_slug' => 'library-subscription',
            'settings_prefix' => '_library_sub_',
            'fluentcrm_tag' => 'Library-Subscription',
            'fluentcrm_field' => 'library_sub_edition',
            'edition_management' => false,
            'asit_eligible' => true,
            'asit_discount_mode' => 'always',
            'asit_early_bird_discount' => 10,
            'asit_normal_discount' => 10,
            'asit_show_field' => true,
            'bomss_eligible' => true,
            'bomss_discount_mode' => 'always',
            'bomss_early_bird_discount' => 10,
            'bomss_normal_discount' => 10,
            'bomss_show_field' => true,
            'rouleaux_eligible' => false,
            'rouleaux_discount_mode' => 'none',
            'rouleaux_early_bird_discount' => 0,
            'rouleaux_normal_discount' => 0,
            'rouleaux_show_field' => false,
            'children' => []
        ],
        'scfhs' => [
            'name' => 'SCFHS',
            'category_slug' => 'scfhs',
            'settings_prefix' => '_scfhs_',
            'fluentcrm_tag' => 'SCFHS',
            'fluentcrm_field' => 'scfhs_edition',
            'edition_management' => true,
            'asit_eligible' => false,
            'asit_discount_mode' => 'none',
            'asit_early_bird_discount' => 0,
            'asit_normal_discount' => 0,
            'asit_show_field' => false,
            'children' => ['scfhs-participation-for-1-topic']
        ]
    ];

    /**
     * Clear the cache when courses are updated
     */
    public static function clear_cache() {
        self::$courses_cache = null;
        self::$child_map_cache = null;
    }

    /**
     * Get all courses - loads from database with fallback to defaults
     */
    public static function get_courses() {
        if (self::$courses_cache !== null) {
            return self::$courses_cache;
        }

        $courses = get_option('pmcm_course_mappings', null);

        if ($courses === null || empty($courses)) {
            // No database config yet - use defaults
            $courses = self::$default_courses;
        }

        self::$courses_cache = apply_filters('pmcm_courses', $courses);
        return self::$courses_cache;
    }

    /**
     * Get default courses (for migration/reset)
     */
    public static function get_default_courses() {
        return self::$default_courses;
    }

    /**
     * Get only courses with edition management enabled
     */
    public static function get_edition_managed_courses() {
        return array_filter(self::get_courses(), function($course) {
            return isset($course['edition_management']) && $course['edition_management'] === true;
        });
    }

    /**
     * Get courses eligible for ASiT discount
     */
    public static function get_asit_eligible_courses() {
        return array_filter(self::get_courses(), function($course) {
            return isset($course['asit_eligible']) && $course['asit_eligible'] === true;
        });
    }

    /**
     * Get courses that should show ASiT field on checkout
     */
    public static function get_asit_visible_courses() {
        return array_filter(self::get_courses(), function($course) {
            return isset($course['asit_show_field']) && $course['asit_show_field'] === true;
        });
    }

    /**
     * Get ASiT discount settings for a specific course
     * Returns discount percentage based on early bird status
     */
    public static function get_asit_discount_for_course($course_slug, $edition_slot = 'current') {
        $courses = self::get_courses();
        $course = isset($courses[$course_slug]) ? $courses[$course_slug] : null;

        if (!$course) {
            return ['discount' => 0, 'is_eligible' => false, 'show_field' => false];
        }

        $mode = isset($course['asit_discount_mode']) ? $course['asit_discount_mode'] : 'none';
        $eb_discount = isset($course['asit_early_bird_discount']) ? intval($course['asit_early_bird_discount']) : 0;
        $normal_discount = isset($course['asit_normal_discount']) ? intval($course['asit_normal_discount']) : 0;
        $show_field = isset($course['asit_show_field']) ? $course['asit_show_field'] : false;

        if ($mode === 'none') {
            return ['discount' => 0, 'is_eligible' => false, 'show_field' => false, 'mode' => $mode];
        }

        if ($mode === 'always') {
            // Always give discount - use normal_discount
            return ['discount' => $normal_discount, 'is_eligible' => true, 'show_field' => $show_field, 'mode' => $mode];
        }

        if ($mode === 'early_bird_only') {
            $scope = self::normalize_asit_edition_scope(
                isset($course['asit_edition_scope']) ? $course['asit_edition_scope'] : '',
                $mode
            );
            $next_eb = self::is_next_edition_early_bird_active($course_slug);
            $current_eb = self::is_course_early_bird_active($course_slug);

            if ($scope === 'current') {
                $is_early_bird = ($edition_slot === 'current') && $current_eb;
            } elseif ($scope === 'next') {
                $is_early_bird = ($edition_slot === 'next') && $next_eb;
            } else { // 'both'
                $is_early_bird = ($edition_slot === 'next') ? $next_eb : $current_eb;
            }

            if ($is_early_bird) {
                return ['discount' => $eb_discount, 'is_eligible' => true, 'show_field' => $show_field, 'mode' => $mode];
            }
            // Not early bird - no discount, but still show field (user can enter number but won't get discount)
            return ['discount' => 0, 'is_eligible' => false, 'show_field' => $show_field, 'mode' => $mode];
        }

        return ['discount' => 0, 'is_eligible' => false, 'show_field' => false, 'mode' => $mode];
    }

    /**
     * Normalize the ASiT edition scope.
     * Legacy installs had no scope field, so early bird discounts should apply to both slots by default.
     */
    public static function normalize_asit_edition_scope($scope, $mode = self::ASIT_MODE_NONE) {
        if ($mode !== self::ASIT_MODE_EARLY_BIRD_ONLY) {
            return 'both';
        }

        return in_array($scope, ['current', 'next', 'both'], true) ? $scope : 'both';
    }

    /**
     * Check if a specific course has early bird active
     */
    public static function is_course_early_bird_active($course_slug) {
        $courses = self::get_courses();
        if (!isset($courses[$course_slug])) {
            return false;
        }

        $course = $courses[$course_slug];
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

        return false;
    }

    /**
     * Check if a specific course has next edition early bird active
     */
    public static function is_next_edition_early_bird_active($course_slug) {
        $courses = self::get_courses();
        if (!isset($courses[$course_slug])) return false;

        $course = $courses[$course_slug];
        $prefix = $course['settings_prefix'];

        if (get_option($prefix . 'next_enabled', 'no') !== 'yes') return false;

        $eb_enabled = get_option($prefix . 'next_early_bird_enabled', 'no');
        $eb_start   = get_option($prefix . 'next_early_bird_start', '');
        $eb_end     = get_option($prefix . 'next_early_bird_end', '');
        $today      = current_time('Y-m-d');

        if ($eb_enabled === 'yes' && !empty($eb_end)) {
            $start_ok = empty($eb_start) || strtotime($today) >= strtotime($eb_start);
            $end_ok   = strtotime($today) <= strtotime($eb_end);
            return $start_ok && $end_ok;
        }
        return false;
    }

    /**
     * Get the ASiT course config for a product
     * Returns the parent course's ASiT settings
     * Supports per-course product-level filtering
     */
    public static function get_asit_config_for_product($product_id, $edition_slot = 'current') {
        $categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'slugs']);
        $child_map = self::get_child_to_parent_map();
        $courses = self::get_courses();

        foreach ($categories as $cat_slug) {
            // Check if it's a parent course
            if (isset($courses[$cat_slug])) {
                return self::check_course_product_eligibility($cat_slug, $product_id, $courses[$cat_slug], false, $edition_slot);
            }
            // Check if it's a child category
            if (isset($child_map[$cat_slug])) {
                $parent_slug = $child_map[$cat_slug];
                if (isset($courses[$parent_slug])) {
                    return self::check_course_product_eligibility($parent_slug, $product_id, $courses[$parent_slug], true, $edition_slot);
                }
            }
        }

        return ['discount' => 0, 'is_eligible' => false, 'show_field' => false, 'mode' => 'none'];
    }

    /**
     * Check if a product is eligible for ASiT discount based on course settings
     * Handles per-course product-level filtering
     */
    private static function check_course_product_eligibility($course_slug, $product_id, $course, $is_child_category, $edition_slot = 'current') {
        $mode = isset($course['asit_discount_mode']) ? $course['asit_discount_mode'] : 'none';

        // If mode is none, no discount
        if ($mode === 'none') {
            return ['discount' => 0, 'is_eligible' => false, 'show_field' => false, 'mode' => 'none'];
        }

        // Check if course has product-level filtering enabled
        $has_product_filter = isset($course['asit_product_filter']) && $course['asit_product_filter'] === true;

        if ($has_product_filter) {
            $selected_products = isset($course['asit_selected_products']) ? (array) $course['asit_selected_products'] : [];
            $include_children = isset($course['asit_include_children']) ? (bool) $course['asit_include_children'] : false;

            // Check if product is eligible
            $product_in_list = in_array($product_id, array_map('intval', $selected_products), true);
            $child_included = $is_child_category && $include_children;

            if (!$product_in_list && !$child_included) {
                // Product not selected and not in included children
                return ['discount' => 0, 'is_eligible' => false, 'show_field' => false, 'mode' => 'none'];
            }
        }

        // Product is eligible, return the discount config
        return self::get_asit_discount_for_course($course_slug, $edition_slot);
    }

    /**
     * Build child to parent mapping dynamically from course children arrays
     */
    public static function get_child_to_parent_map() {
        if (self::$child_map_cache !== null) {
            return self::$child_map_cache;
        }

        $child_map = [];
        foreach (self::get_courses() as $parent_slug => $course) {
            if (!empty($course['children']) && is_array($course['children'])) {
                foreach ($course['children'] as $child_slug) {
                    $child_map[$child_slug] = $parent_slug;
                }
            }
        }

        self::$child_map_cache = apply_filters('pmcm_child_to_parent_map', $child_map);
        return self::$child_map_cache;
    }

    /**
     * Get the array of category slugs marked closed for a course's current edition.
     */
    public static function get_closed_categories_current($course_slug) {
        $courses = self::get_courses();
        if (!isset($courses[$course_slug])) return [];
        $prefix = $courses[$course_slug]['settings_prefix'];
        $list = json_decode((string) get_option($prefix . 'closed_categories_current', '[]'), true);
        return is_array($list) ? $list : [];
    }

    /**
     * Check whether a product belongs to a closed category for a given course's current edition.
     */
    public static function is_product_in_closed_category_current($product_id, $course_slug) {
        $closed = self::get_closed_categories_current($course_slug);
        if (empty($closed)) return false;
        $cats = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'slugs']);
        if (is_wp_error($cats) || empty($cats)) return false;
        return (bool) array_intersect($cats, $closed);
    }

    /**
     * Resolve a product slug to a product ID (used by Elementor shortcodes that pass slugs).
     */
    public static function get_product_id_by_slug($product_slug) {
        if (empty($product_slug)) return 0;
        $post = get_page_by_path($product_slug, OBJECT, 'product');
        return $post ? (int) $post->ID : 0;
    }

    /**
     * Resolve a category slug to its parent course slug
     */
    public static function resolve_category_to_parent($category_slug) {
        $courses = self::get_courses();
        $child_map = self::get_child_to_parent_map();

        if (isset($courses[$category_slug])) {
            return $category_slug;
        }

        if (isset($child_map[$category_slug])) {
            return $child_map[$category_slug];
        }

        return null;
    }

    /**
     * Get list of product IDs explicitly allowed for ASiT under library-subscription
     */
    public static function get_library_asit_products() {
        $ids = get_option('pmcm_asit_library_products', []);
        if (!is_array($ids)) {
            return [];
        }
        return array_values(array_unique(array_map('absint', $ids)));
    }

    /**
     * Whether library child categories should inherit ASiT eligibility
     */
    public static function library_includes_children() {
        return (bool) get_option('pmcm_asit_library_include_children', false);
    }

    /**
     * Sanitize array of integers
     */
    public static function sanitize_int_array($value) {
        if (!is_array($value)) {
            return [];
        }
        return array_values(array_unique(array_filter(array_map('absint', $value))));
    }

    /**
     * Resolve the requested edition number from POST, GET, or referrer.
     * This keeps edition context stable even when themes rebuild the add-to-cart form.
     */
    public static function get_requested_edition_number() {
        $candidates = [];

        if (isset($_POST['pmcm_edition_number'])) {
            $candidates[] = wp_unslash($_POST['pmcm_edition_number']);
        }

        if (isset($_REQUEST['edition'])) {
            $candidates[] = wp_unslash($_REQUEST['edition']);
        }

        foreach ($candidates as $candidate) {
            $edition = absint($candidate);
            if ($edition > 0) {
                return $edition;
            }
        }

        $referer = function_exists('wp_get_raw_referer') ? wp_get_raw_referer() : '';
        if (empty($referer) && function_exists('wp_get_referer')) {
            $referer = wp_get_referer();
        }

        if (!empty($referer)) {
            $query = wp_parse_url($referer, PHP_URL_QUERY);
            if (!empty($query)) {
                $params = [];
                parse_str($query, $params);
                if (!empty($params['edition'])) {
                    $edition = absint($params['edition']);
                    if ($edition > 0) {
                        return $edition;
                    }
                }
            }
        }

        return 0;
    }

    /**
     * Get course config for a category (works for both parent and child categories)
     */
    public static function get_course_for_category($category_slug) {
        $parent_slug = self::resolve_category_to_parent($category_slug);

        if ($parent_slug && isset(self::get_courses()[$parent_slug])) {
            return [
                'parent_slug' => $parent_slug,
                'original_slug' => $category_slug,
                'is_child' => ($category_slug !== $parent_slug),
                'course' => self::get_courses()[$parent_slug]
            ];
        }

        return null;
    }

    /**
     * Get ordinal suffix for a number
     */
    public static function get_ordinal($number) {
        $suffixes = ['th', 'st', 'nd', 'rd', 'th', 'th', 'th', 'th', 'th', 'th'];
        if (($number % 100) >= 11 && ($number % 100) <= 13) {
            return $number . 'th';
        }
        return $number . $suffixes[$number % 10];
    }

    /**
     * Get edition data for a specific slot (current or next)
     */
    public static function get_edition_slot($course_slug, $slot = 'current') {
        $courses = self::get_courses();
        if (!isset($courses[$course_slug])) {
            return null;
        }

        $course = $courses[$course_slug];
        $prefix = $course['settings_prefix'];

        if ($slot === 'current') {
            return [
                'slot' => 'current',
                'edition_number' => intval(get_option($prefix . 'current_edition', 1)),
                'edition_start' => get_option($prefix . 'edition_start', ''),
                'edition_end' => get_option($prefix . 'edition_end', ''),
                'early_bird_enabled' => get_option($prefix . 'early_bird_enabled', 'no'),
                'early_bird_start' => get_option($prefix . 'early_bird_start', ''),
                'early_bird_end' => get_option($prefix . 'early_bird_end', '')
            ];
        } elseif ($slot === 'next') {
            $next_enabled = get_option($prefix . 'next_enabled', 'no');
            if ($next_enabled !== 'yes') {
                return null;
            }
            return [
                'slot' => 'next',
                'edition_number' => intval(get_option($prefix . 'next_edition', 0)),
                'edition_start' => get_option($prefix . 'next_start', ''),
                'edition_end' => get_option($prefix . 'next_end', ''),
                'early_bird_enabled' => get_option($prefix . 'next_early_bird_enabled', 'no'),
                'early_bird_start' => get_option($prefix . 'next_early_bird_start', ''),
                'early_bird_end' => get_option($prefix . 'next_early_bird_end', '')
            ];
        }

        return null;
    }

    /**
     * Get active editions for a course (returns array of 1 or 2 editions)
     * An edition is active if today is within its start and end date range
     */
    public static function get_active_editions($course_slug) {
        $courses = self::get_courses();
        if (!isset($courses[$course_slug])) {
            return [];
        }

        $course = $courses[$course_slug];
        $today = current_time('Y-m-d');
        $today_timestamp = strtotime($today);
        $active = [];

        // Check current edition
        $current = self::get_edition_slot($course_slug, 'current');
        if ($current) {
            $is_active = true;

            // Check date range if dates are set
            if (!empty($current['edition_start']) && $today_timestamp < strtotime($current['edition_start'])) {
                $is_active = false;
            }
            if (!empty($current['edition_end']) && $today_timestamp > strtotime($current['edition_end'])) {
                $is_active = false;
            }

            if ($is_active) {
                $current['course_name'] = $course['name'];
                $current['edition_name'] = self::get_ordinal($current['edition_number']) . ' ' . $course['name'];
                $active[] = $current;
            }
        }

        // Check next edition
        $next = self::get_edition_slot($course_slug, 'next');
        if ($next && $next['edition_number'] > 0) {
            $is_active = true;

            // Check date range if dates are set
            if (!empty($next['edition_start']) && $today_timestamp < strtotime($next['edition_start'])) {
                $is_active = false;
            }
            if (!empty($next['edition_end']) && $today_timestamp > strtotime($next['edition_end'])) {
                $is_active = false;
            }

            if ($is_active) {
                $next['course_name'] = $course['name'];
                $next['edition_name'] = self::get_ordinal($next['edition_number']) . ' ' . $course['name'];
                $active[] = $next;
            }
        }

        // If no editions are active, return current as default
        if (empty($active) && $current) {
            $current['course_name'] = $course['name'];
            $current['edition_name'] = self::get_ordinal($current['edition_number']) . ' ' . $course['name'];
            $active[] = $current;
        }

        return $active;
    }

    /**
     * Check if customer must choose edition (when multiple editions are active)
     */
    public static function requires_edition_choice($course_slug) {
        $active = self::get_active_editions($course_slug);
        return count($active) > 1;
    }

    /**
     * Get edition data for a course (backwards compatible)
     */
    public static function get_course_edition($course_slug) {
        $courses = self::get_courses();
        if (!isset($courses[$course_slug])) {
            return null;
        }

        $course = $courses[$course_slug];
        $prefix = $course['settings_prefix'];

        return [
            'course_name' => $course['name'],
            'edition_number' => intval(get_option($prefix . 'current_edition', 1)),
            'edition_start' => get_option($prefix . 'edition_start', ''),
            'edition_end' => get_option($prefix . 'edition_end', ''),
            'early_bird_enabled' => get_option($prefix . 'early_bird_enabled', 'no'),
            'early_bird_start' => get_option($prefix . 'early_bird_start', ''),
            'early_bird_end' => get_option($prefix . 'early_bird_end', '')
        ];
    }

    /**
     * Save a single course to database
     */
    public static function save_course($course_slug, $course_data) {
        $courses = get_option('pmcm_course_mappings', []);

        if (empty($courses)) {
            $courses = self::$default_courses;
        }

        // Ensure required fields
        $course_data['category_slug'] = $course_slug;
        if (empty($course_data['settings_prefix'])) {
            // Generate settings prefix from slug
            $course_data['settings_prefix'] = '_' . str_replace('-', '_', $course_slug) . '_';
        }
        if (!isset($course_data['children'])) {
            $course_data['children'] = [];
        }
        if (!isset($course_data['edition_management'])) {
            $course_data['edition_management'] = true;
        }

        // ASiT configuration defaults
        if (!isset($course_data['asit_discount_mode'])) {
            $course_data['asit_discount_mode'] = 'none';
        }
        if (!isset($course_data['asit_early_bird_discount'])) {
            $course_data['asit_early_bird_discount'] = 0;
        }
        if (!isset($course_data['asit_normal_discount'])) {
            $course_data['asit_normal_discount'] = 0;
        }
        if (!isset($course_data['asit_show_field'])) {
            $course_data['asit_show_field'] = false;
        }
        $course_data['asit_edition_scope'] = self::normalize_asit_edition_scope(
            isset($course_data['asit_edition_scope']) ? $course_data['asit_edition_scope'] : '',
            $course_data['asit_discount_mode']
        );

        // Set asit_eligible based on mode for backward compatibility
        $course_data['asit_eligible'] = ($course_data['asit_discount_mode'] !== 'none');

        // BOMSS/Rouleaux configuration defaults
        foreach (['bomss', 'rouleaux'] as $partner) {
            if (!isset($course_data[$partner . '_discount_mode'])) {
                $course_data[$partner . '_discount_mode'] = 'none';
            }
            if (!isset($course_data[$partner . '_early_bird_discount'])) {
                $course_data[$partner . '_early_bird_discount'] = 0;
            }
            if (!isset($course_data[$partner . '_normal_discount'])) {
                $course_data[$partner . '_normal_discount'] = 0;
            }
            if (!isset($course_data[$partner . '_show_field'])) {
                $course_data[$partner . '_show_field'] = false;
            }
            $course_data[$partner . '_eligible'] = ($course_data[$partner . '_discount_mode'] !== 'none');
        }

        $courses[$course_slug] = $course_data;
        update_option('pmcm_course_mappings', $courses);

        self::clear_cache();
        self::log_activity('Course saved: ' . $course_data['name'], 'success');

        return true;
    }

    /**
     * Delete a course from database
     */
    public static function delete_course($course_slug) {
        $courses = get_option('pmcm_course_mappings', []);

        if (isset($courses[$course_slug])) {
            $course_name = $courses[$course_slug]['name'];
            unset($courses[$course_slug]);
            update_option('pmcm_course_mappings', $courses);

            self::clear_cache();
            self::log_activity('Course deleted: ' . $course_name, 'info');

            return true;
        }

        return false;
    }

    /**
     * Migrate default courses to database (run once)
     */
    public static function migrate_to_database() {
        $existing = get_option('pmcm_course_mappings', null);

        if ($existing !== null && !empty($existing)) {
            // Already migrated - ensure all partner fields are present
            self::migrate_asit_fields();
            self::migrate_partner_fields();
            return false;
        }

        // Migrate defaults to database
        update_option('pmcm_course_mappings', self::$default_courses);
        self::clear_cache();
        self::log_activity('Migrated course configuration to database', 'success');

        return true;
    }

    /**
     * Migrate ASiT fields to existing courses (for existing installations)
     */
    public static function migrate_asit_fields() {
        $courses = get_option('pmcm_course_mappings', []);
        $updated = false;

        foreach ($courses as $slug => &$course) {
            // Check if new ASiT fields are missing
            if (!isset($course['asit_discount_mode'])) {
                // Determine mode based on old asit_eligible flag
                if (isset($course['asit_eligible']) && $course['asit_eligible']) {
                    // Use default configuration based on course type
                    if (in_array($slug, ['frcs', 'frcs-vasc'])) {
                        $course['asit_discount_mode'] = 'early_bird_only';
                        $course['asit_early_bird_discount'] = 5;
                        $course['asit_normal_discount'] = 0;
                        $course['asit_show_field'] = true;
                    } elseif ($slug === 'library-subscription') {
                        $course['asit_discount_mode'] = 'always';
                        $course['asit_early_bird_discount'] = 10;
                        $course['asit_normal_discount'] = 10;
                        $course['asit_show_field'] = true;
                    } else {
                        $course['asit_discount_mode'] = 'always';
                        $course['asit_early_bird_discount'] = 5;
                        $course['asit_normal_discount'] = 10;
                        $course['asit_show_field'] = true;
                    }
                } else {
                    $course['asit_discount_mode'] = 'none';
                    $course['asit_early_bird_discount'] = 0;
                    $course['asit_normal_discount'] = 0;
                    $course['asit_show_field'] = false;
                }
                $updated = true;
            }

            $normalized_scope = self::normalize_asit_edition_scope(
                isset($course['asit_edition_scope']) ? $course['asit_edition_scope'] : '',
                isset($course['asit_discount_mode']) ? $course['asit_discount_mode'] : self::ASIT_MODE_NONE
            );

            if (!isset($course['asit_edition_scope']) || $course['asit_edition_scope'] !== $normalized_scope) {
                $course['asit_edition_scope'] = $normalized_scope;
                $updated = true;
            }
        }

        if ($updated) {
            update_option('pmcm_course_mappings', $courses);
            self::clear_cache();
            self::log_activity('Migrated ASiT fields to course configuration', 'success');
        }

        return $updated;
    }

    /**
     * Migrate BOMSS and Rouleaux partner fields to existing course installations
     */
    public static function migrate_partner_fields() {
        $courses = get_option('pmcm_course_mappings', []);
        if (empty($courses)) {
            return false;
        }

        $updated = false;
        $partner_defaults = [
            'bomss'   => ['eligible' => false, 'discount_mode' => 'none', 'early_bird_discount' => 0, 'normal_discount' => 0, 'show_field' => false],
            'rouleaux' => ['eligible' => false, 'discount_mode' => 'none', 'early_bird_discount' => 0, 'normal_discount' => 0, 'show_field' => false],
        ];

        // Known initial values per discount matrix
        $preset_overrides = [
            'frcs'                 => ['bomss' => ['eligible' => true, 'discount_mode' => 'early_bird_only', 'early_bird_discount' => 10, 'show_field' => true]],
            'frcs-vasc'            => ['rouleaux' => ['eligible' => true, 'discount_mode' => 'early_bird_only', 'early_bird_discount' => 10, 'show_field' => true]],
            'library-subscription' => ['bomss' => ['eligible' => true, 'discount_mode' => 'always', 'early_bird_discount' => 10, 'normal_discount' => 10, 'show_field' => true]],
        ];

        foreach ($courses as $slug => &$course) {
            foreach (array_keys($partner_defaults) as $partner) {
                if (!isset($course[$partner . '_discount_mode'])) {
                    $defaults = $partner_defaults[$partner];
                    if (isset($preset_overrides[$slug][$partner])) {
                        $defaults = array_merge($defaults, $preset_overrides[$slug][$partner]);
                    }
                    $course[$partner . '_eligible']            = $defaults['eligible'];
                    $course[$partner . '_discount_mode']       = $defaults['discount_mode'];
                    $course[$partner . '_early_bird_discount'] = $defaults['early_bird_discount'];
                    $course[$partner . '_normal_discount']     = $defaults['normal_discount'];
                    $course[$partner . '_show_field']          = $defaults['show_field'];
                    $updated = true;
                }
            }
        }
        unset($course);

        if ($updated) {
            update_option('pmcm_course_mappings', $courses);
            self::clear_cache();
            self::log_activity('Migrated BOMSS/Rouleaux partner fields to course configuration', 'success');
        }

        return $updated;
    }

    /**
     * Get partner discount config for a product (mirrors get_asit_config_for_product)
     *
     * @param int    $product_id
     * @param string $partner_slug  asit | bomss | rouleaux
     * @param string $edition_slot  current | next
     */
    public static function get_partner_config_for_product($product_id, $partner_slug, $edition_slot = 'current') {
        if ($partner_slug === 'asit') {
            return self::get_asit_config_for_product($product_id, $edition_slot);
        }

        $categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'slugs']);
        $child_map  = self::get_child_to_parent_map();
        $courses    = self::get_courses();

        foreach ($categories as $cat_slug) {
            if (isset($courses[$cat_slug])) {
                return self::get_partner_discount_for_course($cat_slug, $partner_slug, $edition_slot);
            }
            if (isset($child_map[$cat_slug]) && isset($courses[$child_map[$cat_slug]])) {
                return self::get_partner_discount_for_course($child_map[$cat_slug], $partner_slug, $edition_slot);
            }
        }

        return ['discount' => 0, 'is_eligible' => false, 'show_field' => false, 'mode' => 'none'];
    }

    /**
     * Get partner discount settings for a specific course + partner
     */
    public static function get_partner_discount_for_course($course_slug, $partner_slug, $edition_slot = 'current') {
        if ($partner_slug === 'asit') {
            return self::get_asit_discount_for_course($course_slug, $edition_slot);
        }

        $courses = self::get_courses();
        $course  = isset($courses[$course_slug]) ? $courses[$course_slug] : null;

        if (!$course) {
            return ['discount' => 0, 'is_eligible' => false, 'show_field' => false, 'mode' => 'none'];
        }

        $p         = $partner_slug;
        $mode      = isset($course[$p . '_discount_mode']) ? $course[$p . '_discount_mode'] : 'none';
        $eb        = isset($course[$p . '_early_bird_discount']) ? intval($course[$p . '_early_bird_discount']) : 0;
        $norm      = isset($course[$p . '_normal_discount']) ? intval($course[$p . '_normal_discount']) : 0;
        $show      = isset($course[$p . '_show_field']) ? (bool) $course[$p . '_show_field'] : false;
        $eb_type   = isset($course[$p . '_eb_discount_type']) && $course[$p . '_eb_discount_type'] === 'fixed' ? 'fixed' : 'percent';
        $norm_type = isset($course[$p . '_normal_discount_type']) && $course[$p . '_normal_discount_type'] === 'fixed' ? 'fixed' : 'percent';

        if ($mode === 'none') {
            return ['discount' => 0, 'is_eligible' => false, 'show_field' => false, 'mode' => 'none', 'discount_type' => 'percent'];
        }

        if ($mode === 'always') {
            return ['discount' => $norm, 'is_eligible' => true, 'show_field' => $show, 'mode' => 'always', 'discount_type' => $norm_type];
        }

        if ($mode === 'early_bird_only') {
            $current_eb = self::is_course_early_bird_active($course_slug);
            $next_eb    = self::is_next_edition_early_bird_active($course_slug);
            $is_early_bird = ($edition_slot === 'next') ? $next_eb : $current_eb;
            if ($is_early_bird) {
                return ['discount' => $eb, 'is_eligible' => true, 'show_field' => $show, 'mode' => 'early_bird_only', 'discount_type' => $eb_type];
            }
            return ['discount' => 0, 'is_eligible' => false, 'show_field' => $show, 'mode' => 'early_bird_only', 'discount_type' => $eb_type];
        }

        return ['discount' => 0, 'is_eligible' => false, 'show_field' => false, 'mode' => 'none', 'discount_type' => 'percent'];
    }

    /**
     * Check if migration has been done
     */
    public static function is_migrated() {
        $courses = get_option('pmcm_course_mappings', null);
        return $courses !== null && !empty($courses);
    }

    /**
     * Get all WooCommerce product categories (for admin dropdown)
     */
    public static function get_wc_categories() {
        $categories = get_terms([
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC'
        ]);

        if (is_wp_error($categories)) {
            return [];
        }

        $result = [];
        foreach ($categories as $cat) {
            $result[$cat->slug] = $cat->name;
        }

        return $result;
    }

    /**
     * Log activity
     */
    public static function log_activity($message, $type = 'info') {
        $log = get_option('wcem_activity_log', []);

        array_unshift($log, [
            'time' => current_time('mysql'),
            'message' => $message,
            'type' => $type
        ]);

        $log = array_slice($log, 0, 100);
        update_option('wcem_activity_log', $log);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[PMCM ' . strtoupper($type) . '] ' . $message);
        }
    }
}
