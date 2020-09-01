<?php
/**
 * Plugin Name: Content Management
 * Plugin URI: https://www.godaddy.com
 * Description: Content management
 * Author: GoDaddy
 * Author URI: https://www.godaddy.com
 * Version: 1.0.0
 * Text Domain: content-management
 * Domain Path: /languages
 * Tested up to: 5.4
 *
 * Content Management is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * any later version.
 *
 * You should have received a copy of the GNU General Public License
 * along with Content Management. If not, see <http://www.gnu.org/licenses/>.
 *
 * @package Content_Management
 */

defined( 'ABSPATH' ) || exit;

define( 'CONTENT_MANAGEMENT_VERSION', '1.0.0' );
define( 'CONTENT_MANAGEMENT_PLUGIN_DIR', dirname( __FILE__ ) );
define( 'CONTENT_MANAGEMENT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Enqueue the scripts and styles.
 */
function content_management_register_scripts() {
	$default_asset_file = array(
		'dependencies' => array(),
		'version'      => CONTENT_MANAGEMENT_VERSION,
	);

	// Editor Script.
	$asset_filepath = CONTENT_MANAGEMENT_PLUGIN_DIR . '/build/index.asset.php';
	$asset_file     = file_exists( $asset_filepath ) ? include $asset_filepath : $default_asset_file;

	wp_enqueue_script(
		'content-management',
		CONTENT_MANAGEMENT_PLUGIN_URL . 'build/index.js',
		$asset_file['dependencies'],
		$asset_file['version'],
		true // Enqueue script in the footer.
	);

	wp_localize_script(
		'content-management',
		'jsPageNav',
		array(
			'postTypes'    => (array) content_management_get_page_nav_post_types(),
			'preloaderUrl' => admin_url( 'images/spinner-2x.gif' ),
		)
	);

	// Editor Styles.
	$asset_filepath = CONTENT_MANAGEMENT_PLUGIN_DIR . '/build/style.asset.php';
	$asset_file     = file_exists( $asset_filepath ) ? include $asset_filepath : $default_asset_file;

	wp_enqueue_style(
		'content-management-style',
		CONTENT_MANAGEMENT_PLUGIN_URL . 'build/style.css',
		array(),
		$asset_file['version']
	);
}

add_action( 'admin_init', 'content_management_register_scripts' );


/**
 * Retreive the available post types
 */
function content_management_get_page_nav_post_types() {

	$post_types = get_post_types();
	$white_list = array(
		'page' => 'pages',
		// 'post'    => 'posts',
		// 'product' => 'products',
	);

	foreach ( $post_types as $post_type_slug ) {

		if ( ! array_key_exists( $post_type_slug, $white_list ) ) {
			unset( $post_types[ $post_type_slug ] );
			continue;
		}

		$post_type_obj = get_post_type_object( $post_type_slug );

		$post_types[ $white_list[ $post_type_slug ] ] = $post_type_obj->label;
		unset( $post_types[ $post_type_slug ] );
	}

	ksort( $post_types );

	return $post_types;
}
