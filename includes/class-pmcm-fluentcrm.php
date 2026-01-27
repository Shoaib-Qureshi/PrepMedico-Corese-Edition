<?php
/**
 * PMCM FluentCRM Class
 * Handles all FluentCRM integration
 *
 * @package PrepMedico_Course_Management
 */

if (!defined('ABSPATH')) {
    exit;
}

class PMCM_FluentCRM {

    /**
     * Initialize FluentCRM hooks
     */
    public static function init() {
        add_action('woocommerce_order_status_processing', [__CLASS__, 'trigger_update'], 20, 1);
        add_action('woocommerce_order_status_completed', [__CLASS__, 'trigger_update'], 20, 1);
        add_action('woocommerce_payment_complete', [__CLASS__, 'trigger_update'], 20, 1);
        add_action('woocommerce_thankyou', [__CLASS__, 'trigger_update'], 20, 1);
    }

    /**
     * Check if FluentCRM is active
     */
    public static function is_active() {
        return defined('FLUENTCRM') && class_exists('FluentCrm\App\Models\Subscriber');
    }

    /**
     * Trigger FluentCRM update
     */
    public static function trigger_update($order_id) {
        $order = wc_get_order($order_id);

        if (!$order) {
            return;
        }

        $synced = $order->get_meta('_wcem_fluentcrm_synced');
        if ($synced === 'yes') {
            return;
        }

        $courses_json = $order->get_meta('_wcem_courses_data');
        if (empty($courses_json)) {
            return;
        }

        $courses_in_order = json_decode($courses_json, true);
        if (empty($courses_in_order)) {
            return;
        }

        if (self::is_active()) {
            $result = self::update_contact($order, $courses_in_order);

            if ($result) {
                $order->update_meta_data('_wcem_fluentcrm_synced', 'yes');
                $order->update_meta_data('_wcem_fluentcrm_sync_time', current_time('mysql'));
                $order->save();
            }
        }
    }

    /**
     * Update FluentCRM contact with course and edition data
     */
    private static function update_contact($order, $courses_in_order) {
        if (!class_exists('FluentCrm\App\Models\Subscriber')) {
            PMCM_Core::log_activity('FluentCRM Subscriber model not found', 'error');
            return false;
        }

        $email = $order->get_billing_email();

        if (empty($email)) {
            PMCM_Core::log_activity('No email for order #' . $order->get_id(), 'error');
            return false;
        }

        try {
            $subscriber = \FluentCrm\App\Models\Subscriber::where('email', $email)->first();

            if (!$subscriber) {
                PMCM_Core::log_activity('Creating new FluentCRM subscriber: ' . $email, 'info');

                $subscriber = \FluentCrm\App\Models\Subscriber::create([
                    'email' => $email,
                    'first_name' => $order->get_billing_first_name(),
                    'last_name' => $order->get_billing_last_name(),
                    'status' => 'subscribed',
                    'source' => 'woocommerce'
                ]);

                if (!$subscriber) {
                    PMCM_Core::log_activity('Failed to create FluentCRM subscriber: ' . $email, 'error');
                    return false;
                }
            }

            PMCM_Core::log_activity('Found/created FluentCRM subscriber ID: ' . $subscriber->id . ' for ' . $email, 'success');

            foreach ($courses_in_order as $course_data) {
                $course = $course_data['course'];
                $edition_name = $course_data['edition_name'];

                self::apply_tag($subscriber, $course['fluentcrm_tag']);
                self::update_custom_field($subscriber, $course['fluentcrm_field'], $edition_name);

                PMCM_Core::log_activity(
                    'Updated FluentCRM for ' . $email . ': Tag=' . $course['fluentcrm_tag'] . ', Field=' . $course['fluentcrm_field'] . '=' . $edition_name,
                    'success'
                );
            }

            return true;

        } catch (Exception $e) {
            PMCM_Core::log_activity('FluentCRM error: ' . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Apply FluentCRM tag to subscriber
     */
    private static function apply_tag($subscriber, $tag_name) {
        try {
            if (!class_exists('FluentCrm\App\Models\Tag')) {
                PMCM_Core::log_activity('FluentCRM Tag model not found', 'error');
                return;
            }

            $tag = \FluentCrm\App\Models\Tag::where('title', $tag_name)
                ->orWhere('slug', sanitize_title($tag_name))
                ->first();

            if (!$tag) {
                $tag = \FluentCrm\App\Models\Tag::create([
                    'title' => $tag_name,
                    'slug' => sanitize_title($tag_name)
                ]);
                PMCM_Core::log_activity('Created new tag: ' . $tag_name, 'info');
            }

            if ($tag) {
                $subscriber->attachTags([$tag->id]);
                PMCM_Core::log_activity('Applied tag "' . $tag_name . '" (ID: ' . $tag->id . ') to ' . $subscriber->email, 'success');
            }

        } catch (Exception $e) {
            PMCM_Core::log_activity('Tag error for "' . $tag_name . '": ' . $e->getMessage(), 'error');
        }
    }

    /**
     * Update FluentCRM custom field for subscriber
     */
    private static function update_custom_field($subscriber, $field_slug, $value) {
        try {
            if (method_exists($subscriber, 'syncCustomFieldValues')) {
                $subscriber->syncCustomFieldValues([$field_slug => $value], false);
                PMCM_Core::log_activity('Updated field via syncCustomFieldValues: ' . $field_slug . ' = ' . $value, 'success');
                return;
            }

            $customField = null;
            if (class_exists('FluentCrm\App\Models\CustomContactField')) {
                $customField = \FluentCrm\App\Models\CustomContactField::where('slug', $field_slug)->first();
            }

            if (!$customField) {
                PMCM_Core::log_activity('Custom field not found: ' . $field_slug . ' - Please create it in FluentCRM', 'error');
                return;
            }

            if (class_exists('FluentCrm\App\Models\SubscriberMeta')) {
                \FluentCrm\App\Models\SubscriberMeta::updateOrCreate(
                    [
                        'subscriber_id' => $subscriber->id,
                        'key' => $field_slug
                    ],
                    [
                        'value' => $value,
                        'object_type' => 'custom_field',
                        'created_at' => current_time('mysql'),
                        'updated_at' => current_time('mysql')
                    ]
                );
                PMCM_Core::log_activity('Updated field via SubscriberMeta: ' . $field_slug . ' = ' . $value, 'success');
                return;
            }

            global $wpdb;
            $table = $wpdb->prefix . 'fc_subscriber_meta';

            if ($wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
                $exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM $table WHERE subscriber_id = %d AND `key` = %s",
                    $subscriber->id,
                    $field_slug
                ));

                if ($exists) {
                    $wpdb->update(
                        $table,
                        ['value' => $value, 'updated_at' => current_time('mysql')],
                        ['subscriber_id' => $subscriber->id, 'key' => $field_slug]
                    );
                } else {
                    $wpdb->insert(
                        $table,
                        [
                            'subscriber_id' => $subscriber->id,
                            'key' => $field_slug,
                            'value' => $value,
                            'object_type' => 'custom_field',
                            'created_at' => current_time('mysql'),
                            'updated_at' => current_time('mysql')
                        ]
                    );
                }
                PMCM_Core::log_activity('Updated field via direct DB: ' . $field_slug . ' = ' . $value, 'success');
            } else {
                PMCM_Core::log_activity('FluentCRM meta table not found', 'error');
            }

        } catch (Exception $e) {
            PMCM_Core::log_activity('Custom field error for "' . $field_slug . '": ' . $e->getMessage(), 'error');
        }
    }
}
