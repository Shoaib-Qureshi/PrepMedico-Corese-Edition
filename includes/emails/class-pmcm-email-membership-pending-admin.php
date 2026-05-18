<?php
/**
 * Admin email: membership pending confirmation
 *
 * @package PrepMedico_Course_Management
 */

if (!defined('ABSPATH')) {
    exit;
}

class PMCM_Email_Membership_Pending_Admin extends WC_Email {

    public function __construct() {
        $this->id             = 'pmcm_membership_pending_admin';
        $this->title          = __('Academic Partner — Membership Pending (Admin)', 'prepmedico-course-management');
        $this->description    = __('Sent to the store admin when an order requires academic partner membership verification.', 'prepmedico-course-management');
        $this->heading        = __('Membership Verification Required', 'prepmedico-course-management');
        $this->subject        = __('Membership Verification Required — Order #{order_number}', 'prepmedico-course-management');
        $this->recipient      = $this->get_option('recipient', get_option('admin_email'));
        $this->template_html  = '';
        $this->template_plain = '';

        // Trigger when order moves to membership-pending status
        add_action('woocommerce_order_status_wc-membership-pending', [$this, 'trigger'], 10, 2);
        add_action('woocommerce_order_status_pending_to_wc-membership-pending', [$this, 'trigger'], 10, 2);

        parent::__construct();
    }

    public function trigger($order_id, $order = null) {
        $this->setup_locale();

        if (!$order_id) {
            return;
        }

        if (!is_a($order, 'WC_Order')) {
            $order = wc_get_order($order_id);
        }

        if (!$order) {
            return;
        }

        $this->object = $order;

        $partner      = $order->get_meta('_pmcm_academic_partner');
        $partner_num  = $order->get_meta('_pmcm_partner_number');

        // Only send for orders with an academic partner
        if (empty($partner)) {
            return;
        }

        $this->placeholders['{order_number}'] = $order->get_order_number();
        $this->placeholders['{order_date}']   = wc_format_datetime($order->get_date_created());

        if ($this->is_enabled() && $this->get_recipient()) {
            $this->send($this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments());
        }

        $this->restore_locale();
    }

    public function get_content_html() {
        $order       = $this->object;
        $partner     = $order->get_meta('_pmcm_academic_partner');
        $partner_num = $order->get_meta('_pmcm_partner_number');
        $partner_labels = [
            'asit'    => 'ASiT',
            'bomss'   => 'BOMSS',
            'rouleaux' => 'Rouleaux Club',
        ];
        $partner_label = isset($partner_labels[$partner]) ? $partner_labels[$partner] : strtoupper($partner);
        $admin_url     = admin_url('post.php?post=' . $order->get_id() . '&action=edit');

        ob_start();
        echo $this->get_email_header($this->get_heading());
        ?>
        <p><?php printf(esc_html__('Order #%s has been placed with an %s membership discount and is awaiting membership verification.', 'prepmedico-course-management'), esc_html($order->get_order_number()), esc_html($partner_label)); ?></p>

        <table cellspacing="0" cellpadding="6" style="width:100%;border:1px solid #eee;margin-bottom:20px;">
            <tr>
                <th style="text-align:left;padding:8px;border-bottom:1px solid #eee;background:#f8f8f8;"><?php esc_html_e('Field', 'prepmedico-course-management'); ?></th>
                <th style="text-align:left;padding:8px;border-bottom:1px solid #eee;background:#f8f8f8;"><?php esc_html_e('Value', 'prepmedico-course-management'); ?></th>
            </tr>
            <tr>
                <td style="padding:8px;border-bottom:1px solid #eee;"><?php esc_html_e('Order Number', 'prepmedico-course-management'); ?></td>
                <td style="padding:8px;border-bottom:1px solid #eee;"><?php echo esc_html($order->get_order_number()); ?></td>
            </tr>
            <tr>
                <td style="padding:8px;border-bottom:1px solid #eee;"><?php esc_html_e('Customer', 'prepmedico-course-management'); ?></td>
                <td style="padding:8px;border-bottom:1px solid #eee;"><?php echo esc_html($order->get_formatted_billing_full_name() . ' &lt;' . $order->get_billing_email() . '&gt;'); ?></td>
            </tr>
            <tr>
                <td style="padding:8px;border-bottom:1px solid #eee;"><?php esc_html_e('Academic Partner', 'prepmedico-course-management'); ?></td>
                <td style="padding:8px;border-bottom:1px solid #eee;font-weight:bold;"><?php echo esc_html($partner_label); ?></td>
            </tr>
            <tr>
                <td style="padding:8px;border-bottom:1px solid #eee;"><?php esc_html_e('Membership Number', 'prepmedico-course-management'); ?></td>
                <td style="padding:8px;border-bottom:1px solid #eee;font-weight:bold;color:#8d2063;"><?php echo esc_html($partner_num); ?></td>
            </tr>
            <tr>
                <td style="padding:8px;border-bottom:1px solid #eee;"><?php esc_html_e('Order Total', 'prepmedico-course-management'); ?></td>
                <td style="padding:8px;border-bottom:1px solid #eee;"><?php echo wp_kses_post($order->get_formatted_order_total()); ?></td>
            </tr>
        </table>

        <p><?php esc_html_e('Please verify this membership number against your partner records and then change the order status to Processing once confirmed.', 'prepmedico-course-management'); ?></p>
        <p><a href="<?php echo esc_url($admin_url); ?>" style="background:#8d2063;color:#fff;padding:10px 20px;text-decoration:none;border-radius:4px;display:inline-block;"><?php esc_html_e('View Order in Admin', 'prepmedico-course-management'); ?></a></p>
        <?php
        echo $this->get_email_footer();
        return ob_get_clean();
    }

    public function get_content_plain() {
        $order       = $this->object;
        $partner     = $order->get_meta('_pmcm_academic_partner');
        $partner_num = $order->get_meta('_pmcm_partner_number');
        $partner_labels = ['asit' => 'ASiT', 'bomss' => 'BOMSS', 'rouleaux' => 'Rouleaux Club'];
        $partner_label  = isset($partner_labels[$partner]) ? $partner_labels[$partner] : strtoupper($partner);
        $admin_url      = admin_url('post.php?post=' . $order->get_id() . '&action=edit');

        return sprintf(
            "Membership Verification Required\n\nOrder #%s requires %s membership verification.\n\nMembership Number: %s\nCustomer: %s <%s>\nOrder Total: %s\n\nPlease verify and change the order status to Processing once confirmed.\n\nView Order: %s",
            $order->get_order_number(),
            $partner_label,
            $partner_num,
            $order->get_formatted_billing_full_name(),
            $order->get_billing_email(),
            $order->get_formatted_order_total(),
            $admin_url
        );
    }

    private function get_email_header($heading) {
        ob_start();
        do_action('woocommerce_email_header', $heading, $this);
        return ob_get_clean();
    }

    private function get_email_footer() {
        ob_start();
        do_action('woocommerce_email_footer', $this);
        return ob_get_clean();
    }
}
