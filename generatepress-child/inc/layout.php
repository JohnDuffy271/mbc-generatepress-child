<?php
/**
 * Layout: takes over GeneratePress's header hooks, registers menus and
 * enqueues the MBC styles and scripts.
 *
 * DELIBERATE: remove_all_actions() on generate_header means anything else
 * hooked there (GeneratePress's own header, Elementor Pro's Theme Builder
 * header) will not render. That is intended: the header is
 * template-parts/header.php, in git.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'after_setup_theme', 'mbc_register_menus' );
function mbc_register_menus() {
    register_nav_menus( array(
        'primary' => __( 'Primary Navigation', 'generatepress-child' ),
    ) );
}

// Late on `wp`, after GeneratePress has added its own hooks.
add_action( 'wp', 'mbc_take_over_header', 100 );
function mbc_take_over_header() {
    remove_all_actions( 'generate_header' );
    remove_action( 'generate_after_header', 'generate_add_navigation_after_header', 5 );
    remove_action( 'generate_before_header', 'generate_add_navigation_before_header', 5 );
    remove_action( 'generate_before_header', 'generate_top_bar', 5 );
    add_action( 'generate_header', 'mbc_render_header' );
}

function mbc_render_header() {
    get_template_part( 'template-parts/header' );
}

// "mbc-js" lets CSS show the burger and hide closed menus only when the
// script will run; without JS the menu is a plain list of links.
add_action( 'wp_head', 'mbc_js_flag', 1 );
function mbc_js_flag() {
    echo "<script>document.documentElement.classList.add('mbc-js');</script>\n";
}

add_action( 'wp_enqueue_scripts', 'mbc_enqueue_layout_assets', 20 );
function mbc_enqueue_layout_assets() {
    $dir = get_stylesheet_directory();
    $uri = get_stylesheet_directory_uri();

    wp_enqueue_style( 'mbc-header', $uri . '/css/header.css', array( 'mbc-base' ), filemtime( $dir . '/css/header.css' ) );
    wp_enqueue_script(
        'mbc-navigation',
        $uri . '/js/navigation.js',
        array(),
        filemtime( $dir . '/js/navigation.js' ),
        array( 'strategy' => 'defer', 'in_footer' => true )
    );
    // GeneratePress's own menu script is not used.
    wp_dequeue_script( 'generate-menu' );
}
