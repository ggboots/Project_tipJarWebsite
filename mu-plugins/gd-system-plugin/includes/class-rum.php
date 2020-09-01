<?php

namespace WPaaS;

if ( ! defined( 'ABSPATH' ) ) {

	exit;

}

final class RUM {

	/**
	 * Class constructor.
	 */
	public function __construct() {

		if ( ! self::is_enabled() ) {

			return;

		}

		add_action( 'wp_footer',    [ $this, 'print_inline_script' ], PHP_INT_MAX );
		add_action( 'admin_footer', [ $this, 'print_inline_script' ], PHP_INT_MAX );

	}

	/**
	 * Add the RUM code to the footer of all pages.
	 *
	 * @action wp_footer
	 * @action admin_footer
	 */
	public function print_inline_script() {

		global $wp_version, $post;

		$env  = Plugin::get_env();
		$host = in_array( $env, [ 'dev', 'test' ], true ) ? "{$env}-secureserver.net" : 'secureserver.net';
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : null;

		?>
		<script>'undefined'=== typeof _trfq || (window._trfq = []);'undefined'=== typeof _trfd && (window._trfd=[]),_trfd.push({'tccl.baseHost':'<?php echo esc_js( $host ); ?>'}),_trfd.push({'ap':'wpaas'},{'server':'<?php echo esc_js( gethostname() ); ?>'},{'xid':'<?php echo absint( Plugin::xid() ); ?>'},{'wp':'<?php echo esc_js( $wp_version ); ?>'},{'php':'<?php echo esc_js( PHP_VERSION ); ?>'},{'loggedin':'<?php echo is_user_logged_in() ? 1 : 0; ?>'},{'cdn':'<?php echo CDN::is_enabled() ? 1 : 0; ?>'},{'builder':'<?php echo esc_js( Plugin::get_page_builder( $post ) ); ?>'},{'theme':'<?php echo esc_js( sanitize_title( get_template() ) ); ?>'})</script>
		<script src='https://img1.wsimg.com/tcc/tcc_l.combined.1.0.6.min.js'></script>
		<?php

	}

	/**
	 * Return whether RUM should be enabled on the current page load.
	 *
	 * @return bool
	 */
	public static function is_enabled() {

		$rum_enabled = (bool) apply_filters( 'wpaas_rum_enabled', defined( 'GD_RUM_ENABLED' ) ? GD_RUM_ENABLED : false );
		$temp_domain = defined( 'GD_TEMP_DOMAIN' ) ? GD_TEMP_DOMAIN : null;
		$is_nocache  = (bool) filter_input( INPUT_GET, 'nocache' );
		$is_gddebug  = (bool) filter_input( INPUT_GET, 'gddebug' );

		return ( $rum_enabled && $temp_domain && ! $is_nocache && ! $is_gddebug && ! WP_DEBUG );

	}

}
