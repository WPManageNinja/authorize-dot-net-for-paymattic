<?php

namespace AuthorizeDotNetForPaymattic\API;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

use WPPayForm\Framework\Support\Arr;
use WPPayForm\App\Models\Submission;
use AuthorizeDotNetForPaymattic\Settings\AuthorizeSettings;
use WPPayForm\App\Models\Transaction;

class IPN
{
    public function init()
    {
        $this->verifyIPN();
    }

    public function verifyIPN()
    {
        if (!isset($_REQUEST['wpf_authorize_listener'])) {
            return;
        }

        // Check the request method is POST
        if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] != 'POST') {
            return;
        }

        // Set initial post data to empty string
        $post_data = '';

        // Fallback just in case post_max_size is lower than needed
        if (ini_get('allow_url_fopen')) {
            $post_data = file_get_contents('php://input');
        } else {
            // If allow_url_fopen is not enabled, then make sure that post_max_size is large enough
            ini_set('post_max_size', '12M');
        }

        $data =  json_decode($post_data);
        // will do something iwth data
        exit(200);
    }

    protected function handleIpn($data)
    {
        //handle specific events in the future
    }

    protected function handleInvoicePaid($data)
    {
        // $invoiceId = $data->id;
        // $externalId = $data->external_id;

        // //get transaction from database
        // $transaction = Transaction::where('charge_id', $invoiceId)
        //     ->where('payment_method', 'xendit')
        //     ->first();

        // if (!$transaction || $transaction->payment_method != 'xendit') {
        //     return;
        // }

        // $submissionModel = new Submission();
        // $submission = $submissionModel->getSubmission($transaction->submission_id);

        // if ($submission->submission_hash != $externalId) {
        //     // not our invoice
        //     return;
        // }

        // $invoice = $this->makeApiCall('invoices/' . $invoiceId, [], $transaction->form_id, '');

        // if (!$invoice || is_wp_error($invoice)) {
        //     return;
        // }

        // do_action('wppayform/form_submission_activity_start', $transaction->form_id);

        // $status = 'paid';

        // $updateData = [
        //     'payment_note'     => maybe_serialize($data),
        //     'charge_id'        => sanitize_text_field($invoiceId),
        // ];

        // $xenditProcessor = new AuthorizeProcessor();
        // $xenditProcessor->markAsPaid($status, $updateData, $transaction);
    }


    public function makeApiCall($path, $args, $formId, $method = 'GET')
    {
        $apiKeys = (new AuthorizeSettings())->getApiKeys($formId);
        $headers = [
            'Accept' => 'application/json',
            'Content-type' => 'application/json'
        ];
        $api_url = Arr::get($apiKeys, 'api_url');
        $args = json_encode($args, true);
        // dd($api_url, $args, site_url());

        if ($method == 'POST') {
            $response = wp_remote_post($api_url . $path, [
                'headers' => $headers,
                'body' => $args
            ]);
        } else {
            $response = wp_remote_get($api_url . $path, [
                'headers' => $headers,
                'body' => $args
            ]);
        }

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $body = preg_replace('/^[\x{FEFF}]+/u', '', $body);
        $responseData = json_decode($body, true);
        
        if (empty($responseData['token'])) {
            $message = Arr::get($responseData, 'detail');
            if (!$message) {
                $message = Arr::get($responseData, 'error.message');
            }
            if (!$message) {
                $message = 'Unknown Authorize.net API request error';
            }

            return new \WP_Error(423, $message, $responseData);
        }

        return $responseData;
    }
}
