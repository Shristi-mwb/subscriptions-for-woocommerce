<?php
/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @link       https://makewebbetter.com/
 * @since      1.0.0
 *
 * @package    Subscriptions_For_Woocommerce
 * @subpackage Subscriptions_For_Woocommerce/admin/partials
 */

if ( ! defined( 'ABSPATH' ) ) {

	exit(); // Exit if accessed directly.
}

global $sfw_mwb_sfw_obj;
$sfw_active_tab   = isset( $_GET['sfw_tab'] ) ? sanitize_key( $_GET['sfw_tab'] ) : 'subscriptions-for-woocommerce-general';
$sfw_default_tabs = $sfw_mwb_sfw_obj->mwb_sfw_plug_default_tabs();
?>
<header>
	<div class="mwb-header-container mwb-bg-white mwb-r-8">
		<h1 class="mwb-header-title"><?php echo esc_attr( strtoupper( str_replace( '-', ' ', $sfw_mwb_sfw_obj->sfw_get_plugin_name() ) ) ); ?></h1>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=subscriptions_for_woocommerce_menu' ) . '&sfw_tab=' . esc_attr( 'subscriptions-for-woocommerce-support' ) ); ?>" class="mwb-link"><?php esc_html_e( 'Support', 'subscriptions-for-woocommerce' ); ?></a>
	</div>
</header>

<main class="mwb-main mwb-bg-white mwb-r-8">
	<nav class="mwb-navbar">
		<ul class="mwb-navbar__items">
			<?php
			if ( is_array( $sfw_default_tabs ) && ! empty( $sfw_default_tabs ) ) {

				foreach ( $sfw_default_tabs as $sfw_tab_key => $sfw_default_tabs ) {

					$sfw_tab_classes = 'mwb-link ';

					if ( ! empty( $sfw_active_tab ) && $sfw_active_tab === $sfw_tab_key ) {
						$sfw_tab_classes .= 'active';
					}
					?>
					<li>
						<a id="<?php echo esc_attr( $sfw_tab_key ); ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=subscriptions_for_woocommerce_menu' ) . '&sfw_tab=' . esc_attr( $sfw_tab_key ) ); ?>" class="<?php echo esc_attr( $sfw_tab_classes ); ?>"><?php echo esc_html( $sfw_default_tabs['title'] ); ?></a>
					</li>
					<?php
				}
			}
			?>
		</ul>
	</nav>

	<section class="mwb-section">
		<div>
			<?php 
				do_action( 'mwb_sfw_before_general_settings_form' );
						// if submenu is directly clicked on woocommerce.
				if ( empty( $sfw_active_tab ) ) {
					$sfw_active_tab = 'mwb_sfw_plug_general';
				}

						// look for the path based on the tab id in the admin templates.
				$sfw_tab_content_path = 'admin/partials/' . $sfw_active_tab . '.php';

				$sfw_mwb_sfw_obj->mwb_sfw_plug_load_template( $sfw_tab_content_path );

				do_action( 'mwb_sfw_after_general_settings_form' ); 
			?>
		</div>
	</section>
