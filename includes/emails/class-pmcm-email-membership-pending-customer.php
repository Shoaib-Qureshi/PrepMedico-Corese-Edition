<?php
/**
 * Customer email: membership pending confirmation
 *
 * @package PrepMedico_Course_Management
 */

if (!defined('ABSPATH')) {
    exit;
}

class PMCM_Email_Membership_Pending_Customer extends WC_Email {

    public function __construct() {
        $this->id             = 'pmcm_membership_pending_customer';
        $this->title          = __('Academic Partner — Membership Pending (Customer)', 'prepmedico-course-management');
        $this->description    = __('Sent to the customer when their order is pending academic partner membership verification.', 'prepmedico-course-management');
        $this->heading        = __('Your Order is Awaiting Membership Verification', 'prepmedico-course-management');
        $this->subject        = __('Your order is pending membership verification — Order #{order_number}', 'prepmedico-course-management');
        $this->customer_email = true;
        $this->template_html  = '';
        $this->template_plain = '';

        add_action('woocommerce_order_status_wc-membership-pending', [$this, 'trigger'], 15, 2);
        add_action('woocommerce_order_status_pending_to_wc-membership-pending', [$this, 'trigger'], 15, 2);

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

        $this->object    = $order;
        $this->recipient = $order->get_billing_email();

        $partner = $order->get_meta('_pmcm_academic_partner');
        if (empty($partner)) {
            return;
        }

        // Guard against double-sending (direct trigger + action hook both firing).
        // Admin email sets this flag first (priority 10), customer email checks it (priority 15).
        // Use a separate flag so each sends exactly once.
        if ($order->get_meta('_pmcm_customer_email_sent')) {
            $this->restore_locale();
            return;
        }

        $this->placeholders['{order_number}'] = $order->get_order_number();
        $this->placeholders['{order_date}']   = wc_format_datetime($order->get_date_created());

        if ($this->is_enabled() && $this->get_recipient()) {
            $this->send($this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments());
            $order->update_meta_data('_pmcm_customer_email_sent', 'yes');
            $order->save();
        }

        $this->restore_locale();
    }

    public function get_content_html() {
        $order       = $this->object;
        $partner     = $order->get_meta('_pmcm_academic_partner');
        $partner_num = $order->get_meta('_pmcm_partner_number');
        $partner_labels = ['asit' => 'ASiT', 'bomss' => 'BOMSS', 'rouleaux' => 'Rouleaux Club'];
        $partner_label  = isset($partner_labels[$partner]) ? $partner_labels[$partner] : strtoupper($partner);

        ob_start();
        echo $this->get_email_header($this->get_heading());
        ?>
        <p><?php printf(esc_html__('Thank you for your order, %s.', 'prepmedico-course-management'), esc_html($order->get_billing_first_name())); ?></p>
        <p><?php printf(
            esc_html__('Your order #%s has been received and is currently on hold while we verify your %s membership.', 'prepmedico-course-management'),
            esc_html($order->get_order_number()),
            esc_html($partner_label)
        ); ?></p>

        <table cellspacing="0" cellpadding="6" style="width:100%;border:1px solid #eee;margin-bottom:20px;">
            <tr>
                <td style="padding:8px;border-bottom:1px solid #eee;"><?php esc_html_e('Academic Partner', 'prepmedico-course-management'); ?></td>
                <td style="padding:8px;border-bottom:1px solid #eee;font-weight:bold;"><?php echo esc_html($partner_label); ?></td>
            </tr>
            <tr>
                <td style="padding:8px;border-bottom:1px solid #eee;"><?php esc_html_e('Membership Number Provided', 'prepmedico-course-management'); ?></td>
                <td style="padding:8px;border-bottom:1px solid #eee;"><?php echo esc_html($partner_num); ?></td>
            </tr>
        </table>

        <p style="background:#fff8e1;border-left:4px solid #f9a825;padding:12px;margin:20px 0;">
            <?php esc_html_e('Once your membership has been verified, your order will be confirmed and you will receive a full order confirmation email with your receipt and course access details.', 'prepmedico-course-management'); ?>
        </p>

        <p><?php esc_html_e('If you have any questions please contact us and quote your order number.', 'prepmedico-course-management'); ?></p>
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

        return sprintf(
            "Thank you for your order, %s.\n\nOrder #%s is currently on hold while we verify your %s membership (number: %s).\n\nOnce your membership has been verified, your order will be confirmed and you will receive a full order confirmation email with your receipt and course access details.\n\nIf you have any questions please contact us and quote your order number.",
            $order->get_billing_first_name(),
            $order->get_order_number(),
            $partner_label,
            $partner_num
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
