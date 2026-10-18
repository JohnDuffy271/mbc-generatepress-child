/* Weekly diary: saves notes as they are edited, refreshes the email output, copies it. */
( function () {
    'use strict';
    var cfg = window.mbcDiary;
    var body = document.getElementById( 'mbc-diary-body' );
    if ( ! cfg || ! body ) { return; }

    function api( method, path, data ) {
        var url = cfg.root + path;
        var qs = [];
        if ( cfg.fixture ) { qs.push( 'mbc_cal_fixture=' + encodeURIComponent( cfg.fixture ) ); }
        if ( method === 'GET' || method === 'DELETE' ) {
            Object.keys( data || {} ).forEach( function ( k ) { qs.push( k + '=' + encodeURIComponent( data[ k ] ) ); } );
        }
        url += ( url.indexOf( '?' ) > -1 ? '&' : '?' ) + qs.join( '&' );
        var opts = { method: method, headers: { 'X-WP-Nonce': cfg.nonce }, credentials: 'same-origin' };
        if ( method === 'POST' ) {
            opts.headers[ 'Content-Type' ] = 'application/json';
            opts.body = JSON.stringify( data );
        }
        return fetch( url, opts ).then( function ( r ) {
            if ( ! r.ok ) { throw new Error( 'HTTP ' + r.status ); }
            return r.json();
        } );
    }

    var orphans = document.getElementById( 'mbc-diary-orphans' );
    function refreshOutput() {
        return api( 'GET', 'diary', { from: orphans.dataset.from, to: orphans.dataset.to } ).then( function ( d ) {
            document.getElementById( 'mbc-diary-rich' ).innerHTML = d.html;
            document.getElementById( 'mbc-diary-plain' ).value = d.text;
        } );
    }

    body.addEventListener( 'change', function ( ev ) {
        var input = ev.target;
        if ( ! input.classList.contains( 'mbc-diary__note' ) ) { return; }
        var status = input.parentNode.querySelector( '.mbc-diary__status' );
        status.textContent = 'Saving...';
        api( 'POST', 'diary/note', { key: input.dataset.key, text: input.value, title: input.dataset.title } )
            .then( function ( res ) {
                // The same repeating event can appear on several rows: keep them in step.
                body.querySelectorAll( '.mbc-diary__note' ).forEach( function ( other ) {
                    if ( other.dataset.key === res.key ) { other.value = res.text; }
                } );
                return refreshOutput();
            } )
            .then( function () { status.textContent = 'Saved.'; } )
            .catch( function () { status.textContent = 'Not saved. Please try again.'; } );
    } );

    orphans.addEventListener( 'click', function ( ev ) {
        var btn = ev.target.closest( '.mbc-diary__delete' );
        if ( ! btn ) { return; }
        api( 'DELETE', 'diary/note', { key: btn.dataset.key } ).then( function () {
            var li = btn.closest( 'li' );
            var list = li.parentNode;
            li.remove();
            if ( ! list.children.length ) { list.outerHTML = '<p class="mbc-diary__none">No unused notes.</p>'; }
        } );
    } );

    var copyStatus = document.getElementById( 'mbc-diary-copy-status' );
    document.getElementById( 'mbc-diary-copy' ).addEventListener( 'click', function () {
        var rich = document.getElementById( 'mbc-diary-rich' );
        var plain = document.getElementById( 'mbc-diary-plain' ).value;
        function done( msg ) { copyStatus.textContent = msg; }
        function legacy() {
            var range = document.createRange();
            range.selectNodeContents( rich );
            var sel = window.getSelection();
            sel.removeAllRanges();
            sel.addRange( range );
            var ok = false;
            try { ok = document.execCommand( 'copy' ); } catch ( e ) { ok = false; }
            sel.removeAllRanges();
            if ( ok ) { done( 'Copied.' ); return; }
            if ( navigator.clipboard && navigator.clipboard.writeText ) {
                navigator.clipboard.writeText( plain ).then( function () { done( 'Copied as plain text.' ); }, function () { done( 'Could not copy. Select the text and copy it by hand.' ); } );
            } else {
                done( 'Could not copy. Select the text and copy it by hand.' );
            }
        }
        if ( navigator.clipboard && window.ClipboardItem ) {
            navigator.clipboard.write( [ new ClipboardItem( {
                'text/html': new Blob( [ rich.innerHTML ], { type: 'text/html' } ),
                'text/plain': new Blob( [ plain ], { type: 'text/plain' } )
            } ) ] ).then( function () { done( 'Copied.' ); }, legacy );
        } else {
            legacy();
        }
    } );
}() );
