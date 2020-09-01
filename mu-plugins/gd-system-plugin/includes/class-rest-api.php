<?php

namespace WPaaS;

if ( ! defined( 'ABSPATH' ) ) {

	exit;

}

final class REST_API {

	private $namespaces = [];

	public function __construct() {

		if ( ! self::has_valid_account_uid() ) {

			return;

		}

		$this->namespaces['v1'] = 'wpaas/v1';

		add_action( 'rest_api_init', [ $this, 'flush_cache' ] );
		add_action( 'rest_api_init', [ $this, 'google_site_kit' ] );
		add_action( 'rest_api_init', [ $this, 'yoast' ] );

	}

	public static function has_valid_account_uid() {

		return defined( 'GD_ACCOUNT_UID' ) && GD_ACCOUNT_UID && isset( $_SERVER['HTTP_X_ACCOUNT_UID'] ) && GD_ACCOUNT_UID === $_SERVER['HTTP_X_ACCOUNT_UID'];

	}

	public static function has_valid_site_token() {

		return defined( 'GD_SITE_TOKEN' ) && GD_SITE_TOKEN && isset( $_SERVER['HTTP_X_SITE_TOKEN'] ) && GD_SITE_TOKEN === $_SERVER['HTTP_X_SITE_TOKEN'];

	}

	private static function get_url() {

		return defined( 'GD_TEMP_DOMAIN' ) && GD_TEMP_DOMAIN ? 'https://' . GD_TEMP_DOMAIN : home_url();

	}

	private static function get_user_id() {

		$users = get_users(
			[
				'role'   => 'administrator',
				'number' => 1,
			]
		);

		return ! empty( $users[0] ) ? (int) $users[0]->data->ID : 0;

	}

	private function get_user_nonce( $user_id, $cookie ) {

		$parts = wp_parse_auth_cookie( $cookie, 'logged_in' );
		$token = ! empty( $parts['token'] ) ? $parts['token'] : '';

		return substr( wp_hash( wp_nonce_tick() . '|wp_rest|' . $user_id . '|' . $token, 'nonce' ), -12, 10 );

	}

	/**
	 * POST route to flush cache.
	 */
	public function flush_cache() {

		register_rest_route( $this->namespaces['v1'], 'flush-cache', [
			'methods'             => 'POST',
			'permission_callback' => [ __CLASS__, 'has_valid_site_token' ],
			'callback'            => function () {
				add_action( 'shutdown', [ __NAMESPACE__ . '\Cache', 'flush_transients' ], PHP_INT_MAX );
				add_action( 'shutdown', [ __NAMESPACE__ . '\Cache', 'ban' ], PHP_INT_MAX );

				return [ 'success' => true ];
			},
		] );

	}

	/**
	 * GET route for Google Site Kit data.
	 */
	public function google_site_kit() {

		register_rest_route( $this->namespaces['v1'], 'google-site-kit', [
			'methods'  => 'GET',
			'callback' => function () {
				$gsk_is_connected   = (bool) get_option( 'googlesitekit_has_connected_admins' );
				$gsk_active_modules = (array) get_option( 'googlesitekit_active_modules', [] );

				if ( $gsk_is_connected ) {

					/**
					 * The search console is not techcnially a module and not stored in the `googlesitekit_active_modules` option
					 * once GSK is connected, search-console is always active.
					 */
					array_unshift( $gsk_active_modules, 'search-console' );

				}

				return [
					'active'         => defined( 'GOOGLESITEKIT_VERSION' ),
					'version'        => defined( 'GOOGLESITEKIT_VERSION' ) ? GOOGLESITEKIT_VERSION : null,
					'active_modules' => defined( 'GOOGLESITEKIT_VERSION' ) ? $gsk_active_modules : [],
				];
			},
		] );

		register_rest_route( $this->namespaces['v1'], 'google-site-kit/v1/modules/analytics/data/(?P<endpoint>[a-zA-Z0-9-]+)', [
			'methods'             => 'GET',
			'permission_callback' => [ __CLASS__, 'has_valid_site_token' ],
			'callback'            => function ( $request ) {
				$args = [];

				if ( ! empty( $_SERVER['QUERY_STRING'] ) ) {

					parse_str( $_SERVER['QUERY_STRING'], $args );

				}

				$args['metrics']    = ! empty( $args['metrics'] ) ? $args['metrics'] : 'ga:hits';
				$args['rest_route'] = "/google-site-kit/v1/modules/analytics/data/{$request['endpoint']}/";

				$url     = esc_url_raw( add_query_arg( $args, self::get_url() ) );
				$user_id = self::get_user_id();
				$expires = time() + MINUTE_IN_SECONDS;
				$cookie  = wp_generate_auth_cookie( $user_id, $expires, 'logged_in' );

				$response = wp_remote_get( $url, [
					'cookies' => [
						new \WP_Http_Cookie( [ 'name' => LOGGED_IN_COOKIE, 'value' => $cookie, 'expires' => $expires, 'domain' => wp_parse_url( self::get_url(), PHP_URL_HOST ) ] ),
					],
					'headers' => [
						'Content-Type' => 'application/json',
						'X-WP-Nonce'   => $this->get_user_nonce( $user_id, $cookie ),
					],
					'timeout' => 15,
				] );

				return json_decode( wp_remote_retrieve_body( $response ), true );
			},
		] );

	}

	/**
	 * GET route for Yoast SEO info.
	 */
	public function yoast() {

		register_rest_route( $this->namespaces['v1'], 'yoast', [
			'methods'  => 'GET',
			'callback' => function () {
				$wpseo = (array) get_option( 'wpseo', [] );

				return [
					'active'                 => defined( 'WPSEO_VERSION' ),
					'environment_type'       => ! empty( $wpseo['environment_type'] ) ? $wpseo['environment_type'] : null,
					'first_activated_on'     => ! empty( $wpseo['first_activated_on'] ) ? $wpseo['first_activated_on'] : null,
					'show_onboarding_notice' => ! empty( $wpseo['show_onboarding_notice'] ),
					'site_type'              => ! empty( $wpseo['site_type'] ) ? $wpseo['site_type'] : null,
					'version'                => ! empty( $wpseo['version'] ) ? $wpseo['version'] : null,
				];
			},
		] );

	}

}
