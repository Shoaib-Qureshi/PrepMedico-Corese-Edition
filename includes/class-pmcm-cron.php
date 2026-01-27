<?php
/**
 * PMCM Cron Class
 * Handles scheduled tasks and edition auto-switching
 *
 * @package PrepMedico_Course_Management
 */

if (!defined('ABSPATH')) {
    exit;
}

class PMCM_Cron {

    /**
     * Initialize cron hooks
     */
    public static function init() {
        add_action('wcem_daily_edition_check', [__CLASS__, 'check_and_update_editions']);
        add_filter('cron_schedules', [__CLASS__, 'add_cron_schedule']);
    }

    /**
     * Add custom cron schedule
     */
    public static function add_cron_schedule($schedules) {
        $schedules['wcem_daily'] = [
            'interval' => DAY_IN_SECONDS,
            'display' => __('Once Daily (1 AM)', 'prepmedico-course-management')
        ];
        return $schedules;
    }

    /**
     * Schedule cron on plugin activation
     */
    public static function schedule_cron() {
        if (!wp_next_scheduled('wcem_daily_edition_check')) {
            $timestamp = strtotime('tomorrow 1:00am');
            wp_schedule_event($timestamp, 'daily', 'wcem_daily_edition_check');
        }
    }

    /**
     * Unschedule cron on plugin deactivation
     */
    public static function unschedule_cron() {
        $timestamp = wp_next_scheduled('wcem_daily_edition_check');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'wcem_daily_edition_check');
        }
    }

    /**
     * Check and update editions via cron
     * Logic:
     * - When Current Edition End Date has passed
     * - Increment current edition by 1
     * - Clear edition dates (admin needs to set new dates)
     * - Clear Early Bird settings
     */
    public static function check_and_update_editions() {
        $today = current_time('Y-m-d');
        $today_timestamp = strtotime($today);
        $switched = [];

        PMCM_Core::log_activity('Edition check started. Today: ' . $today, 'info');

        foreach (PMCM_Core::get_edition_managed_courses() as $category_slug => $course) {
            $prefix = $course['settings_prefix'];

            $current_edition = intval(get_option($prefix . 'current_edition', 1));
            $edition_end = get_option($prefix . 'edition_end', '');

            // Log current state for debugging
            PMCM_Core::log_activity($course['name'] . ': Edition ' . $current_edition . ', End Date: ' . ($edition_end ?: 'not set'), 'info');

            // Check if Current Edition End Date has passed
            if (!empty($edition_end) && $today_timestamp > strtotime($edition_end)) {
                $old_edition = $current_edition;
                $new_edition = $current_edition + 1;

                // Increment edition number by 1
                update_option($prefix . 'current_edition', $new_edition);

                // Clear edition dates (admin needs to set new dates for new edition)
                update_option($prefix . 'edition_start', '');
                update_option($prefix . 'edition_end', '');

                // Clear Early Bird settings for new edition
                update_option($prefix . 'early_bird_enabled', 'no');
                update_option($prefix . 'early_bird_start', '');
                update_option($prefix . 'early_bird_end', '');

                $switched[] = ['course' => $course['name'], 'from' => $old_edition, 'to' => $new_edition];
                PMCM_Core::log_activity('Auto-incremented ' . $course['name'] . ': Edition ' . $old_edition . ' → ' . $new_edition . ' (End Date passed)', 'success');
            }
        }

        if (!empty($switched)) {
            self::send_edition_switch_notification($switched);
        } else {
            PMCM_Core::log_activity('Edition check completed. No editions needed incrementing.', 'info');
        }
    }

    /**
     * Send edition switch notification
     */
    private static function send_edition_switch_notification($switched) {
        $admin_email = get_option('admin_email');
        $subject = '[PrepMedico] Edition Auto-Increment Completed';

        $message = "The following course editions have been automatically incremented:\n\n";
        foreach ($switched as $switch) {
            $message .= sprintf("%s: Edition %d → Edition %d\n", $switch['course'], $switch['from'], $switch['to']);
        }
        $message .= "\nIMPORTANT: Please set the new edition dates for these courses.";
        $message .= "\n\n" . admin_url('admin.php?page=prepmedico-management');

        wp_mail($admin_email, $subject, $message);
    }
}
