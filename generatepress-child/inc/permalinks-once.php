<?php
/**
 * ONE-SHOT: set the permalink structure to match the live site, once.
 *
 * REST cannot change permalink_structure and there is no SSH or WP-CLI, so
 * this sets it from git. It records what it set and then does nothing.
 * REMOVE this file and its require in functions.php once it has run
 * (CLAUDE.md, "Permalinks").
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_loaded', 'mbc_set_permalinks_once' );
function mbc_set_permalinks_once() {
    $target = '/%postname%/'; // The live site's structure.
    if ( get_option( 'mbc_permalinks_set' ) === $target ) {
        return;
    }
    global $wp_rewrite;
    $wp_rewrite->set_permalink_structure( $target );
    flush_rewrite_rules( false ); // Soft flush: does not touch .htaccess.
    update_option( 'mbc_permalinks_set', $target );
}
