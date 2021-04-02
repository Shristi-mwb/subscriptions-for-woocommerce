<?php
/**
 * PayPal Standard IPN Handler
 *
 * Handles IPN requests from PayPal for PayPal Standard Subscription transactions
 *
 * Example IPN payloads https://gist.github.com/thenbrent/3037967
 *
 * @link https://developer.paypal.com/docs/classic/ipn/integration-guide/IPNandPDTVariables/#id08CTB0S055Z
 *
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Mwb_Sfw_PayPal_IPN_Handler extends WC_Gateway_Paypal_IPN_Handler {

	/** @var Array transaction types this class can handle */
	protected $transaction_types = array(
		'subscr_signup',  // Subscription started
		'subscr_payment', // Subscription payment received
		'subscr_cancel',  // Subscription canceled
		'subscr_eot',     // Subscription expired
		'subscr_failed',  // Subscription payment failed
		'subscr_modify',  // Subscription modified

		// The PayPal docs say these are for Express Checkout recurring payments but they are also sent for PayPal Standard subscriptions
		'recurring_payment_skipped',   // Recurring payment skipped; it will be retried up to 3 times, 5 days apart
		'recurring_payment_suspended', // Recurring payment suspended. This transaction type is sent if PayPal tried to collect a recurring payment, but the related recurring payments profile has been suspended.
		'recurring_payment_suspended_due_to_max_failed_payment', // Recurring payment failed and the related recurring payment profile has been suspended
	);

	/**
	 * Constructor from WC_Gateway_Paypal_IPN_Handler
	 */
	public function __construct( $sandbox = false, $receiver_email = '' ) {
		$this->receiver_email = $receiver_email;
		$this->sandbox        = $sandbox;
	}

	/**
	 * There was a valid response
	 *
	 * Based on the IPN Variables documented here: https://developer.paypal.com/docs/classic/ipn/integration-guide/IPNandPDTVariables/#id091EB0901HT
	 *
	 * @param array $transaction_details Post data after wp_unslash
	 * @since 2.0
	 */
	public function valid_response( $transaction_details ) {
		global $wpdb;

		$transaction_details = stripslashes_deep( $transaction_details );

		if ( ! $this->validate_transaction_type( $transaction_details['txn_type'] ) ) {
			return;
		}

		$transaction_details['txn_type'] = strtolower( $transaction_details['txn_type'] );

		$this->process_ipn_request( $transaction_details );

	}

	/**
	 * Process a PayPal Standard Subscription IPN request
	 *
	 * @param array $transaction_details Post data after wp_unslash
	 * @since 2.0
	 */
	protected function process_ipn_request( $transaction_details ) {
		
	}

	/**
	 * Return valid transaction types
	 *
	 * @since 2.0
	 */
	public function get_transaction_types() {
		return $this->transaction_types;
	}

	/**
	 * Check for a valid transaction type
	 *
	 * @param  string $txn_type
	 * @since 2.0
	 */
	protected function validate_transaction_type( $txn_type ) {
		if ( in_array( strtolower( $txn_type ), $this->get_transaction_types() ) ) {
			return true;
		} else {
			return false;
		}
	}
}
