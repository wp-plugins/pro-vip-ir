<?php

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Pro_VIP_IR_Payline_Gateway extends Pro_VIP_Payment_Gateway {

	protected static $API_KEY = 'adxcv-zzadq-polkjsad-opp13opoz-1sdf455aadzmck1244567';

	public
		$id = 'payline',
		$frontendLabel = 'پی لاین',
		$testMode = false,
		$adminLabel = 'پی لاین';

	public function __construct() {
		parent::__construct();
		if ( ! empty( $this->settings[ 'api_key' ] ) ) {
			$this::$API_KEY = $this->settings[ 'api_key' ];
		}
		if ( isset( $this->settings[ 'test' ] ) && $this->settings[ 'test' ] == 'yes' ) {
			$this->testMode = true;
		}
	}

	public function adminSettings( PV_Framework_Form_Builder $form ) {

		$form->textfield( 'api_key' )->label( 'کلید API' );
		$form->dropdown( 'test', array( 'no' => 'خیر', 'yes' => 'بله' ) )->label( 'حالت تست' )->desc( 'در حالت تست نیازی به وارد کردن کلید درگاه نیست.' );

	}

	public function beforePayment( Pro_VIP_Payment $payment ) {


		$price = $payment->price;
		if ( pvGetOption( 'currency' ) === 'IRT' ) {
			$price *= 10;
		}
		$req = $this::requestPayment( $price, $this->getReturnUrl(), $this::$API_KEY, $this->testMode );


		if ( ! $req ) {
			pvAddNotice( 'خطایی هنگام درخواست تراکنش از پی لاین رخ داد. لطفا دوباره امتحان کنید.' );

			return false;
		}

		$payment->key  = $req;
		$payment->user = get_current_user_id();

		$payment->save();

		if ( $this->testMode ) {
			$send_url = 'http://payline.ir/payment-test/gateway-%d';
		} else {
			$send_url = 'http://payline.ir/payment/gateway-%d';

		}

		$send_url = sprintf( $send_url, $req );

		$this->redirect(
			$send_url,
			array()
		);

	}

	public function afterPayment() {
		if ( empty( $_POST[ 'id_get' ] ) || ! is_numeric( $_POST[ 'id_get' ] ) || empty( $_POST[ 'trans_id' ] ) || ! is_numeric( $_POST[ 'trans_id' ] ) ) {
			pvAddNotice( 'خطا در تراکنش' );
			$this->paymentFailed( null );

			return false;
		}

		try {
			$payment = new Pro_VIP_Payment( Pro_VIP_Payment::getPaymentIdFromKey( $_POST[ 'id_get' ] ) );
		} catch ( Exception $e ) {
			pvAddNotice( $e->getMessage() );
			$this->paymentFailed( null );

			return false;
		}

		if ( $payment->status != 'pending' ) {
			pvAddNotice( 'خطا در تراکنش' );
			$this->paymentFailed( $payment );

			return false;
		}

		$verify = $this::verifyPayment( $_POST[ 'trans_id' ], $_POST[ 'id_get' ], $this::$API_KEY, $this->testMode );

		if ( $verify !== 1 ) {
			pvAddNotice(
				sprintf(
					'تراکنش شما از طریق پی لاین تایید نشد.'
					. '<br/>'
					. 'لطفا با مدیریت سایت تماس بگیرید.'
					. '<br/>'
					. 'کد پیگیری: %s',
					$_POST[ 'id_get' ]
				)
			);
			$this->paymentFailed( $payment );

			return false;
		}


		$payment->status = 'publish';
		$payment->save();
		pvAddNotice( 'پرداخت شما موفقیت آمیز بود.', 'success' );

		$this->paymentComplete( $payment );
	}

	public static function verifyPayment( $transId, $idGet, $api, $testMode = false ) {

		if ( $testMode ) {
			$url = 'http://payline.ir/payment-test/gateway-result-second';
		} else {
			$url = 'http://payline.ir/payment/gateway-result-second';
		}

		$req = wp_remote_post(
			$url,
			array(
				'body' => array(
					'api'      => $api,
					'id_get'   => $idGet,
					'trans_id' => $transId
				)
			)
		);

		if ( is_wp_error( $req ) ) {
			return - 9;
		}

		return (int) $req[ 'body' ];
	}

	public static function requestPayment( $price, $returnUrl, $apiKey, $testMode = false ) {
		$url = $testMode ? 'http://payline.ir/payment-test/gateway-send' : 'http://payline.ir/payment/gateway-send';
		$req = wp_remote_post(
			$url,
			array(
				'body' => array(
					'api'      => $apiKey,
					'amount'   => $price,
					'redirect' => urlencode( $returnUrl )
				)
			)
		);
		if ( is_wp_error( $req ) ) {
			return false;
		}

		return (int) $req[ 'body' ] > 0 ? (int) $req[ 'body' ] : false;
	}
}

