<?php

/**
 * PMCM Admin Class
 * Handles all admin pages and settings
 *
 * @package PrepMedico_Course_Management
 */

if (!defined('ABSPATH')) {
    exit;
}

class PMCM_Admin
{

    /**
     * Initialize admin hooks
     */
    public static function init()
    {
        add_action('admin_menu', [__CLASS__, 'add_admin_menu']);
        add_action('admin_init', [__CLASS__, 'register_settings']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
        add_action('admin_notices', [__CLASS__, 'admin_notices']);
        add_action('admin_bar_menu', [__CLASS__, 'add_admin_bar_node'], 100);

        // AJAX handlers
        add_action('wp_ajax_wcem_manual_edition_switch', [__CLASS__, 'ajax_manual_edition_switch']);
        add_action('wp_ajax_wcem_test_fluentcrm', [__CLASS__, 'ajax_test_fluentcrm']);
        add_action('wp_ajax_wcem_run_cron', [__CLASS__, 'ajax_run_cron']);
        add_action('wp_ajax_wcem_sync_order', [__CLASS__, 'ajax_sync_order_to_fluentcrm']);
        add_action('wp_ajax_wcem_update_order_edition', [__CLASS__, 'ajax_update_order_edition']);
        add_action('wp_ajax_wcem_bulk_sync_asit', [__CLASS__, 'ajax_bulk_sync_asit_orders']);
        add_action('wp_ajax_wcem_save_course', [__CLASS__, 'ajax_save_course']);
        add_action('wp_ajax_wcem_delete_course', [__CLASS__, 'ajax_delete_course']);

        // Bulk actions on WooCommerce Orders page (HPOS + Legacy)
        add_filter('bulk_actions-woocommerce_page_wc-orders', [__CLASS__, 'register_orders_bulk_actions']);
        add_filter('bulk_actions-edit-shop_order', [__CLASS__, 'register_orders_bulk_actions']);
        add_filter('handle_bulk_actions-woocommerce_page_wc-orders', [__CLASS__, 'handle_orders_bulk_action'], 10, 3);
        add_filter('handle_bulk_actions-edit-shop_order', [__CLASS__, 'handle_orders_bulk_action'], 10, 3);
    }

    /**
     * Add admin menu
     */
    public static function add_admin_menu()
    {
        add_menu_page(
            __('Edition/Course MGMT', 'prepmedico-course-management'),
            __('Edition/Course MGMT', 'prepmedico-course-management'),
            'manage_woocommerce',
            'prepmedico-management',
            [__CLASS__, 'render_edition_page'],
            'dashicons-welcome-learn-more',
            56
        );

        add_submenu_page(
            'prepmedico-management',
            __('Edition Management', 'prepmedico-course-management'),
            __('Edition Management', 'prepmedico-course-management'),
            'manage_woocommerce',
            'prepmedico-management',
            [__CLASS__, 'render_edition_page']
        );

        add_submenu_page(
            'prepmedico-management',
            __('Course Configuration', 'prepmedico-course-management'),
            __('Course Configuration', 'prepmedico-course-management'),
            'manage_woocommerce',
            'prepmedico-course-config',
            [__CLASS__, 'render_course_config_page']
        );

        add_submenu_page(
            'prepmedico-management',
            __('Academic Partners', 'prepmedico-course-management'),
            __('Academic Partners', 'prepmedico-course-management'),
            'manage_woocommerce',
            'prepmedico-asit-management',
            [__CLASS__, 'render_asit_page']
        );
    }

    /**
     * Add Edition Management shortcut to WP admin bar
     */
    public static function add_admin_bar_node($wp_admin_bar) {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }
        $wp_admin_bar->add_node([
            'id'     => 'pmcm-edition-management',
            'title'  => '<span class="ab-icon dashicons dashicons-welcome-learn-more" style="font-size:16px;margin-top:3px;"></span> Edition MGMT',
            'href'   => admin_url('admin.php?page=prepmedico-management'),
            'meta'   => ['title' => 'Edition / Course Management'],
        ]);
        $wp_admin_bar->add_node([
            'id'     => 'pmcm-edition-management-editions',
            'parent' => 'pmcm-edition-management',
            'title'  => 'Edition Management',
            'href'   => admin_url('admin.php?page=prepmedico-management'),
        ]);
        $wp_admin_bar->add_node([
            'id'     => 'pmcm-edition-management-partners',
            'parent' => 'pmcm-edition-management',
            'title'  => 'Academic Partners',
            'href'   => admin_url('admin.php?page=prepmedico-asit-management'),
        ]);
        $wp_admin_bar->add_node([
            'id'     => 'pmcm-edition-management-courses',
            'parent' => 'pmcm-edition-management',
            'title'  => 'Course Configuration',
            'href'   => admin_url('admin.php?page=prepmedico-course-config'),
        ]);
    }

    /**
     * Register settings
     */
    public static function register_settings()
    {
        foreach (PMCM_Core::get_courses() as $category_slug => $course) {
            $prefix = $course['settings_prefix'];

            // Current edition slot settings
            register_setting('wcem_settings', $prefix . 'current_edition', ['type' => 'integer', 'sanitize_callback' => 'absint']);
            register_setting('wcem_settings', $prefix . 'edition_start', ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field']);
            register_setting('wcem_settings', $prefix . 'edition_end', ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field']);
            register_setting('wcem_settings', $prefix . 'early_bird_enabled', ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field']);
            register_setting('wcem_settings', $prefix . 'early_bird_start', ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field']);
            register_setting('wcem_settings', $prefix . 'early_bird_end', ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field']);

            // Next edition slot settings
            register_setting('wcem_settings', $prefix . 'next_enabled', ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field']);
            register_setting('wcem_settings', $prefix . 'next_edition', ['type' => 'integer', 'sanitize_callback' => 'absint']);
            register_setting('wcem_settings', $prefix . 'next_start', ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field']);
            register_setting('wcem_settings', $prefix . 'next_end', ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field']);
            register_setting('wcem_settings', $prefix . 'next_early_bird_enabled', ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field']);
            register_setting('wcem_settings', $prefix . 'next_early_bird_start', ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field']);
            register_setting('wcem_settings', $prefix . 'next_early_bird_end', ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field']);

            // Exam dates (free-text, per slot)
            register_setting('wcem_settings', $prefix . 'exam_dates', ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field']);
            register_setting('wcem_settings', $prefix . 'next_exam_dates', ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field']);
        }

        register_setting('pmcm_asit_settings', 'pmcm_asit_discount_early_bird', ['type' => 'integer', 'sanitize_callback' => 'absint']);
        register_setting('pmcm_asit_settings', 'pmcm_asit_discount_normal', ['type' => 'integer', 'sanitize_callback' => 'absint']);
        register_setting('pmcm_asit_settings', 'pmcm_asit_library_products', ['type' => 'array', 'sanitize_callback' => ['PMCM_Core', 'sanitize_int_array']]);
        register_setting('pmcm_asit_settings', 'pmcm_asit_library_include_children', ['type' => 'boolean', 'sanitize_callback' => function ($v) {
            return $v ? 1 : 0;
        }]);
    }

    /**
     * Enqueue admin scripts and styles
     */
    public static function enqueue_scripts($hook)
    {
        // Check if we're on one of our plugin pages by looking for our page slugs
        // in the hook name (robust against menu title changes that affect hook prefixes)
        $our_slugs = [
            'prepmedico-management',
            'prepmedico-asit-management',
            'prepmedico-course-config',
            'wc-edition-management'
        ];

        $is_our_page = false;
        foreach ($our_slugs as $slug) {
            if (strpos($hook, $slug) !== false) {
                $is_our_page = true;
                break;
            }
        }

        if (!$is_our_page) {
            return;
        }

        // Add Material Icons
        wp_enqueue_style('material-icons', 'https://fonts.googleapis.com/icon?family=Material+Icons+Round', [], null);

        // Add Inter font
        wp_enqueue_style('google-fonts-inter', 'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap', [], null);

        wp_enqueue_style('pmcm-admin', PMCM_PLUGIN_URL . 'assets/css/admin.css', ['material-icons', 'google-fonts-inter'], PMCM_VERSION);
        wp_enqueue_script('pmcm-admin', PMCM_PLUGIN_URL . 'assets/js/admin.js', ['jquery'], PMCM_VERSION, true);
        wp_localize_script('pmcm-admin', 'wcemAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wcem_admin_nonce'),
            'strings' => [
                'confirmSwitch' => __('Are you sure you want to manually switch this edition?', 'prepmedico-course-management'),
                'switching' => __('Switching...', 'prepmedico-course-management'),
                'success' => __('Edition switched successfully!', 'prepmedico-course-management'),
                'error' => __('Error switching edition. Please try again.', 'prepmedico-course-management')
            ]
        ]);
    }

    /**
     * Display admin notices
     */
    public static function admin_notices()
    {
        $warnings = [];
        foreach (PMCM_Core::get_edition_managed_courses() as $category_slug => $course) {
            $prefix = $course['settings_prefix'];
            $end_date = get_option($prefix . 'edition_end');

            // Warn if edition end date is approaching or passed and dates need to be set
            if (!empty($end_date)) {
                $end_timestamp = strtotime($end_date);
                $days_until_end = ($end_timestamp - time()) / DAY_IN_SECONDS;

                if ($days_until_end <= 7 && $days_until_end > 0) {
                    $warnings[] = sprintf(
                        __('%s edition ends in %d days! Edition will auto-increment after end date.', 'prepmedico-course-management'),
                        $course['name'],
                        ceil($days_until_end)
                    );
                }
            }

            // Warn if no dates are set
            $start_date = get_option($prefix . 'edition_start');
            if (empty($start_date) || empty($end_date)) {
                $current_edition = get_option($prefix . 'current_edition', 1);
                $warnings[] = sprintf(
                    __('%s (Edition %d) has no dates set. Please configure edition dates.', 'prepmedico-course-management'),
                    $course['name'],
                    $current_edition
                );
            }
        }

        if (!empty($warnings) && current_user_can('manage_woocommerce')) {
            echo '<div class="notice notice-warning is-dismissible">';
            echo '<p><strong>' . __('Edition/Course MGMT Edition Notice:', 'prepmedico-course-management') . '</strong></p>';
            echo '<ul>';
            foreach ($warnings as $warning) {
                echo '<li>' . esc_html($warning) . '</li>';
            }
            echo '</ul>';
            echo '<p><a href="' . admin_url('admin.php?page=prepmedico-management') . '">' . __('Configure edition settings', 'prepmedico-course-management') . '</a></p>';
            echo '</div>';
        }

        // Bulk action result notices
        if (isset($_GET['pmcm_bulk_action']) && current_user_can('manage_woocommerce')) {
            $action = sanitize_text_field($_GET['pmcm_bulk_action']);
            $success = intval($_GET['pmcm_success'] ?? 0);
            $failed = intval($_GET['pmcm_failed'] ?? 0);
            $skipped = intval($_GET['pmcm_skipped'] ?? 0);
            $total = $success + $failed + $skipped;

            if ($action === 'pmcm_update_edition') {
                $label = __('Update Edition Data', 'prepmedico-course-management');
            } else {
                $label = __('Sync to FluentCRM', 'prepmedico-course-management');
            }

            $notice_type = ($failed > 0) ? 'warning' : 'success';
            $parts = [];
            if ($success > 0) {
                $parts[] = sprintf(_n('%d order processed', '%d orders processed', $success, 'prepmedico-course-management'), $success);
            }
            if ($skipped > 0) {
                $parts[] = sprintf(_n('%d skipped (no course data)', '%d skipped (no course data)', $skipped, 'prepmedico-course-management'), $skipped);
            }
            if ($failed > 0) {
                $parts[] = sprintf(_n('%d failed', '%d failed', $failed, 'prepmedico-course-management'), $failed);
            }

            echo '<div class="notice notice-' . esc_attr($notice_type) . ' is-dismissible">';
            echo '<p><strong>Edition/Course MGMT ' . esc_html($label) . ':</strong> ' . esc_html(implode(', ', $parts)) . '.</p>';
            echo '</div>';
        }
    }

    private static function get_registration_override(string $prefix): string
    {
        $val = get_option($prefix . 'registration_override', '');
        if (!in_array($val, ['auto', 'force_open', 'force_closed'], true)) {
            // Migrate from old binary toggle
            $val = get_option($prefix . 'force_registration_open', 'no') === 'yes' ? 'force_open' : 'auto';
        }

        // Self-cancel once dates catch up. Keeps the admin UI in sync with frontend behaviour.
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

    public static function get_notification_recipients(): array
    {
        $admin      = get_option('admin_email');
        $extra_raw  = get_option('pmcm_notification_emails', '');
        $extra      = array_filter(array_map('trim', explode(',', $extra_raw)));
        $all        = array_unique(array_merge([$admin], $extra));
        return array_values(array_filter($all, 'is_email'));
    }

    private static function maybe_send_registration_notification(array $course, string $old_val, string $new_val, string $trigger): void
    {
        $triggers = json_decode((string) get_option('pmcm_notification_triggers', '[]'), true);
        if (!is_array($triggers) || !in_array($trigger, $triggers, true)) {
            return;
        }
        $recipients = self::get_notification_recipients();
        if (empty($recipients)) {
            return;
        }
        $labels = [
            'force_open'   => 'Force Open (Enroll button shown)',
            'force_closed' => 'Force Closed (Opening Soon)',
            'auto'         => 'Auto — date-based',
        ];
        $subject = sprintf('[PrepMedico] %s — Registration Override Changed', $course['name']);
        $message  = "Registration override for {$course['name']} has been updated.\n\n";
        $message .= 'Previous: ' . ($labels[$old_val] ?? $old_val) . "\n";
        $message .= 'New: '      . ($labels[$new_val] ?? $new_val) . "\n\n";
        $message .= 'Changed by: ' . (wp_get_current_user()->user_email ?: 'system') . "\n";
        $message .= 'Manage editions: ' . admin_url('admin.php?page=prepmedico-management');
        wp_mail($recipients, $subject, $message);
    }

    private static function save_notification_settings(): void
    {
        $emails_raw = isset($_POST['pmcm_notification_emails']) ? sanitize_text_field($_POST['pmcm_notification_emails']) : '';
        update_option('pmcm_notification_emails', $emails_raw);

        $allowed    = ['reg_force_open', 'reg_force_closed', 'reg_auto', 'edition_switch'];
        $raw        = isset($_POST['pmcm_notification_triggers']) && is_array($_POST['pmcm_notification_triggers'])
                        ? $_POST['pmcm_notification_triggers'] : [];
        $triggers   = array_values(array_intersect(array_map('sanitize_text_field', $raw), $allowed));
        update_option('pmcm_notification_triggers', wp_json_encode($triggers));
    }

    /**
     * Save edition settings
     */
    private static function save_settings()
    {
        foreach (PMCM_Core::get_edition_managed_courses() as $category_slug => $course) {
            $prefix = $course['settings_prefix'];

            // Current edition slot fields
            $current_fields = ['current_edition', 'edition_start', 'edition_end', 'early_bird_enabled', 'early_bird_start', 'early_bird_end', 'exam_dates'];
            foreach ($current_fields as $field) {
                $key = $prefix . $field;
                if ($field === 'early_bird_enabled') {
                    $value = isset($_POST[$key]) ? 'yes' : 'no';
                } elseif (isset($_POST[$key])) {
                    $value = sanitize_text_field($_POST[$key]);
                    if ($field === 'current_edition') {
                        $value = absint($value);
                    }
                } else {
                    continue;
                }
                update_option($key, $value);
            }

            // Next edition slot fields
            $next_enabled_key = $prefix . 'next_enabled';
            $next_enabled = isset($_POST[$next_enabled_key]) ? 'yes' : 'no';
            update_option($next_enabled_key, $next_enabled);

            $next_fields = ['next_edition', 'next_start', 'next_end', 'next_early_bird_enabled', 'next_early_bird_start', 'next_early_bird_end', 'next_exam_dates'];
            foreach ($next_fields as $field) {
                $key = $prefix . $field;
                if ($field === 'next_early_bird_enabled') {
                    $value = isset($_POST[$key]) ? 'yes' : 'no';
                } elseif (isset($_POST[$key])) {
                    $value = sanitize_text_field($_POST[$key]);
                    if ($field === 'next_edition') {
                        $value = absint($value);
                    }
                } else {
                    continue;
                }
                update_option($key, $value);
            }

            $sd_key = $prefix . 'shortcode_display_next';
            update_option($sd_key, isset($_POST[$sd_key]) ? 'yes' : 'no');

            $cc_key = $prefix . 'closed_categories_current';
            $closed = isset($_POST[$cc_key]) && is_array($_POST[$cc_key]) ? array_map('sanitize_text_field', $_POST[$cc_key]) : [];
            update_option($cc_key, wp_json_encode(array_values($closed)));

            $ro_key = $prefix . 'registration_override';
            $ro_old = self::get_registration_override($prefix);
            $ro_val = isset($_POST[$ro_key]) ? sanitize_text_field($_POST[$ro_key]) : 'auto';
            if (!in_array($ro_val, ['auto', 'force_open', 'force_closed'], true)) {
                $ro_val = 'auto';
            }
            update_option($ro_key, $ro_val);

            if ($ro_val !== $ro_old) {
                $trigger_map = ['force_open' => 'reg_force_open', 'force_closed' => 'reg_force_closed', 'auto' => 'reg_auto'];
                $trigger = $trigger_map[$ro_val] ?? '';
                if ($trigger) {
                    self::maybe_send_registration_notification($course, $ro_old, $ro_val, $trigger);
                }
            }
        }
    }

    /**
     * Save ASiT settings (global and per-course)
     */
    private static function save_asit_settings()
    {
        if (isset($_POST['pmcm_asit_discount_early_bird'])) {
            update_option('pmcm_asit_discount_early_bird', absint($_POST['pmcm_asit_discount_early_bird']));
        }
        if (isset($_POST['pmcm_asit_discount_normal'])) {
            update_option('pmcm_asit_discount_normal', absint($_POST['pmcm_asit_discount_normal']));
        }

        // Save per-course ASiT configuration
        if (isset($_POST['asit_config']) && is_array($_POST['asit_config'])) {
            $courses = get_option('pmcm_course_mappings', []);

            if (empty($courses)) {
                $courses = PMCM_Core::get_default_courses();
            }

            foreach ($_POST['asit_config'] as $course_slug => $config) {
                $course_slug = sanitize_text_field($course_slug);

                if (!isset($courses[$course_slug])) {
                    continue;
                }

                $mode = isset($config['mode']) ? sanitize_text_field($config['mode']) : 'none';
                $eb_discount = isset($config['eb_discount']) ? absint($config['eb_discount']) : 0;
                $normal_discount = isset($config['normal_discount']) ? absint($config['normal_discount']) : 0;
                $show_field = isset($config['show_field']) && $config['show_field'] == '1';
                $product_filter = isset($config['product_filter']) && $config['product_filter'] == '1';
                $include_children = true; // Always include children as per UI change
                $selected_products = isset($config['selected_products']) ? array_map('absint', (array) $config['selected_products']) : [];
                $edition_scope = isset($config['edition_scope']) ? sanitize_text_field($config['edition_scope']) : '';

                // Validate mode
                if (!in_array($mode, ['none', 'early_bird_only', 'always'])) {
                    $mode = 'none';
                }

                // Validate edition scope
                $edition_scope = PMCM_Core::normalize_asit_edition_scope($edition_scope, $mode);

                // Update course configuration
                $courses[$course_slug]['asit_discount_mode'] = $mode;
                $courses[$course_slug]['asit_early_bird_discount'] = $eb_discount;
                $courses[$course_slug]['asit_normal_discount'] = $normal_discount;
                $courses[$course_slug]['asit_show_field'] = $show_field;
                $courses[$course_slug]['asit_product_filter'] = $product_filter;
                $courses[$course_slug]['asit_include_children'] = $include_children;
                $courses[$course_slug]['asit_selected_products'] = array_values(array_unique(array_filter($selected_products)));
                $courses[$course_slug]['asit_edition_scope'] = $edition_scope;

                // Update legacy asit_eligible field for backward compatibility
                $courses[$course_slug]['asit_eligible'] = ($mode !== 'none');
            }

            update_option('pmcm_course_mappings', $courses);
            PMCM_Core::clear_cache();
            PMCM_Core::log_activity('ASiT per-course settings updated', 'success');
        }

        // Save per-course BOMSS configuration
        if (isset($_POST['bomss_config']) && is_array($_POST['bomss_config'])) {
            $courses = get_option('pmcm_course_mappings', []);
            if (empty($courses)) $courses = PMCM_Core::get_default_courses();

            foreach ($_POST['bomss_config'] as $course_slug => $config) {
                $course_slug = sanitize_text_field($course_slug);
                if (!isset($courses[$course_slug])) continue;

                $mode = in_array($config['mode'] ?? '', ['none', 'early_bird_only', 'always']) ? $config['mode'] : 'none';
                $courses[$course_slug]['bomss_discount_mode']       = $mode;
                $courses[$course_slug]['bomss_early_bird_discount'] = absint($config['eb_discount'] ?? 0);
                $courses[$course_slug]['bomss_normal_discount']     = absint($config['normal_discount'] ?? 0);
                $courses[$course_slug]['bomss_show_field']          = isset($config['show_field']) && $config['show_field'] == '1';
                $courses[$course_slug]['bomss_eligible']            = ($mode !== 'none');
            }

            update_option('pmcm_course_mappings', $courses);
            PMCM_Core::clear_cache();
            PMCM_Core::log_activity('BOMSS per-course settings updated', 'success');
        }

        // Save per-course Rouleaux configuration
        if (isset($_POST['rouleaux_config']) && is_array($_POST['rouleaux_config'])) {
            $courses = get_option('pmcm_course_mappings', []);
            if (empty($courses)) $courses = PMCM_Core::get_default_courses();

            foreach ($_POST['rouleaux_config'] as $course_slug => $config) {
                $course_slug = sanitize_text_field($course_slug);
                if (!isset($courses[$course_slug])) continue;

                $mode = in_array($config['mode'] ?? '', ['none', 'early_bird_only', 'always']) ? $config['mode'] : 'none';
                $courses[$course_slug]['rouleaux_discount_mode']       = $mode;
                $courses[$course_slug]['rouleaux_early_bird_discount'] = absint($config['eb_discount'] ?? 0);
                $courses[$course_slug]['rouleaux_normal_discount']     = absint($config['normal_discount'] ?? 0);
                $courses[$course_slug]['rouleaux_show_field']          = isset($config['show_field']) && $config['show_field'] == '1';
                $courses[$course_slug]['rouleaux_eligible']            = ($mode !== 'none');
            }

            update_option('pmcm_course_mappings', $courses);
            PMCM_Core::clear_cache();
            PMCM_Core::log_activity('Rouleaux per-course settings updated', 'success');
        }
    }

    /**
     * Display activity log
     */
    private static function display_activity_log()
    {
        $log = get_option('wcem_activity_log', []);

        if (empty($log)) {
            echo '<p>' . __('No activity recorded yet.', 'prepmedico-course-management') . '</p>';
            return;
        }

        echo '<div style="max-height:400px;overflow-y:auto;">';
        foreach (array_slice($log, 0, 30) as $entry) {
            $class = isset($entry['type']) ? $entry['type'] : 'info';
            echo '<div class="wcem-log-entry ' . esc_attr($class) . '">';
            echo '<span class="time">' . esc_html($entry['time']) . '</span> - ';
            echo esc_html($entry['message']);
            echo '</div>';
        }
        echo '</div>';
    }

    /**
     * Get available shortcodes for reference
     */
    public static function get_shortcode_reference()
    {
        return [
            [
                'shortcode' => '[current_edition course="frcs"]',
                'description' => __('Displays "12th - Current Edition" format', 'prepmedico-course-management'),
                'output' => __('12th - Current Edition', 'prepmedico-course-management')
            ],
            [
                'shortcode' => '[edition_number course="frcs"]',
                'description' => __('Displays just the ordinal number', 'prepmedico-course-management'),
                'output' => __('12th', 'prepmedico-course-management')
            ],
            [
                'shortcode' => '[edition_info course="frcs"]',
                'description' => __('Edition with enrollment period dates', 'prepmedico-course-management'),
                'output' => __('12th - Current Edition + dates', 'prepmedico-course-management')
            ],
            [
                'shortcode' => '[registration_status course="frcs"]',
                'description' => __('Displays status badge (Live/Closed/Opening Soon/Early Bird)', 'prepmedico-course-management'),
                'output' => __('Registration is Live / Early Bird Registration Open', 'prepmedico-course-management')
            ],
            [
                'shortcode' => '[early_bird_message course="frcs"]',
                'description' => __('Shows Early Bird end date message (if active)', 'prepmedico-course-management'),
                'output' => __('Early Bird Offer Valid Until January 15, 2025', 'prepmedico-course-management')
            ],
            [
                'shortcode' => '[course_registration_info course="frcs"]',
                'description' => __('Complete info: edition + dates + status + early bird', 'prepmedico-course-management'),
                'output' => __('Full registration info box', 'prepmedico-course-management')
            ]
        ];
    }

    /**
     * Get course status info for display
     */
    private static function get_course_status_info($course)
    {
        $prefix = $course['settings_prefix'];
        $current = get_option($prefix . 'current_edition', 1);
        $start = get_option($prefix . 'edition_start', '');
        $end = get_option($prefix . 'edition_end', '');
        $early_bird = get_option($prefix . 'early_bird_enabled', 'no');
        $eb_start   = get_option($prefix . 'early_bird_start', '');
        $eb_end     = get_option($prefix . 'early_bird_end', '');
        $override   = self::get_registration_override($prefix);

        $today_ts = strtotime(current_time('Y-m-d'));
        $start_ts = !empty($start) ? strtotime($start) : null;
        $end_ts   = !empty($end)   ? strtotime($end)   : null;
        $eb_s_ts  = !empty($eb_start) ? strtotime($eb_start) : null;
        $eb_e_ts  = !empty($eb_end)   ? strtotime($eb_end)   : null;

        // Override wins (it self-cancels once dates catch up, so this only fires when really active)
        if ($override === 'force_open') {
            $status = 'forced-open';
            $status_label = __('Forced Open', 'prepmedico-course-management');
        } elseif ($override === 'force_closed') {
            $status = 'forced-closed';
            $status_label = __('Forced Closed', 'prepmedico-course-management');
        } elseif (empty($start) || empty($end)) {
            $status = 'needs-dates';
            $status_label = __('Needs Dates', 'prepmedico-course-management');
        } elseif ($end_ts && $today_ts > $end_ts) {
            // Past end → either rollover pending or just closed
            $next_enabled = get_option($prefix . 'next_enabled', 'no') === 'yes';
            $status = $next_enabled ? 'awaiting-next' : 'expired';
            $status_label = $next_enabled ? __('Awaiting Rollover', 'prepmedico-course-management') : __('Closed', 'prepmedico-course-management');
        } elseif ($early_bird === 'yes' && $eb_e_ts && (!$eb_s_ts || $today_ts >= $eb_s_ts) && $today_ts <= $eb_e_ts) {
            $status = 'early-bird';
            $status_label = __('Early Bird Live', 'prepmedico-course-management');
        } elseif ($start_ts && $today_ts < $start_ts) {
            $status = 'opening-soon';
            $status_label = __('Opening Soon', 'prepmedico-course-management');
        } else {
            $status = 'active';
            $status_label = __('Registration Live', 'prepmedico-course-management');
            if ($end_ts && (($end_ts - $today_ts) / DAY_IN_SECONDS) <= 7) {
                $status = 'ending-soon';
                $status_label = __('Ending Soon', 'prepmedico-course-management');
            }
        }

        return [
            'status' => $status,
            'label' => $status_label,
            'edition_number' => $current,
            'edition_ordinal' => PMCM_Core::get_ordinal($current),
            'start' => $start,
            'end' => $end
        ];
    }

    /**
     * Render Edition Management admin page
     */
    public static function render_edition_page()
    {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        if (isset($_POST['wcem_save_settings']) && check_admin_referer('wcem_settings_nonce')) {
            self::save_settings();
            PMCM_Cron::check_and_update_editions();
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Settings saved! Edition check completed - see Activity Log for details.', 'prepmedico-course-management') . '</p></div>';
        }

        if (isset($_POST['wcem_save_notifications']) && check_admin_referer('wcem_notifications_nonce')) {
            self::save_notification_settings();
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Notification settings saved.', 'prepmedico-course-management') . '</p></div>';
        }

        if (isset($_GET['clear_log']) && wp_verify_nonce($_GET['_wpnonce'], 'clear_log')) {
            delete_option('wcem_activity_log');
            wp_safe_redirect(admin_url('admin.php?page=prepmedico-management'));
            exit;
        }

        $courses = PMCM_Core::get_edition_managed_courses();
        $course_keys = array_keys($courses);
        $first_course_slug = !empty($course_keys) ? $course_keys[0] : '';
?>
        <div class="wrap wcem-admin-wrap wcem-modern-layout">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <div class="wcem-two-column-container">
                <aside class="wcem-course-list-sidebar">
                    <div class="wcem-course-list-header">
                        <h2><?php _e('Course List', 'prepmedico-course-management'); ?></h2>
                        <button type="button" class="wcem-refresh-btn" onclick="location.reload()">
                            <span class="material-icons-round">refresh</span>
                        </button>
                    </div>
                    <div class="wcem-course-list-items">
                        <?php
                        $is_first = true;
                        foreach ($courses as $category_slug => $course):
                            $status_info = self::get_course_status_info($course);
                            $prefix = $course['settings_prefix'];
                            $current = get_option($prefix . 'current_edition', 1);
                        ?>
                            <button type="button" class="wcem-course-item<?php echo $is_first ? ' active' : ''; ?>" data-course="<?php echo esc_attr($category_slug); ?>">
                                <div class="wcem-course-item-header">
                                    <span class="wcem-course-name"><?php echo esc_html($course['name']); ?></span>
                                    <span class="wcem-course-status wcem-status-<?php echo esc_attr($status_info['status']); ?>"><?php echo esc_html($status_info['label']); ?></span>
                                </div>
                                <div class="wcem-course-item-footer">
                                    <span class="wcem-edition-label"><?php echo esc_html(PMCM_Core::get_ordinal($current) . ' Edition'); ?></span>
                                    <span class="material-icons-round">chevron_right</span>
                                </div>
                            </button>
                        <?php
                            $is_first = false;
                        endforeach;
                        ?>
                    </div>
                </aside>

                <main class="wcem-settings-panel">
                    <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=prepmedico-management')); ?>" id="wcem-edition-form" novalidate>
                        <?php wp_nonce_field('wcem_settings_nonce'); ?>
                        <input type="hidden" name="wcem_save_settings" value="1">
                        <?php
                        $is_first = true;
                        foreach ($courses as $category_slug => $course):
                            $prefix = $course['settings_prefix'];
                            $current = get_option($prefix . 'current_edition', 1);
                            $start = get_option($prefix . 'edition_start', '');
                            $end = get_option($prefix . 'edition_end', '');
                            $reg_override = self::get_registration_override($prefix);
                            $eb_enabled = get_option($prefix . 'early_bird_enabled', 'no') === 'yes';
                            $eb_start = get_option($prefix . 'early_bird_start', '');
                            $eb_end = get_option($prefix . 'early_bird_end', '');
                            $next_enabled = get_option($prefix . 'next_enabled', 'no') === 'yes';
                            $next_edition = max(1, intval(get_option($prefix . 'next_edition', $current + 1)));
                            $next_start = get_option($prefix . 'next_start', '');
                            $next_end = get_option($prefix . 'next_end', '');
                            $next_eb_enabled = get_option($prefix . 'next_early_bird_enabled', 'no') === 'yes';
                            $next_eb_start = get_option($prefix . 'next_early_bird_start', '');
                            $next_eb_end = get_option($prefix . 'next_early_bird_end', '');
                            $shortcode_display_next = get_option($prefix . 'shortcode_display_next', 'no') === 'yes';
                            $exam_dates = get_option($prefix . 'exam_dates', '');
                            $next_exam_dates = get_option($prefix . 'next_exam_dates', '');
                            $closed_cats_current = json_decode((string) get_option($prefix . 'closed_categories_current', '[]'), true);
                            if (!is_array($closed_cats_current)) { $closed_cats_current = []; }
                            $all_cats_for_course = array_merge([$course['category_slug']], !empty($course['children']) ? $course['children'] : []);
                            $status_info = self::get_course_status_info($course);
                        ?>
                            <div class="wcem-course-settings-panel wcem-card" data-course="<?php echo esc_attr($category_slug); ?>" style="<?php echo $is_first ? '' : 'display:none;'; ?>">
                                <div class="wcem-course-settings-header wcem-card-header">
                                    <div class="wcem-settings-title-group">
                                        <div>
                                            <p class="wcem-settings-course"><?php echo esc_html($course['name']); ?></p>
                                            <h3 class="wcem-settings-heading"><?php printf(__('Edition %s Settings', 'prepmedico-course-management'), esc_html($status_info['edition_ordinal'])); ?></h3>
                                        </div>
                                        <span class="wcem-course-status wcem-status-<?php echo esc_attr($status_info['status']); ?>"><?php echo esc_html($status_info['label']); ?></span>
                                    </div>
                                    <button type="submit" name="wcem_save_settings" value="1" class="wcem-save-btn">
                                        <span class="material-icons-round">save</span>
                                        <?php _e('Save Settings', 'prepmedico-course-management'); ?>
                                    </button>
                                </div>

                                <div class="wcem-course-settings-body wcem-card-body">
                                    <!-- Current Edition -->
                                    <section class="wcem-section">
                                        <div class="wcem-section-header">
                                            <div class="wcem-icon-box wcem-icon-indigo">
                                                <span class="material-icons-round">event</span>
                                            </div>
                                            <div>
                                                <h4><?php _e('Current Edition Configuration', 'prepmedico-course-management'); ?></h4>
                                                <p class="description"><?php _e('Set the active edition number and enrollment window.', 'prepmedico-course-management'); ?></p>
                                            </div>
                                        </div>
                                        <div class="wcem-fields-grid wcem-fields-3col">
                                            <div class="wcem-field">
                                                <label for="<?php echo esc_attr($prefix); ?>current_edition"><?php _e('Edition Number', 'prepmedico-course-management'); ?></label>
                                                <input type="number" id="<?php echo esc_attr($prefix); ?>current_edition" name="<?php echo esc_attr($prefix); ?>current_edition" value="<?php echo esc_attr($current); ?>" min="1">
                                            </div>
                                            <div class="wcem-field">
                                                <label for="<?php echo esc_attr($prefix); ?>edition_start"><?php _e('Start Date', 'prepmedico-course-management'); ?></label>
                                                <input type="date" id="<?php echo esc_attr($prefix); ?>edition_start" name="<?php echo esc_attr($prefix); ?>edition_start" value="<?php echo esc_attr($start); ?>">
                                            </div>
                                            <div class="wcem-field">
                                                <label for="<?php echo esc_attr($prefix); ?>edition_end"><?php _e('End Date', 'prepmedico-course-management'); ?></label>
                                                <input type="date" id="<?php echo esc_attr($prefix); ?>edition_end" name="<?php echo esc_attr($prefix); ?>edition_end" value="<?php echo esc_attr($end); ?>">
                                            </div>
                                        </div>

                                        <!-- Advanced: emergency registration override (collapsed by default) -->
                                        <details class="pmcm-advanced-toggle" <?php echo ($reg_override !== 'auto') ? 'open' : ''; ?>>
                                            <summary>
                                                <span class="pmcm-advanced-arrow material-icons-round">chevron_right</span>
                                                <span class="pmcm-advanced-title"><?php _e('Advanced — Emergency Override', 'prepmedico-course-management'); ?></span>
                                                <?php if ($reg_override !== 'auto'): ?>
                                                    <span class="pmcm-advanced-pill pmcm-advanced-pill-<?php echo esc_attr($reg_override); ?>">
                                                        <?php echo $reg_override === 'force_open' ? esc_html__('Forced Open', 'prepmedico-course-management') : esc_html__('Forced Closed', 'prepmedico-course-management'); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </summary>
                                            <div class="pmcm-advanced-body">
                                                <p class="description" style="margin:0 0 10px 0;"><?php _e('Leave on Auto — dates drive the status. Use override only in emergencies; it self-resets to Auto the moment dates catch up.', 'prepmedico-course-management'); ?></p>
                                                <select name="<?php echo esc_attr($prefix); ?>registration_override" class="pmcm-advanced-select">
                                                    <option value="auto" <?php selected($reg_override, 'auto'); ?>><?php _e('Auto (use dates) — default', 'prepmedico-course-management'); ?></option>
                                                    <option value="force_open" <?php selected($reg_override, 'force_open'); ?>><?php _e('Force Open — show Enrol now', 'prepmedico-course-management'); ?></option>
                                                    <option value="force_closed" <?php selected($reg_override, 'force_closed'); ?>><?php _e('Force Closed — show Opening Soon', 'prepmedico-course-management'); ?></option>
                                                </select>
                                                <p class="description" style="margin:8px 0 0 0;font-size:11px;color:#64748b;">
                                                    <span class="material-icons-round" style="font-size:13px;vertical-align:middle;">info</span>
                                                    <?php _e('Force Open auto-reverts to Auto once the Start date arrives. Force Closed auto-reverts after the End date.', 'prepmedico-course-management'); ?>
                                                </p>
                                            </div>
                                        </details>
                                    </section>

                                    <!-- Exam Dates -->
                                    <section class="wcem-section">
                                        <div class="wcem-section-header">
                                            <div class="wcem-icon-box" style="background:#fef3c7;color:#d97706;">
                                                <span class="material-icons-round">school</span>
                                            </div>
                                            <div>
                                                <h4><?php _e('Exam Dates', 'prepmedico-course-management'); ?></h4>
                                                <p class="description"><?php _e('Enter the exam dates shown on the frontend table. Free text — type exactly what should be displayed (e.g. "May 11-15 Birmingham AND June 22-23 KL, Malaysia").', 'prepmedico-course-management'); ?></p>
                                            </div>
                                        </div>
                                        <div class="wcem-fields-grid wcem-fields-2col">
                                            <div class="wcem-field">
                                                <label for="<?php echo esc_attr($prefix); ?>exam_dates"><?php _e('Current Edition Exam Dates', 'prepmedico-course-management'); ?></label>
                                                <input type="text" id="<?php echo esc_attr($prefix); ?>exam_dates" name="<?php echo esc_attr($prefix); ?>exam_dates" value="<?php echo esc_attr($exam_dates); ?>" placeholder="e.g. May 11-15 Birmingham AND June 22-23 KL, Malaysia" style="width:100%;">
                                            </div>
                                            <div class="wcem-field">
                                                <label for="<?php echo esc_attr($prefix); ?>next_exam_dates"><?php _e('Next Edition Exam Dates', 'prepmedico-course-management'); ?></label>
                                                <input type="text" id="<?php echo esc_attr($prefix); ?>next_exam_dates" name="<?php echo esc_attr($prefix); ?>next_exam_dates" value="<?php echo esc_attr($next_exam_dates); ?>" placeholder="e.g. TBA" style="width:100%;">
                                            </div>
                                        </div>
                                    </section>

                                    <!-- Close Categories (Current Edition) -->
                                    <section class="wcem-section">
                                        <div class="wcem-section-header">
                                            <div class="wcem-icon-box wcem-icon-rose" style="background:#fce7f3;color:#be185d;">
                                                <span class="material-icons-round">block</span>
                                            </div>
                                            <div>
                                                <h4><?php _e('Close Categories (Current Edition)', 'prepmedico-course-management'); ?></h4>
                                                <p class="description"><?php _e('Check any category to close it for the current edition. Closed categories block add-to-cart and show a "Closed" state in Elementor table buttons/status. Unchecked categories remain open.', 'prepmedico-course-management'); ?></p>
                                            </div>
                                        </div>
                                        <div class="pmcm-close-cat-grid">
                                            <?php foreach ($all_cats_for_course as $cat_slug):
                                                $is_parent = ($cat_slug === $course['category_slug']);
                                                $term = get_term_by('slug', $cat_slug, 'product_cat');
                                                $label = $term ? $term->name : $cat_slug;
                                                $is_checked = in_array($cat_slug, $closed_cats_current, true);
                                            ?>
                                                <label class="pmcm-close-cat-item<?php echo $is_checked ? ' is-checked' : ''; ?>">
                                                    <input type="checkbox" name="<?php echo esc_attr($prefix); ?>closed_categories_current[]" value="<?php echo esc_attr($cat_slug); ?>" <?php checked($is_checked, true); ?>>
                                                    <span class="pmcm-close-cat-label">
                                                        <?php echo esc_html($label); ?>
                                                        <?php if ($is_parent): ?>
                                                            <em class="pmcm-close-cat-tag"><?php esc_html_e('Parent', 'prepmedico-course-management'); ?></em>
                                                        <?php endif; ?>
                                                    </span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </section>

                                    <!-- Early Bird -->
                                    <section class="wcem-section wcem-early-bird-section">
                                        <div class="wcem-section-header wcem-section-header-toggle">
                                            <div class="wcem-section-title-group">
                                                <div class="wcem-icon-box wcem-icon-amber">
                                                    <span class="material-icons-round">local_fire_department</span>
                                                </div>
                                                <div>
                                                    <h4><?php _e('Early Bird Settings', 'prepmedico-course-management'); ?></h4>
                                                    <p class="description"><?php _e('Open and close Early Bird window for this edition.', 'prepmedico-course-management'); ?></p>
                                                </div>
                                            </div>
                                            <label class="wcem-toggle">
                                                <input type="checkbox" name="<?php echo esc_attr($prefix); ?>early_bird_enabled" value="yes" <?php checked($eb_enabled, true); ?> class="wcem-early-bird-toggle" data-course="<?php echo esc_attr($category_slug); ?>">
                                                <span class="wcem-toggle-slider"></span>
                                            </label>
                                        </div>
                                        <div class="wcem-early-bird-fields" style="<?php echo $eb_enabled ? '' : 'display:none;'; ?>" data-course="<?php echo esc_attr($category_slug); ?>">
                                            <div class="wcem-fields-grid wcem-fields-2col">
                                                <div class="wcem-field">
                                                    <label for="<?php echo esc_attr($prefix); ?>early_bird_start"><?php _e('Early Bird Start', 'prepmedico-course-management'); ?></label>
                                                    <input type="date" id="<?php echo esc_attr($prefix); ?>early_bird_start" name="<?php echo esc_attr($prefix); ?>early_bird_start" value="<?php echo esc_attr($eb_start); ?>">
                                                </div>
                                                <div class="wcem-field">
                                                    <label for="<?php echo esc_attr($prefix); ?>early_bird_end"><?php _e('Early Bird End', 'prepmedico-course-management'); ?></label>
                                                    <input type="date" id="<?php echo esc_attr($prefix); ?>early_bird_end" name="<?php echo esc_attr($prefix); ?>early_bird_end" value="<?php echo esc_attr($eb_end); ?>">
                                                </div>
                                            </div>
                                            <div class="wcem-info-banner">
                                                <span class="material-icons-round">info</span>
                                                <span><?php _e('Remember to align WooCommerce sale pricing with the Early Bird window.', 'prepmedico-course-management'); ?></span>
                                            </div>
                                            <div class="wcem-validation-info">
                                                <span class="material-icons-round">rule</span>
                                                <span><?php _e('Early Bird end date must be before or equal to Course Start date. Early Bird pricing only applies before the course begins.', 'prepmedico-course-management'); ?></span>
                                            </div>
                                        </div>
                                    </section>

                                    <!-- Next Edition -->
                                    <section class="wcem-section wcem-next-edition-section">
                                        <div class="wcem-section-header wcem-section-header-toggle">
                                            <div class="wcem-section-title-group">
                                                <div class="wcem-icon-box wcem-icon-blue">
                                                    <span class="material-icons-round">fast_forward</span>
                                                </div>
                                                <div>
                                                    <h4><?php _e('Next Edition (Slot B)', 'prepmedico-course-management'); ?></h4>
                                                    <p class="description"><?php _e('Preconfigure the upcoming edition; it will be promoted when enabled.', 'prepmedico-course-management'); ?></p>
                                                </div>
                                            </div>
                                            <label class="wcem-toggle">
                                                <input type="checkbox" name="<?php echo esc_attr($prefix); ?>next_enabled" value="yes" <?php checked($next_enabled, true); ?> class="wcem-next-edition-toggle">
                                                <span class="wcem-toggle-slider"></span>
                                            </label>
                                        </div>
                                        <div class="wcem-next-edition-fields" style="<?php echo $next_enabled ? '' : 'display:none;'; ?>">
                                            <div class="wcem-fields-grid wcem-fields-3col">
                                                <div class="wcem-field">
                                                    <label for="<?php echo esc_attr($prefix); ?>next_edition"><?php _e('Edition Number', 'prepmedico-course-management'); ?></label>
                                                    <input type="number" id="<?php echo esc_attr($prefix); ?>next_edition" name="<?php echo esc_attr($prefix); ?>next_edition" value="<?php echo esc_attr($next_edition); ?>" min="1">
                                                </div>
                                                <div class="wcem-field">
                                                    <label for="<?php echo esc_attr($prefix); ?>next_start"><?php _e('Start Date', 'prepmedico-course-management'); ?></label>
                                                    <input type="date" id="<?php echo esc_attr($prefix); ?>next_start" name="<?php echo esc_attr($prefix); ?>next_start" value="<?php echo esc_attr($next_start); ?>">
                                                </div>
                                                <div class="wcem-field">
                                                    <label for="<?php echo esc_attr($prefix); ?>next_end"><?php _e('End Date', 'prepmedico-course-management'); ?></label>
                                                    <input type="date" id="<?php echo esc_attr($prefix); ?>next_end" name="<?php echo esc_attr($prefix); ?>next_end" value="<?php echo esc_attr($next_end); ?>">
                                                </div>
                                            </div>

                                            <div class="wcem-next-eb-subsection">
                                                <div class="wcem-subsection-header">
                                                    <span><?php _e('Next Edition - Early Bird', 'prepmedico-course-management'); ?></span>
                                                    <label class="wcem-toggle wcem-toggle-small">
                                                        <input type="checkbox" name="<?php echo esc_attr($prefix); ?>next_early_bird_enabled" value="yes" <?php checked($next_eb_enabled, true); ?> class="wcem-next-early-bird-toggle">
                                                        <span class="wcem-toggle-slider"></span>
                                                    </label>
                                                </div>
                                                <div class="wcem-next-early-bird-fields" style="<?php echo $next_eb_enabled ? '' : 'display:none;'; ?>">
                                                    <div class="wcem-fields-grid wcem-fields-2col">
                                                        <div class="wcem-field">
                                                            <label for="<?php echo esc_attr($prefix); ?>next_early_bird_start"><?php _e('Early Bird Start', 'prepmedico-course-management'); ?></label>
                                                            <input type="date" id="<?php echo esc_attr($prefix); ?>next_early_bird_start" name="<?php echo esc_attr($prefix); ?>next_early_bird_start" value="<?php echo esc_attr($next_eb_start); ?>">
                                                        </div>
                                                        <div class="wcem-field">
                                                            <label for="<?php echo esc_attr($prefix); ?>next_early_bird_end"><?php _e('Early Bird End', 'prepmedico-course-management'); ?></label>
                                                            <input type="date" id="<?php echo esc_attr($prefix); ?>next_early_bird_end" name="<?php echo esc_attr($prefix); ?>next_early_bird_end" value="<?php echo esc_attr($next_eb_end); ?>">
                                                        </div>
                                                    </div>
                                                    <div class="wcem-validation-info" style="margin-top:10px; color:#666; font-size:12px;">
                                                        <span class="material-icons-round" style="font-size:14px; vertical-align:middle;">info</span>
                                                        <span><?php _e('Tip: Early Bird end should be before Next Edition Start date. Leave Early Bird Start empty to begin immediately.', 'prepmedico-course-management'); ?></span>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="wcem-next-eb-subsection" style="margin-top:12px;">
                                                <div class="wcem-subsection-header">
                                                    <div>
                                                        <span><?php _e('Frontend Shortcode Display', 'prepmedico-course-management'); ?></span>
                                                        <p class="description" style="margin-top:4px;font-size:12px;"><?php _e('When ON, shortcodes like [current_edition], registration status, dates and early bird will all display the next edition\'s data on the frontend.', 'prepmedico-course-management'); ?></p>
                                                    </div>
                                                    <label class="wcem-toggle wcem-toggle-small">
                                                        <input type="checkbox" name="<?php echo esc_attr($prefix); ?>shortcode_display_next" value="yes" <?php checked($shortcode_display_next, true); ?>>
                                                        <span class="wcem-toggle-slider"></span>
                                                    </label>
                                                </div>
                                            </div>

                                        </div>
                                    </section>

                                    <!-- Manual increment -->
                                    <section class="wcem-section">
                                        <button type="button" class="button wcem-manual-increment" data-course="<?php echo esc_attr($category_slug); ?>">
                                            <span class="material-icons-round" style="font-size:16px;vertical-align:middle;margin-right:4px;">add_circle</span>
                                            <?php _e('Increment Edition (+1)', 'prepmedico-course-management'); ?>
                                        </button>
                                        <p class="description" style="margin-top:8px;">&nbsp;<?php _e('Manually increment edition number. If "Next Edition" is enabled it will be promoted to Current.', 'prepmedico-course-management'); ?></p>
                                    </section>
                                </div>
                            </div>
                        <?php
                            $is_first = false;
                        endforeach;
                        ?>
                    </form>
                </main>
            </div>

            <!-- Bottom cards -->
            <div class="wcem-bottom-sections">
                <section class="wcem-card wcem-shortcodes-card">
                    <div class="wcem-card-header">
                        <div class="wcem-icon-box wcem-icon-emerald">
                            <span class="material-icons-round">code</span>
                        </div>
                        <h3><?php _e('Available Shortcodes', 'prepmedico-course-management'); ?></h3>
                    </div>
                    <div class="wcem-card-body wcem-shortcodes-body">
                        <div class="wcem-shortcode-list">
                            <h4 class="wcem-shortcode-group-title"><?php _e('Display Shortcodes', 'prepmedico-course-management'); ?></h4>
                            <div class="wcem-shortcode-item">
                                <code class="wcem-shortcode-code">[current_edition course="<span class="wcem-dynamic-course"><?php echo esc_html($first_course_slug); ?></span>"]</code>
                                <p class="wcem-shortcode-desc"><?php _e('Displays the current edition name in standard format (e.g. "12th - Current Edition").', 'prepmedico-course-management'); ?></p>
                            </div>
                            <div class="wcem-shortcode-item">
                                <code class="wcem-shortcode-code">[edition_number course="<span class="wcem-dynamic-course"><?php echo esc_html($first_course_slug); ?></span>"]</code>
                                <p class="wcem-shortcode-desc"><?php _e('Displays just the edition ordinal number (e.g. "12th").', 'prepmedico-course-management'); ?></p>
                            </div>
                            <div class="wcem-shortcode-item">
                                <code class="wcem-shortcode-code">[edition_info course="<span class="wcem-dynamic-course"><?php echo esc_html($first_course_slug); ?></span>" show_dates="yes"]</code>
                                <p class="wcem-shortcode-desc"><?php _e('Shows edition info box with name and enrollment dates.', 'prepmedico-course-management'); ?></p>
                            </div>
                            <div class="wcem-shortcode-item">
                                <code class="wcem-shortcode-code">[registration_status course="<span class="wcem-dynamic-course"><?php echo esc_html($first_course_slug); ?></span>"]</code>
                                <p class="wcem-shortcode-desc"><?php _e('Shows status badges (Live / Closed / Early Bird / Opening Soon).', 'prepmedico-course-management'); ?></p>
                            </div>
                            <div class="wcem-shortcode-item">
                                <code class="wcem-shortcode-code">[early_bird_message course="<span class="wcem-dynamic-course"><?php echo esc_html($first_course_slug); ?></span>"]</code>
                                <p class="wcem-shortcode-desc"><?php _e('Shows early bird offer message with end date (only visible during early bird period).', 'prepmedico-course-management'); ?></p>
                            </div>
                            <div class="wcem-shortcode-item">
                                <code class="wcem-shortcode-code">[course_registration_info course="<span class="wcem-dynamic-course"><?php echo esc_html($first_course_slug); ?></span>"]</code>
                                <p class="wcem-shortcode-desc"><?php _e('Complete registration info box with edition name, dates, status badge, and early bird message.', 'prepmedico-course-management'); ?></p>
                            </div>
                            <div class="wcem-shortcode-item">
                                <code class="wcem-shortcode-code">[edition_dates course="<span class="wcem-dynamic-course"><?php echo esc_html($first_course_slug); ?></span>"]</code>
                                <p class="wcem-shortcode-desc"><?php _e('Displays the current edition date range as plain text (e.g. "01 Jan 2025 – 28 Feb 2025"). Returns "TBA" when no dates are set.', 'prepmedico-course-management'); ?></p>
                            </div>

                            <h4 class="wcem-shortcode-group-title" style="margin-top: 16px;"><?php _e('Elementor Table / Frontend Shortcodes', 'prepmedico-course-management'); ?></h4>
                            <div class="wcem-shortcode-item">
                                <code class="wcem-shortcode-code">[pmcm_edition_ordinal course="<span class="wcem-dynamic-course"><?php echo esc_html($first_course_slug); ?></span>" slot="current"]</code>
                                <p class="wcem-shortcode-desc"><?php _e('Edition ordinal (e.g. "12th"). Use slot="next" for next edition. Falls back to current+1 if next is not configured.', 'prepmedico-course-management'); ?></p>
                            </div>
                            <div class="wcem-shortcode-item">
                                <code class="wcem-shortcode-code">[pmcm_edition_dates course="<span class="wcem-dynamic-course"><?php echo esc_html($first_course_slug); ?></span>" slot="current" format="range"]</code>
                                <p class="wcem-shortcode-desc"><?php _e('Edition dates. format: "range", "start", "end". Returns "TBA" if next edition dates unavailable.', 'prepmedico-course-management'); ?></p>
                            </div>
                            <div class="wcem-shortcode-item">
                                <code class="wcem-shortcode-code">[pmcm_edition_status course="<span class="wcem-dynamic-course"><?php echo esc_html($first_course_slug); ?></span>" slot="current" output="text"]</code>
                                <p class="wcem-shortcode-desc"><?php _e('Registration status as text (open/closed/upcoming/dates-tba) or CSS class (output="class").', 'prepmedico-course-management'); ?></p>
                            </div>
                            <div class="wcem-shortcode-item">
                                <code class="wcem-shortcode-code">[pmcm_edition_button course="<span class="wcem-dynamic-course"><?php echo esc_html($first_course_slug); ?></span>" slot="current" product="<?php echo esc_html($first_course_slug); ?>-course" text="Enrol"]</code>
                                <p class="wcem-shortcode-desc"><?php _e('Enrol button with edition URL. Auto-disables when closed or dates TBA.', 'prepmedico-course-management'); ?></p>
                            </div>
                            <div class="wcem-shortcode-item">
                                <code class="wcem-shortcode-code">[pmcm_edition_url course="<span class="wcem-dynamic-course"><?php echo esc_html($first_course_slug); ?></span>" slot="current" product="<?php echo esc_html($first_course_slug); ?>-course"]</code>
                                <p class="wcem-shortcode-desc"><?php _e('Returns raw product URL with ?edition= parameter. Use inside href attributes.', 'prepmedico-course-management'); ?></p>
                            </div>
                            <div class="wcem-shortcode-item">
                                <code class="wcem-shortcode-code">[pmcm_edition_number_raw course="<span class="wcem-dynamic-course"><?php echo esc_html($first_course_slug); ?></span>" slot="current"]</code>
                                <p class="wcem-shortcode-desc"><?php _e('Raw edition number without ordinal suffix (e.g. "12"). Useful for data attributes.', 'prepmedico-course-management'); ?></p>
                            </div>
                            <div class="wcem-shortcode-item">
                                <code class="wcem-shortcode-code">[pmcm_edition_marker course="<span class="wcem-dynamic-course"><?php echo esc_html($first_course_slug); ?></span>" slot="current"]</code>
                                <p class="wcem-shortcode-desc"><?php _e('Hidden marker span with edition data. Place inside Elementor toggle button containers.', 'prepmedico-course-management'); ?></p>
                            </div>
                            <div class="wcem-shortcode-item">
                                <code class="wcem-shortcode-code">[pmcm_edition_product_script]</code>
                                <p class="wcem-shortcode-desc"><?php _e('JavaScript for handling edition parameter on product links with data-pmcm-edition buttons.', 'prepmedico-course-management'); ?></p>
                            </div>
                            <div class="wcem-shortcode-item">
                                <code class="wcem-shortcode-code">[pmcm_edition_products_script]</code>
                                <p class="wcem-shortcode-desc"><?php _e('Simplified JS for edition links. Works with pmcm_edition_marker - no data attributes needed on buttons.', 'prepmedico-course-management'); ?></p>
                            </div>
                            <div class="wcem-shortcode-item">
                                <code class="wcem-shortcode-code">[pmcm_exam_dates course="<span class="wcem-dynamic-course"><?php echo esc_html($first_course_slug); ?></span>" slot="current"]</code>
                                <p class="wcem-shortcode-desc"><?php _e('Displays the free-text exam dates entered in settings (e.g. "May 11-15 Birmingham"). Use slot="next" for next edition. Returns "TBA" when empty.', 'prepmedico-course-management'); ?></p>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Notification Settings -->
                <section class="wcem-card">
                    <div class="wcem-card-header">
                        <div class="wcem-icon-box" style="background:#fef3c7;color:#d97706;">
                            <span class="material-icons-round">notifications_active</span>
                        </div>
                        <h3><?php _e('Email Notification Settings', 'prepmedico-course-management'); ?></h3>
                    </div>
                    <div class="wcem-card-body">
                        <form method="post" action="">
                            <?php wp_nonce_field('wcem_notifications_nonce'); ?>
                            <input type="hidden" name="wcem_save_notifications" value="1">

                            <!-- Recipients -->
                            <div class="wcem-section">
                                <div class="wcem-section-header">
                                    <div class="wcem-icon-box" style="background:#e0f2fe;color:#0369a1;">
                                        <span class="material-icons-round">group</span>
                                    </div>
                                    <div>
                                        <h4><?php _e('Notification Recipients', 'prepmedico-course-management'); ?></h4>
                                        <p class="description"><?php _e('The site admin email is always included. Add extra addresses below.', 'prepmedico-course-management'); ?></p>
                                    </div>
                                </div>
                                <div class="wcem-field" style="margin-top:12px;">
                                    <label for="pmcm_notification_emails"><?php _e('Additional Recipients', 'prepmedico-course-management'); ?></label>
                                    <input type="text" id="pmcm_notification_emails" name="pmcm_notification_emails" value="<?php echo esc_attr(get_option('pmcm_notification_emails', '')); ?>" placeholder="editor@example.com, manager@example.com" style="width:100%;margin-top:4px;">
                                    <p class="description" style="margin-top:6px;"><?php printf(__('Comma-separated emails. Admin email <strong>%s</strong> is always notified.', 'prepmedico-course-management'), esc_html(get_option('admin_email'))); ?></p>
                                </div>
                            </div>

                            <!-- Triggers -->
                            <div class="wcem-section" style="margin-top:20px;">
                                <div class="wcem-section-header">
                                    <div class="wcem-icon-box" style="background:#f0fdf4;color:#16a34a;">
                                        <span class="material-icons-round">bolt</span>
                                    </div>
                                    <div>
                                        <h4><?php _e('Email Triggers', 'prepmedico-course-management'); ?></h4>
                                        <p class="description"><?php _e('Select which events send an email notification to all recipients.', 'prepmedico-course-management'); ?></p>
                                    </div>
                                </div>
                                <?php
                                $notif_triggers = json_decode((string) get_option('pmcm_notification_triggers', '[]'), true);
                                if (!is_array($notif_triggers)) $notif_triggers = [];
                                $trigger_opts = [
                                    'reg_force_open'   => ['color' => '#16a34a', 'label' => __('Registration → Force Open',   'prepmedico-course-management'), 'desc' => __('Send email when any course registration override is set to Force Open.',          'prepmedico-course-management')],
                                    'reg_force_closed' => ['color' => '#dc2626', 'label' => __('Registration → Force Closed', 'prepmedico-course-management'), 'desc' => __('Send email when any course registration override is set to Force Closed (Opening Soon).', 'prepmedico-course-management')],
                                    'reg_auto'         => ['color' => '#2563eb', 'label' => __('Registration → Auto',         'prepmedico-course-management'), 'desc' => __('Send email when any course registration override is set back to Auto (date-based).', 'prepmedico-course-management')],
                                    'edition_switch'   => ['color' => '#7c3aed', 'label' => __('Edition Auto-Switch',         'prepmedico-course-management'), 'desc' => __('Send email when the daily cron auto-increments or promotes an edition.',           'prepmedico-course-management')],
                                ];
                                ?>
                                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:12px;">
                                    <?php foreach ($trigger_opts as $val => $opt): ?>
                                    <label style="display:flex;align-items:flex-start;gap:8px;padding:10px 12px;border:1.5px solid <?php echo in_array($val, $notif_triggers) ? esc_attr($opt['color']) : '#e2e8f0'; ?>;border-radius:8px;cursor:pointer;background:<?php echo in_array($val, $notif_triggers) ? 'rgba(0,0,0,0.02)' : '#f8fafc'; ?>;">
                                        <input type="checkbox" name="pmcm_notification_triggers[]" value="<?php echo esc_attr($val); ?>" <?php checked(in_array($val, $notif_triggers)); ?> style="margin-top:2px;flex-shrink:0;">
                                        <div>
                                            <span style="font-size:12px;font-weight:700;color:<?php echo esc_attr($opt['color']); ?>;"><?php echo esc_html($opt['label']); ?></span>
                                            <p class="description" style="font-size:11px;margin-top:2px;line-height:1.4;"><?php echo esc_html($opt['desc']); ?></p>
                                        </div>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div style="margin-top:16px;text-align:right;">
                                <button type="submit" class="wcem-save-btn">
                                    <span class="material-icons-round">save</span>
                                    <?php _e('Save Notification Settings', 'prepmedico-course-management'); ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </section>

                <section class="wcem-card wcem-fluentcrm-card">
                    <div class="wcem-card-header">
                        <div class="wcem-icon-box wcem-icon-indigo">
                            <span class="material-icons-round">sync</span>
                        </div>
                        <h3><?php _e('FluentCRM Integration', 'prepmedico-course-management'); ?></h3>
                        <?php if (PMCM_FluentCRM::is_active()): ?>
                            <span class="wcem-connected-badge">
                                <span class="material-icons-round">check_circle</span>
                                <?php _e('CONNECTED', 'prepmedico-course-management'); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="wcem-card-body">
                        <div class="wcem-fluentcrm-info">
                            <div class="wcem-fluentcrm-row">
                                <span class="wcem-dot wcem-dot-blue"></span>
                                <span class="wcem-fluentcrm-label"><?php _e('Custom Fields Mapping', 'prepmedico-course-management'); ?></span>
                                <span class="wcem-fluentcrm-count"><?php echo count(PMCM_Core::get_courses()); ?> <?php _e('TOTAL', 'prepmedico-course-management'); ?></span>
                            </div>
                            <div class="wcem-fluentcrm-tags">
                                <?php foreach (array_slice(PMCM_Core::get_courses(), 0, 3) as $course): ?>
                                    <span class="wcem-tag"><?php echo esc_html($course['fluentcrm_field']); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php if (PMCM_FluentCRM::is_active()): ?>
                            <button type="button" class="wcem-btn-secondary" id="wcem-test-fluentcrm">
                                <?php _e('Test Connection', 'prepmedico-course-management'); ?>
                            </button>
                        <?php endif; ?>
                    </div>
                </section>
            </div>

            <section class="wcem-card wcem-activity-log-card">
                <div class="wcem-card-header">
                    <h3><?php _e('Recent Activity Log', 'prepmedico-course-management'); ?></h3>
                </div>
                <div class="wcem-card-body wcem-activity-log-body">
                    <?php
                    $log = get_option('wcem_activity_log', []);
                    if (!empty($log)):
                        foreach (array_slice($log, 0, 10) as $entry):
                    ?>
                            <div class="wcem-log-entry">
                                <div class="wcem-log-time"><?php echo esc_html($entry['time']); ?></div>
                                <div class="wcem-log-message"><?php echo esc_html($entry['message']); ?></div>
                                <span class="material-icons-round wcem-log-icon wcem-log-<?php echo esc_attr($entry['type'] ?? 'info'); ?>">
                                    <?php echo ($entry['type'] ?? 'info') === 'success' ? 'check_circle' : (($entry['type'] ?? 'info') === 'error' ? 'error' : 'info'); ?>
                                </span>
                            </div>
                        <?php
                        endforeach;
                    else:
                        ?>
                        <p class="wcem-no-log"><?php _e('No activity recorded yet.', 'prepmedico-course-management'); ?></p>
                    <?php endif; ?>
                </div>
            </section>

            <section class="wcem-card wcem-cron-card">
                <div class="wcem-card-header">
                    <h3><?php _e('Scheduled Tasks', 'prepmedico-course-management'); ?></h3>
                </div>
                <div class="wcem-card-body">
                    <?php
                    $next_run = wp_next_scheduled('wcem_daily_edition_check');
                    if ($next_run):
                        $next_run_local = get_date_from_gmt(date('Y-m-d H:i:s', $next_run), 'F j, Y g:i a');
                    ?>
                        <p><?php printf(__('Next edition check: %s', 'prepmedico-course-management'), '<strong>' . esc_html($next_run_local) . '</strong>'); ?></p>
                    <?php else: ?>
                        <p class="wcem-warning"><?php _e('Cron job not scheduled. Please deactivate and reactivate the plugin.', 'prepmedico-course-management'); ?></p>
                    <?php endif; ?>
                    <button type="button" class="wcem-btn-secondary" id="wcem-run-cron">
                        <?php _e('Run Edition Check Now', 'prepmedico-course-management'); ?>
                    </button>
                </div>
            </section>
        </div>

        <!-- Debug info -->
        <script type="text/javascript">
            console.log('PMCM Admin v2.5.0 - Form validation active');
        </script>
    <?php
    }
    /**
     * Render ASiT Coupon Management admin page
     */
    public static function render_asit_page()
    {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        if (isset($_POST['pmcm_save_asit_settings']) && check_admin_referer('pmcm_asit_settings_nonce')) {
            self::save_asit_settings();
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Academic Partners settings saved successfully!', 'prepmedico-course-management') . '</p></div>';
        }

        $default_discount = get_option('pmcm_asit_discount_normal', 15);
        $all_courses = PMCM_Core::get_courses();
    ?>
        <div class="wrap wcem-admin-wrap wcem-asit-modern">
            <!-- Header -->
            <header class="wcem-asit-header">
                <div class="wcem-asit-header-content">
                    <span class="material-icons-round wcem-asit-icon">local_offer</span>
                    <h1><?php _e('Academic Partners', 'prepmedico-course-management'); ?></h1>
                </div>
            </header>

            <!-- Global Partner Discount Configuration -->
            <form method="post" action="" id="wcem-asit-form">
                <?php wp_nonce_field('pmcm_asit_settings_nonce'); ?>

                <section class="wcem-asit-global-config">
                    <div class="wcem-asit-global-header">
                        <div class="wcem-asit-global-icon">
                            <span class="material-icons-round">settings</span>
                        </div>
                        <h2><?php _e('Global Discount Configuration', 'prepmedico-course-management'); ?></h2>
                        <button type="submit" name="pmcm_save_asit_settings" class="wcem-asit-save-global">
                            <span class="material-icons-round">save</span>
                            <?php _e('Save Global Settings', 'prepmedico-course-management'); ?>
                        </button>
                    </div>
                    <div class="wcem-asit-global-body">
                        <div class="wcem-asit-global-field">
                            <label><?php _e('Default Discount Percentage', 'prepmedico-course-management'); ?></label>
                            <div class="wcem-asit-input-with-note">
                                <input type="number" name="pmcm_asit_discount_normal" value="<?php echo esc_attr($default_discount); ?>" min="0" max="100" class="wcem-asit-discount-input">
                                <span class="wcem-asit-percent">%</span>
                            </div>
                            <p class="wcem-asit-field-note"><?php _e('Default value applied to all courses unless specified below.', 'prepmedico-course-management'); ?></p>
                        </div>
                    </div>
                </section>

                <!-- Per-Course Discount Status Section -->
                <section class="wcem-asit-courses-section">
                    <div class="wcem-asit-courses-header">
                        <div>
                            <h2><?php _e('Per-Course Discount Status', 'prepmedico-course-management'); ?></h2>
                            <p class="wcem-asit-subtitle"><?php _e('Manage granular discount logic and frontend visibility per course module.', 'prepmedico-course-management'); ?></p>
                        </div>
                        <div class="wcem-asit-courses-actions">
                            <div class="wcem-asit-view-toggle">
                                <button type="button" class="wcem-asit-view-btn active" data-view="grid">
                                    <span class="material-icons-round">grid_view</span>
                                </button>
                                <button type="button" class="wcem-asit-view-btn" data-view="list">
                                    <span class="material-icons-round">view_list</span>
                                </button>
                            </div>
                            <div class="wcem-asit-sort-dropdown">
                                <span><?php _e('Sort:', 'prepmedico-course-management'); ?></span>
                                <select id="wcem-asit-sort">
                                    <option value="alpha"><?php _e('A-Z Alphabetical', 'prepmedico-course-management'); ?></option>
                                    <option value="status"><?php _e('By Status', 'prepmedico-course-management'); ?></option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Course Cards Grid -->
                    <div class="wcem-asit-courses-grid" id="wcem-asit-courses-grid">
                        <?php foreach ($all_courses as $slug => $course):
                            $mode = isset($course['asit_discount_mode']) ? $course['asit_discount_mode'] : 'none';
                            $eb_discount = isset($course['asit_early_bird_discount']) ? intval($course['asit_early_bird_discount']) : 0;
                            $normal_discount = isset($course['asit_normal_discount']) ? intval($course['asit_normal_discount']) : 0;
                            $show_field = isset($course['asit_show_field']) ? $course['asit_show_field'] : false;
                            $product_filter = isset($course['asit_product_filter']) ? (bool) $course['asit_product_filter'] : false;
                            $selected_products = isset($course['asit_selected_products']) ? (array) $course['asit_selected_products'] : [];
                            $is_eb_active = PMCM_Core::is_course_early_bird_active($slug);
                            $edition_scope = PMCM_Core::normalize_asit_edition_scope(
                                isset($course['asit_edition_scope']) ? $course['asit_edition_scope'] : '',
                                $mode
                            );
                            $is_next_eb_active = PMCM_Core::is_next_edition_early_bird_active($slug);
                            $prefix = isset($course['settings_prefix']) ? $course['settings_prefix'] : '';
                            $current_eb_end = $prefix ? get_option($prefix . 'early_bird_end', '') : '';
                            $next_eb_end    = $prefix ? get_option($prefix . 'next_early_bird_end', '') : '';

                            // Determine status badge
                            $status_class = 'inactive';
                            $status_label = __('Inactive', 'prepmedico-course-management');
                            if ($mode === 'always') {
                                $status_class = 'active';
                                $status_label = __('Active', 'prepmedico-course-management');
                            } elseif ($mode === 'early_bird_only') {
                                $status_class = 'early-bird';
                                $status_label = __('Early Bird Only', 'prepmedico-course-management');
                            }

                            // Current discount
                            $current_discount = 0;
                            if ($mode === 'always') {
                                $current_discount = $normal_discount;
                            } elseif ($mode === 'early_bird_only' && $is_eb_active) {
                                $current_discount = $eb_discount;
                            }

                            // Get products for this course
                            $course_products = get_posts([
                                'post_type' => 'product',
                                'numberposts' => -1,
                                'orderby' => 'title',
                                'order' => 'ASC',
                                'tax_query' => [
                                    [
                                        'taxonomy' => 'product_cat',
                                        'field' => 'slug',
                                        'terms' => [$slug],
                                        'include_children' => true
                                    ]
                                ]
                            ]);
                        ?>
                            <div class="wcem-asit-course-card" data-course="<?php echo esc_attr($slug); ?>" data-status="<?php echo esc_attr($status_class); ?>">
                                <div class="wcem-asit-card-header">
                                    <div class="wcem-asit-card-title">
                                        <h3><?php echo esc_html($course['name']); ?></h3>
                                        <span class="wcem-asit-status-badge <?php echo esc_attr($status_class); ?>"><?php echo esc_html($status_label); ?></span>
                                    </div>
                                    <span class="wcem-asit-card-category"><?php echo esc_html(strtoupper($slug)); ?></span>
                                </div>

                                <div class="wcem-asit-card-body">
                                    <!-- Discount Mode Toggle -->
                                    <div class="wcem-asit-mode-section">
                                        <label class="wcem-asit-mode-label"><?php _e('Discount Mode', 'prepmedico-course-management'); ?></label>
                                        <div class="wcem-asit-mode-toggle" data-course="<?php echo esc_attr($slug); ?>">
                                            <button type="button" class="wcem-asit-mode-btn <?php echo ($mode === 'none') ? 'active' : ''; ?>" data-mode="none">
                                                <?php _e('No Discount', 'prepmedico-course-management'); ?>
                                            </button>
                                            <button type="button" class="wcem-asit-mode-btn <?php echo ($mode === 'early_bird_only') ? 'active' : ''; ?>" data-mode="early_bird_only">
                                                <?php _e('Early Bird', 'prepmedico-course-management'); ?>
                                            </button>
                                            <button type="button" class="wcem-asit-mode-btn <?php echo ($mode === 'always') ? 'active' : ''; ?>" data-mode="always">
                                                <?php _e('Always Active', 'prepmedico-course-management'); ?>
                                            </button>
                                        </div>
                                        <input type="hidden" name="asit_config[<?php echo esc_attr($slug); ?>][mode]" value="<?php echo esc_attr($mode); ?>" class="wcem-asit-mode-input" data-course="<?php echo esc_attr($slug); ?>">
                                    </div>

                                    <!-- Edition Scope (only for Early Bird mode) -->
                                    <div class="wcem-asit-edition-scope-section" data-course="<?php echo esc_attr($slug); ?>" style="<?php echo ($mode !== 'early_bird_only') ? 'display:none;' : ''; ?>">
                                        <label class="wcem-asit-mode-label"><?php _e('Apply To Edition', 'prepmedico-course-management'); ?></label>
                                        <div class="wcem-asit-edition-scope-toggle" data-course="<?php echo esc_attr($slug); ?>">
                                            <button type="button" class="wcem-asit-scope-btn <?php echo ($edition_scope === 'current') ? 'active' : ''; ?>" data-scope="current">
                                                <?php _e('Current', 'prepmedico-course-management'); ?>
                                            </button>
                                            <button type="button" class="wcem-asit-scope-btn <?php echo ($edition_scope === 'next') ? 'active' : ''; ?>" data-scope="next">
                                                <?php _e('Next', 'prepmedico-course-management'); ?>
                                            </button>
                                            <button type="button" class="wcem-asit-scope-btn <?php echo ($edition_scope === 'both') ? 'active' : ''; ?>" data-scope="both">
                                                <?php _e('Both', 'prepmedico-course-management'); ?>
                                            </button>
                                        </div>
                                        <input type="hidden" name="asit_config[<?php echo esc_attr($slug); ?>][edition_scope]" value="<?php echo esc_attr($edition_scope); ?>" class="wcem-asit-scope-input" data-course="<?php echo esc_attr($slug); ?>">
                                    </div>

                                    <!-- Discount Percentage -->
                                    <div class="wcem-asit-discount-section" data-course="<?php echo esc_attr($slug); ?>" style="<?php echo ($mode === 'none') ? 'display:none;' : ''; ?>">
                                        <div class="wcem-asit-discount-row">
                                            <div class="wcem-asit-discount-field">
                                                <label><?php _e('Discount %', 'prepmedico-course-management'); ?></label>
                                                <div class="wcem-asit-discount-input-wrap">
                                                    <?php if ($mode === 'early_bird_only'): ?>
                                                        <input type="number" name="asit_config[<?php echo esc_attr($slug); ?>][eb_discount]" value="<?php echo esc_attr($eb_discount); ?>" min="0" max="100" class="wcem-asit-card-discount-input">
                                                    <?php else: ?>
                                                        <input type="number" name="asit_config[<?php echo esc_attr($slug); ?>][normal_discount]" value="<?php echo esc_attr($normal_discount); ?>" min="0" max="100" class="wcem-asit-card-discount-input">
                                                    <?php endif; ?>
                                                    <span>%</span>
                                                </div>
                                            </div>
                                            <div class="wcem-asit-toggles">
                                                <label class="wcem-asit-toggle-item">
                                                    <span><?php _e('Show ASiT Field', 'prepmedico-course-management'); ?></span>
                                                    <input type="checkbox" name="asit_config[<?php echo esc_attr($slug); ?>][show_field]" value="1" <?php checked($show_field, true); ?> class="wcem-asit-toggle-checkbox">
                                                    <span class="wcem-asit-toggle-slider"></span>
                                                </label>
                                                <label class="wcem-asit-toggle-item">
                                                    <span><?php _e('Product Filtering', 'prepmedico-course-management'); ?></span>
                                                    <input type="checkbox" name="asit_config[<?php echo esc_attr($slug); ?>][product_filter]" value="1" <?php checked($product_filter, true); ?> class="wcem-asit-toggle-checkbox wcem-asit-product-filter-toggle" data-course="<?php echo esc_attr($slug); ?>">
                                                    <span class="wcem-asit-toggle-slider"></span>
                                                </label>
                                            </div>
                                        </div>

                                        <!-- Hidden Early Bird Discount (keep for form submission when mode changes) -->
                                        <?php if ($mode !== 'early_bird_only'): ?>
                                            <input type="hidden" name="asit_config[<?php echo esc_attr($slug); ?>][eb_discount]" value="<?php echo esc_attr($eb_discount); ?>">
                                        <?php endif; ?>
                                        <?php if ($mode !== 'always'): ?>
                                            <input type="hidden" name="asit_config[<?php echo esc_attr($slug); ?>][normal_discount]" value="<?php echo esc_attr($normal_discount); ?>">
                                        <?php endif; ?>

                                        <!-- Product Selection (collapsible) -->
                                        <div class="wcem-asit-product-selection" data-course="<?php echo esc_attr($slug); ?>" style="<?php echo ($product_filter) ? '' : 'display:none;'; ?>">
                                            <div class="wcem-asit-product-header">
                                                <button type="button" class="wcem-asit-toggle-products" data-course="<?php echo esc_attr($slug); ?>">
                                                    <span class="material-icons-round">expand_more</span>
                                                    <?php _e('Select Products', 'prepmedico-course-management'); ?>
                                                </button>
                                                <span class="wcem-asit-product-count" data-course="<?php echo esc_attr($slug); ?>">
                                                    <?php printf(__('%d / %d selected', 'prepmedico-course-management'), count($selected_products), count($course_products)); ?>
                                                </span>
                                            </div>
                                            <div class="wcem-asit-products-list" data-course="<?php echo esc_attr($slug); ?>" style="display:none;">
                                                <div class="wcem-asit-products-actions">
                                                    <button type="button" class="wcem-asit-select-all" data-course="<?php echo esc_attr($slug); ?>"><?php _e('Select All', 'prepmedico-course-management'); ?></button>
                                                    <button type="button" class="wcem-asit-deselect-all" data-course="<?php echo esc_attr($slug); ?>"><?php _e('Deselect All', 'prepmedico-course-management'); ?></button>
                                                </div>
                                                <div class="wcem-asit-products-scroll">
                                                    <?php if (empty($course_products)): ?>
                                                        <p class="wcem-asit-no-products"><?php _e('No products found.', 'prepmedico-course-management'); ?></p>
                                                    <?php else: ?>
                                                        <?php
                                                        $grouped = [];
                                                        foreach ($course_products as $product) {
                                                            $cats = wp_get_post_terms($product->ID, 'product_cat', ['fields' => 'all']);
                                                            $cat_name = $course['name'];
                                                            foreach ($cats as $cat) {
                                                                if ($cat->slug !== $slug) {
                                                                    $cat_name = $cat->name;
                                                                    break;
                                                                }
                                                            }
                                                            if (!isset($grouped[$cat_name])) {
                                                                $grouped[$cat_name] = [];
                                                            }
                                                            $grouped[$cat_name][] = $product;
                                                        }
                                                        ksort($grouped);
                                                        ?>
                                                        <?php foreach ($grouped as $cat_name => $products): ?>
                                                            <div class="wcem-asit-product-group">
                                                                <span class="wcem-asit-product-group-title"><?php echo esc_html($cat_name); ?></span>
                                                                <?php foreach ($products as $product): ?>
                                                                    <?php $is_selected = in_array($product->ID, array_map('intval', $selected_products), true); ?>
                                                                    <label class="wcem-asit-product-item">
                                                                        <input type="checkbox" class="wcem-asit-product-checkbox" data-course="<?php echo esc_attr($slug); ?>" name="asit_config[<?php echo esc_attr($slug); ?>][selected_products][]" value="<?php echo esc_attr($product->ID); ?>" <?php checked($is_selected); ?>>
                                                                        <span><?php echo esc_html(get_the_title($product)); ?></span>
                                                                    </label>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- BOMSS Sub-section -->
                                <?php
                                $b_mode   = isset($course['bomss_discount_mode']) ? $course['bomss_discount_mode'] : 'none';
                                $b_eb     = isset($course['bomss_early_bird_discount']) ? intval($course['bomss_early_bird_discount']) : 0;
                                $b_norm   = isset($course['bomss_normal_discount']) ? intval($course['bomss_normal_discount']) : 0;
                                $b_show   = isset($course['bomss_show_field']) ? (bool) $course['bomss_show_field'] : false;
                                ?>
                                <details class="wcem-partner-subsection wcem-partner-bomss" <?php echo ($b_mode !== 'none') ? 'open' : ''; ?>>
                                    <summary class="wcem-partner-summary">
                                        <span class="wcem-partner-summary-arrow material-icons-round">chevron_right</span>
                                        <span class="wcem-partner-name">BOMSS</span>
                                        <span class="wcem-asit-status-badge <?php echo ($b_mode !== 'none') ? 'active' : 'inactive'; ?>">
                                            <?php echo ($b_mode !== 'none') ? 'ON' : 'OFF'; ?>
                                        </span>
                                    </summary>
                                    <div class="wcem-partner-subsection-body">
                                        <div class="wcem-asit-mode-section">
                                            <label class="wcem-asit-mode-label"><?php _e('Discount Mode', 'prepmedico-course-management'); ?></label>
                                            <div class="wcem-asit-mode-toggle" data-partner="bomss" data-course="<?php echo esc_attr($slug); ?>">
                                                <button type="button" class="wcem-asit-mode-btn wcem-partner-mode-btn <?php echo ($b_mode === 'none') ? 'active' : ''; ?>" data-mode="none"><?php _e('No Discount', 'prepmedico-course-management'); ?></button>
                                                <button type="button" class="wcem-asit-mode-btn wcem-partner-mode-btn <?php echo ($b_mode === 'early_bird_only') ? 'active' : ''; ?>" data-mode="early_bird_only"><?php _e('Early Bird', 'prepmedico-course-management'); ?></button>
                                                <button type="button" class="wcem-asit-mode-btn wcem-partner-mode-btn <?php echo ($b_mode === 'always') ? 'active' : ''; ?>" data-mode="always"><?php _e('Always Active', 'prepmedico-course-management'); ?></button>
                                            </div>
                                        </div>
                                        <input type="hidden" name="bomss_config[<?php echo esc_attr($slug); ?>][mode]" value="<?php echo esc_attr($b_mode); ?>" class="wcem-partner-mode-input" data-partner="bomss" data-course="<?php echo esc_attr($slug); ?>">
                                        <div class="wcem-partner-discount-fields wcem-partner-discount-row" data-partner="bomss" data-course="<?php echo esc_attr($slug); ?>"<?php echo ($b_mode === 'none') ? ' style="display:none;"' : ''; ?>>
                                            <div class="wcem-asit-discount-field">
                                                <label><?php _e('EB Discount %', 'prepmedico-course-management'); ?></label>
                                                <div class="wcem-asit-discount-input-wrap">
                                                    <input type="number" name="bomss_config[<?php echo esc_attr($slug); ?>][eb_discount]" value="<?php echo esc_attr($b_eb); ?>" min="0" max="100" class="wcem-asit-card-discount-input">
                                                    <span>%</span>
                                                </div>
                                            </div>
                                            <div class="wcem-asit-discount-field">
                                                <label><?php _e('Normal %', 'prepmedico-course-management'); ?></label>
                                                <div class="wcem-asit-discount-input-wrap">
                                                    <input type="number" name="bomss_config[<?php echo esc_attr($slug); ?>][normal_discount]" value="<?php echo esc_attr($b_norm); ?>" min="0" max="100" class="wcem-asit-card-discount-input">
                                                    <span>%</span>
                                                </div>
                                            </div>
                                            <label class="wcem-asit-toggle-item">
                                                <span><?php _e('Show Field', 'prepmedico-course-management'); ?></span>
                                                <input type="checkbox" name="bomss_config[<?php echo esc_attr($slug); ?>][show_field]" value="1" <?php checked($b_show, true); ?> class="wcem-asit-toggle-checkbox">
                                                <span class="wcem-asit-toggle-slider"></span>
                                            </label>
                                        </div>
                                        <?php if ($b_mode === 'none'): ?>
                                        <span class="wcem-asit-footer-status disabled wcem-partner-status-pill">
                                            <span class="material-icons-round">block</span>
                                            <?php _e('Disabled', 'prepmedico-course-management'); ?>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                </details>

                                <!-- Rouleaux Club Sub-section -->
                                <?php
                                $r_mode   = isset($course['rouleaux_discount_mode']) ? $course['rouleaux_discount_mode'] : 'none';
                                $r_eb     = isset($course['rouleaux_early_bird_discount']) ? intval($course['rouleaux_early_bird_discount']) : 0;
                                $r_norm   = isset($course['rouleaux_normal_discount']) ? intval($course['rouleaux_normal_discount']) : 0;
                                $r_show   = isset($course['rouleaux_show_field']) ? (bool) $course['rouleaux_show_field'] : false;
                                ?>
                                <details class="wcem-partner-subsection wcem-partner-rouleaux" <?php echo ($r_mode !== 'none') ? 'open' : ''; ?>>
                                    <summary class="wcem-partner-summary">
                                        <span class="wcem-partner-summary-arrow material-icons-round">chevron_right</span>
                                        <span class="wcem-partner-name">Rouleaux Club</span>
                                        <span class="wcem-asit-status-badge <?php echo ($r_mode !== 'none') ? 'active' : 'inactive'; ?>">
                                            <?php echo ($r_mode !== 'none') ? 'ON' : 'OFF'; ?>
                                        </span>
                                    </summary>
                                    <div class="wcem-partner-subsection-body">
                                        <div class="wcem-asit-mode-section">
                                            <label class="wcem-asit-mode-label"><?php _e('Discount Mode', 'prepmedico-course-management'); ?></label>
                                            <div class="wcem-asit-mode-toggle" data-partner="rouleaux" data-course="<?php echo esc_attr($slug); ?>">
                                                <button type="button" class="wcem-asit-mode-btn wcem-partner-mode-btn <?php echo ($r_mode === 'none') ? 'active' : ''; ?>" data-mode="none"><?php _e('No Discount', 'prepmedico-course-management'); ?></button>
                                                <button type="button" class="wcem-asit-mode-btn wcem-partner-mode-btn <?php echo ($r_mode === 'early_bird_only') ? 'active' : ''; ?>" data-mode="early_bird_only"><?php _e('Early Bird', 'prepmedico-course-management'); ?></button>
                                                <button type="button" class="wcem-asit-mode-btn wcem-partner-mode-btn <?php echo ($r_mode === 'always') ? 'active' : ''; ?>" data-mode="always"><?php _e('Always Active', 'prepmedico-course-management'); ?></button>
                                            </div>
                                        </div>
                                        <input type="hidden" name="rouleaux_config[<?php echo esc_attr($slug); ?>][mode]" value="<?php echo esc_attr($r_mode); ?>" class="wcem-partner-mode-input" data-partner="rouleaux" data-course="<?php echo esc_attr($slug); ?>">
                                        <div class="wcem-partner-discount-fields wcem-partner-discount-row" data-partner="rouleaux" data-course="<?php echo esc_attr($slug); ?>"<?php echo ($r_mode === 'none') ? ' style="display:none;"' : ''; ?>>
                                            <div class="wcem-asit-discount-field">
                                                <label><?php _e('EB Discount %', 'prepmedico-course-management'); ?></label>
                                                <div class="wcem-asit-discount-input-wrap">
                                                    <input type="number" name="rouleaux_config[<?php echo esc_attr($slug); ?>][eb_discount]" value="<?php echo esc_attr($r_eb); ?>" min="0" max="100" class="wcem-asit-card-discount-input">
                                                    <span>%</span>
                                                </div>
                                            </div>
                                            <div class="wcem-asit-discount-field">
                                                <label><?php _e('Normal %', 'prepmedico-course-management'); ?></label>
                                                <div class="wcem-asit-discount-input-wrap">
                                                    <input type="number" name="rouleaux_config[<?php echo esc_attr($slug); ?>][normal_discount]" value="<?php echo esc_attr($r_norm); ?>" min="0" max="100" class="wcem-asit-card-discount-input">
                                                    <span>%</span>
                                                </div>
                                            </div>
                                            <label class="wcem-asit-toggle-item">
                                                <span><?php _e('Show Field', 'prepmedico-course-management'); ?></span>
                                                <input type="checkbox" name="rouleaux_config[<?php echo esc_attr($slug); ?>][show_field]" value="1" <?php checked($r_show, true); ?> class="wcem-asit-toggle-checkbox">
                                                <span class="wcem-asit-toggle-slider"></span>
                                            </label>
                                        </div>
                                        <?php if ($r_mode === 'none'): ?>
                                        <span class="wcem-asit-footer-status disabled wcem-partner-status-pill">
                                            <span class="material-icons-round">block</span>
                                            <?php _e('Disabled', 'prepmedico-course-management'); ?>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                </details>

                                <div class="wcem-asit-card-footer">
                                    <?php if ($mode === 'early_bird_only'): ?>
                                        <?php if ($edition_scope === 'next' || $edition_scope === 'both'): ?>
                                            <?php if ($is_next_eb_active): ?>
                                                <span class="wcem-asit-footer-status active">
                                                    <span class="material-icons-round">check_circle</span>
                                                    <?php echo !empty($next_eb_end) ? sprintf(__('Next EB: Ends %s', 'prepmedico-course-management'), date_i18n('M j', strtotime($next_eb_end))) : __('Next EB Active', 'prepmedico-course-management'); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="wcem-asit-footer-status expired">
                                                    <span class="material-icons-round">schedule</span>
                                                    <?php echo !empty($next_eb_end) ? sprintf(__('Next EB: Ended %s', 'prepmedico-course-management'), date_i18n('M j', strtotime($next_eb_end))) : __('Next EB Not Set', 'prepmedico-course-management'); ?>
                                                </span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        <?php if ($edition_scope === 'current' || $edition_scope === 'both'): ?>
                                            <?php if ($is_eb_active): ?>
                                                <span class="wcem-asit-footer-status active">
                                                    <span class="material-icons-round">check_circle</span>
                                                    <?php echo !empty($current_eb_end) ? sprintf(__('Current EB: Ends %s', 'prepmedico-course-management'), date_i18n('M j', strtotime($current_eb_end))) : __('Early Bird Active', 'prepmedico-course-management'); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="wcem-asit-footer-status expired">
                                                    <span class="material-icons-round">schedule</span>
                                                    <?php echo !empty($current_eb_end) ? sprintf(__('Current EB: Ended %s', 'prepmedico-course-management'), date_i18n('M j', strtotime($current_eb_end))) : __('Current EB Not Set', 'prepmedico-course-management'); ?>
                                                </span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    <?php elseif ($mode === 'none'): ?>
                                        <span class="wcem-asit-footer-status disabled">
                                            <span class="material-icons-round">block</span>
                                            <?php _e('Disabled', 'prepmedico-course-management'); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="wcem-asit-footer-status active">
                                            <span class="material-icons-round">verified</span>
                                            <?php printf(__('%d%% Discount Active', 'prepmedico-course-management'), $current_discount); ?>
                                        </span>
                                    <?php endif; ?>
                                    <a href="#" class="wcem-asit-settings-link" data-course="<?php echo esc_attr($slug); ?>">
                                        <?php _e('Settings', 'prepmedico-course-management'); ?>
                                        <span class="material-icons-round">arrow_forward</span>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>

                <!-- Sticky Save Bar -->
                <div class="wcem-asit-sticky-save">
                    <button type="submit" name="pmcm_save_asit_settings" class="wcem-asit-save-btn">
                        <span class="material-icons-round">save</span>
                        <?php _e('Save All Changes', 'prepmedico-course-management'); ?>
                    </button>
                </div>
            </form>

            <!-- Bulk Sync Section -->
            <section class="wcem-asit-sync-section">
                <div class="wcem-asit-sync-header">
                    <span class="material-icons-round">sync</span>
                    <h3><?php _e('Sync ASiT Orders to FluentCRM', 'prepmedico-course-management'); ?></h3>
                </div>
                <p class="wcem-asit-sync-desc"><?php _e('Scan old orders for ASiT coupon usage and sync them to FluentCRM.', 'prepmedico-course-management'); ?></p>

                <div id="wcem-asit-sync-results" class="wcem-asit-sync-results" style="display: none;">
                    <div id="wcem-asit-sync-progress"></div>
                </div>

                <div class="wcem-asit-sync-buttons">
                    <button type="button" id="wcem-scan-asit-orders" class="wcem-asit-scan-btn">
                        <span class="material-icons-round">search</span>
                        <?php _e('Scan Orders', 'prepmedico-course-management'); ?>
                    </button>
                    <button type="button" id="wcem-bulk-sync-asit" class="wcem-asit-sync-btn" disabled>
                        <span class="material-icons-round">cloud_upload</span>
                        <?php _e('Sync to FluentCRM', 'prepmedico-course-management'); ?>
                    </button>
                </div>
            </section>
        </div>

        <script>
            jQuery(document).ready(function($) {
                // Mode toggle buttons
                $('.wcem-asit-mode-toggle').on('click', '.wcem-asit-mode-btn', function() {
                    var $btn = $(this);
                    var $toggle = $btn.closest('.wcem-asit-mode-toggle');
                    var course = $toggle.data('course');
                    var mode = $btn.data('mode');

                    // Update active button
                    $toggle.find('.wcem-asit-mode-btn').removeClass('active');
                    $btn.addClass('active');

                    // Update hidden input
                    $('.wcem-asit-mode-input[data-course="' + course + '"]').val(mode);

                    // Show/hide discount section
                    var $card = $btn.closest('.wcem-asit-course-card');
                    var $discountSection = $card.find('.wcem-asit-discount-section');

                    if (mode === 'none') {
                        $discountSection.slideUp(150);
                    } else {
                        $discountSection.slideDown(150);
                    }

                    // Show/hide edition scope section (only for early_bird_only)
                    var $scopeSection = $card.find('.wcem-asit-edition-scope-section');
                    if (mode === 'early_bird_only') {
                        $scopeSection.slideDown(150);
                    } else {
                        $scopeSection.slideUp(150);
                    }

                    // Update status badge
                    var $badge = $card.find('.wcem-asit-status-badge');
                    $badge.removeClass('active early-bird inactive');
                    if (mode === 'always') {
                        $badge.addClass('active').text('<?php echo esc_js(__('Active', 'prepmedico-course-management')); ?>');
                    } else if (mode === 'early_bird_only') {
                        $badge.addClass('early-bird').text('<?php echo esc_js(__('Early Bird Only', 'prepmedico-course-management')); ?>');
                    } else {
                        $badge.addClass('inactive').text('<?php echo esc_js(__('Inactive', 'prepmedico-course-management')); ?>');
                    }

                    $card.attr('data-status', mode === 'always' ? 'active' : (mode === 'early_bird_only' ? 'early-bird' : 'inactive'));
                });

                // BOMSS / Rouleaux partner mode toggle buttons
                $(document).on('click', '.wcem-partner-mode-btn', function() {
                    var $btn     = $(this);
                    var $toggle  = $btn.closest('.wcem-asit-mode-toggle');
                    var partner  = $toggle.data('partner');
                    var course   = $toggle.data('course');
                    var mode     = $btn.data('mode');

                    $toggle.find('.wcem-partner-mode-btn').removeClass('active');
                    $btn.addClass('active');

                    // Update hidden input
                    $('.wcem-partner-mode-input[data-partner="' + partner + '"][data-course="' + course + '"]').val(mode);

                    // Show/hide discount row
                    var $row = $('.wcem-partner-discount-row[data-partner="' + partner + '"][data-course="' + course + '"]');
                    if (mode === 'none') {
                        $row.hide();
                    } else {
                        $row.show();
                    }
                });

                // Edition scope toggle buttons
                $('.wcem-asit-edition-scope-toggle').on('click', '.wcem-asit-scope-btn', function() {
                    var $btn = $(this);
                    var $toggle = $btn.closest('.wcem-asit-edition-scope-toggle');
                    var course = $toggle.data('course');
                    var scope = $btn.data('scope');

                    $toggle.find('.wcem-asit-scope-btn').removeClass('active');
                    $btn.addClass('active');

                    $('.wcem-asit-scope-input[data-course="' + course + '"]').val(scope);
                });

                // Product filter toggle
                $('.wcem-asit-product-filter-toggle').on('change', function() {
                    var course = $(this).data('course');
                    var $selection = $('.wcem-asit-product-selection[data-course="' + course + '"]');
                    if ($(this).is(':checked')) {
                        $selection.slideDown(150);
                    } else {
                        $selection.slideUp(150);
                    }
                });

                // Toggle products list
                $('.wcem-asit-toggle-products').on('click', function() {
                    var course = $(this).data('course');
                    var $list = $('.wcem-asit-products-list[data-course="' + course + '"]');
                    var $icon = $(this).find('.material-icons-round');
                    $list.slideToggle(150);
                    $icon.text($list.is(':visible') ? 'expand_less' : 'expand_more');
                });

                // Select/Deselect all products
                $('.wcem-asit-select-all').on('click', function() {
                    var course = $(this).data('course');
                    $('.wcem-asit-product-checkbox[data-course="' + course + '"]').prop('checked', true);
                    updateProductCount(course);
                });

                $('.wcem-asit-deselect-all').on('click', function() {
                    var course = $(this).data('course');
                    $('.wcem-asit-product-checkbox[data-course="' + course + '"]').prop('checked', false);
                    updateProductCount(course);
                });

                // Update count on change
                $('.wcem-asit-product-checkbox').on('change', function() {
                    var course = $(this).data('course');
                    updateProductCount(course);
                });

                function updateProductCount(course) {
                    var checked = $('.wcem-asit-product-checkbox[data-course="' + course + '"]:checked').length;
                    var total = $('.wcem-asit-product-checkbox[data-course="' + course + '"]').length;
                    $('.wcem-asit-product-count[data-course="' + course + '"]').text(checked + ' / ' + total + ' <?php echo esc_js(__('selected', 'prepmedico-course-management')); ?>');
                }

                // View toggle
                $('.wcem-asit-view-btn').on('click', function() {
                    var view = $(this).data('view');
                    $('.wcem-asit-view-btn').removeClass('active');
                    $(this).addClass('active');
                    $('#wcem-asit-courses-grid').removeClass('list-view grid-view').addClass(view + '-view');
                });

                // Sort dropdown
                $('#wcem-asit-sort').on('change', function() {
                    var sortBy = $(this).val();
                    var $grid = $('#wcem-asit-courses-grid');
                    var $cards = $grid.children('.wcem-asit-course-card').detach();

                    if (sortBy === 'alpha') {
                        $cards.sort(function(a, b) {
                            return $(a).find('h3').text().localeCompare($(b).find('h3').text());
                        });
                    } else if (sortBy === 'status') {
                        var order = {
                            'active': 1,
                            'early-bird': 2,
                            'inactive': 3
                        };
                        $cards.sort(function(a, b) {
                            return order[$(a).data('status')] - order[$(b).data('status')];
                        });
                    }

                    $grid.append($cards);
                });

                // Bulk sync functionality
                var asitOrders = [];

                $('#wcem-scan-asit-orders').on('click', function() {
                    var btn = $(this);
                    btn.prop('disabled', true).find('span:last').text('<?php _e('Scanning...', 'prepmedico-course-management'); ?>');
                    $('#wcem-asit-sync-results').show();
                    $('#wcem-asit-sync-progress').html('<p><?php _e('Scanning orders for ASiT coupon usage...', 'prepmedico-course-management'); ?></p>');

                    $.post(ajaxurl, {
                        action: 'wcem_bulk_sync_asit',
                        mode: 'scan',
                        nonce: '<?php echo wp_create_nonce('wcem_admin_nonce'); ?>'
                    }, function(response) {
                        btn.prop('disabled', false).find('span:last').text('<?php _e('Scan Orders', 'prepmedico-course-management'); ?>');

                        if (response.success) {
                            asitOrders = response.data.orders;
                            var html = '<p><strong>' + response.data.found + '</strong> <?php _e('orders found with ASiT coupon.', 'prepmedico-course-management'); ?></p>';
                            html += '<p><?php _e('Already synced:', 'prepmedico-course-management'); ?> <strong>' + response.data.already_synced + '</strong></p>';
                            html += '<p><?php _e('Need to sync:', 'prepmedico-course-management'); ?> <strong>' + response.data.need_sync + '</strong></p>';

                            if (response.data.need_sync > 0) {
                                $('#wcem-bulk-sync-asit').prop('disabled', false);
                            }

                            $('#wcem-asit-sync-progress').html(html);
                        } else {
                            $('#wcem-asit-sync-progress').html('<p style="color:red;">' + response.data.message + '</p>');
                        }
                    });
                });

                $('#wcem-bulk-sync-asit').on('click', function() {
                    if (!confirm('<?php _e('This will sync all ASiT orders to FluentCRM. Continue?', 'prepmedico-course-management'); ?>')) {
                        return;
                    }

                    var btn = $(this);
                    btn.prop('disabled', true).find('span:last').text('<?php _e('Syncing...', 'prepmedico-course-management'); ?>');

                    $.post(ajaxurl, {
                        action: 'wcem_bulk_sync_asit',
                        mode: 'sync',
                        nonce: '<?php echo wp_create_nonce('wcem_admin_nonce'); ?>'
                    }, function(response) {
                        btn.prop('disabled', true).find('span:last').text('<?php _e('Sync to FluentCRM', 'prepmedico-course-management'); ?>');

                        if (response.success) {
                            var html = '<p style="color:green;"><strong><?php _e('Sync completed!', 'prepmedico-course-management'); ?></strong></p>';
                            html += '<p><?php _e('Orders processed:', 'prepmedico-course-management'); ?> <strong>' + response.data.processed + '</strong></p>';
                            html += '<p><?php _e('Contacts updated:', 'prepmedico-course-management'); ?> <strong>' + response.data.synced + '</strong></p>';
                            if (response.data.errors > 0) {
                                html += '<p style="color:orange;"><?php _e('Errors:', 'prepmedico-course-management'); ?> <strong>' + response.data.errors + '</strong></p>';
                            }
                            $('#wcem-asit-sync-progress').html(html);
                        } else {
                            $('#wcem-asit-sync-progress').html('<p style="color:red;">' + response.data.message + '</p>');
                        }
                    });
                });
            });
        </script>
    <?php
    }

    /**
     * AJAX: Manual edition increment
     */
    public static function ajax_manual_edition_switch()
    {
        check_ajax_referer('wcem_admin_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        $course_slug = sanitize_text_field($_POST['course'] ?? '');

        if (!isset(PMCM_Core::get_courses()[$course_slug])) {
            wp_send_json_error(['message' => 'Invalid course']);
        }

        $course = PMCM_Core::get_courses()[$course_slug];
        $prefix = $course['settings_prefix'];

        $old_edition = get_option($prefix . 'current_edition', 1);
        $new_edition = $old_edition + 1;

        // Increment edition number
        update_option($prefix . 'current_edition', $new_edition);

        // Clear dates for new edition entry
        update_option($prefix . 'edition_start', '');
        update_option($prefix . 'edition_end', '');

        // Clear Early Bird settings
        update_option($prefix . 'early_bird_enabled', 'no');
        update_option($prefix . 'early_bird_start', '');
        update_option($prefix . 'early_bird_end', '');

        // Clear closed categories — the new edition starts fresh
        update_option($prefix . 'closed_categories_current', '[]');

        PMCM_Core::log_activity('Manual increment ' . $course['name'] . ': Edition ' . $old_edition . ' → ' . $new_edition, 'success');

        wp_send_json_success(['message' => $course['name'] . ' incremented from Edition ' . $old_edition . ' to Edition ' . $new_edition . '. Please set new dates.']);
    }

    /**
     * AJAX: Test FluentCRM
     */
    public static function ajax_test_fluentcrm()
    {
        check_ajax_referer('wcem_admin_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        if (!PMCM_FluentCRM::is_active()) {
            wp_send_json_error(['message' => 'FluentCRM is not active or Subscriber model not found']);
        }

        try {
            $found_tags = [];
            $missing_tags = [];
            foreach (PMCM_Core::get_courses() as $course) {
                $tag = \FluentCrm\App\Models\Tag::where('title', $course['fluentcrm_tag'])
                    ->orWhere('slug', sanitize_title($course['fluentcrm_tag']))
                    ->first();

                if ($tag) {
                    $found_tags[] = $course['fluentcrm_tag'];
                } else {
                    $missing_tags[] = $course['fluentcrm_tag'];
                }
            }

            $found_fields = [];
            $missing_fields = [];
            if (class_exists('FluentCrm\App\Models\CustomContactField')) {
                foreach (PMCM_Core::get_courses() as $course) {
                    $field = \FluentCrm\App\Models\CustomContactField::where('slug', $course['fluentcrm_field'])->first();
                    if ($field) {
                        $found_fields[] = $course['fluentcrm_field'];
                    } else {
                        $missing_fields[] = $course['fluentcrm_field'];
                    }
                }
            }

            $message = "Tags: " . count($found_tags) . "/" . count(PMCM_Core::get_courses()) . " found. ";
            if (!empty($missing_tags)) {
                $message .= "Missing: " . implode(', ', $missing_tags) . ". ";
            }

            $message .= "Custom Fields: " . count($found_fields) . "/" . count(PMCM_Core::get_courses()) . " found. ";
            if (!empty($missing_fields)) {
                $message .= "Missing: " . implode(', ', $missing_fields) . " (Please create these as TEXT fields in FluentCRM > Settings > Custom Fields)";
            }

            PMCM_Core::log_activity('FluentCRM test: ' . $message, empty($missing_fields) && empty($missing_tags) ? 'success' : 'info');

            wp_send_json_success([
                'message' => $message,
                'found_tags' => $found_tags,
                'missing_tags' => $missing_tags,
                'found_fields' => $found_fields,
                'missing_fields' => $missing_fields
            ]);
        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * AJAX: Run cron
     */
    public static function ajax_run_cron()
    {
        check_ajax_referer('wcem_admin_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        PMCM_Cron::check_and_update_editions();
        wp_send_json_success(['message' => 'Edition check completed. Check the Activity Log below for details.']);
    }

    /**
     * AJAX: Sync order to FluentCRM
     */
    public static function ajax_sync_order_to_fluentcrm()
    {
        check_ajax_referer('wcem_admin_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        $order_id = intval($_POST['order_id'] ?? 0);

        if (!$order_id) {
            wp_send_json_error(['message' => 'Invalid order ID']);
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error(['message' => 'Order not found']);
        }

        $order->delete_meta_data('_wcem_fluentcrm_synced');
        $order->save();

        PMCM_FluentCRM::trigger_update($order_id);

        $order = wc_get_order($order_id);
        $synced = $order->get_meta('_wcem_fluentcrm_synced');

        if ($synced === 'yes') {
            wp_send_json_success(['message' => 'Order synced successfully']);
        } else {
            wp_send_json_error(['message' => 'Sync failed. Check activity log for details.']);
        }
    }

    /**
     * AJAX: Update order edition data based on current settings
     */
    public static function ajax_update_order_edition()
    {
        check_ajax_referer('wcem_admin_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        $order_id = intval($_POST['order_id'] ?? 0);

        if (!$order_id) {
            wp_send_json_error(['message' => 'Invalid order ID']);
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error(['message' => 'Order not found']);
        }

        $courses_in_order = [];
        $processed_parents = [];
        $updated_courses = [];

        foreach ($order->get_items() as $item_id => $item) {
            $product_id = $item->get_product_id();
            $categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'slugs']);

            foreach ($categories as $category_slug) {
                $course_data = PMCM_Core::get_course_for_category($category_slug);

                if ($course_data) {
                    $parent_slug = $course_data['parent_slug'];

                    if (in_array($parent_slug, $processed_parents)) {
                        continue;
                    }

                    $course = $course_data['course'];
                    $prefix = $course['settings_prefix'];

                    $edition_number = get_option($prefix . 'current_edition', 1);

                    // Check if course has edition management
                    $has_edition_management = isset($course['edition_management']) && $course['edition_management'] === true;

                    // For backend storage: all courses with edition_management get full edition name
                    // For Library Subscription (no edition_management): just the course name
                    $edition_name = $has_edition_management
                        ? PMCM_Core::get_ordinal($edition_number) . ' ' . $course['name']
                        : $course['name'];

                    // Store edition number for all courses with edition_management (including sub-categories)
                    // Only Library Subscription (edition_management: false) gets null
                    $edition_number_to_save = $has_edition_management ? $edition_number : null;

                    $edition_start = get_option($prefix . 'edition_start', '');
                    $edition_end = get_option($prefix . 'edition_end', '');

                    // Update order meta
                    $order->update_meta_data('_course_slug_' . $parent_slug, $parent_slug);
                    $order->update_meta_data('_course_edition_' . $parent_slug, $edition_number_to_save);
                    $order->update_meta_data('_edition_name_' . $parent_slug, $edition_name);
                    $order->update_meta_data('_edition_start_' . $parent_slug, $edition_start);
                    $order->update_meta_data('_edition_end_' . $parent_slug, $edition_end);
                    $order->update_meta_data('_original_category_' . $parent_slug, $category_slug);

                    // Update line item meta
                    wc_update_order_item_meta($item_id, '_course_slug', $parent_slug);
                    wc_update_order_item_meta($item_id, '_original_category', $category_slug);
                    wc_update_order_item_meta($item_id, '_course_edition', $edition_number_to_save);
                    wc_update_order_item_meta($item_id, '_edition_name', $edition_name);
                    wc_update_order_item_meta($item_id, '_edition_start', $edition_start);
                    wc_update_order_item_meta($item_id, '_edition_end', $edition_end);

                    $courses_in_order[] = [
                        'slug' => $parent_slug,
                        'original_category' => $category_slug,
                        'is_child' => $course_data['is_child'],
                        'course' => $course,
                        'edition' => $edition_number_to_save,
                        'edition_name' => $edition_name
                    ];

                    $updated_courses[] = $edition_name;
                    $processed_parents[] = $parent_slug;
                    break;
                }
            }
        }

        if (!empty($courses_in_order)) {
            $order->update_meta_data('_wcem_needs_fluentcrm_sync', 'yes');
            $order->update_meta_data('_wcem_courses_data', json_encode($courses_in_order));
            // Clear sync status so it can be re-synced
            $order->delete_meta_data('_wcem_fluentcrm_synced');
            $order->delete_meta_data('_wcem_fluentcrm_sync_time');
        }

        $order->save();

        PMCM_Core::log_activity('Order #' . $order_id . ' edition data updated: ' . implode(', ', $updated_courses), 'success');

        wp_send_json_success([
            'message' => 'Edition data updated for: ' . implode(', ', $updated_courses) . '. You can now sync to FluentCRM.'
        ]);
    }

    /**
     * AJAX: Bulk sync ASiT orders to FluentCRM
     */
    public static function ajax_bulk_sync_asit_orders()
    {
        check_ajax_referer('wcem_admin_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        $mode = sanitize_text_field($_POST['mode'] ?? 'scan');

        // Get all orders
        $args = [
            'limit' => -1,
            'status' => ['completed', 'processing'],
            'return' => 'ids'
        ];

        $order_ids = wc_get_orders($args);

        $asit_orders = [];
        $already_synced = 0;
        $need_sync = 0;

        foreach ($order_ids as $order_id) {
            $order = wc_get_order($order_id);
            if (!$order) continue;

            // Check for ASiT membership number
            $asit_number = $order->get_meta('_asit_membership_number');
            if (empty($asit_number)) {
                $asit_number = $order->get_meta('_wcem_asit_number');
            }

            if (!empty($asit_number)) {
                // Ensure _wcem_asit_number is set
                if ($order->get_meta('_wcem_asit_number') !== $asit_number) {
                    $order->update_meta_data('_wcem_asit_member', 'yes');
                    $order->update_meta_data('_wcem_asit_number', $asit_number);
                    $order->save();
                }

                $asit_orders[] = [
                    'id' => $order_id,
                    'email' => $order->get_billing_email(),
                    'asit_number' => $asit_number,
                    'synced' => $order->get_meta('_wcem_asit_fluentcrm_synced') === 'yes'
                ];

                if ($order->get_meta('_wcem_asit_fluentcrm_synced') === 'yes') {
                    $already_synced++;
                } else {
                    $need_sync++;
                }
            }
        }

        if ($mode === 'scan') {
            wp_send_json_success([
                'orders' => $asit_orders,
                'found' => count($asit_orders),
                'already_synced' => $already_synced,
                'need_sync' => $need_sync
            ]);
            return;
        }

        // Sync mode
        if (!PMCM_FluentCRM::is_active()) {
            wp_send_json_error(['message' => 'FluentCRM is not active']);
        }

        $processed = 0;
        $synced = 0;
        $errors = 0;

        foreach ($asit_orders as $order_data) {
            if ($order_data['synced']) {
                continue;
            }

            $order = wc_get_order($order_data['id']);
            if (!$order) {
                $errors++;
                continue;
            }

            $email = $order->get_billing_email();
            if (empty($email)) {
                $errors++;
                continue;
            }

            $asit_number = $order_data['asit_number'];

            try {
                $subscriber = \FluentCrm\App\Models\Subscriber::where('email', $email)->first();

                if (!$subscriber) {
                    // Create subscriber
                    $subscriber = \FluentCrm\App\Models\Subscriber::create([
                        'email' => $email,
                        'first_name' => $order->get_billing_first_name(),
                        'last_name' => $order->get_billing_last_name(),
                        'status' => 'subscribed',
                        'source' => 'woocommerce'
                    ]);
                }

                if ($subscriber) {
                    // Update asit custom field with the membership number
                    if (method_exists($subscriber, 'syncCustomFieldValues')) {
                        $subscriber->syncCustomFieldValues(['asit' => $asit_number], false);
                    } else {
                        global $wpdb;
                        $table = $wpdb->prefix . 'fc_subscriber_meta';

                        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
                            $exists = $wpdb->get_var($wpdb->prepare(
                                "SELECT id FROM $table WHERE subscriber_id = %d AND `key` = %s",
                                $subscriber->id,
                                'asit'
                            ));

                            if ($exists) {
                                $wpdb->update(
                                    $table,
                                    ['value' => $asit_number, 'updated_at' => current_time('mysql')],
                                    ['subscriber_id' => $subscriber->id, 'key' => 'asit']
                                );
                            } else {
                                $wpdb->insert(
                                    $table,
                                    [
                                        'subscriber_id' => $subscriber->id,
                                        'key' => 'asit',
                                        'value' => $asit_number,
                                        'object_type' => 'custom_field',
                                        'created_at' => current_time('mysql'),
                                        'updated_at' => current_time('mysql')
                                    ]
                                );
                            }
                        }
                    }

                    // Mark as synced
                    $order->update_meta_data('_wcem_asit_fluentcrm_synced', 'yes');
                    $order->update_meta_data('_wcem_asit_fluentcrm_sync_time', current_time('mysql'));
                    $order->save();

                    $synced++;
                    PMCM_Core::log_activity('Bulk ASiT sync: Updated FluentCRM asit=' . $asit_number . ' for ' . $email . ' (Order #' . $order_data['id'] . ')', 'success');
                } else {
                    $errors++;
                }
            } catch (Exception $e) {
                $errors++;
                PMCM_Core::log_activity('Bulk ASiT sync error for ' . $email . ': ' . $e->getMessage(), 'error');
            }

            $processed++;
        }

        PMCM_Core::log_activity('Bulk ASiT sync completed: ' . $synced . ' synced, ' . $errors . ' errors', 'success');

        wp_send_json_success([
            'processed' => $processed,
            'synced' => $synced,
            'errors' => $errors
        ]);
    }

    /**
     * Render Course Configuration admin page
     */
    public static function render_course_config_page()
    {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        $courses = PMCM_Core::get_courses();
        $wc_categories = PMCM_Core::get_wc_categories();
    ?>
        <div class="wrap wcem-admin-wrap wcem-config-modern">
            <!-- Header -->
            <header class="wcem-cfg-header">
                <div class="wcem-cfg-header-content">
                    <span class="material-icons-round wcem-cfg-icon">school</span>
                    <div>
                        <h1><?php _e('Course Configuration', 'prepmedico-course-management'); ?></h1>
                        <p class="wcem-cfg-header-desc"><?php _e('Configure which WooCommerce categories are managed as courses. Map each category to its FluentCRM tag and custom field.', 'prepmedico-course-management'); ?></p>
                    </div>
                </div>
                <button type="button" id="wcem-add-course" class="wcem-cfg-add-btn">
                    <span class="material-icons-round">add</span>
                    <?php _e('Add New Course', 'prepmedico-course-management'); ?>
                </button>
            </header>

            <!-- FluentCRM Setup Instructions -->
            <section class="wcem-cfg-instructions">
                <div class="wcem-cfg-instructions-header">
                    <span class="material-icons-round">help_outline</span>
                    <h3><?php _e('FluentCRM Custom Field Setup', 'prepmedico-course-management'); ?></h3>
                </div>
                <div class="wcem-cfg-instructions-body">
                    <p><?php _e('Before adding a course, create the custom field in FluentCRM:', 'prepmedico-course-management'); ?></p>
                    <ol>
                        <li><?php _e('Go to FluentCRM &rarr; Settings &rarr; Custom Fields', 'prepmedico-course-management'); ?></li>
                        <li><?php _e('Click "Add Field"', 'prepmedico-course-management'); ?></li>
                        <li><?php _e('Field Type: <strong>Text</strong>', 'prepmedico-course-management'); ?></li>
                        <li><?php _e('Field Label: e.g., "FRCS Edition"', 'prepmedico-course-management'); ?></li>
                        <li><?php _e('Field Slug: e.g., "frcs_edition" (use this below)', 'prepmedico-course-management'); ?></li>
                        <li><?php _e('Save the field', 'prepmedico-course-management'); ?></li>
                    </ol>
                </div>
            </section>

            <!-- Course Summary Stats -->
            <?php
            $total_courses = count($courses);
            $edition_count = 0;
            $asit_count = 0;
            foreach ($courses as $c) {
                if (!empty($c['edition_management'])) $edition_count++;
                if (!empty($c['asit_eligible'])) $asit_count++;
            }
            ?>
            <div class="wcem-cfg-stats">
                <div class="wcem-cfg-stat-card">
                    <span class="material-icons-round">library_books</span>
                    <div>
                        <span class="wcem-cfg-stat-value"><?php echo $total_courses; ?></span>
                        <span class="wcem-cfg-stat-label"><?php _e('Total Courses', 'prepmedico-course-management'); ?></span>
                    </div>
                </div>
                <div class="wcem-cfg-stat-card">
                    <span class="material-icons-round">layers</span>
                    <div>
                        <span class="wcem-cfg-stat-value"><?php echo $edition_count; ?></span>
                        <span class="wcem-cfg-stat-label"><?php _e('Edition Managed', 'prepmedico-course-management'); ?></span>
                    </div>
                </div>
                <div class="wcem-cfg-stat-card">
                    <span class="material-icons-round">local_offer</span>
                    <div>
                        <span class="wcem-cfg-stat-value"><?php echo $asit_count; ?></span>
                        <span class="wcem-cfg-stat-label"><?php _e('ASiT Eligible', 'prepmedico-course-management'); ?></span>
                    </div>
                </div>
            </div>

            <!-- Courses Section -->
            <section class="wcem-cfg-courses-section">
                <div class="wcem-cfg-courses-header">
                    <div>
                        <h2><?php _e('Registered Courses', 'prepmedico-course-management'); ?></h2>
                        <p class="wcem-cfg-subtitle"><?php _e('Manage course-to-category mappings and FluentCRM integration settings.', 'prepmedico-course-management'); ?></p>
                    </div>
                    <div class="wcem-cfg-courses-count">
                        <span><?php echo $total_courses; ?> <?php _e('courses', 'prepmedico-course-management'); ?></span>
                    </div>
                </div>

                <!-- Course Cards Grid -->
                <div class="wcem-cfg-courses-grid">
                    <?php if (empty($courses)): ?>
                        <div class="wcem-cfg-empty-state">
                            <span class="material-icons-round">school</span>
                            <h3><?php _e('No Courses Configured', 'prepmedico-course-management'); ?></h3>
                            <p><?php _e('Click "Add New Course" to get started.', 'prepmedico-course-management'); ?></p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($courses as $slug => $course):
                            $has_edition = !empty($course['edition_management']);
                            $has_asit = !empty($course['asit_eligible']);
                            $children = !empty($course['children']) ? $course['children'] : [];
                        ?>
                            <div class="wcem-cfg-course-card" data-slug="<?php echo esc_attr($slug); ?>">
                                <!-- Card Header -->
                                <div class="wcem-cfg-card-header">
                                    <div class="wcem-cfg-card-title-row">
                                        <h3><?php echo esc_html($course['name']); ?></h3>
                                        <div class="wcem-cfg-card-badges">
                                            <?php if ($has_edition): ?>
                                                <span class="wcem-cfg-badge edition"><?php _e('Edition Mgmt', 'prepmedico-course-management'); ?></span>
                                            <?php endif; ?>
                                            <?php if ($has_asit): ?>
                                                <span class="wcem-cfg-badge asit"><?php _e('ASiT', 'prepmedico-course-management'); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <span class="wcem-cfg-card-slug"><?php echo esc_html(strtoupper($slug)); ?></span>
                                </div>

                                <!-- Card Body -->
                                <div class="wcem-cfg-card-body">
                                    <div class="wcem-cfg-detail-row">
                                        <span class="wcem-cfg-detail-icon"><span class="material-icons-round">category</span></span>
                                        <div class="wcem-cfg-detail-content">
                                            <span class="wcem-cfg-detail-label"><?php _e('Category', 'prepmedico-course-management'); ?></span>
                                            <code class="wcem-cfg-detail-value"><?php echo esc_html($slug); ?></code>
                                        </div>
                                    </div>
                                    <div class="wcem-cfg-detail-row">
                                        <span class="wcem-cfg-detail-icon"><span class="material-icons-round">sell</span></span>
                                        <div class="wcem-cfg-detail-content">
                                            <span class="wcem-cfg-detail-label"><?php _e('FluentCRM Tag', 'prepmedico-course-management'); ?></span>
                                            <code class="wcem-cfg-detail-value"><?php echo esc_html($course['fluentcrm_tag']); ?></code>
                                        </div>
                                    </div>
                                    <div class="wcem-cfg-detail-row">
                                        <span class="wcem-cfg-detail-icon"><span class="material-icons-round">data_object</span></span>
                                        <div class="wcem-cfg-detail-content">
                                            <span class="wcem-cfg-detail-label"><?php _e('FluentCRM Field', 'prepmedico-course-management'); ?></span>
                                            <code class="wcem-cfg-detail-value"><?php echo esc_html($course['fluentcrm_field']); ?></code>
                                        </div>
                                    </div>
                                    <?php if (!empty($children)): ?>
                                        <div class="wcem-cfg-detail-row">
                                            <span class="wcem-cfg-detail-icon"><span class="material-icons-round">account_tree</span></span>
                                            <div class="wcem-cfg-detail-content">
                                                <span class="wcem-cfg-detail-label"><?php _e('Child Categories', 'prepmedico-course-management'); ?></span>
                                                <div class="wcem-cfg-children-tags">
                                                    <?php foreach ($children as $child): ?>
                                                        <code class="wcem-cfg-child-tag"><?php echo esc_html($child); ?></code>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Card Footer -->
                                <div class="wcem-cfg-card-footer">
                                    <div class="wcem-cfg-card-features">
                                        <?php if ($has_edition): ?>
                                            <span class="wcem-cfg-feature active"><span class="material-icons-round">check_circle</span> <?php _e('Editions', 'prepmedico-course-management'); ?></span>
                                        <?php else: ?>
                                            <span class="wcem-cfg-feature"><span class="material-icons-round">cancel</span> <?php _e('Editions', 'prepmedico-course-management'); ?></span>
                                        <?php endif; ?>
                                        <?php if ($has_asit): ?>
                                            <span class="wcem-cfg-feature active"><span class="material-icons-round">check_circle</span> <?php _e('ASiT', 'prepmedico-course-management'); ?></span>
                                        <?php else: ?>
                                            <span class="wcem-cfg-feature"><span class="material-icons-round">cancel</span> <?php _e('ASiT', 'prepmedico-course-management'); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="wcem-cfg-card-actions">
                                        <button type="button" class="wcem-cfg-edit-btn wcem-edit-course" data-slug="<?php echo esc_attr($slug); ?>">
                                            <span class="material-icons-round">edit</span>
                                            <?php _e('Edit', 'prepmedico-course-management'); ?>
                                        </button>
                                        <button type="button" class="wcem-cfg-delete-btn wcem-delete-course" data-slug="<?php echo esc_attr($slug); ?>" data-name="<?php echo esc_attr($course['name']); ?>">
                                            <span class="material-icons-round">delete_outline</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Add/Edit Course Modal -->
            <div id="wcem-course-modal" class="wcem-modal" style="display: none;">
                <div class="wcem-modal-content">
                    <div class="wcem-modal-header">
                        <h2 id="wcem-modal-title"><?php _e('Add New Course', 'prepmedico-course-management'); ?></h2>
                        <button type="button" class="wcem-modal-close">&times;</button>
                    </div>
                    <div class="wcem-modal-body">
                        <form id="wcem-course-form">
                            <input type="hidden" id="wcem-edit-mode" value="add">
                            <input type="hidden" id="wcem-original-slug" value="">

                            <div class="wcem-cfg-form-group">
                                <label for="wcem-course-category"><?php _e('Category', 'prepmedico-course-management'); ?> <span class="wcem-cfg-required">*</span></label>
                                <select id="wcem-course-category" name="category_slug" required>
                                    <option value=""><?php _e('Select a category...', 'prepmedico-course-management'); ?></option>
                                    <?php foreach ($wc_categories as $cat_slug => $cat_name): ?>
                                        <option value="<?php echo esc_attr($cat_slug); ?>"><?php echo esc_html($cat_name); ?> (<?php echo esc_html($cat_slug); ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="wcem-cfg-form-hint"><?php _e('Select the WooCommerce product category for this course.', 'prepmedico-course-management'); ?></p>
                            </div>

                            <div class="wcem-cfg-form-group">
                                <label for="wcem-course-name"><?php _e('Display Name', 'prepmedico-course-management'); ?> <span class="wcem-cfg-required">*</span></label>
                                <input type="text" id="wcem-course-name" name="name" required>
                                <p class="wcem-cfg-form-hint"><?php _e('The display name for this course (e.g., "FRCS", "FRCOphth Part 1").', 'prepmedico-course-management'); ?></p>
                            </div>

                            <div class="wcem-cfg-form-row">
                                <div class="wcem-cfg-form-group">
                                    <label for="wcem-course-tag"><?php _e('FluentCRM Tag', 'prepmedico-course-management'); ?> <span class="wcem-cfg-required">*</span></label>
                                    <input type="text" id="wcem-course-tag" name="fluentcrm_tag" required>
                                    <p class="wcem-cfg-form-hint"><?php _e('Tag name in FluentCRM (e.g., "FRCS").', 'prepmedico-course-management'); ?></p>
                                </div>
                                <div class="wcem-cfg-form-group">
                                    <label for="wcem-course-field"><?php _e('FluentCRM Custom Field', 'prepmedico-course-management'); ?> <span class="wcem-cfg-required">*</span></label>
                                    <input type="text" id="wcem-course-field" name="fluentcrm_field" required>
                                    <p class="wcem-cfg-form-hint"><?php _e('Custom field slug (e.g., "frcs_edition").', 'prepmedico-course-management'); ?></p>
                                </div>
                            </div>

                            <div class="wcem-cfg-form-group">
                                <label for="wcem-course-children"><?php _e('Child Categories', 'prepmedico-course-management'); ?></label>
                                <select id="wcem-course-children" name="children[]" multiple>
                                    <?php foreach ($wc_categories as $cat_slug => $cat_name): ?>
                                        <option value="<?php echo esc_attr($cat_slug); ?>"><?php echo esc_html($cat_name); ?> (<?php echo esc_html($cat_slug); ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="wcem-cfg-form-hint"><?php _e('Select child categories that inherit this course\'s tag and field. Hold Ctrl/Cmd to select multiple.', 'prepmedico-course-management'); ?></p>
                            </div>

                            <div class="wcem-cfg-form-group">
                                <label><?php _e('Options', 'prepmedico-course-management'); ?></label>
                                <div class="wcem-cfg-checkbox-group">
                                    <label class="wcem-cfg-checkbox-label">
                                        <input type="checkbox" id="wcem-course-edition-mgmt" name="edition_management" value="1" checked>
                                        <span class="wcem-cfg-checkbox-text">
                                            <strong><?php _e('Enable Edition Management', 'prepmedico-course-management'); ?></strong>
                                            <small><?php _e('Track edition numbers and dates', 'prepmedico-course-management'); ?></small>
                                        </span>
                                    </label>
                                    <label class="wcem-cfg-checkbox-label">
                                        <input type="checkbox" id="wcem-course-asit" name="asit_eligible" value="1">
                                        <span class="wcem-cfg-checkbox-text">
                                            <strong><?php _e('ASiT Eligible', 'prepmedico-course-management'); ?></strong>
                                            <small><?php _e('Enable ASiT membership discounts', 'prepmedico-course-management'); ?></small>
                                        </span>
                                    </label>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="wcem-modal-footer">
                        <button type="button" class="wcem-cfg-modal-cancel wcem-modal-cancel"><?php _e('Cancel', 'prepmedico-course-management'); ?></button>
                        <button type="button" class="wcem-cfg-modal-save" id="wcem-save-course">
                            <span class="material-icons-round">save</span>
                            <?php _e('Save Course', 'prepmedico-course-management'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <script>
            jQuery(document).ready(function($) {
                var coursesData = <?php echo json_encode($courses); ?>;

                // Open modal for adding
                $('#wcem-add-course').on('click', function() {
                    $('#wcem-modal-title').text('<?php _e('Add New Course', 'prepmedico-course-management'); ?>');
                    $('#wcem-edit-mode').val('add');
                    $('#wcem-original-slug').val('');
                    $('#wcem-course-form')[0].reset();
                    $('#wcem-course-edition-mgmt').prop('checked', true);
                    $('#wcem-course-category').prop('disabled', false);
                    $('#wcem-course-modal').addClass('wcem-modal-visible');
                });

                // Open modal for editing
                $(document).on('click', '.wcem-edit-course', function() {
                    var slug = $(this).data('slug');
                    var course = coursesData[slug];

                    $('#wcem-modal-title').text('<?php _e('Edit Course', 'prepmedico-course-management'); ?>');
                    $('#wcem-edit-mode').val('edit');
                    $('#wcem-original-slug').val(slug);

                    $('#wcem-course-category').val(slug).prop('disabled', true);
                    $('#wcem-course-name').val(course.name);
                    $('#wcem-course-tag').val(course.fluentcrm_tag);
                    $('#wcem-course-field').val(course.fluentcrm_field);
                    $('#wcem-course-children').val(course.children || []);
                    $('#wcem-course-edition-mgmt').prop('checked', course.edition_management === true);
                    $('#wcem-course-asit').prop('checked', course.asit_eligible === true);

                    $('#wcem-course-modal').addClass('wcem-modal-visible');
                });

                // Close modal
                $('.wcem-modal-close, .wcem-modal-cancel').on('click', function() {
                    $('#wcem-course-modal').removeClass('wcem-modal-visible');
                });

                // Save course
                $('#wcem-save-course').on('click', function() {
                    var btn = $(this);
                    var mode = $('#wcem-edit-mode').val();
                    var slug = mode === 'edit' ? $('#wcem-original-slug').val() : $('#wcem-course-category').val();

                    if (!slug) {
                        alert('<?php _e('Please select a category.', 'prepmedico-course-management'); ?>');
                        return;
                    }

                    var data = {
                        action: 'wcem_save_course',
                        nonce: wcemAdmin.nonce,
                        category_slug: slug,
                        name: $('#wcem-course-name').val(),
                        fluentcrm_tag: $('#wcem-course-tag').val(),
                        fluentcrm_field: $('#wcem-course-field').val(),
                        children: $('#wcem-course-children').val() || [],
                        edition_management: $('#wcem-course-edition-mgmt').is(':checked') ? 1 : 0,
                        asit_eligible: $('#wcem-course-asit').is(':checked') ? 1 : 0
                    };

                    btn.prop('disabled', true).find('.material-icons-round').text('hourglass_empty');

                    $.post(ajaxurl, data, function(response) {
                        btn.prop('disabled', false).find('.material-icons-round').text('save');

                        if (response.success) {
                            location.reload();
                        } else {
                            alert(response.data.message || '<?php _e('Error saving course.', 'prepmedico-course-management'); ?>');
                        }
                    });
                });

                // Delete course
                $(document).on('click', '.wcem-delete-course', function() {
                    var slug = $(this).data('slug');
                    var name = $(this).data('name');

                    if (!confirm('<?php _e('Are you sure you want to delete the course:', 'prepmedico-course-management'); ?> ' + name + '?')) {
                        return;
                    }

                    var $card = $(this).closest('.wcem-cfg-course-card');
                    $card.css('opacity', '0.5');

                    $.post(ajaxurl, {
                        action: 'wcem_delete_course',
                        nonce: wcemAdmin.nonce,
                        category_slug: slug
                    }, function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            $card.css('opacity', '1');
                            alert(response.data.message || '<?php _e('Error deleting course.', 'prepmedico-course-management'); ?>');
                        }
                    });
                });

                // Close modal on outside click
                $('#wcem-course-modal').on('click', function(e) {
                    if ($(e.target).is('.wcem-modal')) {
                        $(this).removeClass('wcem-modal-visible');
                    }
                });
            });
        </script>
<?php
    }

    /**
     * Register bulk actions on WooCommerce Orders page
     */
    public static function register_orders_bulk_actions($bulk_actions)
    {
        $bulk_actions['pmcm_update_edition'] = __('Update Edition Data (Edition/Course MGMT)', 'prepmedico-course-management');
        $bulk_actions['pmcm_sync_fluentcrm'] = __('Sync to FluentCRM (Edition/Course MGMT)', 'prepmedico-course-management');
        return $bulk_actions;
    }

    /**
     * Handle bulk actions on WooCommerce Orders page
     */
    public static function handle_orders_bulk_action($redirect_to, $action, $order_ids)
    {
        if (!in_array($action, ['pmcm_update_edition', 'pmcm_sync_fluentcrm'])) {
            return $redirect_to;
        }

        if (!current_user_can('manage_woocommerce')) {
            return $redirect_to;
        }

        $success = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($order_ids as $order_id) {
            $order = wc_get_order($order_id);
            if (!$order) {
                $failed++;
                continue;
            }

            if ($action === 'pmcm_update_edition') {
                $result = self::bulk_update_order_edition($order);
                if ($result === 'updated') {
                    $success++;
                } elseif ($result === 'skipped') {
                    $skipped++;
                } else {
                    $failed++;
                }
            } elseif ($action === 'pmcm_sync_fluentcrm') {
                $result = self::bulk_sync_order_fluentcrm($order);
                if ($result === 'synced') {
                    $success++;
                } elseif ($result === 'skipped') {
                    $skipped++;
                } else {
                    $failed++;
                }
            }
        }

        $redirect_to = add_query_arg([
            'pmcm_bulk_action' => $action,
            'pmcm_success' => $success,
            'pmcm_failed' => $failed,
            'pmcm_skipped' => $skipped,
        ], $redirect_to);

        return $redirect_to;
    }

    /**
     * Update edition data for a single order (bulk helper)
     */
    private static function bulk_update_order_edition($order)
    {
        $courses_in_order = [];
        $processed_parents = [];

        foreach ($order->get_items() as $item_id => $item) {
            $product_id = $item->get_product_id();
            $categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'slugs']);

            foreach ($categories as $category_slug) {
                $course_data = PMCM_Core::get_course_for_category($category_slug);

                if ($course_data) {
                    $parent_slug = $course_data['parent_slug'];

                    if (in_array($parent_slug, $processed_parents)) {
                        continue;
                    }

                    $course = $course_data['course'];
                    $prefix = $course['settings_prefix'];
                    $edition_number = get_option($prefix . 'current_edition', 1);
                    $has_edition_management = isset($course['edition_management']) && $course['edition_management'] === true;

                    $edition_name = $has_edition_management
                        ? PMCM_Core::get_ordinal($edition_number) . ' ' . $course['name']
                        : $course['name'];

                    $edition_number_to_save = $has_edition_management ? $edition_number : null;
                    $edition_start = get_option($prefix . 'edition_start', '');
                    $edition_end = get_option($prefix . 'edition_end', '');

                    // Update order meta
                    $order->update_meta_data('_course_slug_' . $parent_slug, $parent_slug);
                    $order->update_meta_data('_course_edition_' . $parent_slug, $edition_number_to_save);
                    $order->update_meta_data('_edition_name_' . $parent_slug, $edition_name);
                    $order->update_meta_data('_edition_start_' . $parent_slug, $edition_start);
                    $order->update_meta_data('_edition_end_' . $parent_slug, $edition_end);
                    $order->update_meta_data('_original_category_' . $parent_slug, $category_slug);

                    // Update line item meta
                    wc_update_order_item_meta($item_id, '_course_slug', $parent_slug);
                    wc_update_order_item_meta($item_id, '_original_category', $category_slug);
                    wc_update_order_item_meta($item_id, '_course_edition', $edition_number_to_save);
                    wc_update_order_item_meta($item_id, '_edition_name', $edition_name);
                    wc_update_order_item_meta($item_id, '_edition_start', $edition_start);
                    wc_update_order_item_meta($item_id, '_edition_end', $edition_end);

                    $courses_in_order[] = [
                        'slug' => $parent_slug,
                        'original_category' => $category_slug,
                        'is_child' => $course_data['is_child'],
                        'course' => $course,
                        'edition' => $edition_number_to_save,
                        'edition_name' => $edition_name
                    ];

                    $processed_parents[] = $parent_slug;
                    break;
                }
            }
        }

        if (empty($courses_in_order)) {
            return 'skipped';
        }

        $order->update_meta_data('_wcem_needs_fluentcrm_sync', 'yes');
        $order->update_meta_data('_wcem_courses_data', json_encode($courses_in_order));
        $order->delete_meta_data('_wcem_fluentcrm_synced');
        $order->delete_meta_data('_wcem_fluentcrm_sync_time');
        $order->save();

        return 'updated';
    }

    /**
     * Sync a single order to FluentCRM (bulk helper)
     */
    private static function bulk_sync_order_fluentcrm($order)
    {
        $order_id = $order->get_id();
        $courses_data = $order->get_meta('_wcem_courses_data');

        if (empty($courses_data)) {
            return 'skipped';
        }

        $order->delete_meta_data('_wcem_fluentcrm_synced');
        $order->save();

        PMCM_FluentCRM::trigger_update($order_id);

        $order = wc_get_order($order_id);
        $synced = $order->get_meta('_wcem_fluentcrm_synced');

        return ($synced === 'yes') ? 'synced' : 'failed';
    }

    /**
     * AJAX: Save course
     */
    public static function ajax_save_course()
    {
        check_ajax_referer('wcem_admin_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        $slug = sanitize_text_field($_POST['category_slug'] ?? '');
        $name = sanitize_text_field($_POST['name'] ?? '');
        $tag = sanitize_text_field($_POST['fluentcrm_tag'] ?? '');
        $field = sanitize_text_field($_POST['fluentcrm_field'] ?? '');

        if (empty($slug) || empty($name) || empty($tag) || empty($field)) {
            wp_send_json_error(['message' => 'All required fields must be filled.']);
        }

        $children = [];
        if (!empty($_POST['children']) && is_array($_POST['children'])) {
            $children = array_map('sanitize_text_field', $_POST['children']);
            // Remove the parent itself from children if somehow selected
            $children = array_filter($children, function ($c) use ($slug) {
                return $c !== $slug;
            });
        }

        $course_data = [
            'name' => $name,
            'fluentcrm_tag' => $tag,
            'fluentcrm_field' => $field,
            'children' => array_values($children),
            'edition_management' => !empty($_POST['edition_management']),
            'asit_eligible' => !empty($_POST['asit_eligible'])
        ];

        PMCM_Core::save_course($slug, $course_data);

        wp_send_json_success(['message' => 'Course saved successfully.']);
    }

    /**
     * AJAX: Delete course
     */
    public static function ajax_delete_course()
    {
        check_ajax_referer('wcem_admin_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        $slug = sanitize_text_field($_POST['category_slug'] ?? '');

        if (empty($slug)) {
            wp_send_json_error(['message' => 'Invalid course.']);
        }

        if (PMCM_Core::delete_course($slug)) {
            wp_send_json_success(['message' => 'Course deleted successfully.']);
        } else {
            wp_send_json_error(['message' => 'Course not found.']);
        }
    }
}
