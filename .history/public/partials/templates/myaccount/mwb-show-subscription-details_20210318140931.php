<?php
/**
 * The add new payment.
 *
 * @link       https://makewebbetter.com/
 * @since      1.0.0
 *
 * @package    Subscriptions_For_Woocommerce
 * @subpackage Subscriptions_For_Woocommerce/public
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/*Subscription details*/
function mwb_sfw_cancel_url( $mwb_subscription_id, $mwb_status ) {

	$mwb_link = add_query_arg(
		array(
			'mwb_subscription_id'        => $mwb_subscription_id,
			'mwb_subscription_status' => $mwb_status,
		)
	);
	$mwb_link = wp_nonce_url( $mwb_link, $mwb_subscription_id . $mwb_status );

	return $mwb_link;
}

?>
<div class="mwb_sfw_details_wrap">
	<table class="shop_table mwb_sfw_details">
		<h3><?php esc_html_e( 'Subscription Details', 'subscriptions-for-woocommerce' ); ?></h3>
		<tbody>
			<tr>
				<td><?php esc_html_e( 'Status', 'subscriptions-for-woocommerce' ); ?></td>
				<td>
				<?php
					$mwb_status = get_post_meta( $mwb_subscription_id, 'mwb_subscription_status', true );
					echo esc_html( $mwb_status );
				?>
				</td>
			</tr>
			<tr>
				<td><?php esc_html_e( 'Subscription Date', 'subscriptions-for-woocommerce' ); ?></td>
				<td>
				<?php
					$mwb_schedule_start = get_post_meta( $mwb_subscription_id, 'mwb_schedule_start', true );
					echo esc_html( mwb_sfw_get_the_wordpress_date_format( $mwb_schedule_start ) );
				?>
				</td>
			</tr>
			<tr>
				<td><?php esc_html_e( 'Next Payment Date', 'subscriptions-for-woocommerce' ); ?></td>
				<td>
				<?php
					$mwb_next_payment_date = get_post_meta( $mwb_subscription_id, 'mwb_next_payment_date', true );
					echo esc_html( mwb_sfw_get_the_wordpress_date_format( $mwb_next_payment_date ) );
				?>
				</td>
			</tr>
			<?php
			$mwb_trail_date = get_post_meta( $mwb_subscription_id, 'mwb_susbcription_trial_end', true );

			if ( ! empty( $mwb_trail_date ) ) {
				?>
				<tr>
					<td><?php esc_html_e( 'Trial End Date', 'subscriptions-for-woocommerce' ); ?></td>
					<td>
					<?php
						echo esc_html( mwb_sfw_get_the_wordpress_date_format( $mwb_trail_date ) );
					?>
					</td>
				</tr>
				<?php
			}
			?>
			
			<?php
				$mwb_next_payment_date = get_post_meta( $mwb_subscription_id, '_payment_method', true );
			if ( empty( $mwb_next_payment_date ) ) {
					$subscription = wc_get_order( $mwb_subscription_id );
					$mwb_sfw_add_payment_url = wp_nonce_url( add_query_arg( array( 'mwb_add_payment_method' => $mwb_subscription_id ), $subscription->get_checkout_payment_url() ) );
				?>
							<tr>
								<td>
									<a href="<?php echo esc_url( $mwb_sfw_add_payment_url ); ?>" class="button mwb_sfw_add_payment_url"><?php esc_html_e( 'Add Payment Method', 'subscriptions-for-woocommerce' ); ?></a>
								</td>
							</tr>
						<?php

			}

			?>
			<?php do_action( 'mwb_sfw_subscription_details_html', $mwb_subscription_id ); ?>
		</tbody>
	</table>
	<table class="shop_table mwb_sfw_order_details">
		<h3><?php esc_html_e( 'Subscription Order Details', 'subscriptions-for-woocommerce' ); ?></h3>
		<thead>
			<tr>
				<th>
					<?php esc_html_e( 'Product Name', 'subscriptions-for-woocommerce' ); ?>
				</th>
				<th>
					<?php esc_html_e( 'Total', 'subscriptions-for-woocommerce' ); ?>
				</th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td>
					<?php
						$mwb_product_name = get_post_meta( $mwb_subscription_id, 'product_name', true );
						$product_qty = get_post_meta( $mwb_subscription_id, 'product_qty', true );

						echo esc_html( $mwb_product_name ) . ' x ' . esc_html( $product_qty );
					?>
					
				 </td>
				<td>
				<?php
					do_action( 'mwb_sfw_display_susbcription_recerring_total_account_page', $mwb_subscription_id );
				?>
				</td>
			</tr>
			<?php do_action( 'mwb_sfw_order_details_html_before_cancel', $mwb_subscription_id ); ?>
				<?php
					$mwb_sfw_cancel_subscription = get_option( 'mwb_sfw_cancel_subscription_for_customer', '' );
				if ( 'on' == $mwb_sfw_cancel_subscription ) {

					$mwb_status = get_post_meta( $mwb_subscription_id, 'mwb_subscription_status', true );
					if ( $mwb_status == 'active' ) {
						$mwb_cancel_url = mwb_sfw_cancel_url( $mwb_subscription_id, $mwb_status );
						?>
							<tr>
								<td>
									<a href="<?php echo esc_url( $mwb_cancel_url ); ?>" class="button mwb_sfw_cancel_subscription"><?php esc_html_e( 'Cancel', 'subscriptions-for-woocommerce' ); ?></a>
								</td>
							</tr>
						<?php
					}
				}
				?>
				<?php do_action( 'mwb_sfw_order_details_html_after_cancel', $mwb_subscription_id ); ?>
		</tbody>
	</table>
</div>
