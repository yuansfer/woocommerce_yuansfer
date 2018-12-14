/* global wc_yuansfer_params */

jQuery( function( $ ) {
	'use strict';

	var yuansfer = Yuansfer( wc_yuansfer_params.key );

	/**
	 * Object to handle Yuansfer elements payment form.
	 */
	var wc_yuansfer_form = {
		/**
		 * Get WC AJAX endpoint URL.
		 *
		 * @param  {String} endpoint Endpoint.
		 * @return {String}
		 */
		getAjaxURL: function( endpoint ) {
			return wc_yuansfer_params.ajaxurl
				.toString()
				.replace( '%%endpoint%%', 'wc_yuansfer_' + endpoint );
		},

		/**
		 * Initialize event handlers and UI state.
		 */
		init: function() {
			// Initialize tokenization script if on change payment method page and pay for order page.
			if ( 'yes' === wc_yuansfer_params.is_change_payment_page || 'yes' === wc_yuansfer_params.is_pay_for_order_page ) {
				$( document.body ).trigger( 'wc-credit-card-form-init' );
			}


			// pay order page
			if ( $( 'form#order_review' ).length ) {
				this.form = $( 'form#order_review' );
			}

			$( 'form#order_review, form#add_payment_method' )
				.on(
					'submit',
					this.onSubmit
				);

			// add payment method page
			if ( $( 'form#add_payment_method' ).length ) {
				this.form = $( 'form#add_payment_method' );
			}

			$( document )
				.on(
					'yuansferError',
					this.onError
				)
				.on(
					'checkout_error',
					this.reset
				);
		},

		// Check to see if Yuansfer in general is being used for checkout.
		isYuansferChosen: function() {
			return $( '#payment_method_yuansfer, #payment_method_yuansfer_alipay, #payment_method_yuansfer_wechatpay, #payment_method_yuansfer_lakalapay' ).is( ':checked' ) || ( $( '#payment_method_yuansfer' ).is( ':checked' ) && 'new' === $( 'input[name="wc-yuansfer-payment-token"]:checked' ).val() );
		},

		// Currently only support saved cards via credit cards and SEPA. No other payment method.
		isYuansferSaveCardChosen: function() {
			return ( $( '#payment_method_yuansfer' ).is( ':checked' ) && ( $( 'input[name="wc-yuansfer-payment-token"]' ).is( ':checked' ) && 'new' !== $( 'input[name="wc-yuansfer-payment-token"]:checked' ).val() ) );
		},

		// Yuansfer credit card used.
		isYuansferCardChosen: function() {
			return $( '#payment_method_yuansfer' ).is( ':checked' );
		},

		isAlipayChosen: function() {
			return $( '#payment_method_yuansfer_alipay' ).is( ':checked' );
		},

    isWechatPayChosen: function() {
      return $( '#payment_method_yuansfer_wechatpay' ).is( ':checked' );
    },

    isLakalaPayChosen: function() {
      return $( '#payment_method_yuansfer_lakalapay' ).is( ':checked' );
    },

		hasSource: function() {
			return 0 < $( 'input.yuansfer-source' ).length;
		},

		// Legacy
		hasToken: function() {
			return 0 < $( 'input.yuansfer_token' ).length;
		},

		isMobile: function() {
			if( /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent) ) {
				return true;
			}

			return false;
		},

		isYuansferModalNeeded: function( e ) {
			var token = wc_yuansfer_form.form.find( 'input.yuansfer_token' ),
				$required_inputs;

			// If this is a yuansfer submission (after modal) and token exists, allow submit.
			if ( wc_yuansfer_form.yuansfer_submit && token ) {
				return false;
			}

			// Don't affect submission if modal is not needed.
			if ( ! wc_yuansfer_form.isYuansferChosen() ) {
				return false;
			}

			return true;
		},

		block: function() {
			if ( ! wc_yuansfer_form.isMobile() ) {
				wc_yuansfer_form.form.block( {
					message: null,
					overlayCSS: {
						background: '#fff',
						opacity: 0.6
					}
				} );
			}
		},

		unblock: function() {
			wc_yuansfer_form.form.unblock();
		},

		getSelectedPaymentElement: function() {
			return $( '.payment_methods input[name="payment_method"]:checked' );
		},

		getOwnerDetails: function() {
			var first_name = $( '#billing_first_name' ).length ? $( '#billing_first_name' ).val() : wc_yuansfer_params.billing_first_name,
				last_name  = $( '#billing_last_name' ).length ? $( '#billing_last_name' ).val() : wc_yuansfer_params.billing_last_name,
				extra_details = { owner: { name: '', address: {}, email: '', phone: '' } };

			extra_details.owner.name = first_name;

			if ( first_name && last_name ) {
				extra_details.owner.name = first_name + ' ' + last_name;
			} else {
				extra_details.owner.name = $( '#yuansfer-payment-data' ).data( 'full-name' );
			}

			extra_details.owner.email = $( '#billing_email' ).val();
			extra_details.owner.phone = $( '#billing_phone' ).val();

			/* Yuansfer does not like empty string values so
			 * we need to remove the parameter if we're not
			 * passing any value.
			 */
			if ( typeof extra_details.owner.phone !== 'undefined' && 0 >= extra_details.owner.phone.length ) {
				delete extra_details.owner.phone;
			}

			if ( typeof extra_details.owner.email !== 'undefined' && 0 >= extra_details.owner.email.length ) {
				delete extra_details.owner.email;
			}

			if ( typeof extra_details.owner.name !== 'undefined' && 0 >= extra_details.owner.name.length ) {
				delete extra_details.owner.name;
			}

			if ( $( '#billing_address_1' ).length > 0 ) {
				extra_details.owner.address.line1       = $( '#billing_address_1' ).val();
				extra_details.owner.address.line2       = $( '#billing_address_2' ).val();
				extra_details.owner.address.state       = $( '#billing_state' ).val();
				extra_details.owner.address.city        = $( '#billing_city' ).val();
				extra_details.owner.address.postal_code = $( '#billing_postcode' ).val();
				extra_details.owner.address.country     = $( '#billing_country' ).val();
			} else if ( wc_yuansfer_params.billing_address_1 ) {
				extra_details.owner.address.line1       = wc_yuansfer_params.billing_address_1;
				extra_details.owner.address.line2       = wc_yuansfer_params.billing_address_2;
				extra_details.owner.address.state       = wc_yuansfer_params.billing_state;
				extra_details.owner.address.city        = wc_yuansfer_params.billing_city;
				extra_details.owner.address.postal_code = wc_yuansfer_params.billing_postcode;
				extra_details.owner.address.country     = wc_yuansfer_params.billing_country;
			}

			return extra_details;
		},

		createSource: function() {
			var extra_details = wc_yuansfer_form.getOwnerDetails(),
				source_type   = 'unionpay';

			if ( wc_yuansfer_form.isAlipayChosen() ) {
				source_type = 'alipay';
			} else if ( wc_yuansfer_form.isWechatPayChosen() ) {
        source_type = 'wechatpay';
      } else if ( wc_yuansfer_form.isLakalaPayChosen() ) {
        source_type = 'lakalapay';
      }

			// These redirect flow payment methods need this information to be set at source creation.
			extra_details.amount   = $( '#yuansfer-' + source_type + '-payment-data' ).data( 'amount' );
			extra_details.currency = $( '#yuansfer-' + source_type + '-payment-data' ).data( 'currency' );
			extra_details.redirect = { return_url: wc_yuansfer_params.return_url };

			if ( wc_yuansfer_params.statement_descriptor ) {
				extra_details.statement_descriptor = wc_yuansfer_params.statement_descriptor;
			}

			// Handle special inputs that are unique to a payment method.
			extra_details.currency = $( '#yuansfer-' + source_type + '-payment-data' ).data( 'currency' );
			extra_details.amount = $( '#yuansfer-' + source_type + '-payment-data' ).data( 'amount' );

			extra_details.type = source_type;

			yuansfer.createSource( extra_details ).then( wc_yuansfer_form.sourceResponse );
		},

		sourceResponse: function( response ) {
			if ( response.error ) {
				$( document.body ).trigger( 'yuansferError', response );
			} else {
				wc_yuansfer_form.processYuansferResponse( response.source );
			}
		},

		processYuansferResponse: function( source ) {
			wc_yuansfer_form.reset();

			// Insert the Source into the form so it gets submitted to the server.
			wc_yuansfer_form.form.append( "<input type='hidden' class='yuansfer-source' name='yuansfer_source' value='" + source.id + "'/>" );

			if ( $( 'form#add_payment_method' ).length ) {
				$( wc_yuansfer_form.form ).off( 'submit', wc_yuansfer_form.form.onSubmit );
			}

			wc_yuansfer_form.form.submit();
		},

		onSubmit: function( e ) {
			if ( ! wc_yuansfer_form.isYuansferChosen() ) {
				return;
			}

			if ( ! wc_yuansfer_form.isYuansferSaveCardChosen() && ! wc_yuansfer_form.hasSource() && ! wc_yuansfer_form.hasToken() ) {
				e.preventDefault();

				wc_yuansfer_form.block();

				/*
				 * For methods that needs redirect, we will create the
				 * source server side so we can obtain the order ID.
				 */
				if (
					wc_yuansfer_form.isAlipayChosen() ||
          wc_yuansfer_form.isWechatPayChosen()
				) {
					if ( $( 'form#order_review' ).length ) {
						$( 'form#order_review' )
							.off(
								'submit',
								this.onSubmit
							);

						wc_yuansfer_form.form.submit();

						return false;
					}

					if ( $( 'form#add_payment_method' ).length ) {
						$( 'form#add_payment_method' )
							.off(
								'submit',
								this.onSubmit
							);

						wc_yuansfer_form.form.submit();

						return false;
					}
				}

				wc_yuansfer_form.createSource();

				// Prevent form submitting
				return false;
			} else if ( $( 'form#add_payment_method' ).length ) {
				e.preventDefault();

				wc_yuansfer_form.block();

				wc_yuansfer_form.createSource();
				return false;
			}
		},

		reset: function() {
			$( '.wc-yuansfer-error, .yuansfer-source, .yuansfer_token' ).remove();
		},

		onError: function( e, result ) {
			var message = result.error.message,
				errorContainer = wc_yuansfer_form.getSelectedPaymentElement().parents( 'li' ).eq(0).find( '.yuansfer-source-errors' );

			/*
			 * Customers do not need to know the specifics of the below type of errors
			 * therefore return a generic localizable error message.
			 */
			if (
				'invalid_request_error' === result.error.type ||
				'api_connection_error'  === result.error.type ||
				'api_error'             === result.error.type ||
				'authentication_error'  === result.error.type ||
				'rate_limit_error'      === result.error.type
			) {
				message = wc_yuansfer_params.invalid_request_error;
			}

			if ( 'card_error' === result.error.type && wc_yuansfer_params.hasOwnProperty( result.error.code ) ) {
				message = wc_yuansfer_params[ result.error.code ];
			}

			if ( 'validation_error' === result.error.type && wc_yuansfer_params.hasOwnProperty( result.error.code ) ) {
				message = wc_yuansfer_params[ result.error.code ];
			}

			wc_yuansfer_form.reset();
			$( '.woocommerce-NoticeGroup-checkout' ).remove();
			console.log( result.error.message ); // Leave for troubleshooting.
			$( errorContainer ).html( '<ul class="woocommerce_error woocommerce-error wc-yuansfer-error"><li>' + message + '</li></ul>' );

			if ( $( '.wc-yuansfer-error' ).length ) {
				$( 'html, body' ).animate({
					scrollTop: ( $( '.wc-yuansfer-error' ).offset().top - 200 )
				}, 200 );
			}
			wc_yuansfer_form.unblock();
		},

		submitError: function( error_message ) {
			$( '.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message' ).remove();
			wc_yuansfer_form.form.prepend( '<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout">' + error_message + '</div>' );
			wc_yuansfer_form.form.removeClass( 'processing' ).unblock();
			wc_yuansfer_form.form.find( '.input-text, select, input:checkbox' ).blur();
			
			var selector = '';

			if ( $( '#add_payment_method' ).length ) {
				selector = $( '#add_payment_method' );
			}

			if ( $( '#order_review' ).length ) {
				selector = $( '#order_review' );
			}

			if ( $( 'form.checkout' ).length ) {
				selector = $( 'form.checkout' );
			}

			if ( selector.length ) {
				$( 'html, body' ).animate({
					scrollTop: ( selector.offset().top - 100 )
				}, 500 );
			}

			$( document.body ).trigger( 'checkout_error' );
			wc_yuansfer_form.unblock();
		}
	};

	wc_yuansfer_form.init();
} );
