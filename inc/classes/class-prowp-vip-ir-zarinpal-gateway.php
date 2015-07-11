<?php

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Pro_VIP_IR_Zarinpal_Gateway extends Pro_VIP_Payment_Gateway {

	protected static $Merchant_ID;

	public
		$id = 'zarinpal',
		$frontendLabel = 'زرین پال',
		$adminLabel = 'زرین پال';

	public function __construct() {
		parent::__construct();
		$this::$Merchant_ID = ! empty( $this->settings[ 'merchant_id' ] ) ? $this->settings[ 'merchant_id' ] : '';
	}

	public function adminSettings( PV_Framework_Form_Builder $form ) {

		$form->textfield( 'merchant_id' )->label( 'کلید درگاه' );

	}

	public function beforePayment( Pro_VIP_Payment $payment ) {


		$price = $payment->price;
		if ( pvGetOption( 'currency' ) === 'IRR' ) {
			$price /= 10;
		}
		$req = $this::requestPayment( $price, $this->getReturnUrl(), $this::$Merchant_ID );


		if ( ! $req ) {
			pvAddNotice( 'خطایی در درخواست تراکنش از زرین پال رخ داد. لطفا دوباره امتحان کنید.' );

			return false;
		}

		$payment->key  = $req;
		$payment->user = get_current_user_id();

		$payment->save();


		$send_url = 'https://www.zarinpal.com/pg/StartPay/%s';


		$send_url = sprintf( $send_url, $req );

		$this->redirect(
			$send_url,
			array()
		);

	}

	public function afterPayment() {
		if ( empty( $_GET[ 'Authority' ] ) || ! is_string( $_GET[ 'Authority' ] ) || !isset( $_GET['Status'] ) ) {
			pvAddNotice( 'خطا در تراکنش' );
			$this->paymentFailed();

			return false;
		}

		if( $_GET[ 'Status' ] == 'NOK' ){
			pvAddNotice( 'پرداخت ناموفق.' );
			$this->paymentFailed();

			return false;
		}

		try {
			$payment = new Pro_VIP_Payment( Pro_VIP_Payment::getPaymentIdFromKey( $_GET[ 'Authority' ] ) );
		} catch ( Exception $e ) {
			pvAddNotice( $e->getMessage() );
			$this->paymentFailed();

			return false;
		}

		if ( $payment->status != 'pending' ) {
			pvAddNotice( 'خطا در تراکنش' );
			$this->paymentFailed();

			return false;
		}

		$price = $payment->price;
		if ( pvGetOption( 'currency' ) === 'IRR' ) {
			$price /= 10;
		}
		$verify = $this::verifyPayment( $_GET[ 'Authority' ], $this::$Merchant_ID, $price );

		if ( empty( $verify ) ) {
			pvAddNotice(
				'تراکنش شما از طریق زرین پال تایید نشد.'
				. '<br/>'
				. 'لطفا با مدیریت سایت تماس بگیرید.'
			);
			$this->paymentFailed();

			return false;
		}


		$payment->status = 'publish';
		$payment->save();
		pvAddNotice( 'پرداخت شما موفقیت آمیز بود.', 'success' );

		$this->paymentComplete( $payment );
	}

	public static function verifyPayment( $authority, $merchantId, $amount ) {

		$client = new SoapClient( 'https://de.zarinpal.com/pg/services/WebGate/wsdl', array( 'encoding' => 'UTF-8' ) );

		$result = $client->PaymentVerification(
			array(
				'MerchantID' => $merchantId,
				'Authority'  => $authority,
				'Amount'     => $amount
			)
		);

		if ( $result->Status == 100 ) {
			return $result->RefID;
		}

		return false;
	}

	public static function requestPayment( $price, $returnUrl, $MerchantID ) {
		$Description = sprintf( 'خرید از طریق افزونه %s در سایت %s', 'Pro-VIP', get_option( 'blogname' ) );

		$client = new SoapClient( 'https://de.zarinpal.com/pg/services/WebGate/wsdl', array( 'encoding' => 'UTF-8' ) );

		$result = $client->PaymentRequest(
			array(
				'MerchantID'  => $MerchantID,
				'Amount'      => $price,
				'Description' => $Description,
				'Email'       => ! empty( $_POST[ 'pv-email-address' ] ) ? $_POST[ 'pv-email-address' ] : '',
				'Mobile'      => ! empty( $_POST[ 'pv-phone-number' ] ) ? $_POST[ 'pv-phone-number' ] : '',
				'CallbackURL' => $returnUrl
			)
		);

		if ( $result->Status == 100 ) {
			return $result->Authority;
		} else {
			return false;
		}
	}
}

