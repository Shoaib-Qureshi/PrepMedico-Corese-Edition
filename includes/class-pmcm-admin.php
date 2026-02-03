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

        // AJAX handlers
        add_action('wp_ajax_wcem_manual_edition_switch', [__CLASS__, 'ajax_manual_edition_switch']);
        add_action('wp_ajax_wcem_test_fluentcrm', [__CLASS__, 'ajax_test_fluentcrm']);
        add_action('wp_ajax_wcem_run_cron', [__CLASS__, 'ajax_run_cron']);
        add_action('wp_ajax_wcem_sync_order', [__CLASS__, 'ajax_sync_order_to_fluentcrm']);
        add_action('wp_ajax_wcem_update_order_edition', [__CLASS__, 'ajax_update_order_edition']);
        add_action('wp_ajax_wcem_bulk_sync_asit', [__CLASS__, 'ajax_bulk_sync_asit_orders']);
        add_action('wp_ajax_wcem_save_course', [__CLASS__, 'ajax_save_course']);
        add_action('wp_ajax_wcem_delete_course', [__CLASS__, 'ajax_delete_course']);
    }

    /**
     * Add admin menu
     */
    public static function add_admin_menu()
    {
        add_menu_page(
            __('PrepMedico', 'prepmedico-course-management'),
            __('PrepMedico', 'prepmedico-course-management'),
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
            __('ASiT Coupon Management', 'prepmedico-course-management'),
            __('ASiT Coupon Management', 'prepmedico-course-management'),
            'manage_woocommerce',
            'prepmedico-asit-management',
            [__CLASS__, 'render_asit_page']
        );
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
        }

        register_setting('pmcm_asit_settings', 'pmcm_asit_coupon_code', ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field']);
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
        $allowed_hooks = [
            'toplevel_page_prepmedico-management',
            'prepmedico_page_prepmedico-asit-management',
            'prepmedico_page_prepmedico-course-config',
            'woocommerce_page_wc-edition-management'
        ];

        if (!in_array($hook, $allowed_hooks)) {
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
            echo '<p><strong>' . __('PrepMedico Edition Notice:', 'prepmedico-course-management') . '</strong></p>';
            echo '<ul>';
            foreach ($warnings as $warning) {
                echo '<li>' . esc_html($warning) . '</li>';
            }
            echo '</ul>';
            echo '<p><a href="' . admin_url('admin.php?page=prepmedico-management') . '">' . __('Configure edition settings', 'prepmedico-course-management') . '</a></p>';
            echo '</div>';
        }
    }

    /**
     * Save edition settings
     */
    private static function save_settings()
    {
        foreach (PMCM_Core::get_edition_managed_courses() as $category_slug => $course) {
            $prefix = $course['settings_prefix'];

            // Current edition slot fields
            $current_fields = ['current_edition', 'edition_start', 'edition_end', 'early_bird_enabled', 'early_bird_start', 'early_bird_end'];
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

            $next_fields = ['next_edition', 'next_start', 'next_end', 'next_early_bird_enabled', 'next_early_bird_start', 'next_early_bird_end'];
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
        }
    }

    /**
     * Save ASiT settings (global and per-course)
     */
    private static function save_asit_settings()
    {
        // Save global coupon code
        if (isset($_POST['pmcm_asit_coupon_code'])) {
            update_option('pmcm_asit_coupon_code', sanitize_text_field($_POST['pmcm_asit_coupon_code']));
        }
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

                // Validate mode
                if (!in_array($mode, ['none', 'early_bird_only', 'always'])) {
                    $mode = 'none';
                }

                // Update course configuration
                $courses[$course_slug]['asit_discount_mode'] = $mode;
                $courses[$course_slug]['asit_early_bird_discount'] = $eb_discount;
                $courses[$course_slug]['asit_normal_discount'] = $normal_discount;
                $courses[$course_slug]['asit_show_field'] = $show_field;
                $courses[$course_slug]['asit_product_filter'] = $product_filter;
                $courses[$course_slug]['asit_include_children'] = $include_children;
                $courses[$course_slug]['asit_selected_products'] = array_values(array_unique(array_filter($selected_products)));

                // Update legacy asit_eligible field for backward compatibility
                $courses[$course_slug]['asit_eligible'] = ($mode !== 'none');
            }

            update_option('pmcm_course_mappings', $courses);
            PMCM_Core::clear_cache();
            PMCM_Core::log_activity('ASiT per-course settings updated', 'success');
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

        $status = 'active';
        $status_label = __('Active', 'prepmedico-course-management');

        if (empty($start) || empty($end)) {
            $status = 'needs-dates';
            $status_label = __('Needs Dates', 'prepmedico-course-management');
        } elseif ($early_bird === 'yes') {
            $eb_end = get_option($prefix . 'early_bird_end', '');
            if (!empty($eb_end) && time() <= strtotime($eb_end)) {
                $status = 'early-bird';
                $status_label = __('Early Bird', 'prepmedico-course-management');
            }
        }

        if (!empty($end)) {
            $end_timestamp = strtotime($end);
            if (time() > $end_timestamp) {
                $status = 'expired';
                $status_label = __('Opening Soon', 'prepmedico-course-management');
            } elseif ((($end_timestamp - time()) / DAY_IN_SECONDS) <= 7) {
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
                    <form method="post" id="wcem-edition-form">
                        <?php wp_nonce_field('wcem_settings_nonce'); ?>
                        <?php
                        $is_first = true;
                        foreach ($courses as $category_slug => $course):
                            $prefix = $course['settings_prefix'];
                            $current = get_option($prefix . 'current_edition', 1);
                            $start = get_option($prefix . 'edition_start', '');
                            $end = get_option($prefix . 'edition_end', '');
                            $eb_enabled = get_option($prefix . 'early_bird_enabled', 'no') === 'yes';
                            $eb_start = get_option($prefix . 'early_bird_start', '');
                            $eb_end = get_option($prefix . 'early_bird_end', '');
                            $next_enabled = get_option($prefix . 'next_enabled', 'no') === 'yes';
                            $next_edition = get_option($prefix . 'next_edition', $current + 1);
                            $next_start = get_option($prefix . 'next_start', '');
                            $next_end = get_option($prefix . 'next_end', '');
                            $next_eb_enabled = get_option($prefix . 'next_early_bird_enabled', 'no') === 'yes';
                            $next_eb_start = get_option($prefix . 'next_early_bird_start', '');
                            $next_eb_end = get_option($prefix . 'next_early_bird_end', '');
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
                                    <button type="submit" name="wcem_save_settings" class="wcem-save-btn">
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
                    <div class="wcem-card-body">
                        <div class="wcem-shortcode-list">
                            <div class="wcem-shortcode-item">
                                <code class="wcem-shortcode-code">[current_edition course="<span class="wcem-dynamic-course"><?php echo esc_html($first_course_slug); ?></span>"]</code>
                                <p class="wcem-shortcode-desc"><?php _e('Displays the current edition name in standard format.', 'prepmedico-course-management'); ?></p>
                            </div>
                            <div class="wcem-shortcode-item">
                                <code class="wcem-shortcode-code">[registration_status course="<span class="wcem-dynamic-course"><?php echo esc_html($first_course_slug); ?></span>"]</code>
                                <p class="wcem-shortcode-desc"><?php _e('Shows status badges (Live / Closed / Early Bird).', 'prepmedico-course-management'); ?></p>
                            </div>
                        </div>
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
            echo '<div class="notice notice-success is-dismissible"><p>' . __('ASiT settings saved successfully!', 'prepmedico-course-management') . '</p></div>';
        }

        $coupon_code = get_option('pmcm_asit_coupon_code', 'ASIT');
        $default_discount = get_option('pmcm_asit_discount_normal', 15);
        $all_courses = PMCM_Core::get_courses();
    ?>
        <div class="wrap wcem-admin-wrap wcem-asit-modern">
            <!-- Header -->
            <header class="wcem-asit-header">
                <div class="wcem-asit-header-content">
                    <span class="material-icons-round wcem-asit-icon">local_offer</span>
                    <h1><?php _e('ASiT Coupon Management', 'prepmedico-course-management'); ?></h1>
                </div>
            </header>

            <!-- Global Coupon Configuration Card -->
            <form method="post" action="" id="wcem-asit-form">
                <?php wp_nonce_field('pmcm_asit_settings_nonce'); ?>

                <section class="wcem-asit-global-config">
                    <div class="wcem-asit-global-header">
                        <div class="wcem-asit-global-icon">
                            <span class="material-icons-round">settings</span>
                        </div>
                        <h2><?php _e('Global Coupon Configuration', 'prepmedico-course-management'); ?></h2>
                        <button type="submit" name="pmcm_save_asit_settings" class="wcem-asit-save-global">
                            <span class="material-icons-round">save</span>
                            <?php _e('Save Global Settings', 'prepmedico-course-management'); ?>
                        </button>
                    </div>
                    <div class="wcem-asit-global-body">
                        <div class="wcem-asit-global-field">
                            <label><?php _e('Active Coupon Code', 'prepmedico-course-management'); ?></label>
                            <div class="wcem-asit-input-with-tag">
                                <input type="text" name="pmcm_asit_coupon_code" value="<?php echo esc_attr($coupon_code); ?>" class="wcem-asit-coupon-input">
                                <span class="wcem-asit-tag"><?php _e('GLOBAL OVERRIDE', 'prepmedico-course-management'); ?></span>
                            </div>
                        </div>
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

                                <div class="wcem-asit-card-footer">
                                    <?php if ($mode === 'early_bird_only'): ?>
                                        <?php if ($is_eb_active): ?>
                                            <span class="wcem-asit-footer-status active">
                                                <span class="material-icons-round">check_circle</span>
                                                <?php _e('Early Bird Active', 'prepmedico-course-management'); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="wcem-asit-footer-status expired">
                                                <span class="material-icons-round">schedule</span>
                                                <?php _e('Ends Nov 15th', 'prepmedico-course-management'); ?>
                                            </span>
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

            <!-- Instructions Section -->
            <section class="wcem-asit-instructions">
                <div class="wcem-asit-instructions-header">
                    <span class="material-icons-round">help_outline</span>
                    <h3><?php _e('WooCommerce Coupon Setup', 'prepmedico-course-management'); ?></h3>
                </div>
                <div class="wcem-asit-instructions-body">
                    <ol>
                        <li><?php printf(__('Go to <a href="%s">WooCommerce → Coupons</a>', 'prepmedico-course-management'), admin_url('edit.php?post_type=shop_coupon')); ?></li>
                        <li><?php printf(__('Create or edit a coupon with code: <code>%s</code>', 'prepmedico-course-management'), esc_html($coupon_code)); ?></li>
                        <li><?php _e('Set "Discount type" to "Percentage discount"', 'prepmedico-course-management'); ?></li>
                        <li><?php _e('Set any percentage (it will be overridden dynamically per product)', 'prepmedico-course-management'); ?></li>
                    </ol>
                </div>
            </section>

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
                        var order = {'active': 1, 'early-bird': 2, 'inactive': 3};
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
                    $('#wcem-course-modal').show();
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

                    $('#wcem-course-modal').show();
                });

                // Close modal
                $('.wcem-modal-close, .wcem-modal-cancel').on('click', function() {
                    $('#wcem-course-modal').hide();
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
                        $(this).hide();
                    }
                });
            });
        </script>
<?php
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
