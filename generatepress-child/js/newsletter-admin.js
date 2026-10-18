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

/* The ordered post list: Up, Down, Remove, Add, and the hidden field the form saves. */
( function () {
	'use strict';

	var strings = window.mbcNl || {};
	var list = document.getElementById( 'mbc-nl-list' );
	var field = document.getElementById( 'mbc-nl-posts-field' );
	if ( ! list || ! field ) {
		return;
	}
	var empty = document.getElementById( 'mbc-nl-empty' );
	var count = document.getElementById( 'mbc-nl-count' );
	var select = document.getElementById( 'mbc-nl-add' );
	var addButton = document.getElementById( 'mbc-nl-add-button' );
	var refill = document.getElementById( 'mbc-nl-refill' );

	function ids() {
		return Array.prototype.map.call( list.querySelectorAll( '.mbc-nl-item' ), function ( li ) {
			return li.getAttribute( 'data-id' );
		} );
	}

	function sync( message ) {
		var current = ids();
		field.value = current.join( ',' );
		if ( empty ) {
			empty.hidden = current.length > 0;
		}
		if ( count ) {
			count.textContent = message || ( current.length === 1
				? ( strings.one || '1 post in this issue.' )
				: ( strings.count || '%d posts in this issue.' ).replace( '%d', current.length ) );
		}
	}

	list.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( 'button[data-act]' );
		if ( ! button ) {
			return;
		}
		var item = button.closest( '.mbc-nl-item' );
		var act = button.getAttribute( 'data-act' );
		if ( act === 'up' && item.previousElementSibling ) {
			list.insertBefore( item, item.previousElementSibling );
			button.focus();
		} else if ( act === 'down' && item.nextElementSibling ) {
			list.insertBefore( item.nextElementSibling, item );
			button.focus();
		} else if ( act === 'remove' ) {
			var next = item.nextElementSibling || item.previousElementSibling;
			item.remove();
			if ( next ) {
				var target = next.querySelector( 'button[data-act]' );
				if ( target ) {
					target.focus();
				}
			}
		}
		sync();
	} );

	if ( select && addButton ) {
		addButton.addEventListener( 'click', function () {
			var option = select.options[ select.selectedIndex ];
			if ( ! option || ! option.value || ids().indexOf( option.value ) !== -1 ) {
				return;
			}
			list.insertAdjacentHTML( 'beforeend', option.getAttribute( 'data-item' ) );
			select.selectedIndex = 0;
			sync();
		} );
	}

	var since = document.getElementById( 'mbc-nl-since' );
	if ( refill && since ) {
		// The button is a plain link that works without this; here the chosen date is added to it.
		var setHref = function () {
			var base = refill.getAttribute( 'data-base' );
			refill.href = /^\d{4}-\d{2}-\d{2}$/.test( since.value ) ? base + '&since=' + encodeURIComponent( since.value ) : base;
		};
		since.addEventListener( 'input', setHref );
		since.addEventListener( 'change', setHref );
		setHref();
	}

	if ( refill ) {
		refill.addEventListener( 'click', function ( event ) {
			if ( ! window.confirm( strings.refill || 'Replace the list? Unsaved changes will be lost.' ) ) {
				event.preventDefault();
			}
		} );
	}

	sync();
}() );
