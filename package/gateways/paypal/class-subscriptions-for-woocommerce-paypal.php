<?php
/**
 * The admin-specific on-boarding functionality of the plugin.
 *
 * @link       https://makewebbetter.com
 * @since      1.0.0
 *
 * @package     Subscriptions_For_Woocommerce
 * @subpackage  Subscriptions_For_Woocommerce/includes
 */

/**
 * The Onboarding-specific functionality of the plugin admin side.
 *
 * @package     Subscriptions_For_Woocommerce
 * @subpackage  Subscriptions_For_Woocommerce/includes
 * @author      makewebbetter <webmaster@makewebbetter.com>
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( class_exists( 'Subscriptions_For_Woocommerce_Paypal' ) ) {
	return;
}
/**
 * Define class and module for onboarding steps.
 */
class Subscriptions_For_Woocommerce_Paypal {

	/**
	 * The single instance of the class.
	 *
	 * @since   1.0.0
	 * @var $_instance object of onboarding.
	 */
	protected static $_instance = null;
	private $mwb_wclog = '';
	private $mwb_debug;
	private $mwb_sfw_testmode;
	private $mwb_sfw_email;
	private $mwb_sfw_receiver_email;

	private $mwb_sfw_api_username;
	private $mwb_sfw_api_password;
	private $mwb_sfw_api_signature;
	private $mwb_sfw_api_endpoint;


	/**
	 * Define the onboarding functionality of the plugin.
	 *
	 * Set the plugin name and the store name and store url that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		
		if ( $this->mwb_sfw_paypal_check_settings() && $this->mwb_sfw_paypal_credential_set() ) {
			
			//include_once WC()->plugin_path() . '/includes/gateways/paypal/includes/class-wc-gateway-paypal-ipn-handler.php';

			//include_once plugin_dir_path( dirname( __FILE__ ) ) . 'paypal/class-mwb-sfw-paypal-ipn-handler.php';
			
			
			add_filter( 'woocommerce_paypal_args', array( $this, 'mwb_sfw_add_paypal_args' ),10,2 );
			
			add_action( 'valid-paypal-standard-ipn-request', array( $this, 'mwb_sfw_process_ipn_request' ) );

		}

	}

	public function mwb_sfw_process_ipn_request( $transaction_details ) {
			include_once WC()->plugin_path() . '/includes/gateways/paypal/includes/class-wc-gateway-paypal-ipn-handler.php';

			include_once SUBSCRIPTIONS_FOR_WOOCOMMERCE_DIR_PATH . 'package/gateways/paypal/class-mwb-sfw-paypal-ipn-handler.php';
			update_option('paypal_test_res',$transaction_details );
			update_option('paypal_test','testing' );
			
			if ( ! isset( $transaction_details['txn_type'] ) ) {
				return;
			}

			/*WC_Gateway_Paypal::log( 'Subscription Transaction Type: ' . $transaction_details['txn_type'] );
			WC_Gateway_Paypal::log( 'Subscription Transaction Details: ' . print_r( $transaction_details, true ) );*/
			if ( class_exists( 'Mwb_Sfw_PayPal_IPN_Handler' ) ) {
				
				$mwb_paypal_obj = new Mwb_Sfw_PayPal_IPN_Handler( $this->mwb_sfw_testmode, $this->mwb_sfw_receiver_email );
				$mwb_paypal_obj->valid_response( $transaction_details );
			}

			
		
	}

	/**
	 * Main Onboarding steps Instance.
	 *
	 * Ensures only one instance of Onboarding functionality is loaded or can be loaded.
	 *
	 * @since 1.0.0
	 * @static
	 * @return Onboarding Steps - Main instance.
	 */
	public static function get_instance() {

		if ( is_null( self::$_instance ) ) {

			self::$_instance = new self();
		}

		return self::$_instance;
	}

	public function mwb_sfw_paypal_check_settings() {
		
		$mwb_paypal_enable = true; 
		$mwb_paypal_settings = get_option( 'woocommerce_paypal_settings' );
		
		if ( ! isset( $mwb_paypal_settings['enabled'] ) || 'yes' != $mwb_paypal_settings['enabled'] ) {
			$mwb_paypal_enable = false;
		}

		
		$this->mwb_debug           = ( isset( $mwb_paypal_settings['debug'] ) && $mwb_paypal_settings['debug'] == 'yes' ) ? true : false;
		$this->mwb_sfw_testmode        = ( isset( $mwb_paypal_settings['testmode'] ) && $mwb_paypal_settings['testmode'] == 'yes' ) ? true : false;
		$this->mwb_sfw_email           = ( isset( $mwb_paypal_settings['email'] ) ) ? $mwb_paypal_settings['email'] : '';
		$this->mwb_sfw_receiver_email  = ( isset( $mwb_paypal_settings['receiver_email'] ) ) ? $mwb_paypal_settings['receiver_email'] : $this->mwb_sfw_email;

		if ( $this->mwb_debug ) {
			$this->mwb_wclog = new WC_Logger();
		}
		return $mwb_paypal_enable;
	}

	public function mwb_sfw_paypal_credential_set() {

		$mwb_credential_set = false; 
		$mwb_paypal_settings = get_option( 'woocommerce_paypal_settings' );
		
		if ( !empty( $mwb_paypal_settings ) ) {
			if ( isset( $mwb_paypal_settings['testmode'] ) && 'yes' == $mwb_paypal_settings['testmode'] ) {
				if ( '' != $mwb_paypal_settings['sandbox_api_username'] && '' != $mwb_paypal_settings['sandbox_api_password'] && '' != $mwb_paypal_settings['sandbox_api_signature'] ) {
					$mwb_credential_set = true; 
				}
			}
			else{
				if ( '' != $mwb_paypal_settings['api_username'] && '' != $mwb_paypal_settings['api_password'] && '' != $mwb_paypal_settings['api_signature'] ) {
					$mwb_credential_set = true; 
				}
			}
		}
		return $mwb_credential_set;
		
	}



	public function mwb_sfw_add_paypal_args( $mwb_args, $order ) {

		if ( empty( $order ) ) {
			return $mwb_args;
		}

		$order_id = $order->get_id();
		$mwb_order_has_susbcription = get_post_meta( $order_id ,'mwb_sfw_order_has_subscription', true );
		
		if ( $mwb_order_has_susbcription != 'yes' ) {
			return $mwb_args;
		}
		$mwb_susbcription_id = get_post_meta( $order_id ,'mwb_susbcription_id', true );
		if ( empty( $mwb_susbcription_id ) ) {
			return $mwb_args;
		}
		$susbcription = wc_get_order( $mwb_susbcription_id );
		$mwb_order_items = $susbcription->get_items();
		
		// check if order has subscriptions products
		$mwb_order_items = $order->get_items();

		if ( empty( $mwb_order_items ) ) {
			return $mwb_args;
		}
		$mwb_chk_susbcription = false;
		$mwb_item_names = array();

		foreach ( $mwb_order_items as $key => $order_item ) {

			$product_id = ( $order_item['variation_id'] ) ? $order_item['variation_id'] : $order_item['product_id'];
			$product    = wc_get_product( $product_id );

			if ( mwb_sfw_check_subscription_product( $product ) ) {
			
				//It is initialized as susbcription.
				$mwb_args['cmd']      = '_xclick-subscriptions';

				// reattempt failed payments use 0 for not.
				$mwb_args['sra'] = 1;

				
				$mwb_sfw_subscription_interval = get_post_meta( $mwb_susbcription_id, 'mwb_sfw_subscription_interval',true );
				$mwb_price_frequency= mwb_sfw_get_reccuring_time_interval_for_paypal( $mwb_sfw_subscription_interval );
				$mwb_price_is_per = get_post_meta( $mwb_susbcription_id, 'mwb_sfw_subscription_number',true );
				
				$mwb_sfw_subscription_expiry_number = get_post_meta( $mwb_susbcription_id, 'mwb_sfw_subscription_expiry_number',true );
				
				$mwb_schedule_start = get_post_meta( $mwb_susbcription_id, 'mwb_schedule_start',true );
				$mwb_susbcription_trial_end = get_post_meta( $mwb_susbcription_id, 'mwb_susbcription_trial_end',true );
			
				$mwb_susbcription_trial_end = mwb_sfw_susbcription_trial_date( $mwb_susbcription_id, $mwb_schedule_start );
				update_post_meta( $mwb_susbcription_id, 'mwb_susbcription_trial_end', $mwb_susbcription_trial_end );

				/*$mwb_next_payment_date = mwb_sfw_next_payment_date( $mwb_susbcription_id, $mwb_schedule_start, $mwb_susbcription_trial_end );

				update_post_meta( $value->ID, 'mwb_next_payment_date', $mwb_next_payment_date );*/

				$mwb_susbcription_end = mwb_sfw_susbcription_expiry_date( $mwb_susbcription_id, $mwb_schedule_start, $mwb_susbcription_trial_end );
				update_post_meta( $mwb_susbcription_id, 'mwb_susbcription_end', $mwb_susbcription_end );


				/*if ( isset( $mwb_sfw_subscription_expiry_number ) && !empty( $mwb_sfw_subscription_expiry_number ) ) {
					$mwb_sfw_subscription_expiry_interval = get_post_meta( $mwb_susbcription_id, 'mwb_sfw_subscription_expiry_interval',true );
					$result = mwb_sfw_get_subscription_interval_for_paypal( $mwb_schedule_start, $mwb_susbcription_end,$mwb_sfw_subscription_expiry_interval );
					die('-->>');
				}*/
				//else{
					$mwb_subscription_num = '';
				//}

				
				// order total
				
				if ( $susbcription->get_total() != $order->get_total() ) {
					$mwb_args['a1'] = wc_format_decimal( $order->get_total(), 2 );
					$mwb_args['p1'] = $price_is_per;
					$mwb_args['t1'] = $mwb_price_frequency;
				}

				

				$mwb_args['a3'] = wc_format_decimal( $susbcription->get_total(), 2 );
				$mwb_args['p3'] = $price_is_per;
				$mwb_args['t3'] = $mwb_price_frequency;

				if ( $mwb_subscription_num == '' || $mwb_subscription_num > 1 ) {
					$mwb_args['src'] = 1;
					if ( $mwb_subscription_num != '' ) {
						$mwb_args['srt'] = $mwb_subscription_num;
					}
				} else {
					$mwb_args['src'] = 0;
				}
				$mwb_chk_susbcription = true;
			}

			if ( $order_item['qty'] > 1 ) {
				$mwb_item_names[] = $order_item['qty'] . ' x ' . $this->mwb_format_item_name( $order_item['name'] );
			} else {
				$mwb_item_names[] = $this->mwb_format_item_name( $order_item['name'] );
			}
		}

		if ( ! $mwb_chk_susbcription ) {
			return $mwb_args;
		}

		if ( count( $mwb_item_names ) > 1 ) {
			$mwb_args['item_name'] = $this->mwb_format_item_name( sprintf( __( 'Order %s', 'subscriptions-for-woocommerce' ), $susbcription->get_order_number() . ' - ' . implode( ', ', $mwb_item_names ) ) );
		} else {
			$mwb_args['item_name'] = implode( ', ', $mwb_item_names );
		}

		$mwb_args['rm'] = 2;
		if ( $this->mwb_debug ) {
			$this->mwb_wclog->add( 'paypal', 'MWB - Subscription Request: ' . print_r( $mwb_args, true ) );
		}

		return $mwb_args;
	}

	public function mwb_get_order_details( $mwb_args ) {
		return isset( $mwb_args['custom'] ) ? json_decode( $mwb_args['custom'], true ) : false;
	}
	public function mwb_format_item_name( $item_name ) {
		if ( strlen( $item_name ) > 127 ) {
			$item_name = substr( $item_name, 0, 124 ) . '...';
		}
		return html_entity_decode( $item_name, ENT_NOQUOTES, 'UTF-8' );
	}


}
