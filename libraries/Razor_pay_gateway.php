<?php

defined('BASEPATH') or exit('No direct script access allowed');

use Razorpay\Api\Api;

class Razor_pay_gateway extends App_gateway
{
    public function __construct()
    {
        parent::__construct();

        /**
         * The razorpay id gateway unique ID
         */
        $this->setId('razorpay');

        /**
         * The razorpay default name/label
         */
        $this->setName('Razorpay');

        /**
         * Api configuration
         */
        $this->setSettings([
            [
                'name'  => 'key_id',
                'label' => 'Key Id',
                'type'  => 'input',
            ],
            [
                'name'      => 'key_secret',
                'label'     => 'Key Secret',
                'encrypted' => true,
                'type'      => 'input',
            ],
            [
                'name'          => 'description_dashboard',
                'label'         => 'settings_paymentmethod_description',
                'type'          => 'textarea',
                'default_value' => 'Payment for Invoice {invoice_number}',
            ],
            [
                'name'          => 'currencies',
                'label'         => 'settings_paymentmethod_currencies',
                'default_value' => 'INR',
            ],
        ]);
    }

    /**
     * Process the payment and redirects to the payment form
     *
     * @param  array $data
     *
     * @return mixed
     */
    public function process_payment($data)
    {
        $api = $this->createApi();

        $invoice = $data['invoice'];

        try {
            $order = $this->createOrGetRazorPayOrder($invoice, $data['amount'] * 100);
        } catch (Exception $e) {
            set_alert('warning', $e->getMessage());
            redirect(site_url('invoice/' . $invoice->id . '/' . $invoice->hash));
        }

        $this->updateInvoiceTokenData(
            json_encode(['amount' => $order->amount, 'razorpay_order_id' => $order->id]),
            $data['invoiceid']
        );

        $redirectGatewayURI = 'razorpay/pay/' . $data['invoiceid'] . '/' . $invoice->hash;

        $redirectPath = $redirectGatewayURI . '?total='
        . $data['amount']
        . '&order_id=' . $order->id;

        redirect(site_url($redirectPath));
    }

    /**
     * Verifies the payment via the signature provided by Razorpay
     *
     * @param  string $signature
     * @param  string $payment_id
     * @param  string $order_id
     *
     * @return array
     */
    public function verifyPayment($signature, $payment_id, $order_id)
    {
        $api        = $this->createApi();
        $attributes = ['razorpay_signature' => $signature, 'razorpay_payment_id' => $payment_id, 'razorpay_order_id' => $order_id];

        try {
            $api->utility->verifyPaymentSignature($attributes);

            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Record the payment in database
     *
     * @param  string $razorpay_payment_id
     * @param  object $invoice
     *
     * @return array
     */
    public function recordPayment($razorpay_order_id, $razorpay_payment_id, $invoice)
    {
        $api       = $this->createApi();
        $tokenData = json_decode($invoice->token);

        if ($tokenData->razorpay_order_id != $razorpay_order_id) {
            return ['success' => false, 'error' => 'Returned order ID does not matched stored order ID.'];
        }

        try {
            $payment = $api->payment->fetch($razorpay_payment_id);

            if ($payment->status == 'captured') {

                // The the invoice order data to null as the payment is captured
                $this->updateInvoiceTokenData(null, $invoice->id);

                $success = $this->addPayment([
                      'amount'        => $payment->amount / 100,
                      'invoiceid'     => $invoice->id,
                      'transactionid' => $payment->id,
                      'paymentmethod' => $payment->method,
                ]);

                $message = _l($success ? 'online_payment_recorded_success' : 'online_payment_recorded_success_fail_database');

                return [
                    'success' => $success,
                    'message' => $message,
                ];
            } elseif ($payment->status === 'authorized') {
                return ['success' => false, 'error' => 'The payment is authorized but not captured, consult with administrator to capture the payment.'];
            }

            return ['success' => false, 'error' => $payment->error_description];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Update the invoice token data so we can use it later for verification
     *
     * @param  string|json $data
     * @param  string $invoice_id invoice id
     *
     * @return null
     */
    public function updateInvoiceTokenData($data, $invoice_id)
    {
        $this->ci->db->where('id', $invoice_id);
        $this->ci->db->update(db_prefix() . 'invoices', [
            'token' => $data,
        ]);
    }

    /**
     * Creates new Razorpay API instance
     *
     * @return Api
     */
    protected function createApi()
    {
        return new Api($this->getSetting('key_id'), $this->decryptSetting('key_secret'));
    }

    /**
     * Creates razor pay order or or get order if the invoice already has razorpay order id stored
     * This function helps createing multiple orders for one invoice
     *
     * Will use the previous created order for the invoice in case found
     *
     * @param  Object $invoice
     * @param  mixed $amount   Amount in lowest values point
     *
     * @return mixed
     */
    public function createOrGetRazorPayOrder($invoice, $amount)
    {
        $api = $this->createApi();

        $create = true;

        if (!empty($invoice->token)) {

            // In case of trying other gateways
            $token = @json_decode($invoice->token);

            if (isset($token->razorpay_order_id)) {
                $order = $api->order->fetch($token->razorpay_order_id);

                $create = false;

                if ($order->amount != $amount) {
                    $create = true;
                } else {
                    return $order;
                }
            }
        }

        if ($create) {
            return $api->order->create([
                      'receipt'         => 'order_rcptid_' . $invoice->id,
                      'amount'          => $amount, // amount in the smallest currency unit
                      'currency'        => $invoice->currency_name,
                      'payment_capture' => 1,
                      'notes'           => [
                        'invoice'    => format_invoice_number($invoice->id),
                        'invoice_id' => $invoice->id,
                       ],
                ]);
        }
    }
}
