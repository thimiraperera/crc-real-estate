/**
 * Inquiry forms: checks each field while people type, keeps the phone
 * number's country code in view, loads hCaptcha when the form comes near,
 * and sends the inquiry without leaving the page.
 */
( function () {
	'use strict';

	var config = window.crcReInquiry || {};
	var countries = config.countries || {};
	var messages = config.messages || {};
	var captcha = config.captcha || null;
	var KEYS = [ 'first_name', 'last_name', 'phone', 'email', 'message' ];
	var EMAIL = /^[A-Za-z0-9.!#$%&'*+\/=?^_`{|}~-]+@[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?(?:\.[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?)+$/;
	var BAD_ENDINGS = [ '.con', '.cmo', '.cpm', '.vom', '.xom', '.comm', '.coom' ];
	var NUMBER_GAP = 12; // Room between the line after the country code and the number.
	var captchaState = 'idle';
	var waiting = [];
	var nameRule = null;

	// Letters of any language. Older browsers can't check this; the site does.
	try {
		nameRule = new RegExp( '^[\\p{L}\\p{M}][\\p{L}\\p{M}\\u200C\\u200D\'\\u2019. \\-]*$', 'u' );
	} catch ( error ) {
		nameRule = null;
	}

	// Fills in %s, %1$s, %2$s… like the site does.
	function format( text ) {
		var values = Array.prototype.slice.call( arguments, 1 );
		var next = 0;

		return String( text || '' ).replace( /%(?:(\d+)\$)?s/g, function ( match, position ) {
			var value = position ? values[ position - 1 ] : values[ next++ ];

			return undefined === value ? '' : String( value );
		} );
	}

	function country( code ) {
		var details = countries[ code ];

		if ( ! details ) {
			return null;
		}

		return {
			code: code,
			name: details[ 0 ],
			dial: String( details[ 1 ] ),
			min: details[ 2 ],
			max: details[ 3 ],
			pattern: details[ 4 ],
			zero: !! details[ 5 ]
		};
	}

	// Whether the digits after the country code fit the country's numbers.
	function fits( details, digits ) {
		if ( ! /^\d+$/.test( digits ) || digits.length < details.min || digits.length > details.max ) {
			return false;
		}

		if ( ! details.pattern ) {
			return true;
		}

		try {
			return new RegExp( '^(?:' + details.pattern + ')$' ).test( digits );
		} catch ( error ) {
			return true;
		}
	}

	// A number typed the way it's dialled at home: the leading 0 (1 in
	// North America, 8 in Russia) goes, and so does a country code typed
	// without "+".
	function national( details, digits ) {
		var home = digits;

		if ( ! details.zero && '0' === digits.charAt( 0 ) ) {
			home = digits.slice( 1 );
		} else if ( 11 === digits.length && ( ( '1' === details.dial && '1' === digits.charAt( 0 ) ) || ( '7' === details.dial && '8' === digits.charAt( 0 ) ) ) ) {
			home = digits.slice( 1 );
		}

		if ( ! fits( details, home ) && 0 === digits.indexOf( details.dial ) && fits( details, digits.slice( details.dial.length ) ) ) {
			return digits.slice( details.dial.length );
		}

		return home;
	}

	// The country of a number typed with "+": the longest calling code it
	// starts with. With a code several countries share, the chosen one if
	// it's one of them, or the main one.
	function countryFor( digits, chosen ) {
		var main = config.main || {};
		var length;
		var dial;
		var code;

		for ( length = 3; length >= 1; length-- ) {
			dial = digits.slice( 0, length );

			if ( countries[ chosen ] && String( countries[ chosen ][ 1 ] ) === dial ) {
				return chosen;
			}

			if ( main[ dial ] && countries[ main[ dial ] ] ) {
				return main[ dial ];
			}

			for ( code in countries ) {
				if ( Object.prototype.hasOwnProperty.call( countries, code ) && String( countries[ code ][ 1 ] ) === dial ) {
					return code;
				}
			}
		}

		return '';
	}

	// Reads a phone number the same way the site does.
	function parsePhone( code, typed ) {
		var value = String( typed || '' ).replace( / /g, ' ' ).trim();
		var digits;
		var found;
		var rest;
		var details;

		if ( '' === value ) {
			return { error: 'empty', country: code };
		}

		if ( ! country( code ) || ! /^\+?[\d\s().\/-]+$/.test( value ) ) {
			return { error: 'invalid', country: code };
		}

		digits = value.replace( /\D/g, '' );

		if ( '+' === value.charAt( 0 ) || 0 === digits.indexOf( '00' ) ) {
			digits = '+' === value.charAt( 0 ) ? digits : digits.slice( 2 );
			found = countryFor( digits, code );

			if ( ! found ) {
				return { error: 'invalid', country: code };
			}

			code = found;
			rest = digits.slice( country( code ).dial.length );

			// "+94 (0)77…" or "+94 077…": the 0 dialled at home doesn't belong after the country code.
			if ( ! country( code ).zero && '0' === rest.charAt( 0 ) && ! fits( country( code ), rest ) ) {
				rest = rest.slice( 1 );
			}
		} else {
			rest = national( country( code ), digits );
		}

		details = country( code );

		if ( ! fits( details, rest ) ) {
			return { error: 'invalid', country: code };
		}

		return { number: '+' + details.dial + rest, country: code };
	}

	// How many single-letter changes turn one text into another; swapping
	// two letters counts as one.
	function distance( a, b ) {
		var rows = [];
		var i;
		var j;

		for ( i = 0; i <= a.length; i++ ) {
			rows[ i ] = [ i ];
		}

		for ( j = 1; j <= b.length; j++ ) {
			rows[ 0 ][ j ] = j;
		}

		for ( i = 1; i <= a.length; i++ ) {
			for ( j = 1; j <= b.length; j++ ) {
				rows[ i ][ j ] = Math.min(
					rows[ i - 1 ][ j ] + 1,
					rows[ i ][ j - 1 ] + 1,
					rows[ i - 1 ][ j - 1 ] + ( a.charAt( i - 1 ) === b.charAt( j - 1 ) ? 0 : 1 )
				);

				if ( i > 1 && j > 1 && a.charAt( i - 1 ) === b.charAt( j - 2 ) && a.charAt( i - 2 ) === b.charAt( j - 1 ) ) {
					rows[ i ][ j ] = Math.min( rows[ i ][ j ], rows[ i - 2 ][ j - 2 ] + 1 );
				}
			}
		}

		return rows[ a.length ][ b.length ];
	}

	// A likely fix for a typing mistake in a popular email service, such as
	// gmial.com or gmail.con; an empty string when there's none.
	function suggestion( email ) {
		var at = email.lastIndexOf( '@' );
		var domains = config.domains || [];
		var domain;
		var best = '';
		var i;

		if ( at < 1 ) {
			return '';
		}

		domain = email.slice( at + 1 ).toLowerCase();

		if ( -1 !== domains.indexOf( domain ) ) {
			return '';
		}

		for ( i = 0; i < domains.length; i++ ) {
			if ( 1 === distance( domain, domains[ i ] ) ) {
				best = domains[ i ];
				break;
			}
		}

		for ( i = 0; ! best && i < BAD_ENDINGS.length; i++ ) {
			if ( domain.length > BAD_ENDINGS[ i ].length && domain.slice( -BAD_ENDINGS[ i ].length ) === BAD_ENDINGS[ i ] ) {
				best = domain.slice( 0, -BAD_ENDINGS[ i ].length ) + '.com';
			}
		}

		return best ? email.slice( 0, at + 1 ) + best : '';
	}

	function fieldOf( form, key ) {
		return form.querySelector( '[data-crc-field="' + key + '"]' );
	}

	function controlOf( wrap ) {
		return wrap ? wrap.querySelector( '.crc-inquiry-input' ) : null;
	}

	function phoneMessage( code ) {
		var details = country( code );
		var example = ( config.examples || {} )[ code ];

		if ( ! details ) {
			return messages.phone;
		}

		return example ? format( messages.phone_example, details.name, details.dial, example ) : format( messages.phone_invalid, details.name, details.dial );
	}

	function emailError( value ) {
		if ( '' === value ) {
			return messages.email;
		}

		return value.length < 6 || ! EMAIL.test( value ) ? messages.email_invalid : '';
	}

	// What's wrong with a field, or an empty string when it's all right.
	function errorFor( form, key ) {
		var control = controlOf( fieldOf( form, key ) );
		var select;
		var value;
		var phone;

		if ( ! control ) {
			return '';
		}

		value = control.value.trim();

		if ( 'first_name' === key || 'last_name' === key ) {
			if ( '' === value ) {
				return 'first_name' === key ? messages.first_name : '';
			}

			return nameRule && ! nameRule.test( value ) ? messages[ 'first_name' === key ? 'first_letters' : 'last_letters' ] : '';
		}

		if ( 'phone' === key ) {
			select = form.querySelector( '.crc-inquiry-country-select' );
			phone = parsePhone( select ? select.value : '', value );

			if ( ! phone.error ) {
				return '';
			}

			return 'empty' === phone.error ? messages.phone : phoneMessage( phone.country );
		}

		return 'email' === key ? emailError( value ) : '';
	}

	function showError( wrap, message ) {
		var error;
		var control;

		if ( ! wrap ) {
			return;
		}

		error = wrap.querySelector( '.crc-inquiry-error' );
		control = controlOf( wrap );
		wrap.classList.toggle( 'is-invalid', !! message );

		if ( control ) {
			if ( message ) {
				control.setAttribute( 'aria-invalid', 'true' );
			} else {
				control.removeAttribute( 'aria-invalid' );
			}
		}

		if ( error ) {
			error.textContent = message || '';
			error.hidden = ! message;
		}
	}

	function validate( form, key ) {
		var wrap = fieldOf( form, key );
		var message;

		if ( ! wrap ) {
			return true;
		}

		message = errorFor( form, key );
		showError( wrap, message );

		return ! message;
	}

	function focusField( wrap ) {
		var control = controlOf( wrap );

		if ( control ) {
			control.focus();
		} else if ( wrap.scrollIntoView ) {
			wrap.scrollIntoView( { block: 'center' } );
		}
	}

	// The country code sits at the start of the phone box. It takes the text
	// style of the site's fields (read from the E-Mail box, which the form
	// leaves as it is), and the number starts just after it.
	function fitCountry( form ) {
		var country = form.querySelector( '.crc-inquiry-country' );
		var number = controlOf( fieldOf( form, 'phone' ) );
		var model = controlOf( fieldOf( form, 'email' ) );
		var look;

		if ( ! country || ! number || ! model || ! window.getComputedStyle || ! number.offsetWidth ) {
			return;
		}

		look = window.getComputedStyle( model );
		country.style.paddingLeft = ( ( parseFloat( look.paddingLeft ) || 0 ) + ( parseFloat( look.borderLeftWidth ) || 0 ) ) + 'px';
		country.style.color = look.color;
		country.style.fontFamily = look.fontFamily;
		country.style.fontSize = look.fontSize;
		country.style.fontWeight = look.fontWeight;
		country.style.letterSpacing = look.letterSpacing;
		number.style.paddingLeft = Math.ceil( country.getBoundingClientRect().width + NUMBER_GAP - ( parseFloat( window.getComputedStyle( number ).borderLeftWidth ) || 0 ) ) + 'px';
	}

	// Shows the chosen country's flag and code, and its example number in the empty box.
	function showCode( form ) {
		var select = form.querySelector( '.crc-inquiry-country-select' );
		var flag = form.querySelector( '.crc-inquiry-country-flag' );
		var code = form.querySelector( '.crc-inquiry-country-code' );
		var number = controlOf( fieldOf( form, 'phone' ) );
		var details = select ? country( select.value ) : null;
		var examples = config.placeholders || {};
		var example;
		var src;

		if ( flag && details && config.flags ) {
			src = config.flags + details.code.toLowerCase() + '.svg';

			if ( flag.getAttribute( 'src' ) !== src ) {
				flag.setAttribute( 'src', src );
			}
		}

		if ( code && details ) {
			code.textContent = '+' + details.dial;
		}

		if ( number && details && examples.phone ) {
			example = ( config.examples || {} )[ details.code ];
			number.placeholder = example ? format( examples.phone, example ) : examples.phone_other;
		}

		fitCountry( form );
	}

	// A number typed with "+" (or 00) chooses its own country.
	function detectCountry( form ) {
		var select = form.querySelector( '.crc-inquiry-country-select' );
		var control = controlOf( fieldOf( form, 'phone' ) );
		var value = control ? control.value.trim() : '';
		var digits = value.replace( /\D/g, '' );
		var found;

		if ( ! select || ( '+' !== value.charAt( 0 ) && 0 !== digits.indexOf( '00' ) ) ) {
			return;
		}

		digits = '+' === value.charAt( 0 ) ? digits : digits.slice( 2 );
		found = digits ? countryFor( digits, select.value ) : '';

		if ( found && found !== select.value ) {
			select.value = found;
			showCode( form );
		}
	}

	function hideHint( form ) {
		var hint = form.querySelector( '.crc-inquiry-hint' );

		if ( hint ) {
			hint.hidden = true;
			hint.textContent = '';
			hint.crcFor = '';
		}
	}

	// "Did you mean name@gmail.com?" under the email, which fixes it when tapped.
	function suggest( form ) {
		var wrap = fieldOf( form, 'email' );
		var control = controlOf( wrap );
		var hint = wrap ? wrap.querySelector( '.crc-inquiry-hint' ) : null;
		var value = control ? control.value.trim() : '';
		var better = emailError( value ) ? '' : suggestion( value );
		var parts;
		var button;

		if ( ! hint ) {
			return;
		}

		if ( ! better ) {
			hideHint( form );
			return;
		}

		// Already showing it: rebuilding it now would swallow a tap on it.
		if ( better === hint.crcFor && ! hint.hidden ) {
			return;
		}

		parts = String( messages.email_suggest || '%s' ).split( '%s' );
		button = document.createElement( 'button' );
		button.type = 'button';
		button.className = 'crc-inquiry-hint-button';
		button.textContent = better;
		button.addEventListener( 'click', function () {
			control.value = better;
			hideHint( form );
			validate( form, 'email' );
			control.focus();
		} );

		hint.textContent = '';
		hint.appendChild( document.createTextNode( parts[ 0 ] ) );
		hint.appendChild( button );
		hint.appendChild( document.createTextNode( parts.slice( 1 ).join( '%s' ) ) );
		hint.crcFor = better;
		hint.hidden = false;
	}

	function showStatus( form, type, message ) {
		var box = form.querySelector( '.crc-inquiry-status' );

		if ( box ) {
			box.className = 'crc-inquiry-status is-' + type;
			box.textContent = message;
			box.hidden = false;
		}
	}

	function hideStatus( form ) {
		var box = form.querySelector( '.crc-inquiry-status' );

		if ( box ) {
			box.hidden = true;
			box.textContent = '';
		}
	}

	function sending( form, on ) {
		var button = form.querySelector( '.crc-inquiry-button' );
		var label = button ? button.querySelector( '.elementor-button-text' ) : null;

		form.crcSending = on;
		form.classList.toggle( 'is-sending', on );

		if ( button ) {
			button.setAttribute( 'aria-busy', on ? 'true' : 'false' );
		}

		if ( label && on ) {
			label.crcText = label.textContent;
			label.textContent = messages.sending || label.textContent;
		} else if ( label && label.crcText ) {
			label.textContent = label.crcText;
		}
	}

	function renderCaptcha( form ) {
		var box = form.querySelector( '.crc-inquiry-captcha' );
		var wrap = fieldOf( form, 'captcha' );

		if ( ! box || undefined !== box.crcWidget || ! window.hcaptcha ) {
			return;
		}

		try {
			box.crcWidget = window.hcaptcha.render( box, {
				sitekey: captcha.sitekey,
				// The small box on narrow screens, where the wide one doesn't fit.
				size: box.offsetWidth && box.offsetWidth < 303 ? 'compact' : 'normal',
				callback: function () {
					showError( wrap, '' );
				}
			} );
		} catch ( error ) {
			showError( wrap, messages.captcha_load );
		}
	}

	// Loads hCaptcha once, the first time a form needs it.
	function loadCaptcha( form ) {
		var script;

		if ( window.hcaptcha && window.hcaptcha.render ) {
			captchaState = 'ready';
		}

		if ( 'ready' === captchaState ) {
			renderCaptcha( form );
			return;
		}

		if ( -1 === waiting.indexOf( form ) ) {
			waiting.push( form );
		}

		if ( 'idle' !== captchaState ) {
			return;
		}

		captchaState = 'loading';

		window.crcReInquiryCaptcha = function () {
			captchaState = 'ready';
			waiting.splice( 0 ).forEach( renderCaptcha );
		};

		script = document.createElement( 'script' );
		script.src = captcha.script;
		script.async = true;
		script.onerror = function () {
			captchaState = 'failed';
			waiting.splice( 0 ).forEach( function ( waitingForm ) {
				showError( fieldOf( waitingForm, 'captcha' ), messages.captcha_load );
			} );
		};
		document.head.appendChild( script );
	}

	// hCaptcha loads when the form comes near the screen, or is used.
	function watchCaptcha( form ) {
		var observer = null;

		if ( ! captcha || ! form.querySelector( '.crc-inquiry-captcha' ) ) {
			return;
		}

		function start() {
			if ( observer ) {
				observer.disconnect();
				observer = null;
			}

			loadCaptcha( form );
		}

		if ( 'IntersectionObserver' in window ) {
			observer = new window.IntersectionObserver( function ( entries ) {
				var i;

				for ( i = 0; i < entries.length; i++ ) {
					if ( entries[ i ].isIntersecting ) {
						start();
						return;
					}
				}
			}, { rootMargin: '300px 0px' } );
			observer.observe( form );
		} else {
			start();
		}

		form.addEventListener( 'focusin', start );
	}

	function captchaToken( form ) {
		var box = form.querySelector( '.crc-inquiry-captcha' );

		if ( ! box || undefined === box.crcWidget || ! window.hcaptcha ) {
			return '';
		}

		try {
			return window.hcaptcha.getResponse( box.crcWidget ) || '';
		} catch ( error ) {
			return '';
		}
	}

	// Each answer from the box works once, so it's cleared after sending.
	function resetCaptcha( form ) {
		var box = form.querySelector( '.crc-inquiry-captcha' );

		if ( box && undefined !== box.crcWidget && window.hcaptcha ) {
			try {
				window.hcaptcha.reset( box.crcWidget );
			} catch ( error ) {
				// The box is gone: nothing to clear.
			}
		}
	}

	function done( form, result ) {
		var fields = result.fields || {};
		var first = null;
		var key;
		var wrap;

		if ( result.success ) {
			form.reset();
			KEYS.forEach( function ( name ) {
				var field = fieldOf( form, name );

				if ( field ) {
					field.crcTouched = false;
					showError( field, '' );
				}
			} );
			hideHint( form );
			showCode( form );
			showStatus( form, 'success', result.message || messages.sent_plain );
			return;
		}

		for ( key in fields ) {
			if ( Object.prototype.hasOwnProperty.call( fields, key ) ) {
				wrap = fieldOf( form, key );

				if ( wrap ) {
					showError( wrap, fields[ key ] );
					first = first || wrap;
				}
			}
		}

		if ( result.message ) {
			showStatus( form, 'error', result.message );
		} else if ( ! first ) {
			showStatus( form, 'error', messages.failed );
		}

		if ( first ) {
			focusField( first );
		}
	}

	function send( form, token ) {
		var data = new window.FormData( form );

		data.append( 'crc_js', '1' );

		if ( token && data.set ) {
			data.set( 'h-captcha-response', token );
		}

		sending( form, true );

		// The form has a field called "action", so its address is read as an attribute.
		window.fetch( form.getAttribute( 'action' ), {
			method: 'POST',
			body: data,
			credentials: 'same-origin',
			headers: { Accept: 'application/json' }
		} ).then( function ( response ) {
			return response.json().catch( function () {
				return { success: false, message: messages.failed };
			} );
		} ).then( function ( result ) {
			sending( form, false );
			resetCaptcha( form );
			done( form, result || {} );
		}, function () {
			sending( form, false );
			resetCaptcha( form );
			showStatus( form, 'error', messages.network );
		} );
	}

	function submit( form, event ) {
		var invalid = [];
		var token = '';
		var wrap;

		// Very old browsers send the form the usual way.
		if ( ! window.fetch || ! window.FormData ) {
			return;
		}

		event.preventDefault();

		if ( form.crcSending ) {
			return;
		}

		hideStatus( form );

		KEYS.forEach( function ( key ) {
			var field = fieldOf( form, key );

			if ( field ) {
				field.crcTouched = true;

				if ( ! validate( form, key ) ) {
					invalid.push( field );
				}
			}
		} );

		wrap = captcha ? fieldOf( form, 'captcha' ) : null;

		if ( wrap ) {
			token = captchaToken( form );

			if ( ! token ) {
				loadCaptcha( form );
				showError( wrap, 'failed' === captchaState ? messages.captcha_load : messages.captcha );
				invalid.push( wrap );
			}
		}

		if ( invalid.length ) {
			focusField( invalid[ 0 ] );
			return;
		}

		send( form, token );
	}

	function setUp( form ) {
		var select = form.querySelector( '.crc-inquiry-country-select' );
		var flag = form.querySelector( '.crc-inquiry-country-flag' );

		if ( form.crcInquiry ) {
			return;
		}

		form.crcInquiry = true;
		form.noValidate = true;

		KEYS.forEach( function ( key ) {
			var wrap = fieldOf( form, key );
			var control = controlOf( wrap );

			if ( ! control ) {
				return;
			}

			// Checked when leaving a field that has been typed in, and again
			// while it's being fixed.
			control.addEventListener( 'blur', function () {
				if ( '' !== control.value.trim() || wrap.crcTouched ) {
					wrap.crcTouched = true;
					validate( form, key );
				}

				if ( 'email' === key ) {
					suggest( form );
				}
			} );

			control.addEventListener( 'input', function () {
				if ( 'phone' === key ) {
					detectCountry( form );
				}

				if ( 'email' === key ) {
					hideHint( form );
				}

				if ( wrap.classList.contains( 'is-invalid' ) ) {
					validate( form, key );
				}
			} );
		} );

		// A flag that can't load is left out, and the code moves over.
		if ( flag ) {
			flag.addEventListener( 'error', function () {
				flag.hidden = true;
				fitCountry( form );
			} );
			flag.addEventListener( 'load', function () {
				if ( flag.hidden ) {
					flag.hidden = false;
					fitCountry( form );
				}
			} );

			if ( flag.complete && ! flag.naturalWidth && flag.getAttribute( 'src' ) ) {
				flag.hidden = true;
			}
		}

		if ( select ) {
			select.addEventListener( 'change', function () {
				var wrap = fieldOf( form, 'phone' );

				showCode( form );

				if ( wrap && wrap.crcTouched ) {
					validate( form, 'phone' );
				}
			} );
		}

		// The browser may bring back an earlier choice.
		showCode( form );

		form.addEventListener( 'submit', function ( event ) {
			submit( form, event );
		} );

		watchCaptcha( form );
	}

	function start() {
		var forms = document.querySelectorAll( 'form[data-crc-inquiry]' );
		var observer;
		var i;

		function refit() {
			var j;

			for ( j = 0; j < forms.length; j++ ) {
				fitCountry( forms[ j ] );
			}
		}

		for ( i = 0; i < forms.length; i++ ) {
			setUp( forms[ i ] );
		}

		if ( ! forms.length ) {
			return;
		}

		// The site's field style can change with the screen size, and the
		// form can be hidden at first; the country code follows.
		if ( window.ResizeObserver ) {
			observer = new window.ResizeObserver( function ( entries ) {
				entries.forEach( function ( entry ) {
					fitCountry( entry.target );
				} );
			} );

			for ( i = 0; i < forms.length; i++ ) {
				observer.observe( forms[ i ] );
			}
		} else {
			window.addEventListener( 'resize', refit );
		}

		// The site's font may arrive after the page.
		if ( document.fonts && document.fonts.ready ) {
			document.fonts.ready.then( refit );
		}
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}
}() );
