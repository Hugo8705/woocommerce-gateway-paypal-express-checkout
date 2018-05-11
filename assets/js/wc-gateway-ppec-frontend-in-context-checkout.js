;(function ( $, window, document ) {
	'use strict';

	var $wc_ppec = {
		init: function() {
			window.paypalCheckoutReady = function() {
				paypal.Button.render( {
					env: wc_ppec_context.env === 'live' ? 'production' : 'sandbox',
					locale: wc_ppec_context.locale,
					commit: true, // Show a 'Pay Now' button

					funding: {
						allowed: wc_ppec_context.paypal_credit ? [ paypal.FUNDING.CREDIT ] : [],
						disallowed: [ paypal.FUNDING.CARD, paypal.FUNDING.ELV ],
					},

					style: {
						tagline: false,
						size: wc_ppec_context.button_size,
						layout: wc_ppec_context.button_layout,
						color: wc_ppec_context.button_color,
						shape: wc_ppec_context.button_shape,
						label: wc_ppec_context.button_label,
					},

					payment: function( data, actions ) {
						return paypal.request( {
							method: 'post',
							url: wc_ppec_context.start_checkout_url,
							data: { 'nonce': wc_ppec_context.start_checkout_nonce },
						} ).then( function( data ) {
							return data.token;
						} );
					},

					onAuthorize: function( data, actions ) {
						return actions.redirect();
					},

					onCancel: function( data, actions ) {
						return actions.redirect();
					},

				}, '#woo_pp_ec_button' );
			}
		}
	}

	var costs_updated = false;

	$( '#woo_pp_ec_button' ).click( function( event ) {
		if ( costs_updated ) {
			costs_updated = false;

			return;
		}

		event.stopPropagation();

		var data = {
			'nonce':      wc_ppec_context.update_shipping_costs_nonce,
		};

		var href = $(this).attr( 'href' );

		$.ajax( {
			type:    'POST',
			data:    data,
			url:     wc_ppec_context.ajaxurl,
			success: function( response ) {
				costs_updated = true;
				$( '#woo_pp_ec_button' ).click();
			}
		} );
	} );

	$wc_ppec.init();
})( jQuery, window, document );
