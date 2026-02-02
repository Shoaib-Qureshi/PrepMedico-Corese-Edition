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
     * ASiT discount modes
     * - 'none': No ASiT discount, don't show field
     * - 'early_bird_only': ASiT discount only during early bird period
     * - 'always': ASiT discount always applies
     */
    const ASIT_MODE_NONE = 'none';
    const ASIT_MODE_EARLY_BIRD_ONLY = 'early_bird_only';
    const ASIT_MODE_ALWAYS = 'always';

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
            'asit_early_bird_discount' => 5,
            'asit_normal_discount' => 0,
            'asit_show_field' => true,
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
            'asit_early_bird_discount' => 5,
            'asit_normal_discount' => 0,
            'asit_show_field' => true,
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
    public static function get_asit_discount_for_course($course_slug) {
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
            // Only give discount during early bird
            $is_early_bird = self::is_course_early_bird_active($course_slug);
            if ($is_early_bird) {
                return ['discount' => $eb_discount, 'is_eligible' => true, 'show_field' => $show_field, 'mode' => $mode];
            }
            // Not early bird - no discount, but still show field (user can enter number but won't get discount)
            return ['discount' => 0, 'is_eligible' => false, 'show_field' => $show_field, 'mode' => $mode];
        }

        return ['discount' => 0, 'is_eligible' => false, 'show_field' => false, 'mode' => $mode];
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
     * Get the ASiT course config for a product
     * Returns the parent course's ASiT settings
     */
    public static function get_asit_config_for_product($product_id) {
        $categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'slugs']);
        $child_map = self::get_child_to_parent_map();
        $courses = self::get_courses();
        $library_products = self::get_library_asit_products();
        $library_include_children = (bool) get_option('pmcm_asit_library_include_children', false);

        foreach ($categories as $cat_slug) {
            // Check if it's a parent course
            if (isset($courses[$cat_slug])) {
                if ($cat_slug === 'library-subscription') {
                    if (in_array($product_id, $library_products, true) || ($library_include_children && !empty($categories))) {
                        return self::get_asit_discount_for_course($cat_slug);
                    }
                    return ['discount' => 0, 'is_eligible' => false, 'show_field' => false, 'mode' => 'none'];
                }
                return self::get_asit_discount_for_course($cat_slug);
            }
            // Check if it's a child category
            if (isset($child_map[$cat_slug])) {
                $parent_slug = $child_map[$cat_slug];
                if ($parent_slug === 'library-subscription') {
                    if ($library_include_children || in_array($product_id, $library_products, true)) {
                        return self::get_asit_discount_for_course($parent_slug);
                    }
                    return ['discount' => 0, 'is_eligible' => false, 'show_field' => false, 'mode' => 'none'];
                }
                return self::get_asit_discount_for_course($parent_slug);
            }
        }

        return ['discount' => 0, 'is_eligible' => false, 'show_field' => false, 'mode' => 'none'];
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

        // Set asit_eligible based on mode for backward compatibility
        $course_data['asit_eligible'] = ($course_data['asit_discount_mode'] !== 'none');

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
            // Already migrated - but check if ASiT fields need to be added
            self::migrate_asit_fields();
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
        }

        if ($updated) {
            update_option('pmcm_course_mappings', $courses);
            self::clear_cache();
            self::log_activity('Migrated ASiT fields to course configuration', 'success');
        }

        return $updated;
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
