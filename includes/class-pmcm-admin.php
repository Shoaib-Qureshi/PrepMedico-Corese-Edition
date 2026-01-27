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

class PMCM_Admin {

    /**
     * Initialize admin hooks
     */
    public static function init() {
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
    }

    /**
     * Add admin menu
     */
    public static function add_admin_menu() {
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
    public static function register_settings() {
        foreach (PMCM_Core::get_courses() as $category_slug => $course) {
            $prefix = $course['settings_prefix'];

            register_setting('wcem_settings', $prefix . 'current_edition', ['type' => 'integer', 'sanitize_callback' => 'absint']);
            register_setting('wcem_settings', $prefix . 'edition_start', ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field']);
            register_setting('wcem_settings', $prefix . 'edition_end', ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field']);
            register_setting('wcem_settings', $prefix . 'early_bird_enabled', ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field']);
            register_setting('wcem_settings', $prefix . 'early_bird_start', ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field']);
            register_setting('wcem_settings', $prefix . 'early_bird_end', ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field']);
        }

        register_setting('pmcm_asit_settings', 'pmcm_asit_coupon_code', ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field']);
        register_setting('pmcm_asit_settings', 'pmcm_asit_discount_early_bird', ['type' => 'integer', 'sanitize_callback' => 'absint']);
        register_setting('pmcm_asit_settings', 'pmcm_asit_discount_normal', ['type' => 'integer', 'sanitize_callback' => 'absint']);
    }

    /**
     * Enqueue admin scripts and styles
     */
    public static function enqueue_scripts($hook) {
        $allowed_hooks = [
            'toplevel_page_prepmedico-management',
            'prepmedico_page_prepmedico-asit-management',
            'woocommerce_page_wc-edition-management'
        ];

        if (!in_array($hook, $allowed_hooks)) {
            return;
        }

        wp_enqueue_style('pmcm-admin', PMCM_PLUGIN_URL . 'assets/css/admin.css', [], PMCM_VERSION);
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
    public static function admin_notices() {
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
    private static function save_settings() {
        foreach (PMCM_Core::get_edition_managed_courses() as $category_slug => $course) {
            $prefix = $course['settings_prefix'];
            $fields = ['current_edition', 'edition_start', 'edition_end', 'early_bird_enabled', 'early_bird_start', 'early_bird_end'];

            foreach ($fields as $field) {
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
        }
    }

    /**
     * Save ASiT settings
     */
    private static function save_asit_settings() {
        if (isset($_POST['pmcm_asit_coupon_code'])) {
            update_option('pmcm_asit_coupon_code', sanitize_text_field($_POST['pmcm_asit_coupon_code']));
        }
        if (isset($_POST['pmcm_asit_discount_early_bird'])) {
            update_option('pmcm_asit_discount_early_bird', absint($_POST['pmcm_asit_discount_early_bird']));
        }
        if (isset($_POST['pmcm_asit_discount_normal'])) {
            update_option('pmcm_asit_discount_normal', absint($_POST['pmcm_asit_discount_normal']));
        }
    }

    /**
     * Display activity log
     */
    private static function display_activity_log() {
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
    public static function get_shortcode_reference() {
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
     * Render Edition Management admin page
     */
    public static function render_edition_page() {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        if (isset($_POST['wcem_save_settings']) && check_admin_referer('wcem_settings_nonce')) {
            self::save_settings();

            // Run edition check after saving to auto-increment any expired editions
            PMCM_Cron::check_and_update_editions();

            echo '<div class="notice notice-success is-dismissible"><p>' . __('Settings saved! Edition check completed - see Activity Log for details.', 'prepmedico-course-management') . '</p></div>';
        }

        ?>
        <div class="wrap wcem-admin-wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <div class="wcem-header-info">
                <p><?php _e('Manage course editions for your WooCommerce products. Edition information is captured at checkout and stored with each order.', 'prepmedico-course-management'); ?></p>
            </div>

            <!-- Status Overview -->
            <div class="wcem-status-overview">
                <h2><?php _e('Current Edition Status', 'prepmedico-course-management'); ?></h2>
                <p class="description"><?php _e('Edition number auto-increments when the end date passes. Set new dates after increment.', 'prepmedico-course-management'); ?></p>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Course', 'prepmedico-course-management'); ?></th>
                            <th><?php _e('Current Edition', 'prepmedico-course-management'); ?></th>
                            <th><?php _e('Date Range', 'prepmedico-course-management'); ?></th>
                            <th><?php _e('Status', 'prepmedico-course-management'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (PMCM_Core::get_edition_managed_courses() as $category_slug => $course):
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
                        ?>
                        <tr>
                            <td><strong><?php echo esc_html($course['name']); ?></strong></td>
                            <td><?php echo esc_html(PMCM_Core::get_ordinal($current) . ' Edition'); ?></td>
                            <td><?php echo ($start && $end) ? esc_html(date('M j, Y', strtotime($start)) . ' - ' . date('M j, Y', strtotime($end))) : '<em class="wcem-warning">Not set</em>'; ?></td>
                            <td><span class="wcem-status wcem-status-<?php echo esc_attr($status); ?>"><?php echo esc_html($status_label); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Settings Form -->
            <form method="post" action="">
                <?php wp_nonce_field('wcem_settings_nonce'); ?>

                <div class="wcem-courses-settings">
                    <?php foreach (PMCM_Core::get_edition_managed_courses() as $category_slug => $course):
                        $prefix = $course['settings_prefix'];
                        $is_asit_eligible = isset($course['asit_eligible']) && $course['asit_eligible'];
                    ?>
                    <div class="wcem-course-card">
                        <h3><?php echo esc_html($course['name']); ?>
                            <?php if ($is_asit_eligible): ?>
                                <span class="wcem-asit-badge" title="ASiT Eligible">ASiT</span>
                            <?php endif; ?>
                        </h3>
                        <p class="description">
                            <?php printf(__('Category: %s | Tag: %s | Field: %s', 'prepmedico-course-management'),
                                '<code>' . esc_html($category_slug) . '</code>',
                                '<code>' . esc_html($course['fluentcrm_tag']) . '</code>',
                                '<code>' . esc_html($course['fluentcrm_field']) . '</code>'
                            ); ?>
                        </p>

                        <div class="wcem-edition-group">
                            <h4><?php _e('Current Edition', 'prepmedico-course-management'); ?></h4>
                            <table class="form-table">
                                <tr>
                                    <th><label for="<?php echo esc_attr($prefix); ?>current_edition"><?php _e('Edition Number', 'prepmedico-course-management'); ?></label></th>
                                    <td><input type="number" id="<?php echo esc_attr($prefix); ?>current_edition" name="<?php echo esc_attr($prefix); ?>current_edition" value="<?php echo esc_attr(get_option($prefix . 'current_edition', 1)); ?>" min="1" class="small-text"></td>
                                </tr>
                                <tr>
                                    <th><label for="<?php echo esc_attr($prefix); ?>edition_start"><?php _e('Start Date', 'prepmedico-course-management'); ?></label></th>
                                    <td><input type="date" id="<?php echo esc_attr($prefix); ?>edition_start" name="<?php echo esc_attr($prefix); ?>edition_start" value="<?php echo esc_attr(get_option($prefix . 'edition_start', '')); ?>"></td>
                                </tr>
                                <tr>
                                    <th><label for="<?php echo esc_attr($prefix); ?>edition_end"><?php _e('End Date', 'prepmedico-course-management'); ?></label></th>
                                    <td><input type="date" id="<?php echo esc_attr($prefix); ?>edition_end" name="<?php echo esc_attr($prefix); ?>edition_end" value="<?php echo esc_attr(get_option($prefix . 'edition_end', '')); ?>"></td>
                                </tr>
                            </table>
                        </div>

                        <div class="wcem-edition-group wcem-early-bird-group">
                            <h4><?php _e('Early Bird Settings', 'prepmedico-course-management'); ?></h4>
                            <table class="form-table">
                                <tr>
                                    <th><label for="<?php echo esc_attr($prefix); ?>early_bird_enabled"><?php _e('Early Bird', 'prepmedico-course-management'); ?></label></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" id="<?php echo esc_attr($prefix); ?>early_bird_enabled" name="<?php echo esc_attr($prefix); ?>early_bird_enabled" value="yes" <?php checked(get_option($prefix . 'early_bird_enabled', 'no'), 'yes'); ?> class="wcem-early-bird-toggle">
                                            <?php _e('Enable Early Bird Offer', 'prepmedico-course-management'); ?>
                                        </label>
                                    </td>
                                </tr>
                                <tr class="wcem-early-bird-date-row" style="<?php echo get_option($prefix . 'early_bird_enabled', 'no') !== 'yes' ? 'display:none;' : ''; ?>">
                                    <th><label for="<?php echo esc_attr($prefix); ?>early_bird_start"><?php _e('Early Bird Start', 'prepmedico-course-management'); ?></label></th>
                                    <td><input type="date" id="<?php echo esc_attr($prefix); ?>early_bird_start" name="<?php echo esc_attr($prefix); ?>early_bird_start" value="<?php echo esc_attr(get_option($prefix . 'early_bird_start', '')); ?>"></td>
                                </tr>
                                <tr class="wcem-early-bird-date-row" style="<?php echo get_option($prefix . 'early_bird_enabled', 'no') !== 'yes' ? 'display:none;' : ''; ?>">
                                    <th><label for="<?php echo esc_attr($prefix); ?>early_bird_end"><?php _e('Early Bird End', 'prepmedico-course-management'); ?></label></th>
                                    <td><input type="date" id="<?php echo esc_attr($prefix); ?>early_bird_end" name="<?php echo esc_attr($prefix); ?>early_bird_end" value="<?php echo esc_attr(get_option($prefix . 'early_bird_end', '')); ?>"></td>
                                </tr>
                                <tr class="wcem-early-bird-date-row" style="<?php echo get_option($prefix . 'early_bird_enabled', 'no') !== 'yes' ? 'display:none;' : ''; ?>">
                                    <th></th>
                                    <td>
                                        <p class="description" style="color: #0073aa; font-style: italic; margin: 0;">
                                            <span class="dashicons dashicons-info" style="font-size: 14px; vertical-align: middle;"></span>
                                            <?php _e('Please update the sale price accordingly for Early Bird on the WooCommerce products.', 'prepmedico-course-management'); ?>
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <div class="wcem-course-actions">
                            <button type="button" class="button wcem-manual-increment" data-course="<?php echo esc_attr($category_slug); ?>">
                                <?php _e('Increment Edition (+1)', 'prepmedico-course-management'); ?>
                            </button>
                            <p class="description" style="margin-top:8px;"><?php _e('Manually increment edition number. Dates will be cleared for new entry.', 'prepmedico-course-management'); ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <p class="submit">
                    <input type="submit" name="wcem_save_settings" class="button-primary" value="<?php _e('Save All Settings', 'prepmedico-course-management'); ?>">
                </p>
            </form>

            <!-- Shortcode Reference -->
            <div class="wcem-shortcode-reference">
                <h2><?php _e('Available Shortcodes', 'prepmedico-course-management'); ?></h2>
                <p><?php _e('Use these shortcodes to display edition and registration information on your pages:', 'prepmedico-course-management'); ?></p>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width:35%;"><?php _e('Shortcode', 'prepmedico-course-management'); ?></th>
                            <th style="width:35%;"><?php _e('Description', 'prepmedico-course-management'); ?></th>
                            <th style="width:30%;"><?php _e('Example Output', 'prepmedico-course-management'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (self::get_shortcode_reference() as $shortcode): ?>
                        <tr>
                            <td><code><?php echo esc_html($shortcode['shortcode']); ?></code></td>
                            <td><?php echo esc_html($shortcode['description']); ?></td>
                            <td><em><?php echo esc_html($shortcode['output']); ?></em></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p class="description" style="margin-top:10px;">
                    <?php _e('Replace "frcs" with the course slug you want to display. Available courses:', 'prepmedico-course-management'); ?>
                    <code><?php echo implode('</code>, <code>', array_keys(PMCM_Core::get_edition_managed_courses())); ?></code>
                </p>
            </div>

            <!-- FluentCRM Integration Status -->
            <div class="wcem-fluentcrm-status">
                <h2><?php _e('FluentCRM Integration', 'prepmedico-course-management'); ?></h2>
                <?php if (PMCM_FluentCRM::is_active()): ?>
                    <p class="wcem-success">
                        <span class="dashicons dashicons-yes-alt"></span>
                        <?php _e('FluentCRM is active and connected.', 'prepmedico-course-management'); ?>
                    </p>
                    <button type="button" class="button" id="wcem-test-fluentcrm">
                        <?php _e('Test FluentCRM Connection', 'prepmedico-course-management'); ?>
                    </button>
                <?php else: ?>
                    <p class="wcem-warning">
                        <span class="dashicons dashicons-warning"></span>
                        <?php _e('FluentCRM is not active. Please install and activate FluentCRM for full functionality.', 'prepmedico-course-management'); ?>
                    </p>
                <?php endif; ?>

                <h3><?php _e('Required FluentCRM Setup', 'prepmedico-course-management'); ?></h3>

                <div class="wcem-setup-columns">
                    <div class="wcem-setup-column">
                        <h4><?php printf(__('Tags (%d total)', 'prepmedico-course-management'), count(PMCM_Core::get_courses())); ?></h4>
                        <ul>
                            <?php foreach (PMCM_Core::get_courses() as $course): ?>
                                <li><code><?php echo esc_html($course['fluentcrm_tag']); ?></code></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <div class="wcem-setup-column">
                        <h4><?php printf(__('Custom Fields (%d total - Text type)', 'prepmedico-course-management'), count(PMCM_Core::get_courses())); ?></h4>
                        <p><em><?php _e('Each field stores the edition name like "12th FRCS"', 'prepmedico-course-management'); ?></em></p>
                        <ul>
                            <?php foreach (PMCM_Core::get_courses() as $course): ?>
                                <li><code><?php echo esc_html($course['fluentcrm_field']); ?></code> (Text)</li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>

                <h3><?php _e('Category Mapping (Parent → Child)', 'prepmedico-course-management'); ?></h3>
                <p><em><?php _e('Child categories automatically inherit the parent\'s tag and custom field.', 'prepmedico-course-management'); ?></em></p>
                <table class="wp-list-table widefat fixed striped" style="margin-top:10px;">
                    <thead>
                        <tr>
                            <th><?php _e('Parent Category', 'prepmedico-course-management'); ?></th>
                            <th><?php _e('Child Categories', 'prepmedico-course-management'); ?></th>
                            <th><?php _e('FluentCRM Tag', 'prepmedico-course-management'); ?></th>
                            <th><?php _e('FluentCRM Field', 'prepmedico-course-management'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $child_map = PMCM_Core::get_child_to_parent_map();
                        foreach (PMCM_Core::get_courses() as $parent_slug => $course):
                            $children = array_keys(array_filter($child_map, function($p) use ($parent_slug) {
                                return $p === $parent_slug;
                            }));
                        ?>
                        <tr>
                            <td><code><?php echo esc_html($parent_slug); ?></code><br><small><?php echo esc_html($course['name']); ?></small></td>
                            <td>
                                <?php if (!empty($children)): ?>
                                    <?php foreach ($children as $child): ?>
                                        <code style="display:block;margin:2px 0;"><?php echo esc_html($child); ?></code>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <em><?php _e('No child categories', 'prepmedico-course-management'); ?></em>
                                <?php endif; ?>
                            </td>
                            <td><code><?php echo esc_html($course['fluentcrm_tag']); ?></code></td>
                            <td><code><?php echo esc_html($course['fluentcrm_field']); ?></code></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Activity Log -->
            <div class="wcem-edition-log">
                <h2><?php _e('Recent Activity Log', 'prepmedico-course-management'); ?></h2>
                <?php self::display_activity_log(); ?>
                <p><button type="button" class="button" onclick="if(confirm('Clear all logs?')){location.href='<?php echo admin_url('admin.php?page=prepmedico-management&clear_log=1&_wpnonce=' . wp_create_nonce('clear_log')); ?>'}"><?php _e('Clear Log', 'prepmedico-course-management'); ?></button></p>
                <?php
                if (isset($_GET['clear_log']) && wp_verify_nonce($_GET['_wpnonce'], 'clear_log')) {
                    delete_option('wcem_activity_log');
                    echo '<script>location.href="' . admin_url('admin.php?page=prepmedico-management') . '";</script>';
                }
                ?>
            </div>

            <!-- Cron Status -->
            <div class="wcem-cron-status">
                <h2><?php _e('Scheduled Tasks', 'prepmedico-course-management'); ?></h2>
                <?php
                $next_run = wp_next_scheduled('wcem_daily_edition_check');
                if ($next_run) {
                    $next_run_local = get_date_from_gmt(date('Y-m-d H:i:s', $next_run), 'F j, Y g:i a');
                    echo '<p>' . sprintf(__('Next edition check: %s', 'prepmedico-course-management'), '<strong>' . esc_html($next_run_local) . '</strong>') . '</p>';
                } else {
                    echo '<p class="wcem-warning">' . __('Cron job not scheduled. Please deactivate and reactivate the plugin.', 'prepmedico-course-management') . '</p>';
                }
                ?>
                <button type="button" class="button" id="wcem-run-cron">
                    <?php _e('Run Edition Check Now', 'prepmedico-course-management'); ?>
                </button>
            </div>
        </div>

        <style>
            .wcem-admin-wrap { max-width: 1200px; }
            .wcem-header-info { background: #fff; padding: 15px; border-left: 4px solid #8d2063; margin-bottom: 20px; }
            .wcem-status-overview, .wcem-shortcode-reference { background: #fff; padding: 20px; margin-bottom: 20px; }
            .wcem-status { padding: 3px 8px; border-radius: 3px; font-size: 12px; font-weight: 500; }
            .wcem-status-active { background: #d4edda; color: #155724; }
            .wcem-status-expired { background: #f8d7da; color: #721c24; }
            .wcem-status-ending-soon { background: #fff3cd; color: #856404; }
            .wcem-status-early-bird { background: #d1ecf1; color: #0c5460; }
            .wcem-status-needs-dates { background: #f8d7da; color: #721c24; }
            .wcem-warning { color: #856404; }
            .wcem-courses-settings { display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 20px; margin: 20px 0; }
            .wcem-course-card { background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; }
            .wcem-course-card h3 { margin-top: 0; padding-bottom: 10px; border-bottom: 1px solid #eee; display: flex; align-items: center; gap: 10px; }
            .wcem-course-card .description { color: #666; font-size: 12px; }
            .wcem-asit-badge { background: #8d2063; color: #fff; padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: 600; }
            .wcem-edition-group { margin: 15px 0; padding: 15px; background: #f9f9f9; border-radius: 4px; }
            .wcem-early-bird-group { background: #fff8e1; border: 1px solid #ffc107; }
            .wcem-edition-group h4 { margin: 0 0 10px 0; }
            .wcem-edition-group .form-table { margin: 0; }
            .wcem-edition-group .form-table th { padding: 8px 10px 8px 0; width: 130px; }
            .wcem-edition-group .form-table td { padding: 8px 0; }
            .wcem-course-actions { margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee; }
            .wcem-fluentcrm-status, .wcem-edition-log, .wcem-cron-status { background: #fff; padding: 20px; margin-top: 20px; border: 1px solid #ccd0d4; }
            .wcem-success { color: #155724; }
            .wcem-success .dashicons { color: #28a745; }
            .wcem-setup-columns { display: flex; gap: 40px; }
            .wcem-setup-column ul { margin: 0; padding-left: 20px; }
            .wcem-setup-column li { margin-bottom: 5px; }
            .wcem-log-entry { padding: 8px; border-bottom: 1px solid #eee; font-size: 13px; }
            .wcem-log-entry.success { background: #d4edda; }
            .wcem-log-entry.error { background: #f8d7da; }
            .wcem-log-entry.info { background: #d1ecf1; }
            .wcem-log-entry .time { color: #666; font-size: 11px; }
            .wcem-shortcode-reference { border: 1px solid #ccd0d4; }
            .wcem-shortcode-reference code { background: #f0f0f1; padding: 2px 6px; border-radius: 3px; }
        </style>
        <script>
        jQuery(document).ready(function($) {
            $('.wcem-early-bird-toggle').on('change', function() {
                var $table = $(this).closest('table');
                var $rows = $table.find('.wcem-early-bird-date-row');
                if ($(this).is(':checked')) {
                    $rows.show();
                } else {
                    $rows.hide();
                }
            });
        });
        </script>
        <?php
    }

    /**
     * Render ASiT Coupon Management admin page
     */
    public static function render_asit_page() {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        if (isset($_POST['pmcm_save_asit_settings']) && check_admin_referer('pmcm_asit_settings_nonce')) {
            self::save_asit_settings();
            echo '<div class="notice notice-success is-dismissible"><p>' . __('ASiT settings saved successfully!', 'prepmedico-course-management') . '</p></div>';
        }

        $coupon_code = get_option('pmcm_asit_coupon_code', 'ASIT');
        $discount_early_bird = get_option('pmcm_asit_discount_early_bird', 5);
        $discount_normal = get_option('pmcm_asit_discount_normal', 10);

        $asit_courses = PMCM_Core::get_asit_eligible_courses();

        $any_early_bird_active = false;
        foreach ($asit_courses as $slug => $course) {
            $prefix = $course['settings_prefix'];
            $eb_enabled = get_option($prefix . 'early_bird_enabled', 'no');
            $eb_end = get_option($prefix . 'early_bird_end', '');
            if ($eb_enabled === 'yes' && !empty($eb_end) && strtotime($eb_end) >= strtotime(current_time('Y-m-d'))) {
                $any_early_bird_active = true;
                break;
            }
        }

        $current_discount = $any_early_bird_active ? $discount_early_bird : $discount_normal;
        ?>
        <div class="wrap wcem-admin-wrap">
            <h1><?php _e('ASiT Coupon Management', 'prepmedico-course-management'); ?></h1>

            <div class="wcem-header-info">
                <p><?php _e('Manage ASiT membership discount settings. The discount automatically adjusts based on Early Bird status of eligible courses.', 'prepmedico-course-management'); ?></p>
            </div>

            <!-- Current Status -->
            <div class="wcem-status-overview">
                <h2><?php _e('Current Status', 'prepmedico-course-management'); ?></h2>
                <table class="wp-list-table widefat fixed striped">
                    <tr>
                        <th style="width:200px;"><?php _e('Coupon Code', 'prepmedico-course-management'); ?></th>
                        <td><code style="font-size:16px;"><?php echo esc_html($coupon_code); ?></code></td>
                    </tr>
                    <tr>
                        <th><?php _e('Early Bird Active?', 'prepmedico-course-management'); ?></th>
                        <td>
                            <?php if ($any_early_bird_active): ?>
                                <span class="wcem-status wcem-status-early-bird"><?php _e('Yes - Early Bird Active', 'prepmedico-course-management'); ?></span>
                            <?php else: ?>
                                <span class="wcem-status wcem-status-active"><?php _e('No - Normal Period', 'prepmedico-course-management'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Current ASiT Discount', 'prepmedico-course-management'); ?></th>
                        <td><strong style="font-size:18px; color:#8d2063;"><?php echo esc_html($current_discount); ?>%</strong></td>
                    </tr>
                </table>
            </div>

            <!-- Eligible Categories -->
            <div class="wcem-status-overview">
                <h2><?php _e('ASiT Eligible Categories', 'prepmedico-course-management'); ?></h2>
                <p><?php _e('ASiT discount only applies to products in these categories:', 'prepmedico-course-management'); ?></p>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Category', 'prepmedico-course-management'); ?></th>
                            <th><?php _e('Slug', 'prepmedico-course-management'); ?></th>
                            <th><?php _e('Early Bird Status', 'prepmedico-course-management'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($asit_courses as $slug => $course):
                            $prefix = $course['settings_prefix'];
                            $eb_enabled = get_option($prefix . 'early_bird_enabled', 'no');
                            $eb_end = get_option($prefix . 'early_bird_end', '');
                            $eb_active = ($eb_enabled === 'yes' && !empty($eb_end) && strtotime($eb_end) >= strtotime(current_time('Y-m-d')));
                        ?>
                        <tr>
                            <td><strong><?php echo esc_html($course['name']); ?></strong></td>
                            <td><code><?php echo esc_html($slug); ?></code></td>
                            <td>
                                <?php if ($eb_active): ?>
                                    <span class="wcem-status wcem-status-early-bird"><?php printf(__('Active until %s', 'prepmedico-course-management'), date('M j, Y', strtotime($eb_end))); ?></span>
                                <?php else: ?>
                                    <span class="wcem-status wcem-status-active"><?php _e('Not Active', 'prepmedico-course-management'); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Settings Form -->
            <form method="post" action="">
                <?php wp_nonce_field('pmcm_asit_settings_nonce'); ?>

                <div class="wcem-status-overview">
                    <h2><?php _e('ASiT Discount Settings', 'prepmedico-course-management'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th><label for="pmcm_asit_coupon_code"><?php _e('Coupon Code', 'prepmedico-course-management'); ?></label></th>
                            <td>
                                <input type="text" id="pmcm_asit_coupon_code" name="pmcm_asit_coupon_code" value="<?php echo esc_attr($coupon_code); ?>" class="regular-text">
                                <p class="description"><?php _e('The WooCommerce coupon code for ASiT members.', 'prepmedico-course-management'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="pmcm_asit_discount_early_bird"><?php _e('Discount During Early Bird', 'prepmedico-course-management'); ?></label></th>
                            <td>
                                <input type="number" id="pmcm_asit_discount_early_bird" name="pmcm_asit_discount_early_bird" value="<?php echo esc_attr($discount_early_bird); ?>" min="1" max="100" class="small-text"> %
                                <p class="description"><?php _e('ASiT discount percentage when Early Bird is active.', 'prepmedico-course-management'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="pmcm_asit_discount_normal"><?php _e('Normal Discount', 'prepmedico-course-management'); ?></label></th>
                            <td>
                                <input type="number" id="pmcm_asit_discount_normal" name="pmcm_asit_discount_normal" value="<?php echo esc_attr($discount_normal); ?>" min="1" max="100" class="small-text"> %
                                <p class="description"><?php _e('ASiT discount percentage when Early Bird is NOT active.', 'prepmedico-course-management'); ?></p>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <input type="submit" name="pmcm_save_asit_settings" class="button button-primary" value="<?php _e('Save ASiT Settings', 'prepmedico-course-management'); ?>">
                    </p>
                </div>
            </form>

            <!-- Instructions -->
            <div class="wcem-fluentcrm-status">
                <h2><?php _e('WooCommerce Coupon Setup', 'prepmedico-course-management'); ?></h2>
                <p><?php _e('To make this work, ensure you have a WooCommerce coupon configured:', 'prepmedico-course-management'); ?></p>
                <ol>
                    <li><?php printf(__('Go to <a href="%s">WooCommerce → Coupons</a>', 'prepmedico-course-management'), admin_url('edit.php?post_type=shop_coupon')); ?></li>
                    <li><?php printf(__('Create or edit a coupon with code: <code>%s</code>', 'prepmedico-course-management'), esc_html($coupon_code)); ?></li>
                    <li><?php _e('Set "Discount type" to "Percentage discount"', 'prepmedico-course-management'); ?></li>
                    <li><?php _e('The discount amount will be dynamically updated by this plugin', 'prepmedico-course-management'); ?></li>
                    <li><?php _e('Under "Usage restriction" → "Product categories", add: <strong>FRCS</strong> and <strong>FRCS-VASC</strong>', 'prepmedico-course-management'); ?></li>
                </ol>

                <h3><?php _e('How It Works', 'prepmedico-course-management'); ?></h3>
                <ul>
                    <li><?php printf(__('When Early Bird is <strong>active</strong>: ASiT members get <strong>%d%%</strong> discount', 'prepmedico-course-management'), $discount_early_bird); ?></li>
                    <li><?php printf(__('When Early Bird is <strong>not active</strong>: ASiT members get <strong>%d%%</strong> discount', 'prepmedico-course-management'), $discount_normal); ?></li>
                </ul>
            </div>

            <!-- Bulk Sync ASiT Orders to FluentCRM -->
            <div class="wcem-status-overview">
                <h2><?php _e('Sync ASiT Orders to FluentCRM', 'prepmedico-course-management'); ?></h2>
                <p><?php _e('Scan old orders for ASiT coupon usage and sync them to FluentCRM. This will update the "asit" custom field for all contacts who used the ASiT coupon.', 'prepmedico-course-management'); ?></p>

                <div id="wcem-asit-sync-results" style="margin: 15px 0; padding: 15px; background: #f6f7f7; border-radius: 4px; display: none;">
                    <div id="wcem-asit-sync-progress"></div>
                </div>

                <p>
                    <button type="button" id="wcem-scan-asit-orders" class="button button-secondary">
                        <?php _e('Scan Orders for ASiT Coupon', 'prepmedico-course-management'); ?>
                    </button>
                    <button type="button" id="wcem-bulk-sync-asit" class="button button-primary" style="margin-left: 10px;" disabled>
                        <?php _e('Sync All to FluentCRM', 'prepmedico-course-management'); ?>
                    </button>
                </p>

                <script>
                jQuery(document).ready(function($) {
                    var asitOrders = [];

                    $('#wcem-scan-asit-orders').on('click', function() {
                        var btn = $(this);
                        btn.prop('disabled', true).text('<?php _e('Scanning...', 'prepmedico-course-management'); ?>');
                        $('#wcem-asit-sync-results').show();
                        $('#wcem-asit-sync-progress').html('<p><?php _e('Scanning orders for ASiT coupon usage...', 'prepmedico-course-management'); ?></p>');

                        $.post(ajaxurl, {
                            action: 'wcem_bulk_sync_asit',
                            mode: 'scan',
                            nonce: '<?php echo wp_create_nonce('wcem_admin_nonce'); ?>'
                        }, function(response) {
                            btn.prop('disabled', false).text('<?php _e('Scan Orders for ASiT Coupon', 'prepmedico-course-management'); ?>');

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
                        btn.prop('disabled', true).text('<?php _e('Syncing...', 'prepmedico-course-management'); ?>');

                        $.post(ajaxurl, {
                            action: 'wcem_bulk_sync_asit',
                            mode: 'sync',
                            nonce: '<?php echo wp_create_nonce('wcem_admin_nonce'); ?>'
                        }, function(response) {
                            btn.prop('disabled', true).text('<?php _e('Sync All to FluentCRM', 'prepmedico-course-management'); ?>');

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
            </div>
        </div>

        <style>
            .wcem-admin-wrap { max-width: 1000px; }
            .wcem-header-info { background: #fff; padding: 15px; border-left: 4px solid #8d2063; margin-bottom: 20px; }
            .wcem-status-overview { background: #fff; padding: 20px; margin-bottom: 20px; border: 1px solid #ccd0d4; }
            .wcem-status { padding: 4px 10px; border-radius: 3px; font-size: 12px; font-weight: 500; }
            .wcem-status-active { background: #d4edda; color: #155724; }
            .wcem-status-early-bird { background: #d1ecf1; color: #0c5460; }
            .wcem-fluentcrm-status { background: #fff; padding: 20px; border: 1px solid #ccd0d4; }
            .wcem-fluentcrm-status ol, .wcem-fluentcrm-status ul { margin-left: 20px; }
            .wcem-fluentcrm-status li { margin-bottom: 8px; }
        </style>
        <?php
    }

    /**
     * AJAX: Manual edition increment
     */
    public static function ajax_manual_edition_switch() {
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
    public static function ajax_test_fluentcrm() {
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
    public static function ajax_run_cron() {
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
    public static function ajax_sync_order_to_fluentcrm() {
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
    public static function ajax_update_order_edition() {
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
    public static function ajax_bulk_sync_asit_orders() {
        check_ajax_referer('wcem_admin_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        $mode = sanitize_text_field($_POST['mode'] ?? 'scan');
        $asit_coupon_code = strtolower(get_option('pmcm_asit_coupon_code', 'ASIT'));

        // Get all orders with the ASiT coupon
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

            $coupons = $order->get_coupon_codes();
            $has_asit = false;

            foreach ($coupons as $coupon_code) {
                if (strtolower($coupon_code) === $asit_coupon_code) {
                    $has_asit = true;
                    break;
                }
            }

            if ($has_asit) {
                // Mark order as ASiT member if not already
                if ($order->get_meta('_wcem_asit_member') !== 'yes') {
                    $order->update_meta_data('_wcem_asit_member', 'yes');
                    $order->update_meta_data('_wcem_asit_coupon_used', $coupon_code);
                    $order->save();
                }

                $asit_orders[] = [
                    'id' => $order_id,
                    'email' => $order->get_billing_email(),
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
                    // Update asit custom field
                    if (method_exists($subscriber, 'syncCustomFieldValues')) {
                        $subscriber->syncCustomFieldValues(['asit' => 'Yes'], false);
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
                                    ['value' => 'Yes', 'updated_at' => current_time('mysql')],
                                    ['subscriber_id' => $subscriber->id, 'key' => 'asit']
                                );
                            } else {
                                $wpdb->insert(
                                    $table,
                                    [
                                        'subscriber_id' => $subscriber->id,
                                        'key' => 'asit',
                                        'value' => 'Yes',
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
                    PMCM_Core::log_activity('Bulk ASiT sync: Updated FluentCRM asit field for ' . $email . ' (Order #' . $order_data['id'] . ')', 'success');
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
}
