<?php
/**
 * Looks: alternate token sets in css/looks/*.css, switched with ?look=<name>.
 *
 * A demo tool. ?look=<name> sets a cookie and applies that look site-wide;
 * ?look=default (or an unknown value) clears it. The value is checked against
 * the .css files that exist in css/looks/ and is never used unvalidated.
 * Disable at launch with: add_filter( 'mbc_looks_enabled', '__return_false' );
 */

defined( 'ABSPATH' ) || exit;

const MBC_LOOK_COOKIE = 'mbc_look';

function mbc_looks_enabled() {
    return (bool) apply_filters( 'mbc_looks_enabled', true );
}

/**
 * @return string[] Names of the available looks (default first).
 */
function mbc_available_looks() {
    $names = array( 'default' );
    foreach ( (array) glob( get_stylesheet_directory() . '/css/looks/*.css' ) as $file ) {
        $name = basename( $file, '.css' );
        if ( 'default' !== $name && preg_match( '/^[a-z0-9-]+$/', $name ) && is_file( $file ) ) {
            $names[] = $name;
        }
    }
    return $names;
}

/**
 * Whitelist check; returns a valid look name or 'default'.
 */
function mbc_sanitise_look( $value ) {
    $value = is_string( $value ) ? $value : '';
    return in_array( $value, mbc_available_looks(), true ) ? $value : 'default';
}

/**
 * The look for this request (a ?look= on this request wins over the cookie).
 */
function mbc_current_look( $set = null ) {
    static $current = null;
    if ( null !== $set ) {
        $current = $set;
    }
    if ( null === $current ) {
        $cookie  = isset( $_COOKIE[ MBC_LOOK_COOKIE ] ) ? sanitize_key( wp_unslash( $_COOKIE[ MBC_LOOK_COOKIE ] ) ) : '';
        $current = mbc_sanitise_look( $cookie );
    }
    return $current;
}

add_action( 'init', 'mbc_handle_look_request', 1 );
function mbc_handle_look_request() {
    if ( ! mbc_looks_enabled() || is_admin() || headers_sent() || ! isset( $_GET['look'] ) ) {
        return;
    }
    $look    = mbc_sanitise_look( sanitize_key( wp_unslash( $_GET['look'] ) ) );
    $options = array(
        'expires'  => 'default' === $look ? time() - DAY_IN_SECONDS : time() + 30 * DAY_IN_SECONDS,
        'path'     => '/',
        'secure'   => is_ssl(),
        'httponly' => true,
        'samesite' => 'Lax',
    );
    setcookie( MBC_LOOK_COOKIE, 'default' === $look ? '' : $look, $options );
    mbc_current_look( $look );
}

// The page varies by cookie: keep shared caches from mixing looks up.
add_filter( 'wp_headers', 'mbc_look_headers' );
function mbc_look_headers( $headers ) {
    if ( mbc_looks_enabled() ) {
        $headers['Vary'] = isset( $headers['Vary'] ) ? $headers['Vary'] . ', Cookie' : 'Cookie';
        if ( 'default' !== mbc_current_look() || isset( $_GET['look'] ) ) {
            $headers['Cache-Control'] = 'private, max-age=0';
        }
    }
    return $headers;
}

add_action( 'wp_enqueue_scripts', 'mbc_enqueue_look', 15 );
function mbc_enqueue_look() {
    if ( ! mbc_looks_enabled() ) {
        return;
    }
    $dir  = get_stylesheet_directory();
    $look = mbc_current_look();
    if ( 'default' !== $look ) {
        wp_enqueue_style(
            'mbc-look',
            get_stylesheet_directory_uri() . '/css/looks/' . $look . '.css',
            array( 'mbc-tokens' ),
            filemtime( $dir . '/css/looks/' . $look . '.css' )
        );
    }
    if ( is_user_logged_in() ) {
        wp_enqueue_style( 'mbc-picker', get_stylesheet_directory_uri() . '/css/picker.css', array( 'mbc-base' ), filemtime( $dir . '/css/picker.css' ) );
    }
}

// Floating picker, logged-in users only.
add_action( 'wp_footer', 'mbc_render_look_picker' );
function mbc_render_look_picker() {
    if ( mbc_looks_enabled() && is_user_logged_in() ) {
        get_template_part( 'template-parts/look-picker' );
    }
}
