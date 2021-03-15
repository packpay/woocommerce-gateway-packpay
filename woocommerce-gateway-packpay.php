<?php
/*
Plugin Name: Woocommerce Gateway Packpay
Version: 1.2
Description: packpay gateway
Plugin URI: https://packpay.ir
Author: Navid Safavi & Parham Damavandi
Author URI: https://packpay.ir
*/

include_once("PackpayWoocommerce.php");
add_action('plugins_loaded', array('PackpayWoocommerce', 'init'));
