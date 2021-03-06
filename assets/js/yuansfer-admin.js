jQuery( function( $ ) {
	'use strict';

	/**
	 * Object to handle Yuansfer admin functions.
	 */
	var wc_yuansfer_admin = {
		isTestMode: function() {
			return $( '#woocommerce_yuansfer_testmode' ).is( ':checked' );
		},

		getApiToken: function() {
			if ( wc_yuansfer_admin.isTestMode() ) {
				return $( '#woocommerce_yuansfer_test_api_token' ).val();
			} else {
				return $( '#woocommerce_yuansfer_api_token' ).val();
			}
		},

		/**
		 * Initialize.
		 */
		init: function() {
			$( document.body ).on( 'change', '#woocommerce_yuansfer_testmode', function() {
				var test_api_token = $( '#woocommerce_yuansfer_test_api_token' ).parents( 'tr' ).eq( 0 ),
					api_token = $( '#woocommerce_yuansfer_api_token' ).parents( 'tr' ).eq( 0 );

				if ( $( this ).is( ':checked' ) ) {
          test_api_token.show();
          api_token.hide();
				} else {
          test_api_token.hide();
          api_token.show();
				}
			} );

			$( '#woocommerce_yuansfer_testmode' ).change();
		}
	};

	wc_yuansfer_admin.init();
} );
