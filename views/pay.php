<?php
defined('BASEPATH') or exit('No direct script access allowed');
echo payment_gateway_head(_l('payment_for_invoice') . ' ' . format_invoice_number($invoice->id)); ?>
<body class="gateway-stripe">
  <div class="container">
    <div class="col-md-8 col-md-offset-2 mtop30">
      <div class="mbot30 text-center">
        <?php echo payment_gateway_logo(); ?>
      </div>
      <div class="row">
        <div class="panel_s">
          <div class="panel-body">
           <h3 class="no-margin bold">
            <?php echo _l('payment_for_invoice'); ?>
            <a href="<?php echo site_url('invoice/' . $invoice->id . '/' . $invoice->hash); ?>">
              <?php echo format_invoice_number($invoice->id); ?>
            </a>
          </h3>
          <h4><?php echo _l('payment_total', app_format_money($total, $invoice->currency_name)); ?></h4>
          <hr />
          <form action="<?php echo site_url('razorpay/success/' . $invoice->id . '/' . $invoice->hash); ?>" method="POST">
            <script
            src="https://checkout.razorpay.com/v1/checkout.js"
            data-key="<?php echo $key_id; ?>"
            data-amount="<?php echo $total * 100; ?>"
            data-name="<?php echo get_option('companyname'); ?>"
            data-buttontext="<?php echo _l('invoice_html_online_payment_button_text'); ?>"
            data-currency="<?php echo $invoice->currency_name; ?>"
            data-order_id="<?php echo $order_id; ?>"
            data-prefill.name="<?php echo $name; ?>"
            data-prefill.email="<?php echo $email; ?>"
            data-prefill.contact="<?php echo $phonenumber; ?>"
            data-description="<?php echo $description; ?>">
          </script>
          <input type="hidden"
          name="<?php echo $this->security->get_csrf_token_name(); ?>"
          value="<?php echo $this->security->get_csrf_hash(); ?>" />
        </form>
      </div>
    </div>
  </div>
</div>
<?php echo payment_gateway_scripts(); ?>
<?php echo payment_gateway_footer(); ?>
