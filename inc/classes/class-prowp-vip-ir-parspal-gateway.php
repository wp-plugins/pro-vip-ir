<?php

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Pro_VIP_IR_Parspal_Gateway extends Pro_VIP_Payment_Gateway {

	protected static $MerchantID, $MerchantPassword;

	public
		$id = 'parspal',
		$frontendLabel = 'پارس‌پال',
		$adminLabel = 'پارس‌پال';

	public function __construct() {
		parent::__construct();
		$this::$MerchantID       = ! empty( $this->settings[ 'MerchantID' ] ) ? $this->settings[ 'MerchantID' ] : '';
		$this::$MerchantPassword = ! empty( $this->settings[ 'MerchantPassword' ] ) ? $this->settings[ 'MerchantPassword' ] : '';
	}

	public function adminSettings( PV_Framework_Form_Builder $form ) {

		$form->textfield( 'MerchantID' )->label( 'کلید API' );
		$form->textfield( 'MerchantPassword' )->label( 'کلید API' );

	}

	public function beforePayment( Pro_VIP_Payment $payment ) {


		$price = $payment->price;
		if ( pvGetOption( 'currency' ) === 'IRR' ) {
			$price /= 10;
		}

		$redirect = self::requestPayment(
			$price,
			$payment->paymentId,
			$this->getReturnUrl(),
			$this::$MerchantID,
			$this::$MerchantPassword
		);

		if ( ! $redirect ) {

			pvAddNotice( 'خطایی هنگام درخواست تراکنش از پارس پال رخ داد. لطفا دوباره امتحان کنید.' );

			return false;

		}

		$payment->key  = $payment->paymentId;
		$payment->user = get_current_user_id();

		$payment->save();


		$this->redirect(
			$redirect,
			array()
		);

	}

	public function afterPayment() {
		if ( empty( $_POST[ 'refnumber' ] ) || empty( $_POST[ 'resnumber' ] ) || ! is_numeric( $_POST[ 'resnumber' ] ) ) {
			pvAddNotice( 'خطا در تراکنش' );
			$this->paymentFailed();

			return false;
		}

		try {
			$payment = new Pro_VIP_Payment( $_POST[ 'resnumber' ] );
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

		$verify = $this::verifyPayment( $payment->price, $_POST[ 'refnumber' ], $this::$MerchantID, $this::$MerchantPassword );

		if ( $verify !== true ) {
			pvAddNotice(
				sprintf(
					'تراکنش شما از طریق پارس پال تایید نشد.'
					. '<br/>'
					. 'لطفا با مدیریت سایت تماس بگیرید.'
					. '<br/>'
					. 'کد پیگیری: %s',
					$_POST[ 'resnumber' ]
				)
			);
			$this->paymentFailed();

			return false;
		}


		$payment->status = 'publish';
		$payment->save();
		pvAddNotice( 'پرداخت شما موفقیت آمیز بود.', 'success' );

		$this->paymentComplete( $payment );
	}

	public static function verifyPayment( $price, $refNumber, $merchantId, $merchantPassword ) {
		$client = new SoapClient( 'http://merchant.parspal.com/WebService.asmx?wsdl' );


		$res = $client->VerifyPayment( array(
			"MerchantID" => $merchantId,
			"Password"   => $merchantPassword,
			"Price"      => $price,
			"RefNum"     => $refNumber
		) );

		return $res->verifyPaymentResult->ResultStatus == 'success';
	}

	public static function requestPayment( $price, $resNumber, $returnUrl, $merchantId, $merchantPassword ) {


		$desc = 'خرید VIP';

		$name = '';
		if ( ! empty( $_POST[ 'pv-first-name' ] ) ) {
			$name .= $_POST[ 'pv-first-name' ];
		}
		if ( ! empty( $_POST[ 'pv-last-name' ] ) ) {
			$name .= ' ' . $_POST[ 'pv-last-name' ];
		}

		$mobile = '';
		if ( ! empty( $_POST[ 'pv-phone-number' ] ) ) {
			$mobile = $_POST[ 'pv-phone-number' ];
		}


		$email = '';
		if ( ! empty( $_POST[ 'pv-email-address' ] ) ) {
			$email = $_POST[ 'pv-email-address' ];
		}

		$client = new SoapClient( 'http://merchant.parspal.com/WebService.asmx?wsdl' );

		$res = $client->RequestPayment( array(
			"MerchantID"  => $merchantId,
			"Password"    => $merchantPassword,
			"Price"       => $price,
			"ReturnPath"  => $returnUrl,
			"ResNumber"   => $resNumber,
			"Description" => $desc,
			"Paymenter"   => $name,
			"Email"       => $email,
			"Mobile"      => $mobile
		) );

		$Status = $res->RequestPaymentResult->ResultStatus;

		return $Status == 'Succeed' ? $res->RequestPaymentResult->PaymentPath : false;

	}
}

