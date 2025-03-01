<?php
/**
 * Expired Email template
 *
 * @link       https://makewebbetter.com/
 * @since      1.0.0
 *
 * @package    Subscriptions_For_Woocommerce
 * @subpackage Subscriptions_For_Woocommerce/email
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<p><?php printf( esc_html__( 'A subscription [#%s] has been Expired. Their subscription\'s details are as follows:', 'subscriptions-for-woocommerce' ), esc_html( $mwb_subscription ) );?></p>

<?php
mwb_sfw_email_subscriptions_details( $mwb_subscription );

do_action( 'woocommerce_email_footer', $email );
