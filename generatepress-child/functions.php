<?php
/**
 * GeneratePress Child Theme — functions.php
 * Moortown Baptist Church
 *
 * All design values live in css/tokens.css. Nothing in here sets colours,
 * fonts or spacing. Layout (header, menus, assets) is in inc/layout.php.
 */

defined( 'ABSPATH' ) || exit;

require_once get_stylesheet_directory() . '/inc/site-info.php';
require_once get_stylesheet_directory() . '/inc/class-mbc-nav-walker.php';
require_once get_stylesheet_directory() . '/inc/layout.php';
require_once get_stylesheet_directory() . '/inc/looks.php';

// YouTube feed shortcode [mbc_youtube_feed]. Still used by the legacy
// "Services on YouTube" page (ID 350); remove once that page is retired.
require_once get_stylesheet_directory() . '/includes/mbc-youtube-feed.php';

add_action( 'wp_enqueue_scripts', 'mbc_child_enqueue_styles' );
function mbc_child_enqueue_styles() {
    wp_enqueue_style(
        'generatepress-parent',
        get_template_directory_uri() . '/style.css'
    );
    wp_enqueue_style(
        'generatepress-child',
        get_stylesheet_uri(),
        array( 'generatepress-parent' ),
        wp_get_theme()->get( 'Version' )
    );
    wp_enqueue_style(
        'mbc-tokens',
        get_stylesheet_directory_uri() . '/css/tokens.css',
        array( 'generatepress-parent' ),
        filemtime( get_stylesheet_directory() . '/css/tokens.css' )
    );
    wp_enqueue_style(
        'mbc-base',
        get_stylesheet_directory_uri() . '/css/base.css',
        array( 'mbc-tokens' ),
        filemtime( get_stylesheet_directory() . '/css/base.css' )
    );
    // Legacy: styles the YouTube feed shortcode (see above).
    wp_enqueue_style(
        'mbc-youtube-feed',
        get_stylesheet_directory_uri() . '/assets/css/mbc-youtube-feed.css',
        array( 'mbc-tokens' ),
        filemtime( get_stylesheet_directory() . '/assets/css/mbc-youtube-feed.css' )
    );
}
