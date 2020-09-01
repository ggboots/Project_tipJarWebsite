( function( $ ) {

	var wpUserData = JSON.parse( window.localStorage.getItem( 'WP_DATA_USER_' + wpaasBlockEditorDefaults.userId ) );

	$( document ).ready( function() {

		if ( ! wpUserData && ! wp.data.select( 'core/edit-post' ).isFeatureActive( 'fixedToolbar' ) ) {

			wp.data.dispatch( 'core/edit-post' ).toggleFeature( 'fixedToolbar' );

		}

	} );

	$( window ).load( function() {

		if ( '0' === wpaasBlockEditorDefaults.closeReferer ) {

			return;

		}

		// Gutenberg
		$target = $( '.edit-post-header' );

		if ( ! $target.length ) {

			// No Gutenberg
			$target = $( '.edit-post-header__toolbar' );

		}

		if ( ! $target.length ) {

			return;

		}

		// Gutenberg
		$target.find( '.edit-post-fullscreen-mode-close' )
			.attr( 'href', wpaasBlockEditorDefaults.closeReferer )
			.attr( 'aria-label', wpaasBlockEditorDefaults.closeLabel );

		// No Gutenberg
		$target.find( '.edit-post-fullscreen-mode-close__toolbar a' )
			.attr( 'href', wpaasBlockEditorDefaults.closeReferer )
			.attr( 'aria-label', wpaasBlockEditorDefaults.closeLabel );

		observer = new MutationObserver( function( mutationsList, observer ) {

			mutationsList.forEach( function( mutation ) {

				if ( mutation.type === 'childList' && typeof mutation.addedNodes[0] !== 'undefined' && ( $( mutation.addedNodes[0] ).hasClass( 'edit-post-fullscreen-mode-close' ) || $( mutation.addedNodes[0] ).hasClass( 'edit-post-fullscreen-mode-close__toolbar' ) ) ) {

					// Gutenberg
					$( mutation.addedNodes[0] )
						.attr( 'href', wpaasBlockEditorDefaults.closeReferer )
						.attr( 'aria-label', wpaasBlockEditorDefaults.closeLabel );

					// No Gutenberg
					$( mutation.addedNodes[0] )
						.find( 'a' )
						.attr( 'href', wpaasBlockEditorDefaults.closeReferer )
						.attr( 'aria-label', wpaasBlockEditorDefaults.closeLabel );

				}

				if ( mutation.type === 'childList' && ( $( mutation.target ).hasClass( 'edit-post-fullscreen-mode-close' ) || $( mutation.target ).parent().hasClass( 'edit-post-fullscreen-mode-close__toolbar' ) ) && typeof mutation.addedNodes[0] !== 'undefined' && 'SPAN' === mutation.addedNodes[0].nodeName ) {

					$( '.components-tooltip .components-popover__content' ).text( wpaasBlockEditorDefaults.closeLabel );

				}

			} );

		} );

		observer.observe( $target[0], { attributes: false, childList: true, subtree: true } );

	} );

} )( jQuery );
