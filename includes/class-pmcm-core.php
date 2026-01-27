<?php
/**
 * PMCM Core Class
 * Contains course definitions and helper methods
 *
 * @package PrepMedico_Course_Management
 */

if (!defined('ABSPATH')) {
    exit;
}

class PMCM_Core {

    /**
     * Parent course configuration mapping
     * These are the main course categories with their FluentCRM tags and fields
     * Courses with 'edition_management' => true will show in Edition Management admin
     * All courses get FluentCRM tagging regardless
     */
    private static $default_courses = [
        'frcophth-part-1' => [
            'name' => 'FRCOphth Part 1',
            'settings_prefix' => '_frcophth_p1_',
            'fluentcrm_tag' => 'FRCOphth-Part1',
            'fluentcrm_field' => 'frcophth_p1_edition',
            'edition_management' => true,
            'asit_eligible' => false
        ],
        'frcophth-part-2' => [
            'name' => 'FRCOphth Part 2',
            'settings_prefix' => '_frcophth_p2_',
            'fluentcrm_tag' => 'FRCOphth-Part2',
            'fluentcrm_field' => 'frcophth_p2_edition',
            'edition_management' => true,
            'asit_eligible' => false
        ],
        'frcs' => [
            'name' => 'FRCS',
            'settings_prefix' => '_frcs_',
            'fluentcrm_tag' => 'FRCS',
            'fluentcrm_field' => 'frcs_edition',
            'edition_management' => true,
            'asit_eligible' => true
        ],
        'frcs-vasc' => [
            'name' => 'FRCS VASC',
            'settings_prefix' => '_frcs_vasc_',
            'fluentcrm_tag' => 'FRCS-VASC',
            'fluentcrm_field' => 'frcs_vasc_edition',
            'edition_management' => true,
            'asit_eligible' => true
        ],
        'library-subscription' => [
            'name' => 'Library Subscription',
            'settings_prefix' => '_library_sub_',
            'fluentcrm_tag' => 'Library-Subscription',
            'fluentcrm_field' => 'library_sub_edition',
            'edition_management' => false,
            'asit_eligible' => false
        ],
        'scfhs' => [
            'name' => 'SCFHS',
            'settings_prefix' => '_scfhs_',
            'fluentcrm_tag' => 'SCFHS',
            'fluentcrm_field' => 'scfhs_edition',
            'edition_management' => true,
            'asit_eligible' => false
        ]
    ];

    /**
     * Child category to parent category mapping
     * Child categories inherit the parent's FluentCRM tag and field
     */
    private static $child_to_parent_map = [
        // FRCOphth Part 1 children
        'frcophth-individual-weekend-viva-part-1' => 'frcophth-part-1',

        // FRCOphth Part 2 children
        'frcophth-individual-weekend-viva-part-2' => 'frcophth-part-2',

        // FRCS children
        'frcs-rapid-review-lecture-series' => 'frcs',
        'mock-viva' => 'frcs',
        'sba-q-bank' => 'frcs',
        'speciality-based-weekend-viva-sessions' => 'frcs',

        // FRCS VASC children
        'frcs-vasc-individual-weekend-viva-sessions' => 'frcs-vasc',
        'frcs-vasc-library-subscription' => 'frcs-vasc',

        // SCFHS children
        'scfhs-participation-for-1-topic' => 'scfhs'
    ];

    /**
     * Get all courses with filter support
     */
    public static function get_courses() {
        return apply_filters('pmcm_courses', self::$default_courses);
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
     * Get child to parent mapping with filter support
     */
    public static function get_child_to_parent_map() {
        return apply_filters('pmcm_child_to_parent_map', self::$child_to_parent_map);
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
     * Get edition data for a course
     */
    public static function get_course_edition($course_slug) {
        if (!isset(self::get_courses()[$course_slug])) {
            return null;
        }

        $course = self::get_courses()[$course_slug];
        $prefix = $course['settings_prefix'];

        return [
            'course_name' => $course['name'],
            'edition_number' => get_option($prefix . 'current_edition', 1),
            'edition_start' => get_option($prefix . 'edition_start', ''),
            'edition_end' => get_option($prefix . 'edition_end', ''),
            'early_bird_enabled' => get_option($prefix . 'early_bird_enabled', 'no'),
            'early_bird_start' => get_option($prefix . 'early_bird_start', ''),
            'early_bird_end' => get_option($prefix . 'early_bird_end', '')
        ];
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
