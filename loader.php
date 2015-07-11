<?php
/*
Plugin Name: فارسی ساز پرو وی آی پی
Plugin URI: http://pro-wp.ir/wp-vip
Description: افزونه ای که درگاه های پی لاین و زرین پال و هم چنین ریال و تومان را به افزونه پرو وی آی پی اضافه می کند.
Author: Pro-WP Team
Version: 0.1
Author URI: http://pro-wp.ir
*/

if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'PRO_VIP_IR_PLUGIN_FILE', __FILE__ );
define( 'PRO_VIP_IR_PATH', trailingslashit( plugin_dir_path( PRO_VIP_IR_PLUGIN_FILE ) ) );
define( 'PRO_VIP_IR_URL', trailingslashit( plugin_dir_url( PRO_VIP_IR_PLUGIN_FILE ) ) );


function wpVipIrSetup() {
	if ( ! class_exists( 'Pro_VIP_IR' ) ) {
		require dirname( __FILE__ ) . '/class-pro-vip-ir.php';
	}

	return Pro_VIP_IR::getInstance();
}

add_action( 'pro_vip_init', 'wpVipIrSetup' );