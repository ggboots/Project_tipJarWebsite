<?php

namespace WPaaS\Admin;

use WC_Helper_Updater;
use WC_Helper;
use WC_Admin_Addons;

use \WPaaS\Plugin;

if ( ! defined( 'ABSPATH' ) ) {

	exit;

}

final class Woo_Extensions {

	/**
	 * Tab slug name.
	 *
	 * @var string
	 */
	const SLUG = 'available_extensions';

	/**
	 * API Instance
	 *
	 * @var object
	 */
	private $api;

	/**
	 * WooCommerce Extensions Data
	 *
	 * @var array
	 */
	private $woo_extensions = [];

	/**
	 * Featured Extensions Data
	 *
	 * @var array
	 */
	private $featured_extensions = [];

	/**
	 * Featured WooCommerce Extensions
	 *
	 * @var array
	 */
	const FEATURED_EXTENSIONS = [
		'woocommerce-checkout-field-editor',
		'woocommerce-follow-up-emails',
		'woocommerce-product-addons',
	];

	/**
	 * Class constructor.
	 *
	 * @param API_Interface $api
	 */
	public function __construct( \WPaaS\API $api ) {

		$this->api = $api;

		add_action( 'init', [ $this, 'init' ] );

		/**
		 * Check that the constants are defined.
		 *
		 * @filter wpaas_woo_extension_management
		 *
		 * @return bool True when both constants are defined, else false.
		 */
		add_filter(
			'wpaas_woo_extension_management',
			function() {
				return defined( 'GD_ACCOUNT_UID' ) && defined( 'GD_SITE_TOKEN' );
			}
		);

	}

	/**
	 * Initialize the script.
	 *
	 * @action init
	 */
	public function init() {

		if (
			! Plugin::has_plan( 'eCommerce Managed WordPress' ) ||
			! is_plugin_active( 'woocommerce/woocommerce.php' ) ||
			! class_exists( 'WC_Helper_Updater' ) ||
			! apply_filters( 'wpaas_woo_extension_management', true )
		) {

			return;

		}

		$this->woo_extensions = $this->api->get_woocommerce_products( 'extensions' );

		if ( empty( $this->woo_extensions ) ) {

			return;

		}

		// Trim verbose "WooCommerce" prefix from all extensions.
		foreach ( $this->woo_extensions as &$extension ) {

			$extension['name'] = trim( preg_replace( '/^WooCommerce/', '', $extension['name'] ) );

		}

		// Sort by name ASC.
		array_multisort( wp_list_pluck( $this->woo_extensions, 'name' ), SORT_ASC, $this->woo_extensions );

		$this->featured_extensions = $this->get_featured_woo_extensions();

		add_action( 'admin_init', [ $this, 'redirect' ], 0 );

		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

		add_filter( 'woocommerce_show_addons_page', '__return_false' );

		add_action( 'admin_menu', [ $this, 'woo_extensions_menu_page' ], PHP_INT_MAX );

		add_action( 'wp_ajax_install_woocommerce_extension', [ $this, 'install_woocommerce_extension' ] );

		add_action( 'wp_ajax_activate_woocommerce_extension', [ $this, 'activate_woocommerce_extension' ] );

	}

	/**
	 * Redirect the default page to our tab.
	 */
	public function redirect() {

		global $pagenow;

		$page = filter_input( INPUT_GET, 'page', FILTER_SANITIZE_STRING );
		$tab  = filter_input( INPUT_GET, 'tab', FILTER_SANITIZE_STRING );

		if ( 'admin.php' === $pagenow && 'wc-addons' === $page && ! $tab ) {

			wp_safe_redirect( add_query_arg( [ 'page' => 'wc-addons', 'tab' => self::SLUG ], admin_url( 'admin.php' ) ) );

			exit;

		}

	}

	/**
	 * Get the featured WooCommerce extension data
	 *
	 * @return array Featured WooCommerce extensions data
	 */
	private function get_featured_woo_extensions() {

		$featured_extensions_cache = get_transient( 'wpaas_woocommerce_featured_extensions' );

		if ( false !== $featured_extensions_cache ) {

			return $featured_extensions_cache;

		}

		// Filter out extensions not in the featured extensions list.
		$featured_extensions = array_filter(
			$this->api->get_woocommerce_products( 'extensions' ),
			function( $extension ) {
				return in_array( $extension['slug'], self::FEATURED_EXTENSIONS, true );
			}
		);

		set_transient( 'wpaas_woocommerce_featured_extensions', $featured_extensions, 8 * HOUR_IN_SECONDS );

		return $featured_extensions;

	}

	/**
	 * Register the custom WooCommerce extensions management page
	 *
	 * @action admin_menu
	 *
	 * @return null
	 */
	public function woo_extensions_menu_page() {

		$count_html = WC_Helper_Updater::get_updates_count_html();

		add_submenu_page(
			'woocommerce',
			__( 'WooCommerce extensions', 'woocommerce' ),
			sprintf(
				/* translators: %s: extensions count */
				__( 'Extensions %s', 'woocommerce' ),
				$count_html
			),
			'manage_woocommerce',
			'wc-addons',
			[ $this, 'extension_management' ]
		);

	}

	/**
	 * Markup for the WooCommerce extension management page
	 *
	 * @return mixed Markup for the extensions managemenet page
	 */
	public function extension_management() {

		$tabs = [
			[
				'slug'  => self::SLUG,
				'label' => __( 'Available Extensions', 'gd-system-plugin' ),
			],
			[
				'slug'  => 'browse_extensions',
				'label' => __( 'Browse Extensions', 'woocommerce' ),
			],
			[
				'slug'  => 'subscriptions',
				'label' => sprintf(
					/* translators: %s: WooCommerce.com Subscriptions tab count HTML. */
					__( 'WooCommerce.com Subscriptions %s', 'woocommerce' ),
					WC_Helper_Updater::get_updates_count_html()
				),
			],
		];

		$current_tab = filter_input( INPUT_GET, 'tab', FILTER_SANITIZE_STRING );
		$current_tab = ! $current_tab ? $tabs[0]['slug'] : $current_tab;

		// Fix Browse Extensions tab search and extension categories
		if ( ! is_null( filter_input( INPUT_GET, 'section', FILTER_SANITIZE_STRING ) ) ) {

			$current_tab = 'browse_extensions';

		}

		?>

		<div class="wrap woocommerce wc_addons_wrap wpaas_wc_addons_wrap">

			<nav class="nav-tab-wrapper woo-nav-tab-wrapper wpaas-system-plugin">
			<?php
			foreach ( $tabs as $tab ) {

				printf(
					'<a href="%1$s" class="nav-tab%2$s">%3$s</a>',
					esc_url( admin_url( 'admin.php?page=wc-addons&tab=' . $tab['slug'] ) ),
					( $current_tab === $tab['slug'] ) ? ' nav-tab-active' : '',
					esc_html( $tab['label'] )
				);

			}
			?>
			</nav>

			<h1 class="screen-reader-text"><?php esc_html_e( 'WooCommerce Extensions', 'woocommerce' ); ?></h1>
			<?php

			if ( self::SLUG === $current_tab ) {

				printf(
					'<h2>%1$s</h2>
					<p class="description">%2$s %3$s</p>',
					sprintf(
						/* translators: Integer. WooCommerce extension count. */
						esc_html__( 'Available Extensions (%s)', 'gd-system-plugin' ),
						absint( count( $this->woo_extensions ) )
					),
					esc_html__( 'Below is a list of premium extensions included as part of your Managed WordPress Ecommerce plan.', 'gd-system-plugin' ),
					Plugin::is_reseller() ? '' : sprintf(
						'<a href="https://www.godaddy.com/help/add-extensions-to-ecommerce-hosting-32278" target="_blank">%s <span class="dashicons dashicons-external"></span></a>',
						esc_html__( 'Learn more', 'gd-system-plugin' )
					)
				);

			}

			if ( is_callable( [ $this, "do_section_{$current_tab}" ] ) ) {

				call_user_func( [ $this, "do_section_{$current_tab}" ] );

			}

		?>
		</div>

		<div class="clear"></div>
		<?php

	}

	/**
	 * Render the available extensions tab section
	 *
	 * @return mixed Markup for the available extensions
	 */
	public function do_section_available_extensions() {

		$this->render_featured_woocommerce_extensions();

		$this->render_woocommerce_extensions();

	}

	/**
	 * Render the featured extensions section
	 *
	 * @return Mixed Markup for the featured extensions.
	 */
	private function render_featured_woocommerce_extensions() {

		if ( empty( $this->featured_extensions ) ) {

			return;

		}

		?>
		<div class="addons-banner-block">
			<h1><?php esc_html_e( 'Featured', 'gd-system-plugin' ); ?></h1>
			<p><?php esc_html_e( 'These are the most commonly used premium extensions for stores.', 'gd-system-plugin' ); ?></p>
			<div class="addons-banner-block-items">
			<?php foreach ( $this->featured_extensions as $extension ) : ?>
				<div class="addons-banner-block-item wpaas-wc-addon">
					<div class="addons-banner-block-item-content">
						<h3><?php echo esc_html( $extension['name'] ); ?></h3>
						<p><?php echo esc_html( wp_strip_all_tags( $extension['short_description'] ) ); ?><span class="view-details"><a href="<?php echo esc_url( $extension['homepage'] ); ?>" target="_blank"><?php esc_html_e( 'View details', 'gd-system-plugin' ); ?> <span class="dashicons dashicons-external"></span></a></span></p>
						<div class="wpaas-wc-install-buttons">
							<?php $this->render_extension_actions( $extension['name'], $extension['slug'], $extension['version'], $extension['download_link'] ); ?>
						</div>
					</div>
				</div>
			<?php endforeach; ?>
			</div>
		</div>
		<hr />
		<?php

	}

	/**
	 * Render the available WooCommerce extensions
	 *
	 * @return mixed Markup for the available WooCommerce extensions.
	 */
	private function render_woocommerce_extensions() {

		?>
		<div class="wpaas-wc-addons-list">
		<?php foreach ( $this->woo_extensions as $extension ) : ?>
			<div class="wpaas-wc-addon postbox">
				<h3><?php echo esc_html( $extension['name'] ); ?></h3>
				<p><?php echo esc_html( wp_strip_all_tags( $extension['short_description'] ) ); ?><span class="view-details"><a href="<?php echo esc_url( $extension['homepage'] ); ?>" target="_blank"><?php esc_html_e( 'View details', 'gd-system-plugin' ); ?> <span class="dashicons dashicons-external"></span></a></span></p>
				<div class="wpaas-wc-install-buttons">
					<?php $this->render_extension_actions( $extension['name'], $extension['slug'], $extension['version'], $extension['download_link'] ); ?>
				</div>
			</div>
		<?php endforeach; ?>
		</div>
		<?php

	}

	/**
	 * Render the extension install buttons
	 *
	 * @param  string $extension_name    The extension name.
	 * @param  string $extension_slug    The extension slug.
	 * @param  string $extension_version The current extension version.
	 * @param  string $download_link     The extension download link.
	 *
	 * @return mixed Markup for the extension install buttons.
	 */
	private function render_extension_actions( $extension_name, $extension_slug, $extension_version, $download_link ) {

		$plugin_basename = Plugin::get_woo_extension_basename( $extension_slug );
		$plugin_path     = trailingslashit( WP_PLUGIN_DIR ) . $plugin_basename;
		$is_installed    = file_exists( $plugin_path );
		$is_active       = is_plugin_active( $plugin_basename );

		if ( ! $is_installed ) {

			printf(
				'<a href="#" data-download-link="%1$s" data-slug="%2$s" data-name="%3$s" data-nonce="%4$s" class="js-install-extension addons-button addons-button-solid">%5$s</a>',
				esc_url( $download_link ),
				esc_attr( $extension_slug ),
				esc_attr( $extension_name ),
				wp_create_nonce( 'updates' ),
				esc_html__( 'Install Now', 'gd-system-plugin' )
			);

			return;

		}

		if ( $is_installed && ! $is_active ) {

			printf(
				'<a href="#" data-slug="%1$s" class="js-activate addons-button addons-button-installed">%2$s</a>',
				esc_attr( $extension_slug ),
				esc_html__( 'Activate' )
			);

			return;

		}

		if ( $is_installed && $is_active ) {

			$plugin_info       = get_plugin_data( $plugin_path );
			$installed_version = empty( $plugin_info ) ? null : $plugin_info['Version'];

			if ( version_compare( $installed_version, $extension_version, '<' ) ) {

				printf(
					'<div class="active update-available">
						%1$s &nbsp;|&nbsp; %2$s
					</div>',
					esc_html__( 'Update Available', 'gd-system-plugin' ),
					sprintf(
						'<a href="%1$s">%2$s</a>',
						esc_url( admin_url( 'update-core.php' ) ),
						esc_html__( 'View Updates', 'gd-system-plugin' )
					)
				);

				return;

			}

		}

		echo wp_kses_post( $this->active_plugin_actions() );

	}

	/**
	 * Render the browse extensions section.
	 *
	 * @return mixed Browse extensions markup.
	 */
	public function do_section_browse_extensions() {

		WC_Admin_Addons::output();

	}

	/**
	 * Render the subscriptions section.
	 *
	 * @return mixed Subscriptions markup.
	 */
	public function do_section_subscriptions() {

		WC_Helper::render_helper_output();

	}

	/**
	 * Enqueue the WooCommerce extensions scripts.
	 *
	 * @action admin_enqueue_scripts
	 *
	 * @return null
	 */
	public function enqueue_scripts() {

		$page = filter_input( INPUT_GET, 'page', FILTER_SANITIZE_STRING );

		if ( ! $page || 'wc-addons' !== $page ) {

			return;

		}

		$rtl    = is_rtl() ? '-rtl' : '';
		$suffix = SCRIPT_DEBUG ? '' : '.min';

		wp_enqueue_style( 'woo-extensions-management', Plugin::assets_url( "css/woo-extensions-management{$rtl}{$suffix}.css" ), [], Plugin::version() );

		wp_enqueue_style( 'woocommerce-helper', WC()->plugin_url() . '/assets/css/helper.css', [], WC_VERSION );

		wp_enqueue_script( 'wpaas-woocommerce-extensions', Plugin::assets_url( "js/wpaas-woocommerce-extension{$suffix}.js" ), [ 'jquery' ], Plugin::version(), true );

		wp_localize_script(
			'wpaas-woocommerce-extensions',
			'wpaasWooCommerceExtensions',
			[
				'installingMarkup' => sprintf(
					/* translators: %s is the dashicons-update HTML markup */
					__( '%s Installing', 'gd-system-plugin' ),
					'<span class="dashicons dashicons-update spin"></span>'
				),
				'activatingText'   => __( 'Activating...', 'gd-system-plugin' ),
			]
		);

	}

	/**
	 * Install WooCommerce extension.
	 *
	 * @action wp_ajax_install_woocommerce_extension
	 *
	 * @return bool JSON Error response on error, else JSON success response.
	 */
	public function install_woocommerce_extension() {

		$extension_slug = filter_input( INPUT_POST, 'extensionSlug', FILTER_SANITIZE_STRING );
		$extension_name = filter_input( INPUT_POST, 'extensionName', FILTER_SANITIZE_STRING );
		$download_link  = filter_input( INPUT_POST, 'downloadLink', FILTER_SANITIZE_STRING );

		if ( ! $download_link || ! $extension_name || ! $extension_slug ) {

			wp_send_json_error( [ 'errorMessage' => __( 'Missing WooCommerce extension data.', 'gd-system-plugin' ) ] );

		}

		$download = $this->download_extension( $download_link, $extension_slug );

		if ( is_wp_error( $download ) ) {

			wp_send_json_error(
				[
					'slug'         => $extension_slug,
					'errorMessage' => $download->get_error_message(),
				]
			);

		}

		wp_send_json_success(
			[
				'slug'        => $extension_slug,
				'actionLinks' => sprintf(
					'<a href="#" data-slug="%1$s" class="js-activate addons-button addons-button-installed">%2$s</a>',
					esc_attr( $extension_slug ),
					esc_html__( 'Activate' )
				),
			]
		);

	}

	/**
	 * Download an extension.
	 *
	 * @param string $download_link  URL where the extension can be downloaded from.
	 * @param string $extension_slug Slug of the extension being installed.
	 *
	 * @return bool|WP_Error True when the extension is installed, else WP_Error.
	 */
	private function download_extension( $download_link, $extension_slug ) {

		$download = download_url( $download_link );

		if ( is_wp_error( $download ) ) {

			return $download;

		}

		WP_Filesystem();

		unzip_file( $download, WP_PLUGIN_DIR );

		@unlink( $download );

		$plugin_basename = Plugin::get_woo_extension_basename( $extension_slug );

		return is_readable( trailingslashit( WP_PLUGIN_DIR ) . $plugin_basename );

	}

	/**
	 * Activate a plugin.
	 *
	 * @action wp_ajax_activate_woocommerce_extension
	 *
	 * @return bool JSON Error response on failed activation, else JSON success response.
	 */
	public function activate_woocommerce_extension() {

		$extension_slug = filter_input( INPUT_POST, 'extensionSlug', FILTER_SANITIZE_STRING );

		if ( ! $extension_slug ) {

			wp_send_json_error( [ 'errorMessage' => __( 'Missing the WooCommerce extension slug.', 'gd-system-plugin' ) ] );

		}

		$plugin_basename = Plugin::get_woo_extension_basename( $extension_slug );
		$plugin_header   = get_plugin_data( trailingslashit( WP_PLUGIN_DIR ) . $plugin_basename );
		$cache_plugins   = wp_cache_get( 'plugins', 'plugins' );

		if ( ! empty( $cache_plugins ) && ! empty( $plugin_header ) ) {

			$cache_plugins[''][ $plugin_basename ] = $plugin_header;

			wp_cache_set( 'plugins', $cache_plugins, 'plugins' );

		}

		$activated = activate_plugin( $plugin_basename );

		if ( is_wp_error( $activated ) ) {

			return wp_send_json_error( [ 'errorMessage' => sprintf( __( 'An error occurred during plugin activation: %s', 'gd-system-plugin' ), $activated->get_error_message() ) ] );

		}

		return wp_send_json_success( [ 'activeMarkup' => $this->active_plugin_actions() ] );

	}

	/**
	 * The active plugin action links
	 *
	 * @return mixed Markup for the active plugin action links.
	 */
	private function active_plugin_actions() {

		return sprintf(
			'<div class="active">
			%1$s &nbsp;|&nbsp; <a href="%2$s">%3$s</a>
			</div>',
			wp_kses_post(
				sprintf(
					/* translators: %s is the checkmark HTML markup. */
					__( '%s Active' ),
					'<span class="dashicons dashicons-yes"></span>'
				)
			),
			esc_url( admin_url( 'plugins.php' ) ),
			esc_html__( 'Manage', 'gd-system-plugin' )
		);

	}

}
