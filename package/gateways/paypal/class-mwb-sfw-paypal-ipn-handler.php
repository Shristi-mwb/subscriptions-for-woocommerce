<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


if ( ! class_exists( 'MWB_Sfw_PayPal_IPN_Handler' ) ) {

	class MWB_Sfw_PayPal_IPN_Handler extends WC_Gateway_Paypal_IPN_Handler {


		private $mwb_transaction_types = array(
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
		 * Constructor
		 *
		 * Initialize plugin and registers actions and filters to be used
		 *
		 * @since  1.0.0
		 * @author Emanuela Castorina
		 *
		 * @param bool   $sandbox
		 * @param string $receiver_email
		 */
		public function __construct( $sandbox = false, $receiver_email = '' ) {
			
			$this->receiver_email = $receiver_email;
			$this->sandbox        = $sandbox;
		}

		/**
		 * There was a valid response
		 *
		 * @param  array $mwb_transaction_details Post data after wp_unslash
		 */
		public function mwb_sfw_valid_response( $mwb_transaction_details ) {
            global $wpdb;

			$mwb_transaction_details = stripslashes_deep( $mwb_transaction_details );
			update_option('mwb_sfw_test',$mwb_transaction_details);
			if ( ! $this->mwb_validate_transaction_type( $mwb_transaction_details['txn_type'] ) ) {
				return;
			}
			
			if ( ! empty( $mwb_transaction_details['custom'] ) ) {
				$order = $this->get_paypal_order( $mwb_transaction_details['custom'] );
			}
			elseif ( ! empty( $mwb_transaction_details['invoice'] ) ) {
				$order = wc_get_order( substr( $mwb_transaction_details['invoice'], strrpos( $mwb_transaction_details['invoice'], '-' ) + 1 ) );
			}

			if ( isset( $order ) ) {

				$order_id = $order->get_id();
				WC_Gateway_Paypal::log( 'MWB - Found order #' . $order_id );

				$mwb_transaction_details['payment_status'] = strtolower( $mwb_transaction_details['payment_status'] );

				WC_Gateway_Paypal::log( 'MWB - Txn Type: ' . $mwb_transaction_details['txn_type'] );

				$this->mwb_sfw_process_ipn_request( $order, $mwb_transaction_details );

			} else {
				WC_Gateway_Paypal::log( 'MWB - Order Not Found.' );
			}
		}

		private function mwb_validate_transaction_type( $txn_type ) {
			if ( in_array( strtolower( $txn_type ), $this->mwb_get_transaction_types() ) ) {
				return true;
			} else {
				return false;
			}
		}
		private function mwb_get_transaction_types() {
			return $this->mwb_transaction_types;
		}

		private function mwb_sfw_process_ipn_request( $order, $mwb_transaction_details ) {
			
			if ( isset( $mwb_transaction_details['mc_currency'] ) ) {
				$this->validate_currency( $order, $mwb_transaction_details['mc_currency'] );
			}
			WC_Gateway_Paypal::log( 'MWB - currency validation successfull' );
			if ( isset( $mwb_transaction_details['receiver_email'] ) ) {
				$this->validate_receiver_email( $order, $mwb_transaction_details['receiver_email'] );
			}

			WC_Gateway_Paypal::log( 'MWB - Email validation successfull' );
			$this->save_paypal_meta_data( $order, $mwb_transaction_details );
			$this->mwb_paypal_ipn_request( $order, $mwb_transaction_details );
		}

		private function mwb_paypal_ipn_request( $order, $mwb_transaction_details ) {
			update_option('mwb_payment_test',$mwb_transaction_details);
			WC_Gateway_Paypal::log( 'MWB - Transaction log payment:'.print_r( $mwb_transaction_details, true ) );
			$mwb_order_statuses = array( 'on-hold', 'pending', 'failed', 'cancelled','wc-mwb_renewal' );
			$mwb_order_info = $this->mwb_sfw_get_order_info( $mwb_transaction_details );
			if ( $order->get_order_key() != $mwb_order_info['order_key'] ) {
				WC_Gateway_Paypal::log( 'MWB - Order keys not matching' );
				return;
			}
			$order_id = $order->get_id();
			// check if the transaction has been processed
			$mwb_order_transaction_id = get_post_meta( $order_id, '_paypal_transaction_ids', true );
			$mwb_order_transactions   = $this->mwb_sfw_validate_transaction( $mwb_order_transaction_id, $mwb_transaction_details );
			update_option('test_transaction',$mwb_order_transactions );
			
			if ( $mwb_order_transactions ) {
				update_post_meta( $order_id, '_paypal_transaction_ids', $order_transactions );
			} else {
				WC_Gateway_Paypal::log( 'MWB - Transaction ID already processed' );
				return;
			}

			$mwb_order_has_susbcription = get_post_meta( $order_id ,'mwb_sfw_order_has_subscription', true );

			if ( $mwb_order_has_susbcription != 'yes' ) {
				WC_Gateway_Paypal::log( 'MWB - Not a valid Subscription' );
				return;
			}

			$mwb_susbcription_id = get_post_meta( $order_id ,'mwb_susbcription_id', true );

			if ( empty( $mwb_susbcription_id ) ) {

				WC_Gateway_Paypal::log( 'MWB - IPN subscription payment error - ' . $order_id . ' haven\'t subscriptions' );
				return;

			}
			/*check for valid subscription*/
			if ( ! mwb_sfw_check_valid_subscription( $mwb_susbcription_id ) ) {
				WC_Gateway_Paypal::log( 'MWB - IPN subscription payment error - ' . $order_id . ' haven\'t valid subscriptions' );
				return;
			}

			$subscription = wc_get_order( $mwb_susbcription_id );

			switch ( $mwb_transaction_details['txn_type'] ) {
				case 'subscr_signup':
					$args = array(
						'mwb_subscriber_id'         => $mwb_transaction_details['subscr_id'],
						'mwb_subscriber_first_name' => $mwb_transaction_details['first_name'],
						'mwb_subscriber_last_name'  => $mwb_transaction_details['last_name'],
						'mwb_subscriber_address'    => $mwb_transaction_details['payer_email'],
					);
					$this->mwb_sfw_save_post_data( $order->get_id(), $args );
					$order->add_order_note( __( 'IPN subscription started', 'subscriptions-for-woocommerce' ) );

					if ( isset( $mwb_transaction_details['mc_amount1'] ) && $mwb_transaction_details['mc_amount1'] == 0 ) {
						$order->payment_complete( $mwb_transaction_details['txn_id'] );
					}
					
					$args = array(
									'mwb_sfw_paypal_transaction_id'        => $mwb_transaction_details['txn_id'],
									'mwb_sfw_paypal_subscriber_id' 		   => $mwb_transaction_details['subscr_id']
									
								);
					$this->mwb_sfw_save_post_data( $mwb_susbcription_id, $args );

					break;
				case 'subscr_payment':
					update_option('mwb_subscr_payment_test',$mwb_transaction_details);
					WC_Gateway_Paypal::log( 'MWB - Transaction log for subscr_payment:'.print_r( $mwb_transaction_details, true ) );
					if ( 'completed' == strtolower( $mwb_transaction_details['payment_status'] ) ) {

						$mwb_order_transactions = get_post_meta( $mwb_susbcription_id, '_paypal_transaction_ids', true );
						$mwb_order_transactions    = $this->mwb_sfw_validate_transaction( $mwb_order_transactions, $mwb_transaction_details );
						if ( $mwb_order_transactions ) {
							update_post_meta( $mwb_susbcription_id, '_paypal_transaction_ids', $mwb_order_transactions );
						} else {
							WC_Gateway_Paypal::log( 'MWB - Transaction ID Error' );
							return;
						}
						$mwb_pending_order    = false;
						$mwb_renewal_order = $subscription->mwb_renewal_subcription_order;
						WC_Gateway_Paypal::log( 'MWB - Renewal Order ID:'. $mwb_renewal_order );
						if ( intval( $mwb_renewal_order ) ) {
							$mwb_pending_order = wc_get_order( $mwb_renewal_order );
						}

						if ( isset( $mwb_transaction_details['mc_gross'] ) ) {
							if ( $mwb_pending_order ) {
								$this->mwb_paypal_validate_amount( $mwb_pending_order, $mwb_transaction_details['mc_gross'] );
							} elseif ( $order->has_status( $mwb_order_statuses ) ) {
								$this->mwb_paypal_validate_amount( $order, $mwb_transaction_details['mc_gross'] );
							}
						}
						if ( isset( $mwb_transaction_details['subscr_id'] ) ) {
							$mwb_sub_id = $mwb_transaction_details['subscr_id'];
						} elseif ( isset( $mwb_transaction_details['recurring_payment_id'] ) ) {
							$mwb_sub_id = $mwb_transaction_details['recurring_payment_id'];
						}
						
						WC_Gateway_Paypal::log( 'MWB - Subscription Status:'. ($subscription->mwb_subscription_status) );

						if ( $subscription->mwb_subscription_status == 'pending' ||  ( ! $mwb_pending_order && $order->has_status( $mwb_order_statuses ) ) )  {
							
							$args = array(
								'mwb_subscriber_id'        => $mwb_sub_id,
								'mwb_subscriber_first_name' => $mwb_transaction_details['first_name'],
								'mwb_subscriber_last_name' => $mwb_transaction_details['last_name'],
								'mwb_subscriber_address'   => $mwb_transaction_details['payer_email'],
								'mwb_subscriber_payment_type' => wc_clean( $mwb_transaction_details['payment_type'] ),
							);

							$this->mwb_sfw_save_post_data( $order->get_id(), $args );
							$order->add_order_note( __( 'IPN subscription payment completed.', 'subscriptions-for-woocommerce' ) );
							$order->payment_complete( $mwb_transaction_details['txn_id'] );
							
						}
						elseif ( $mwb_pending_order ) {
								$args = array(
									'mwb_subscriber_id'        => $mwb_sub_id,
									'mwb_subscriber_first_name' => $mwb_transaction_details['first_name'],
									'mwb_subscriber_last_name' => $mwb_transaction_details['last_name'],
									'mwb_subscriber_address'   => $mwb_transaction_details['payer_email'],
									'mwb_subscriber_payment_type' => wc_clean( $mwb_transaction_details['payment_type'] ),
								);

								$this->mwb_sfw_save_post_data( $mwb_pending_order->get_id(), $args );

								$mwb_pending_order->add_order_note( __( 'IPN subscription payment completed.', 'subscriptions-for-woocommerce' ) );
								$mwb_pending_order->payment_complete( $mwb_transaction_details['txn_id'] );

							} else {
								
								$mwb_renewal_order = mwb_sfw_create_renewal_order_for_paypal( $mwb_susbcription_id );

								if ( ! $mwb_renewal_order ) {
									WC_Gateway_Paypal::log( 'MWB - Renewal Order Creation failed' );
									return;
								}

								if ( isset( $mwb_transaction_details['mc_gross'] ) ) {
									$this->mwb_paypal_validate_amount( $mwb_renewal_order, $mwb_transaction_details['mc_gross'] );
								}

								
								$args = array(
									'mwb_subscriber_id'        => $mwb_sub_id,
									'mwb_subscriber_first_name' => $mwb_transaction_details['first_name'],
									'mwb_subscriber_last_name' => $mwb_transaction_details['last_name'],
									'mwb_subscriber_address'   => $mwb_transaction_details['payer_email'],
									'mwb_subscriber_payment_type' => wc_clean( $mwb_transaction_details['payment_type'] ),
								);

								$this->mwb_sfw_save_post_data( $mwb_renewal_order->get_id(), $args );

								$mwb_renewal_order->add_order_note( __( 'IPN subscription payment completed.', 'subscriptions-for-woocommerce' ) );
								$mwb_renewal_order->payment_complete( $mwb_transaction_details['txn_id'] );

							}
							$args = array(
									'mwb_sfw_paypal_transaction_id'        => $mwb_transaction_details['txn_id'],
									'mwb_sfw_paypal_subscriber_id' 		   => $mwb_sub_id
									
								);
							$this->mwb_sfw_save_post_data( $mwb_susbcription_id, $args );
							
							WC_Gateway_Paypal::log( 'MWB - Subscription successfull' );
					}

					break;
			}

		}

		private function mwb_sfw_validate_transaction( $mwb_transaction_ids, $mwb_transaction_details ) {

			$mwb_transaction_ids = empty( $mwb_transaction_ids ) ? array() : $mwb_transaction_ids;
			// check if the ipn request as been processed
			if ( isset( $mwb_transaction_details['txn_id'] ) ) {
				$transaction_id = $mwb_transaction_details['txn_id'] . '-' . $mwb_transaction_details['txn_type'];

				if ( isset( $mwb_transaction_details['payment_status'] ) ) {
					$transaction_id .= '-' . $mwb_transaction_details['payment_status'];
				}
				if ( empty( $mwb_transaction_ids ) || ! in_array( $transaction_id, $mwb_transaction_ids ) ) {
					$mwb_transaction_ids[] = $transaction_id;
				} else {
					
					WC_Gateway_Paypal::log( 'paypal', 'MWB - Subscription IPN Error: IPN ' . $transaction_id . ' message has already been correctly handled.' );
					return false;
				}
			} elseif ( isset( $mwb_transaction_details['ipn_track_id'] ) ) {
				$track_id = $mwb_transaction_details['txn_type'] . '-' . $mwb_transaction_details['ipn_track_id'];
				if ( empty( $mwb_transaction_ids ) || ! in_array( $track_id, $mwb_transaction_ids ) ) {
					$mwb_transaction_ids[] = $track_id;
				} else {
					
						WC_Gateway_Paypal::log( 'paypal', 'MWB - Subscription IPN Error: IPN ' . $track_id . ' message has already been correctly handled.' );
					return false;
				}
			}

			return $mwb_transaction_ids;

		}

		private function mwb_sfw_save_post_data( $order_id, $args ) {
			if ( isset( $order_id) && !empty( $order_id ) && !empty( $args ) && is_array( $args ) ) {
				foreach ($args as $key => $value) {
					update_post_meta( $order_id, $key, $value );
				}
			}
		}
		private function mwb_sfw_get_order_info( $args ) {
			return isset( $args['custom'] ) ? json_decode( $args['custom'], true ) : false;
		}

		private function mwb_paypal_validate_amount( $mwb_order, $mwb_amount ) {
			if ( wc_format_decimal( $mwb_order->get_total(), 2 ) != wc_format_decimal( $mwb_amount, 2 ) ) {
				WC_Gateway_Paypal::log( 'Amounts not matching: ' . $mwb_amount );
				$order->update_status( 'on-hold' );
				return;
			}
		}
	}

}