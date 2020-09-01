<?php

namespace WPaaS;

if ( ! defined( 'ABSPATH' ) ) {

	exit;

}

final class WooCommerce {

	public function __construct() {

		if ( Plugin::has_plan( 'eCommerce Managed WordPress' ) ) {

			add_action( 'plugins_loaded', [ $this, 'set_defaults' ], PHP_INT_MAX );

		}

		add_filter( 'woocommerce_show_admin_notice', [ $this, 'suppress_notices' ], 10, 2 );

		add_filter( 'woocommerce_helper_suppress_connect_notice', [ $this, 'suppress_helper_notices' ], PHP_INT_MAX );

	}

	/**
	 * Set option defaults for a better Ecommerce plan experience.
	 *
	 * @action plugins_loaded - PHP_INT_MAX
	 * @since @3.17.0
	 */
	public function set_defaults() {

		if ( class_exists( 'WC_Admin_Notices' ) && ! get_option( 'wpnux_imported' ) ) {

			\WC_Admin_Notices::remove_notice( 'install', true );

		}

		if ( 'no' !== get_option( 'woocommerce_onboarding_opt_in' ) ) {

			update_option( 'woocommerce_onboarding_opt_in', 'no' );

		}

		if ( 'yes' !== get_option( 'woocommerce_task_list_hidden' ) ) {

			update_option( 'woocommerce_task_list_hidden', 'yes' );

		}

		$onboarding_profile = (array) get_option( 'woocommerce_onboarding_profile', [] );

		if ( empty( $onboarding_profile['completed'] ) ) {

			update_option( 'woocommerce_onboarding_profile', array_merge( $onboarding_profile, [ 'completed' => true ] ) );

		}

	}

	/**
	 * Suppress WooCommerce admin notices.
	 *
	 * @filter woocommerce_show_admin_notice
	 * @since 3.11.0
	 *
	 * @param  bool   $bool   Boolean value to show/suppress the notice.
	 * @param  string $notice The notice name being displayed.
	 *
	 * @return bool True to show the notice, false to suppress it.
	 */
	public function suppress_notices( $bool, $notice ) {

		// Suppress the SSL notice when using a temp domain.
		if ( 'no_secure_connection' === $notice && Plugin::is_temp_domain() ) {

			return false;

		}

		// Suppress the "Install WooCommerce Admin" notice when the Setup Wizard notice is visible.
		if ( 'wc_admin' === $notice && in_array( 'install', (array) get_option( 'woocommerce_admin_notices', [] ), true ) ) {

			return false;

		}

		return $bool;

	}

	/**
	 * Suppress WooCommerce helper admin notices when on a eCommerce Managed WordPress plan.
	 *
	 * @filter woocommerce_helper_suppress_connect_notice
	 *
	 * @param  bool $bool Boolean value to show/suppress the notice.
	 *
	 * @return bool True when a eCommerce Managed WordPress plan, else false.
	 */
	public function suppress_helper_notices() {

		return Plugin::has_plan( 'eCommerce Managed WordPress' );

	}

}
