/* Newsletter settings screen: choose the default header image from the media library. */
( function () {
    'use strict';
    var input = document.getElementById( 'mbc-nl-default-header' );
    var choose = document.getElementById( 'mbc-nl-default-header-choose' );
    var remove = document.getElementById( 'mbc-nl-default-header-remove' );
    var preview = document.getElementById( 'mbc-nl-default-header-preview' );
    var none = document.getElementById( 'mbc-nl-default-header-none' );
    if ( ! input || ! choose || ! window.wp || ! wp.media ) {
        return;
    }
    var frame;

    function show( id, url, alt ) {
        input.value = id || 0;
        preview.textContent = '';
        if ( id && url ) {
            var img = document.createElement( 'img' );
            img.className = 'mbc-nl-header-preview__img';
            img.src = url;
            img.alt = alt || '';
            preview.appendChild( img );
        }
        none.hidden = !! id;
        remove.hidden = ! id;
    }

    choose.addEventListener( 'click', function () {
        if ( ! frame ) {
            frame = wp.media( {
                title: window.mbcNlSettings.title,
                button: { text: window.mbcNlSettings.button },
                library: { type: 'image' },
                multiple: false
            } );
            // WordPress can open on "Upload files"; people choosing an existing image want the library.
            frame.on( 'open', function () {
                frame.content.mode( 'browse' );
            } );
            frame.on( 'select', function () {
                var a = frame.state().get( 'selection' ).first().toJSON();
                var sizes = a.sizes || {};
                var s = sizes.medium || sizes.full || { url: a.url };
                show( a.id, s.url, a.alt );
            } );
        }
        frame.open();
    } );

    remove.addEventListener( 'click', function () {
        show( 0 );
    } );
}() );
