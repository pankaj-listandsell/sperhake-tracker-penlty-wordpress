/**
 * Sperhake Vehicle Tracker — frontend behaviour.
 *
 * Handles the AJAX vehicle search and the Stripe Checkout redirect.
 */
( function () {
	'use strict';

	var cfg = window.SperhakeTracker || {};

	function ready( fn ) {
		if ( document.readyState !== 'loading' ) {
			fn();
		} else {
			document.addEventListener( 'DOMContentLoaded', fn );
		}
	}

	function postForm( data ) {
		var body = new URLSearchParams();
		Object.keys( data ).forEach( function ( k ) {
			body.append( k, data[ k ] );
		} );

		return fetch( cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString()
		} ).then( function ( res ) {
			return res.json().then( function ( json ) {
				return { ok: res.ok, status: res.status, json: json };
			} );
		} );
	}

	function showError( el, message ) {
		if ( ! el ) {
			return;
		}
		el.textContent = message;
		el.hidden = false;
	}

	function clearError( el ) {
		if ( ! el ) {
			return;
		}
		el.textContent = '';
		el.hidden = true;
	}

	ready( function () {
		var form = document.getElementById( 'sperhake-search-form' );
		if ( ! form ) {
			return;
		}

		var input = document.getElementById( 'sperhake-plate' );
		var refInput = document.getElementById( 'sperhake-reference' );
		var btn = document.getElementById( 'sperhake-search-btn' );
		var results = document.getElementById( 'sperhake-results' );
		var errorBox = document.getElementById( 'sperhake-search-error' );

		function turnstileToken() {
			var field = form.querySelector( '[name="cf-turnstile-response"]' );
			return field ? field.value : '';
		}

		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			clearError( errorBox );
			results.innerHTML = '';

			var plate = ( input.value || '' ).trim();
			if ( ! plate ) {
				showError( errorBox, cfg.i18n.enterPlate );
				input.focus();
				return;
			}

			var reference = refInput ? ( refInput.value || '' ).trim() : '';
			if ( cfg.requireReference && ! reference ) {
				showError( errorBox, cfg.i18n.enterRef );
				if ( refInput ) { refInput.focus(); }
				return;
			}

			var token = turnstileToken();
			if ( cfg.turnstileEnabled && ! token ) {
				showError( errorBox, cfg.i18n.captchaWait );
				return;
			}

			btn.disabled = true;
			btn.textContent = cfg.i18n.searching;

			postForm( {
				action: 'sperhake_search',
				nonce: cfg.nonce,
				license_plate: plate,
				reference: reference,
				cf_turnstile_response: token
			} ).then( function ( resp ) {
				btn.disabled = false;
				btn.textContent = cfg.i18n.search;

				if ( resp.ok && resp.json && resp.json.success ) {
					results.innerHTML = resp.json.data.html;
					bindPayButton();
					bindInvoiceButton();
				} else {
					var msg = ( resp.json && resp.json.data && resp.json.data.message ) || cfg.i18n.genericErr;
					showError( errorBox, msg );
				}
				resetTurnstile();
			} ).catch( function () {
				btn.disabled = false;
				btn.textContent = cfg.i18n.search;
				showError( errorBox, cfg.i18n.genericErr );
				resetTurnstile();
			} );
		} );

		function resetTurnstile() {
			// Turnstile tokens are single-use; reset so the next submit gets a fresh one.
			if ( cfg.turnstileEnabled && window.turnstile && typeof window.turnstile.reset === 'function' ) {
				try { window.turnstile.reset(); } catch ( e ) {}
			}
		}

		function bindInvoiceButton() {
			var invBtn = document.getElementById( 'sperhake-invoice-btn' );
			if ( ! invBtn ) {
				return;
			}

			var msgEl   = document.getElementById( 'sperhake-invoice-msg' );
			var emailEl = document.getElementById( 'sperhake-invoice-email' );

			function showMsg( text, ok ) {
				if ( ! msgEl ) {
					return;
				}
				msgEl.textContent = text;
				msgEl.hidden = false;
				msgEl.className = 'sperhake-invoice__msg ' + ( ok ? 'is-ok' : 'is-error' );
			}

			invBtn.addEventListener( 'click', function () {
				var original = invBtn.textContent;
				invBtn.disabled = true;
				invBtn.textContent = cfg.i18n.requestingInvoice;
				if ( msgEl ) {
					msgEl.hidden = true;
				}

				postForm( {
					action: 'sperhake_request_invoice',
					nonce: invBtn.getAttribute( 'data-nonce' ),
					case_id: invBtn.getAttribute( 'data-case' ),
					email: emailEl ? ( emailEl.value || '' ).trim() : ''
				} ).then( function ( resp ) {
					invBtn.disabled = false;
					invBtn.textContent = original;

					var ok   = resp.ok && resp.json && resp.json.success;
					var text = ( resp.json && resp.json.data && resp.json.data.message ) ||
						( ok ? cfg.i18n.invoiceRequested : cfg.i18n.genericErr );
					showMsg( text, ok );
				} ).catch( function () {
					invBtn.disabled = false;
					invBtn.textContent = original;
					showMsg( cfg.i18n.genericErr, false );
				} );
			} );
		}

		function bindPayButton() {
			var payBtn = document.getElementById( 'sperhake-pay-btn' );
			if ( ! payBtn ) {
				return;
			}

			payBtn.addEventListener( 'click', function () {
				var original = payBtn.textContent;
				payBtn.disabled = true;
				payBtn.textContent = cfg.i18n.redirecting;

				postForm( {
					action: 'sperhake_pay',
					nonce: payBtn.getAttribute( 'data-nonce' ),
					license_plate: payBtn.getAttribute( 'data-plate' ),
					reference: payBtn.getAttribute( 'data-reference' ) || '',
					return_url: window.location.href
				} ).then( function ( resp ) {
					if ( resp.ok && resp.json && resp.json.success && resp.json.data.redirect_url ) {
						window.location.assign( resp.json.data.redirect_url );
					} else {
						var msg = ( resp.json && resp.json.data && resp.json.data.message ) || cfg.i18n.genericErr;
						payBtn.disabled = false;
						payBtn.textContent = original;
						showError( errorBox, msg );
					}
				} ).catch( function () {
					payBtn.disabled = false;
					payBtn.textContent = original;
					showError( errorBox, cfg.i18n.genericErr );
				} );
			} );
		}
	} );
} )();
