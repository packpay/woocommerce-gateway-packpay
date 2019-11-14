<?php

class PackpayWoocommerce
{

    public static function init()
    {
        $class = __CLASS__;
        new $class;
    }

    public function __construct()
    {
        add_filter('woocommerce_payment_gateways', [$this, 'woocommerce_gateway']);

        add_filter('woocommerce_currencies', [$this, 'add_currency']);

        add_filter('woocommerce_currency_symbol', [$this, 'currency_symbol'], 10, 2);

        $this->init_gateway_class();
    }

    public function init_gateway_class()
    {
        if (class_exists('WC_Payment_Gateway')) {
            include plugin_dir_path(__FILE__) . 'PackpayGateway.php';
        }
    }

    public function woocommerce_gateway($methods)
    {
        $methods[] = 'PackpayGateway';

        return $methods;
    }

    public function add_currency($currencies)
    {
        $currencies['IRR'] = 'ریال';
        $currencies['IRT'] = 'تومان';

        return $currencies;
    }

    public function currency_symbol($currency_symbol, $currency)
    {
        switch ($currency) {
            case 'IRR':
                $currency_symbol = 'ریال';
                break;
            case 'IRT':
                $currency_symbol = 'تومان';
                break;
        }

        return $currency_symbol;
    }
}
