<?php

namespace WPaaS\Admin;

if ( ! defined( 'ABSPATH' ) ) {

	exit;

}

use \WPaaS\Plugin;

final class Block_Editor {

	/**
	 * Class constructor.
	 */
	public function __construct() {

		if ( Plugin::use_simple_ux() ) {

			add_action( 'enqueue_block_editor_assets', [ $this, 'block_editor_overrides' ] );

		}

		if ( Plugin::use_nextgen() ) {

			add_action( 'enqueue_block_editor_assets', [ $this, 'load_nextgen' ] );

		}

	}

	/**
	 * Override block editor defaults.
	 *
	 * @action enqueue_block_editor_assets
	 */
	public function block_editor_overrides() {

		$suffix  = SCRIPT_DEBUG ? '' : '.min';
		$referer = wp_get_referer();

		wp_enqueue_script( 'wpaas-block-editor-defaults', Plugin::assets_url( "js/wpaas-block-editor-defaults{$suffix}.js" ), [ 'wp-blocks' ], Plugin::version(), true );

		wp_localize_script(
			'wpaas-block-editor-defaults',
			'wpaasBlockEditorDefaults',
			[
				'closeLabel'   => esc_attr__( 'Back' ), // Use translation from core.
				'closeReferer' => $referer ? esc_url( $referer ) : 0,
				'userId'       => get_current_user_id(),
			]
		);

	}

	/**
	 * Maybe load NextGen assets in the block editor.
	 *
	 * @action enqueue_block_editor_assets
	 */
	public function load_nextgen() {

		$suffix  = SCRIPT_DEBUG ? '' : '.min';

		wp_enqueue_script( 'wpaas-nextgen', Plugin::assets_url( "js/wpaas-nextgen{$suffix}.js" ), [ 'wp-blocks' ], Plugin::version(), true );

		wp_localize_script(
			'wpaas-nextgen',
			'wpaasNextgen',
			[
				'adminUrl'    => admin_url(),
				'buttonLabel' => __( 'WordPress Dashboard', 'gd-system-plugin' ),
			]
		);

		wp_enqueue_style( 'wpaas-block-editor-ux-core', Plugin::assets_url( "css/admin-editor-ux{$suffix}.css" ), [ 'wp-block-library' ], Plugin::version() );

	}

}
