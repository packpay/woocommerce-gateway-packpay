<?php

class PackpayGateway extends WC_Payment_Gateway
{

    public $id;
    public $method_title;
    public $method_description;
    public $has_fields;
    public $success_massage;
    public $failed_massage;
    public $client_id;
    public $secret_id;
    public $token = '';
    public $base_api = 'https://dashboard.packpay.ir/';

    public function __construct()
    {

        $this->id = 'PackpayGateway';
        $this->method_title = 'درگاه پرداخت مستقیم پکپی';
        $this->method_description = 'تنظیمات ' . $this->method_title;
        $this->icon = plugins_url('/assets/images/logo.jpg', __FILE__);
        $this->has_fields = false;

        $this->init_form_fields();
        $this->init_settings();

        $this->save_options_init();

        $this->title = $this->settings['title'];
        $this->description = $this->settings['description'];
        $this->client_id = $this->settings['client_id'];
        $this->secret_id = $this->settings['secret_id'];
        $this->success_massage = wpautop(wptexturize($this->settings['success_massage']));
        $this->failed_massage = wpautop(wptexturize($this->settings['failed_massage']));

        add_action('woocommerce_receipt_' . $this->id . '', array($this, 'Send_to_Packpay_Gateway'));
        add_action(
            'woocommerce_api_' . strtolower(get_class($this)) . '',
            array($this, 'return_from_gateway')
        );
    }

    public function admin_options()
    {
        parent::admin_options();
    }

    public function save_options_init()
    {
        if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
            add_action(
                'woocommerce_update_options_payment_gateways_' . $this->id,
                array($this, 'process_admin_options')
            );
        } else {
            add_action('woocommerce_update_options_payment_gateways', array($this, 'process_admin_options'));
        }
    }

    public function init_form_fields()
    {
        $this->form_fields = [
            'base_config' => [
                'title' => 'تنظیمات اصلی',
                'type' => 'title',
                'description' => '',
            ],
            'enabled' => [
                'title' => 'فعال بودن درگاه',
                'type' => 'checkbox',
                'label' => 'فعال بودن درگاه',
                'description' => 'دررصوت فعال بودن این درگاه در فهرست درگاه های شما ظاهر خواهد شد.',
                'default' => 'yes',
                'desc_tip' => true,
            ],
            'title' => [
                'title' => 'عنوان درگاه پرداخت',
                'type' => 'text',
                'description' => 'عنوان درگاه که در طی خرید به مشتری نمایش داده میشود',
                'default' => 'درگاه پرداخت مستقیم پکپی',
                'desc_tip' => true,
            ],
            'description' => [
                'title' => 'توضیحات درگاه پرداخت',
                'type' => 'text',
                'desc_tip' => true,
                'description' => 'توضیحاتی که در طی عملیات پرداخت برای درگاه پرداخت نمایش داده خواهد شد',
                'default' => 'پرداخت مستقیم به وسیله کلیه کارت های عضو شتاب',
            ],
            'account_config' => [
                'title' => 'تنظیمات سرویس',
                'type' => 'title',
                'description' => '',
            ],
            'client_id' => [
                'title' => 'Client ID',
                'type' => 'text',
                'default' => '',
            ],
            'secret_id' => [
                'title' => 'Secret ID',
                'type' => 'text',
                'default' => '',
            ],
            'refresh_token' => [
                'title' => 'Refresh Token',
                'type' => 'text',
                'default' => '',
            ],
            'payment_config' => [
                'title' => 'تنظیمات عملیات پرداخت',
                'type' => 'title',
                'description' => '',
            ],
            'success_massage' => [
                'title' => 'پیام پرداخت موفق',
                'type' => 'textarea',
                'description' => 'متن پیامی که میخواهید بعد از پرداخت موفق به کاربر نمایش دهید را وارد نمایید . همچنین می توانید از شورت کد {transaction_id} برای نمایش کد رهگیری (توکن) زرین پال استفاده نمایید.',
                'default' => 'با تشکر از شما . سفارش شما با موفقیت پرداخت شد.',
            ],
            'failed_massage' => [
                'title' => 'پیام پرداخت ناموفق',
                'type' => 'textarea',
                'description' => 'متن پیامی که میخواهید بعد از پرداخت ناموفق به کاربر نمایش دهید را وارد نمایید . همچنین می توانید از شورت کد {fault} برای نمایش دلیل خطای رخ داده استفاده نمایید . این دلیل خطا از سایت زرین پال ارسال میگردد.',
                'default' => 'پرداخت شما ناموفق بوده است . لطفا مجددا تلاش نمایید یا در صورت بروز اشکال با مدیر سایت تماس بگیرید.',
            ],
        ];
    }

    public function refresh_token()
    {
        $this->init_settings();
        $data = [
            'grant_type' => 'refresh_token',
            'refresh_token' => $this->settings['refresh_token'],
        ];
        $method = 'oauth/token?' . http_build_query($data);
        $result = $this->request($method, []);
        $this->token = $result['access_token'];
        $this->update_option('token', $this->token);
    }

    public function purchase($amount, $order_id)
    {
        //$call_back_url = add_query_arg('wc_order', $order_id, WC()->api_request_url($this->id));
        $call_back_url = WC()->api_request_url($this->id);
        $data = [
            'access_token' => $this->token,
            'amount' => $amount,
            'callback_url' => $call_back_url,
            'verify_on_request' => true
        ];
        $method = 'developers/bank/api/v1/purchase?' . http_build_query($data);
        $result = $this->request($method, []);

        return $result;
    }

    public function process_payment($order_id)
    {
        global $woocommerce;
        $order = new WC_Order($order_id);
        $woocommerce->session->order_id_packpay = $order_id;

        $currency = $order->get_currency();
        $total = $this->amount_normalize($order->total, $currency);

        $this->refresh_token();

        $result = $this->purchase($total, $order_id);
        $reference_code = $result['reference_code'];

        return array(
            'result' => $result['status'] === '0' ? 'success' : 'failure',
            'redirect' => $this->base_api . 'bank/purchase/send?RefId=' . $reference_code,
        );
    }

    public function request($method, $params, $type = 'POST')
    {
        try {
            $ch = curl_init($this->base_api . $method);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $type);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_USERPWD, $this->client_id . ":" . $this->secret_id);
            curl_setopt(
                $ch,
                CURLOPT_HTTPHEADER,
                array(
                    'Content-Type: application/json',
                    //'Content-Length: '.strlen($params),
                )
            );
            $result = curl_exec($ch);

            return json_decode($result, true);
        } catch (Exception $ex) {
            return false;
        }
    }

    public function amount_normalize($amount, $currency)
    {
        if (strtolower($currency) === strtolower('IRT') || strtolower($currency) == strtolower(
                'TOMAN'
            ) || strtolower($currency) == strtolower('Iran TOMAN') || strtolower($currency) == strtolower(
                'Iranian TOMAN'
            ) || strtolower($currency) == strtolower('Iran-TOMAN') || strtolower($currency) == strtolower(
                'Iranian-TOMAN'
            ) || strtolower($currency) == strtolower('Iran_TOMAN') || strtolower($currency) == strtolower(
                'Iranian_TOMAN'
            ) || strtolower($currency) == strtolower('تومان') || strtolower($currency) == strtolower(
                'تومان ایران'
            )
        ) {
            $amount = $amount * 10;
        } elseif (strtolower($currency) == strtolower('IRR')) {
            $amount = $amount * 1;
        }

        return $amount;
    }

    public function Send_to_Packpay_Gateway($order_id)
    {

        global $woocommerce;

        $woocommerce->session->order_id_packpay = $order_id;
        $order = new WC_Order($order_id);
        $currency = $order->get_currency();
        $currency = apply_filters('WC_Pkp_Currency', $currency, $order_id);


        $form = '<form action="" method="POST" class="packpay-checkout-form" id="packpay-checkout-form">
						<input type="submit" name="packpay_submit" class="button alt" id="packpay-payment-button" value="' . __(
                'پرداخت',
                'woocommerce'
            ) . '"/>
						<a class="button cancel" href="' . $woocommerce->cart->get_checkout_url() . '">' . __(
                'بازگشت',
                'woocommerce'
            ) . '</a>
					 </form><br/>';
        $form = apply_filters('WC_Pkp_Form', $form, $order_id, $woocommerce);

        do_action('WC_Pkp_Gateway_Before_Form', $order_id, $woocommerce);
        echo $form;
        do_action('WC_Pkp_Gateway_After_Form', $order_id, $woocommerce);
        die;

        $Amount = intval($order->order_total);
        $Amount = apply_filters(
            'woocommerce_order_amount_total_IRANIAN_gateways_before_check_currency',
            $Amount,
            $currency
        );


        $Amount = apply_filters(
            'woocommerce_order_amount_total_IRANIAN_gateways_after_check_currency',
            $Amount,
            $currency
        );
        $Amount = apply_filters('woocommerce_order_amount_total_IRANIAN_gateways_irt', $Amount, $currency);
        $Amount = apply_filters('woocommerce_order_amount_total_Packpay_gateway', $Amount, $currency);

        $MerchantCode = $this->merchantcode;
        $CallbackUrl = add_query_arg('wc_order', $order_id, WC()->api_request_url('WC_Pkp'));

        $products = array();
        $order_items = $order->get_items();
        foreach ((array)$order_items as $product) {
            $products[] = $product['name'] . ' (' . $product['qty'] . ') ';
        }
        $products = implode(' - ', $products);

        $Description = 'خرید به شماره سفارش : ' . $order->get_order_number() . ' | خریدار : ' . $order->billing_first_name . ' ' . $order->billing_last_name . ' | محصولات : ' . $products;
        $Mobile = get_post_meta($order_id, '_billing_phone', true) ? get_post_meta(
            $order_id,
            '_billing_phone',
            true
        ) : '-';
        $Email = $order->billing_email;
        $Paymenter = $order->billing_first_name . ' ' . $order->billing_last_name;
        $ResNumber = intval($order->get_order_number());

        //Hooks for iranian developer
        $Description = apply_filters('WC_Pkp_Description', $Description, $order_id);
        $Mobile = apply_filters('WC_Pkp_Mobile', $Mobile, $order_id);
        $Email = apply_filters('WC_Pkp_Email', $Email, $order_id);
        $Paymenter = apply_filters('WC_Pkp_Paymenter', $Paymenter, $order_id);
        $ResNumber = apply_filters('WC_Pkp_ResNumber', $ResNumber, $order_id);
        do_action('WC_Pkp_Gateway_Payment', $order_id, $Description, $Mobile);
        $Email = !filter_var($Email, FILTER_VALIDATE_EMAIL) === false ? $Email : '';
        $Mobile = preg_match('/^09[0-9]{9}/i', $Mobile) ? $Mobile : '';

        $acczarin = ($this->settings['zarinwebgate'] == 'no') ? 'https://www.packpay.com/pg/StartPay/%s/' : 'https://www.packpay.com/pg/StartPay/%s/ZarinGate';

        $data = array(
            'MerchantID' => $this->merchantcode,
            'Amount' => $Amount,
            'CallbackURL' => $CallbackUrl,
            'Description' => $Description,
        );

        $result = $this->request('PaymentRequest', json_encode($data));
        if ($result === false) {
            echo "cURL Error #:" . $err;
        } else {
            if ($result["Status"] == 100) {
                wp_redirect(sprintf($acczarin, $result['Authority']));
                exit;
            } else {
                $Message = ' تراکنش ناموفق بود- کد خطا : ' . $result["Status"];
                $Fault = '';
            }
        }

        if (!empty($Message) && $Message) {

            $Note = sprintf(__('خطا در هنگام ارسال به بانک : %s', 'woocommerce'), $Message);
            $Note = apply_filters('WC_Pkp_Send_to_Gateway_Failed_Note', $Note, $order_id, $Fault);
            $order->add_order_note($Note);


            $Notice = sprintf(
                __('در هنگام اتصال به بانک خطای زیر رخ داده است : <br/>%s', 'woocommerce'),
                $Message
            );
            $Notice = apply_filters('WC_Pkp_Send_to_Gateway_Failed_Notice', $Notice, $order_id, $Fault);
            if ($Notice) {
                wc_add_notice($Notice, 'error');
            }

            do_action('WC_Pkp_Send_to_Gateway_Failed', $order_id, $Fault);
        }
    }

    public function return_from_gateway()
    {
        global $woocommerce;


        if (isset($_GET['wc_order'])) {
            $order_id = $_GET['wc_order'];
        } else {
            $order_id = $woocommerce->session->order_id_packpay;
            unset($woocommerce->session->order_id_packpay);
        }
        $reference_code = $_GET['reference_code'];

        $order_id = intval($order_id);
        //$order_id = 1439;

        global $woocommerce;
        $fault_message = '';

        if ($order_id) {
            $order = new WC_Order($order_id);
            //$currency = $order->get_currency();
            $amount = intval($order->get_total());

            if ($order->get_status() != 'completed') {


                $this->refresh_token();
                $data = [
                    'access_token' => $this->token,
                    'reference_code' => $_GET['reference_code'],
                ];
                $method = 'developers/bank/api/v1/purchase/verify?' . http_build_query($data);
                $result = $this->request($method, [], 'POST');
                $access = $result->status == 0 && $result->message == 'successful' ? false : true;

                if ($access) {
                    update_post_meta($order_id, '_transaction_id', $reference_code);
                    $order->payment_complete($reference_code);
                    $woocommerce->cart->empty_cart();

                    $note = sprintf(
                        'پرداخت با موفقیت انجام شد. کد رهگیری : %s',
                        $reference_code
                    );
                    $order->add_order_note($note, 1);

                    $notice = str_replace("{transaction_id}", $reference_code, $this->success_massage);
                    wc_add_notice($notice, 'success');

                    wp_redirect(add_query_arg('wc_status', 'success', $this->get_return_url($order)));
                    exit;
                } else {
                    $note = sprintf(
                        'خطا در هنگام بازگشت از بانک. کد رهگیری : %s',
                        $reference_code
                    );
                    $fault_message = 'انصراف از پرداخت';
                    $order->add_order_note($note, 1);
                    $notice = str_replace("{transaction_id}", $reference_code, $this->failed_massage);
                    $notice = str_replace("{fault}", $fault_message, $notice);

                    wc_add_notice($notice, 'error');
                    wp_redirect($woocommerce->cart->get_checkout_url());
                    exit;
                }
            } else {
                //Order is Ok redirect and show transaction_id
                $reference_code = get_post_meta($order_id, '_transaction_id', true);
                $notice = $this->success_massage;
                $notice = str_replace("{transaction_id}", $reference_code, $notice);
                if ($notice) {
                    wc_add_notice($notice, 'success');
                }
                wp_redirect(add_query_arg('wc_status', 'success', $this->get_return_url($order)));
                exit;
            }
        } else {
            //Order was not exist
            $Fault = 'شماره سفارش وجود ندارد .';
            $notice = $this->failed_massage;
            $notice = str_replace("{fault}", $Fault, $notice);
            if ($notice) {
                wc_add_notice($notice, 'error');
            }
            wp_redirect($woocommerce->cart->get_checkout_url());
            exit;
        }


        if ($order->status != 'completed') {

            $MerchantCode = $this->merchantcode;

            if ($_GET['Status'] == "OK") {

                $MerchantID = $this->merchantcode;
                $Amount = intval($order->order_total);


                $Authority = $_GET['Authority'];

                $data = array(
                    'MerchantID' => $MerchantID,
                    'Authority' => $Authority,
                    'Amount' => $Amount,
                );
                $result = $this->request('PaymentVerification', json_encode($data));

                if ($result['Status'] == 100) {
                    $Status = 'completed';
                    $Transaction_ID = $result['RefID'];
                    $Fault = '';
                    $Message = '';
                } elseif ($result['Status'] == 101) {
                    $Message = 'این تراکنش قلا تایید شده است';
                    wp_redirect(add_query_arg('wc_status', 'success', $this->get_return_url($order)));
                    exit;
                } else {
                    $Status = 'failed';
                    $Fault = $result['Status'];
                    $Message = 'تراکنش ناموفق بود';
                }
            } else {
                $Status = 'failed';
                $Fault = '';
                $Message = 'تراکنش انجام نشد .';
            }

            if ($Status == 'completed' && isset($Transaction_ID) && $Transaction_ID != 0) {

            } else {


            }
        } else {


        }

    }

}
