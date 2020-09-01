<?php

namespace WPaaS;

if ( ! defined( 'ABSPATH' ) ) {

	exit;

}

final class RAD {

	/**
	 * Instance of the API.
	 *
	 * @var API_Interface
	 */
	private $api;

	/**
	 * Old theme mods before Customizer save.
	 *
	 * @var array
	 */
	private $old_theme_mods = [];

	/**
	 * Class constructor.
	 *
	 * @param API_Interface $api
	 */
	public function __construct( API_Interface $api ) {

		if ( ! defined( 'GD_RAD_ENABLED' ) || ! GD_RAD_ENABLED ) {

			return;

		}

		$this->api = $api;

		add_action( 'save_post', [ $this, 'first_woocommerce_order_created' ], PHP_INT_MAX, 3 );

		if ( ! is_admin() ) {

			return;

		}

		add_action( 'post_updated', [ $this, 'first_page_on_front_update' ], PHP_INT_MAX, 2 );
		add_action( 'update_option_wpseo', [ $this, 'yoast_seo_wizard_completed' ], PHP_INT_MAX, 2 );
		add_action( 'save_post', [ $this, 'first_woocommerce_product_created' ], PHP_INT_MAX, 3 );
		add_action( 'update_option', [ $this, 'woocommerce_payment_gateway_enabled' ], PHP_INT_MAX, 3 );
		add_action( 'activate_plugin', [ $this, 'plugin_activated_from_gd_recommended_tab' ], PHP_INT_MAX, 1 );
		add_action( 'activate_plugin', [ $this, 'woocommerce_plugin_activated_from_available_extensions_tab' ], PHP_INT_MAX, 1 );

		add_action( 'customize_save', function () {

			$this->old_theme_mods = (array) get_option( 'theme_mods_go', [] );

		} );

		add_action( 'customize_save_after', [ $this, 'go_theme_site_design_updated' ], PHP_INT_MAX );
		add_action( 'update_option_theme_mods_go', [ $this, 'go_theme_site_design_updated' ], PHP_INT_MAX, 2 );

	}

	private function log_rad_event( $name, $metadata = [], $action_priority = PHP_INT_MAX ) {

		$this->api->log_rad_event( $name, $metadata );

		remove_action( current_action(), [ $this, $name ], $action_priority );

	}

	public function first_page_on_front_update( $post_id, $post_after ) {

		if ( $post_id !== (int) get_option( 'page_on_front' ) || get_option( 'gd_system_first_page_on_front_update' ) ) {

			return;

		}

		update_option( 'gd_system_first_page_on_front_update', time(), false );

		$this->log_rad_event( __FUNCTION__, [
			'builder' => Plugin::get_page_builder( $post_after ),
			'theme'   => sanitize_title( get_template() ),
			'wpnux'   => ! empty( get_post_meta( $post_id, 'wpnux_id', true ) ),
		] );

	}

	public function yoast_seo_wizard_completed( $old_value, $new_value ) {

		if ( ! empty( $old_value['show_onboarding_notice'] ) && empty( $new_value['show_onboarding_notice'] ) ) {

			$this->log_rad_event( __FUNCTION__, [
				'theme' => sanitize_title( get_template() ),
			] );

		}

	}

	public function first_woocommerce_product_created( $post_id, $post, $update ) {

		if ( 'publish' !== $post->post_status || 'product' !== $post->post_type || ! Plugin::is_plugin_active( 'woocommerce/woocommerce.php' ) || get_option( 'gd_system_first_woo_product' ) ) {

			return;

		}

		update_option( 'gd_system_first_woo_product', time(), false );

		$this->log_rad_event( __FUNCTION__, [
			'theme' => sanitize_title( get_template() ),
			'wpnux' => $update && ! empty( get_post_meta( $post_id, 'wpnux_id', true ) ),
		] );

	}

	public function first_woocommerce_order_created( $post_id, $post, $update ) {

		if ( is_admin() || $update || 'wc-completed' !== $post->post_status || 'shop_order' !== $post->post_type || ! Plugin::is_plugin_active( 'woocommerce/woocommerce.php' ) || get_option( 'gd_system_first_woo_order' ) || ! get_option( 'gd_system_first_woo_product' ) ) {

			return;

		}

		// If the Customer email matches the shop owner, it's probably a test.
		// Loose comparison ok.
		if ( $billing_email = get_post_meta( $post_id, '_billing_email', true ) && $billing_email == get_option( 'admin_email' ) ) {

			return;

		}

		// Must be a Guest (user_id = 0) or a registered user with the Customer role.
		if ( $customer_user_id = (int) get_post_meta( $post_id, '_customer_user', true ) ) {

			$user  = get_userdata( $customer_user_id );
			$roles = isset( $user->roles ) ? (array) $user->roles : [];

			if ( ! in_array( 'customer', $roles, true ) ) {

				return;

			}

		}

		// Autoload for best performance.
		update_option( 'gd_system_first_woo_order', time() );

		$this->log_rad_event( __FUNCTION__, [
			'theme' => sanitize_title( get_template() ),
		] );

	}

	public function woocommerce_payment_gateway_enabled( $option, $old_value, $new_value ) {

		static $logged = [];

		if ( ! preg_match( '/^woocommerce_(.+)_settings$/', $option, $matches ) || ! empty( $logged[ $matches[1] ] ) ) {

			return;

		}

		$new_enabled = ! empty( $new_value['enabled'] ) && 'yes' === $new_value['enabled'];
		$old_enabled = ! empty( $old_value['enabled'] ) && 'yes' === $old_value['enabled'];

		if ( ! $new_enabled || $old_enabled || ! Plugin::is_plugin_active( 'woocommerce/woocommerce.php' ) || ! in_array( $matches[1], array_keys( get_option( 'woocommerce_gateway_order', [] ) ), true ) ) {

			return;

		}

		// Action removal not needed, use api method directly.
		$this->log_rad_event( 'woocommerce_payment_gateway_enabled', [
			'gateway' => $matches[1],
			'theme'   => sanitize_title( get_template() ),
		] );

		$logged[ $matches[1] ] = true;

	}

	public function plugin_activated_from_gd_recommended_tab( $plugin ) {

		parse_str( wp_parse_url( wp_get_referer(), PHP_URL_QUERY ), $query_args );

		if ( empty( $query_args['tab'] ) || Admin\Recommended_Plugins_Tab::SLUG !== $query_args['tab'] ) {

			return;

		}

		$this->log_rad_event( __FUNCTION__, [
			'plugin' => dirname( $plugin ),
		] );

	}

	public function woocommerce_plugin_activated_from_available_extensions_tab( $plugin ) {

		parse_str( wp_parse_url( wp_get_referer(), PHP_URL_QUERY ), $query_args );

		if ( empty( $query_args['page'] ) || 'wc-addons' !== $query_args['page'] || empty( $query_args['tab'] ) || Admin\Woo_Extensions::SLUG !== $query_args['tab'] || ! Plugin::is_plugin_active( 'woocommerce/woocommerce.php' ) ) {

			return;

		}

		$this->log_rad_event( __FUNCTION__, [
			'plugin' => dirname( $plugin ),
		] );

	}

	public function go_theme_site_design_updated( $old_value = [], $new_value = [] ) {

		if ( 'customize_save_after' === current_action() ) {

			$old_value = $this->old_theme_mods;
			$new_value = (array) get_option( 'theme_mods_go', [] );

		}

		// Loose comparison ok.
		if ( $old_value != $new_value ) {

			$this->log_rad_event( __FUNCTION__, array_diff( $new_value, $old_value ) );

		}

	}

}
