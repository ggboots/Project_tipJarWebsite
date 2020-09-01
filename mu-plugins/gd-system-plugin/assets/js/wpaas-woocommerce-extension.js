( function( $ ) {

	/**
	 * WooCommerce handlers
	 *
	 * @type {Object}
	 */
	var wpaasWooCommerce = {

		installExtension: function( e ) {

			e.preventDefault();

			if ( $( this ).is( '[disabled=disabled]' ) ) {

				return;

			}

			var $parentRow = $( this ).closest( '.wpaas-wc-addon' );

			$( this ).replaceWith( '<div class="js-installing">' + wpaasWooCommerceExtensions.installingMarkup + '</div>' );

			$.post(
				ajaxurl,
				{
					'action': 'install_woocommerce_extension',
					'extensionSlug': $( this ).data( 'slug' ),
					'extensionName': $( this ).data( 'name' ),
					'downloadLink': $( this ).data( 'download-link' ),
					'_ajax_nonce': $( this ).data( 'nonce' ),
				},
				function( response ) {

					if ( ! response.success ) {

						console.error( response.data );

						$parentRow.find( '.js-installing' ).replaceWith( '<div class="error"><p>' + response.data.errorMessage + '</p></div>' );

						return;

					}

					$parentRow.find( '.js-installing' ).replaceWith( response.data.actionLinks );

				}
			);

		},

		activateExtension: function( e ) {

			e.preventDefault();

			if ( $( this ).is( '[disabled=disabled]' ) ) {

				return;

			}

			var $parentRow = $( this ).closest( '.wpaas-wc-addon' );

			$( this ).text( wpaasWooCommerceExtensions.activatingText ).attr( 'disabled', 'disabled' );

			$.post(
				ajaxurl,
				{
					'action': 'activate_woocommerce_extension',
					'extensionSlug': $( this ).data( 'slug' ),
				},
				function( response ) {

					if ( ! response.success ) {

						console.error( response.data );

						$parentRow.find( '.js-activate' ).replaceWith( '<div class="error"><p>' + response.data.errorMessage + '</p></div>' );

						return;

					}

					$parentRow.find( '.js-activate' ).replaceWith( response.data.activeMarkup );

				}
			);

		},

	};

	$( document ).on( 'click', '.js-install-extension', wpaasWooCommerce.installExtension );

	$( document ).on( 'click', '.js-activate', wpaasWooCommerce.activateExtension );

} )( jQuery );
