<?php
/**
 * Slideshow: loads css/slideshow.css and js/slideshow.js on pages whose content has a
 * Gallery block with the class mbc-slideshow (see patterns/slideshow-band.php).
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_enqueue_scripts', 'mbc_enqueue_slideshow', 20 );
function mbc_enqueue_slideshow() {
    $post = is_singular() ? get_queried_object() : null;
    if ( ! ( $post instanceof WP_Post ) || false === strpos( $post->post_content, 'mbc-slideshow' ) ) {
        return;
    }
    $dir = get_stylesheet_directory();
    $uri = get_stylesheet_directory_uri();
    wp_enqueue_style( 'mbc-slideshow', $uri . '/css/slideshow.css', array( 'mbc-patterns' ), filemtime( $dir . '/css/slideshow.css' ) );
    wp_enqueue_script(
        'mbc-slideshow',
        $uri . '/js/slideshow.js',
        array(),
        filemtime( $dir . '/js/slideshow.js' ),
        array( 'strategy' => 'defer', 'in_footer' => true )
    );
}
