/* Newsletter issue edit screen: copy the email link, confirm a new key. No dependencies. */
( function () {
	'use strict';

	var strings = window.mbcNl || {};
	var copyButton = document.getElementById( 'mbc-nl-copy' );
	var field = document.getElementById( 'mbc-nl-email-url' );
	var status = document.getElementById( 'mbc-nl-copy-status' );
	var regen = document.getElementById( 'mbc-nl-regen' );

	function say( text ) {
		if ( status ) {
			status.textContent = text;
		}
	}

	if ( copyButton && field ) {
		copyButton.addEventListener( 'click', function () {
			field.select();
			var done = function () { say( strings.copied || 'Copied.' ); };
			var fail = function () { say( strings.failed || 'Select the link and copy it by hand.' ); };
			if ( navigator.clipboard && window.isSecureContext ) {
				navigator.clipboard.writeText( field.value ).then( done, fail );
				return;
			}
			try {
				if ( document.execCommand( 'copy' ) ) {
					done();
					return;
				}
			} catch ( e ) { /* fall through */ }
			fail();
		} );
	}

	if ( regen ) {
		regen.addEventListener( 'click', function ( event ) {
			if ( ! window.confirm( strings.confirm || 'Make a new key? The old link will stop working.' ) ) {
				event.preventDefault();
			}
		} );
	}
}() );
