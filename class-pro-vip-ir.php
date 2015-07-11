<?php

if ( ! defined( 'WPINC' ) ) {
	die;
}

final class Pro_VIP_IR {

	protected
		$_classesList;


	public static function getInstance() {
		static $instance;
		if ( empty( $instance ) ) {
			$instance = new self;
		}

		return $instance;
	}

	protected function __construct() {
		$this->_classesList = require dirname( __FILE__ ) . '/inc/classes-list.php';
		spl_autoload_register( array( $this, 'splCallback' ) );

		add_filter( 'pro_vip_currencies_list', array( $this, 'addCurrencies' ) );
		add_filter( 'pro_vip_config', array( $this, 'filterNewsFeed' ), 10, 2 );
		Pro_VIP_Payment_Gateway::registerGateway( 'Pro_VIP_IR_Payline_Gateway' );
		Pro_VIP_Payment_Gateway::registerGateway( 'Pro_VIP_IR_Zarinpal_Gateway' );
		Pro_VIP_Payment_Gateway::registerGateway( 'Pro_VIP_IR_Parspal_Gateway' );
	}

	public function addCurrencies( $list ) {
		$list[ 'IRT' ] = array(
			'name'   => 'تومان ایران',
			'symbol' => 'تومان'
		);
		$list[ 'IRR' ] = array(
			'name'   => 'ریال ایران',
			'symbol' => 'ریال'
		);

		return $list;
	}


	public function splCallback( $class ) {
		if ( array_key_exists( $class, $this->_classesList ) ) {
			require dirname( __FILE__ ) . '/inc/' . $this->_classesList[ $class ];
		}
	}


	/**
	 * @param       $filename
	 * @param array $vars
	 *
	 * @param bool  $capture_buffer
	 *
	 * @return $this|mixed
	 */
	public static function loadView( $filename, $vars = array(), $capture_buffer = false ) {

		$folder = PRO_VIP_PATH . '/inc/views/';

		$view_name = preg_replace( '/\.php$/', '', $filename ) . '.php';

		if ( file_exists( $folder . $view_name ) ) {
			extract( $vars );
			global $post, $wp_query, $wp;

			if ( $capture_buffer ) {
				ob_start();
				include $folder . $view_name;

				return ob_get_clean();
			}

			return include $folder . $view_name;
		}

		return false;

	}

	public function filterNewsFeed( $val, $key ) {

		if ( $key == 'latestNewsFeed' ) {
			return 'http://pro-vip.ir/fa/category/pro-vip/feed/';
		}

		return $val;

	}

}
