<?php
/**
 * Plugin Name: Site Design
 * Plugin URI: https://www.godaddy.com
 * Description: Site Design
 * Author: GoDaddy
 * Author URI: https://www.godaddy.com
 * Version: 1.0.0
 * Text Domain: site-design
 * Domain Path: /languages
 * Tested up to: 5.4
 *
 * Site Design is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * any later version.
 *
 * You should have received a copy of the GNU General Public License
 * along with Site Design. If not, see <http://www.gnu.org/licenses/>.
 *
 * @package Site_Design
 */

defined( 'ABSPATH' ) || exit;

define( 'SITE_DESIGN_VERSION', '1.0.0' );
define( 'SITE_DESIGN_PLUGIN_DIR', dirname( __FILE__ ) );
define( 'SITE_DESIGN_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Enqueue the scripts and styles.
 */
function site_design_register_scripts() {
	$default_asset_file = array(
		'dependencies' => array(),
		'version'      => SITE_DESIGN_VERSION,
	);

	// short-circuit
	$active_theme = wp_get_theme();
	if ( 'Go' !== $active_theme->get( 'Name' ) ) {
		return;
	}

	// Editor Script.
	$asset_filepath = SITE_DESIGN_PLUGIN_DIR . '/build/index.asset.php';
	$asset_file     = file_exists( $asset_filepath ) ? include $asset_filepath : $default_asset_file;

	wp_enqueue_script(
		'site-design',
		SITE_DESIGN_PLUGIN_URL . 'build/index.js',
		$asset_file['dependencies'],
		$asset_file['version'],
		true // Enqueue script in the footer.
	);

	wp_localize_script(
		'site-design',
		'nextgenSiteDesign',
		array(
			'currentDesignStyle' => get_theme_mod( 'design_style', Go\Core\get_default_design_style() ),
			'currentColorScheme' => get_theme_mod( 'color_scheme', Go\Core\get_default_color_scheme() ),
			'currentColors'      => array(
				'primary'    => get_theme_mod( 'primary_color' ),
				'secondary'  => get_theme_mod( 'secondary_color' ),
				'tertiary'   => get_theme_mod( 'tertiary_color' ),
				'background' => get_theme_mod( 'background_color' ),
			)
		)
	);

	// Editor Styles.
	$asset_filepath = SITE_DESIGN_PLUGIN_DIR . '/build/editor.asset.php';
	$asset_file     = file_exists( $asset_filepath ) ? include $asset_filepath : $default_asset_file;

	wp_enqueue_style(
		'site-design-editor',
		SITE_DESIGN_PLUGIN_URL . 'build/editor.css',
		array(),
		$asset_file['version']
	);
}

add_action( 'admin_init', 'site_design_register_scripts' );

/**
 * Retreive the selected design style styles and return them for injection into the DOM
 */
function site_design_update_design_style() {

	$color_string   = '';
	$selected_style = filter_input( INPUT_POST, 'design_style', FILTER_SANITIZE_STRING );
	$color_palette  = filter_input( INPUT_POST, 'color_palette', FILTER_SANITIZE_STRING );
	$should_update  = filter_input( INPUT_POST, 'should_update', FILTER_VALIDATE_BOOLEAN );

	$custom_colors = array(
		'primary_color'    => filter_input( INPUT_POST, 'primary_color', FILTER_SANITIZE_STRING ),
		'secondary_color'  => filter_input( INPUT_POST, 'secondary_color', FILTER_SANITIZE_STRING ),
		'tertiary_color'   => filter_input( INPUT_POST, 'tertiary_color', FILTER_SANITIZE_STRING ),
		'background_color' => filter_input( INPUT_POST, 'background_color', FILTER_SANITIZE_STRING ),
	);

	if ( ! $selected_style ) {

		wp_send_json_error();

	}

	$available_design_styles = Go\Core\get_available_design_styles();

	if ( ! isset( $available_design_styles[ $selected_style ] ) ) {

		wp_send_json_error();

	}

	if ( $should_update ) {

		set_theme_mod( 'design_style', $selected_style );
		set_theme_mod( 'color_scheme', $color_palette );

	}

	foreach ( $custom_colors as $theme_mod => $color ) {

		$theme_mod_string = str_replace( '_color', '', $theme_mod );
		$color            = ! empty( $color ) ? $color : $available_design_styles[ $selected_style ]['color_schemes']['one'][ $theme_mod_string ];

		$color_string .= '--go--color--' . $theme_mod_string . ': ' . $color . ';';

		if ( $should_update ) {

			set_theme_mod( $theme_mod, $color );

		}

	}

	ob_start();
	include_once sprintf( '%1$s/go/%2$s', get_theme_root(), str_replace( '.min', '', $available_design_styles[ $selected_style ]['editor_style'] ) );
	$stylesheet = ob_get_clean();

	$fonts         = array();
	$design_styles = Go\Core\get_available_design_styles();

	foreach ( $design_styles as $design_style => $data ) {

		if ( ! isset( $data['fonts'] ) ) {

			continue;

		}

		foreach ( $data['fonts'] as $font => $font_weights ) {

			$fonts[] = sprintf( '%1$s:%2$s', $font, implode( ',', $font_weights ) );

		}
	}

	$font_styles = file_get_contents(
		esc_url_raw(
			add_query_arg(
				array(
					'family' => rawurlencode( implode( '|', $fonts ) ),
					'subset' => rawurlencode( 'latin,latin-ext' ),
				),
				'https://fonts.googleapis.com/css'
			)
		)
	);

	wp_send_json_success(
		array(
			'stylesheet'   => str_replace( '../../../dist/images/', '/wp-content/themes/go/dist/images/', str_replace( ':root', '.editor-styles-wrapper', $stylesheet ) ),
			'fontStyles'   => $font_styles,
			'customColors' => ":root { {$color_string} }",
		)
	);

	exit;
}

add_action( 'wp_ajax_site_design_update_design_style', 'site_design_update_design_style' );

/**
 * Remove Go theme inline editor styles
 */
add_action(
	'wp_loaded',
	function() {
		remove_action( 'admin_init', 'Go\Core\editor_styles' );
	}
);

/**
 * Add the shared styles to the editor
 */
add_action(
	'admin_init',
	function() {
		$suffix = SCRIPT_DEBUG ? '' : '.min';
		$rtl    = ! is_rtl() ? '' : '-rtl';
		/**
		 * Enqueue shared editor styles.
		 *
		 * add_editor_style() parameter is the path to the stylesheet relative to the active theme root.
		 * https://developer.wordpress.org/reference/functions/add_editor_style/#parameters
		 */
		add_editor_style(
			"dist/css/style-editor{$rtl}{$suffix}.css"
		);
	}
);
