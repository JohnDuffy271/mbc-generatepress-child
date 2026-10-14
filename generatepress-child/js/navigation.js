/*
 * MBC primary navigation. Vanilla JS, no dependencies.
 *
 * Desktop: dropdowns opened by buttons (click / Enter / Space), one at a
 * time, closed by Esc, click-away or focus leaving.
 * Mobile and tablet: the burger opens a full-screen panel; a parent button
 * drills down to its submenu and Back returns. Focus moves to Back when a
 * submenu opens and back to the parent button when returning; Esc closes
 * the whole menu and focuses the burger.
 *
 * "Mobile" means the burger is displayed. The breakpoint itself lives only
 * in css/header.css.
 */
( function () {
	'use strict';

	var header = document.querySelector( '.mbc-header' );
	var nav = document.getElementById( 'mbc-nav' );
	if ( ! header || ! nav ) {
		return;
	}
	var burger = header.querySelector( '.mbc-header__burger' );
	var back = header.querySelector( '.mbc-header__back' );
	var burgerLabel = burger.querySelector( '.screen-reader-text' );
	var root = document.documentElement;

	var menuOpen = false;
	var openItem = null; // The <li> whose submenu is open.
	var wasMobile = isMobile();

	function isMobile() {
		return window.getComputedStyle( burger ).display !== 'none';
	}

	function toggleOf( item ) {
		return item.querySelector( ':scope > .mbc-toggle' );
	}

	function closeSub( restoreFocus ) {
		if ( ! openItem ) {
			return;
		}
		var button = toggleOf( openItem );
		openItem.classList.remove( 'is-open' );
		button.setAttribute( 'aria-expanded', 'false' );
		openItem = null;
		header.classList.remove( 'is-drilled' );
		nav.classList.remove( 'is-drilled' );
		if ( restoreFocus ) {
			button.focus();
		}
	}

	function openSub( item ) {
		closeSub( false );
		openItem = item;
		item.classList.add( 'is-open' );
		toggleOf( item ).setAttribute( 'aria-expanded', 'true' );
		if ( isMobile() ) {
			header.classList.add( 'is-drilled' );
			nav.classList.add( 'is-drilled' );
			back.focus();
		}
	}

	function setMenu( open ) {
		menuOpen = open;
		nav.classList.toggle( 'is-open', open );
		root.classList.toggle( 'mbc-menu-open', open );
		burger.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		burgerLabel.textContent = burger.getAttribute( open ? 'data-close-label' : 'data-open-label' );
		if ( ! open ) {
			closeSub( false );
		}
	}

	burger.addEventListener( 'click', function () {
		setMenu( ! menuOpen );
	} );

	back.addEventListener( 'click', function () {
		closeSub( true );
	} );

	nav.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '.mbc-toggle' );
		if ( ! button || ! nav.contains( button ) ) {
			return;
		}
		var item = button.parentElement;
		if ( item === openItem ) {
			closeSub( false );
		} else if ( item.querySelector( ':scope > .sub-menu' ) ) {
			openSub( item );
		}
	} );

	document.addEventListener( 'keydown', function ( event ) {
		if ( event.key === 'Escape' ) {
			if ( isMobile() && menuOpen ) {
				setMenu( false );
				burger.focus();
			} else if ( openItem ) {
				closeSub( true );
			}
			return;
		}
		if ( event.key === 'Tab' && menuOpen && isMobile() ) {
			trapFocus( event );
		}
	} );

	// Keep Tab inside the header while the full-screen menu is open.
	function trapFocus( event ) {
		var nodes = Array.prototype.filter.call(
			header.querySelectorAll( 'a[href], button:not([disabled])' ),
			function ( node ) {
				return node.getClientRects().length > 0;
			}
		);
		if ( ! nodes.length ) {
			return;
		}
		var first = nodes[ 0 ];
		var last = nodes[ nodes.length - 1 ];
		if ( event.shiftKey && document.activeElement === first ) {
			event.preventDefault();
			last.focus();
		} else if ( ! event.shiftKey && document.activeElement === last ) {
			event.preventDefault();
			first.focus();
		}
	}

	// Desktop: close a dropdown on click-away or when focus leaves it.
	document.addEventListener( 'click', function ( event ) {
		if ( openItem && ! isMobile() && ! openItem.contains( event.target ) ) {
			closeSub( false );
		}
	} );

	nav.addEventListener( 'focusout', function ( event ) {
		if ( openItem && ! isMobile() && event.relatedTarget && ! openItem.contains( event.relatedTarget ) ) {
			closeSub( false );
		}
	} );

	// Crossing the breakpoint resets everything.
	window.addEventListener( 'resize', function () {
		var mobile = isMobile();
		if ( mobile !== wasMobile ) {
			wasMobile = mobile;
			setMenu( false );
		}
	} );
}() );
