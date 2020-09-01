<?php

namespace WPaaS;

if ( ! defined( 'ABSPATH' ) ) {

	exit;

}

final class WooCommerce_Product_Updates {

	/**
	 * API instance
	 *
	 * @var object
	 */
	private $api;

	/**
	 * Premium WooCommerce extension data
	 *
	 * @var array
	 */
	private static $extensions = [];

	/**
	 * Class constructor.
	 */
	public function __construct( \WPaaS\API $api ) {

		if ( ! Plugin::has_plan( 'eCommerce Managed WordPress' ) ) {

			return;

		}

		$this->api = $api;

		$this->extension_updates();
		$this->theme_updates();

	}

	/**
	 * Update WooCommerce extensions
	 */
	public function extension_updates() {

		self::$extensions = $this->api->get_woocommerce_products( 'extensions' );

		if ( ! self::$extensions ) {

			return;

		}

		add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'update_woocommerce_extensions' ], PHP_INT_MAX, 2 );

		add_action( 'admin_print_scripts', [ $this, 'hide_woo_extension_details_links' ], PHP_INT_MAX );

	}

	/**
	 * Update WooCommerce themes
	 */
	public function theme_updates() {

		add_filter( 'pre_set_site_transient_update_themes', [ $this, 'update_woocommerce_themes' ] );

	}

	/**
	 * Intercept the transient that holds available plugin updates.
	 *
	 * @add_filter pre_set_site_transient_update_plugins
	 *
	 * @param stdClass $value
	 * @param string   $transient
	 */
	public function update_woocommerce_extensions( $value, $transient ) {

		if ( ! is_a( $value, 'stdClass' ) || ! property_exists( $value, 'checked' ) || ! is_array( $value->checked ) ) {

			return $value;

		}

		$installed_extensions = $this->get_installed_extensions();

		if ( ! $installed_extensions ) {

			return $value;

		}

		static $extension_data;

		if ( ! $extension_data ) {

			// Ensure data is only fetched once per page load
			$extension_data = $this->get_extension_data( $installed_extensions );

		}

		foreach ( $extension_data as $data ) {

			$extension_path = Plugin::get_woo_extension_basename( $data['slug'] );

			if ( version_compare( $data['version'], $value->checked[ $extension_path ], '>' ) ) {

				$value->response[ $extension_path ] = $this->format_extension_data( $data );

			}

		}

		return $value;

	}

	/**
	 * Set the transient when updating the WooCommerce themes
	 */
	public function update_woocommerce_themes( $transient ) {

		$installed_woo_themes = $this->get_installed_woo_themes();

		if ( empty( $installed_woo_themes ) ) {

			return $transient;

		}

		foreach ( $installed_woo_themes as $woo_theme ) {

			if ( empty( $transient->checked[ $woo_theme['slug'] ] ) ) {

				return $transient;

			}

			if ( version_compare( $transient->checked[ $woo_theme['slug'] ], $woo_theme['version'], '<' ) ) {

				$woo_theme['new_version'] = $woo_theme['version'];
				$woo_theme['url']         = $woo_theme['homepage'];
				$woo_theme['package']     = $woo_theme['download_link'];

				$transient->response[ $woo_theme['slug'] ] = $woo_theme;

			}

		}

		return $transient;

	}

	/**
	 * Retreive all installed WooCommerce themes.
	 *
	 * @return array Array of installed WooCommerce themes.
	 */
	private function get_installed_woo_themes() {

		$installed_themes     = wp_get_themes();
		$woo_themes           = $this->api->get_woocommerce_products( 'themes' );
		$installed_woo_themes = [];

		foreach ( $woo_themes as $woo_theme ) {

			if ( ! array_key_exists( $woo_theme['slug'], $installed_themes ) ) {

				continue;

			}

			$installed_woo_themes[] = $woo_theme;

		}

		return $installed_woo_themes;

	}

	/**
	 * Hide the WooCommerce extension view details links
	 */
	public function hide_woo_extension_details_links() {

		global $pagenow;

		if ( ! in_array( $pagenow, [ 'update-core.php', 'plugins.php' ], true ) ) {

			return;

		}

		$installed_extensions = $this->get_installed_extensions();

		switch ( $pagenow ) {

			case 'update-core.php':

				$styles = '';

				foreach ( $installed_extensions as $extension_path ) {

					$extension_slug = dirname( $extension_path );

					$styles .= "a[href*='{$extension_slug}']{display: none;}";

				}

				if ( ! $styles ) {

					break;

				}

				?>
				<style type="text/css">
				<?php echo wp_kses_post( $styles ); ?>
				</style>
				<?php

				break;

			case 'plugins.php':

				?>
				<script type="text/javascript">
				jQuery( document ).ready( function() {
				<?php foreach ( $installed_extensions as $extension_path ) : ?>
					var html = jQuery( 'tr.plugin-update-tr[data-plugin="<?php echo esc_attr( $extension_path ); ?>"]' ).html();
					if ( undefined === html ) {
						return;
					}
					jQuery( 'tr.plugin-update-tr[data-plugin="<?php echo esc_attr( $extension_path ); ?>"]' ).html( html.replace( /(<\/a>\s*)([\s\S]*?)(\s*<a\s)/, "$1<span class='or'>$2</span>$3" ) );
					var updateText = jQuery( 'tr.plugin-update-tr[data-plugin="<?php echo esc_attr( $extension_path ); ?>"] .update-link' ).text();
					jQuery( 'tr.plugin-update-tr[data-plugin="<?php echo esc_attr( $extension_path ); ?>"] .update-link' ).text( updateText.charAt(0).toUpperCase() + updateText.slice(1) );
				<?php endforeach; ?>
				} );
				</script>

				<style type="text/css">
				<?php foreach ( $installed_extensions as $extension_path ) : ?>
					tr.plugin-update-tr[data-plugin="<?php echo esc_attr( $extension_path ); ?>"] p *:not(.update-link) {
						display: none;
					}
				<?php endforeach; ?>
				</style>
				<?php

				break;

		}

	}

	/**
	 * Retrieve the installed WooCommerce extensions
	 *
	 * @return array Installed WooCommerce extension paths.
	 */
	private function get_installed_extensions() {

		return array_intersect(
			get_option( 'active_plugins' ),
			array_map(
				function( $extension_slug ) {
					return Plugin::get_woo_extension_basename( $extension_slug );
				},
				wp_list_pluck( self::$extensions, 'slug' )
			)
		);

	}

	/**
	 * Return an array of installed WooCommerce extension data retreived from the
	 * WooCommerce API.
	 *
	 * @param  array $installed_extensions Installed WooCommerce extensions
	 *
	 * @return array Installed WooCommerce extension data.
	 */
	private function get_extension_data( array $installed_extensions ) {

		$installed_extension_data = array_map(
			function( $extension_path ) {
				$extension_slug = dirname( $extension_path );
				$key            = array_search( $extension_slug, array_column( self::$extensions, 'slug' ), true );
				if ( false === $key || ! array_key_exists( $key, self::$extensions ) ) {
					return [];
				}
				return self::$extensions[ $key ];
			},
			$installed_extensions
		);

		return array_values( $installed_extension_data );

	}

	/**
	 * Reformat the WooCommerce API response to match what core expects
	 *
	 * @param  array $extension_data Installed WooCommerce extension data.
	 *
	 * @return array Reformatted extension data.
	 */
	private function format_extension_data( $extension_data ) {

		$wp_core_version = get_bloginfo( 'version' );

		$formatted_extension_data = new \stdClasS();

		$formatted_extension_data->id            = 'w.org/plugins/' . $extension_data['slug'];
		$formatted_extension_data->slug          = $extension_data['slug'];
		$formatted_extension_data->plugin        = Plugin::get_woo_extension_basename( $extension_data['slug'] );
		$formatted_extension_data->new_version   = $extension_data['version'];
		$formatted_extension_data->url           = $extension_data['homepage'];
		$formatted_extension_data->package       = $extension_data['download_link'];
		$formatted_extension_data->icons         = $extension_data['icons'];
		$formatted_extension_data->banners       = [];
		$formatted_extension_data->banners_rtl   = [];
		$formatted_extension_data->tested        = substr( $wp_core_version, 0, strpos( $wp_core_version, '-beta' ) );
		$formatted_extension_data->requires_php  = '';
		$formatted_extension_data->compatibility = new \stdClass();

		return $formatted_extension_data;

	}

}
