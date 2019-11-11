<?php
/*
Plugin Name: Woocommerce Gateway Packpay
Version: 1.0
Description: packpay gateway
Plugin URI: https://packpay.ir
Author: Navid Safavi
Author URI: https://navidsafavi.com

*/

include_once("PackpayWoocommerce.php");
add_action('plugins_loaded', array('PackpayWoocommerce', 'init'));
