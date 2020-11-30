<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Razorpay extends App_Controller
{
    /**
     * Shows the payment form
     *
     * @param  string $id   invoice id
     * @param  string $hash invoice hash
     *
     * @return mixed
     */
    public function pay($id, $hash)
    {
        check_invoice_restrictions($id, $hash);

        $this->load->model('invoices_model');
        $invoice  = $this->invoices_model->get($id);
        $language = load_client_language($invoice->clientid);

        $data['invoice'] = $invoice;

        $contact = is_client_logged_in() ? $this->clients_model->get_contact(get_contact_user_id()) : null;

        $data['order_id']    = $this->input->get('order_id');
        $data['total']       = $this->input->get('total');
        $data['name']        = $contact ? $contact->firstname . ' ' . $contact->lastname : '';
        $data['email']       = $contact ? $contact->email : '';
        $data['phonenumber'] = $contact ? $contact->phonenumber : '';
        $data['key_id']      = $this->razor_pay_gateway->getSetting('key_id');
        $data['description'] = str_replace(
            '{invoice_number}',
            format_invoice_number($invoice->id),
            $this->razor_pay_gateway->getSetting('description_dashboard')
        );

        $this->app_css->add('razorpay-css', module_dir_url('razorpay', 'assets/style.css'), PAYMENT_GATEWAYS_ASSETS_GROUP);

        $this->load->view('pay', $data);
    }

    /**
     * Endpoint for after the Razorpay checkout.js form is submitted
     *
     * @param  string $id   invoice id
     * @param  string $hash invoice hash
     *
     * @return mixed
     */
    public function success($id, $hash)
    {
        check_invoice_restrictions($id, $hash);

        $this->load->model('invoices_model');
        $invoice  = $this->invoices_model->get($id);
        $language = load_client_language($invoice->clientid);

        $payment_id = $this->input->post('razorpay_payment_id');
        $order_id   = $this->input->post('razorpay_order_id');
        $signature  = $this->input->post('razorpay_signature');

        $result = $this->razor_pay_gateway->verifyPayment($signature, $payment_id, $order_id);

        if ($result['success'] === false) {
            set_alert('warning', $result['error']);
        } else {
            // After verification, record the payment in database
            $result = $this->razor_pay_gateway->recordPayment($order_id, $payment_id, $invoice);

            if ($result['success'] === false) {
                set_alert('warning', $result['error']);
            } else {
                set_alert('success', $result['message']);
            }
        }

        redirect(site_url('invoice/' . $id . '/' . $hash));
    }
}
