<?php
/**
 * Layout: takes over GeneratePress's header hooks, registers menus and
 * enqueues the MBC styles and scripts.
 *
 * DELIBERATE: remove_all_actions() on generate_header and generate_footer
 * means anything else hooked there (GeneratePress's own header and footer,
 * Elementor Pro's Theme Builder header and footer, any plugin) will not
 * render. That is intended: the header is
 * template-parts/header.php, in git.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'after_setup_theme', 'mbc_register_menus' );
function mbc_register_menus() {
    register_nav_menus( array(
        'primary' => __( 'Primary Navigation', 'generatepress-child' ),
        'footer'  => __( 'Footer Links', 'generatepress-child' ),
    ) );
}

// Late on `wp`, after GeneratePress has added its own hooks.
add_action( 'wp', 'mbc_take_over_layout', 100 );
function mbc_take_over_layout() {
    remove_all_actions( 'generate_header' );
    remove_all_actions( 'generate_footer' ); // Also drops Elementor Pro's footer (#208) and GP footer widgets.
    remove_action( 'generate_after_header', 'generate_add_navigation_after_header', 5 );
    remove_action( 'generate_before_header', 'generate_add_navigation_before_header', 5 );
    remove_action( 'generate_before_header', 'generate_top_bar', 5 );
    add_action( 'generate_header', 'mbc_render_header' );
    add_action( 'generate_footer', 'mbc_render_footer' );

    // RULE: the new templates place their own image. Never output
    // GeneratePress's featured-image banner on them. (Legacy Elementor pages
    // keep it until they are converted.)
    if ( is_page_template( mbc_page_templates() ) || is_singular( 'post' ) ) {
        remove_action( 'generate_after_header', 'generate_featured_page_header', 10 );
    }
}

/**
 * The page templates this theme provides for content pages.
 *
 * @return string[]
 */
function mbc_page_templates() {
    return array(
        'page-templates/front-page.php',
        'page-templates/activity.php',
        'page-templates/info.php',
        'page-templates/mission-partner.php',
        'page-templates/listing.php',
    );
}

// No sidebar anywhere: the old pages' GP widgets (Search, Recent Posts...)
// are not part of the design.
add_filter( 'generate_sidebar_layout', 'mbc_no_sidebar' );
function mbc_no_sidebar() {
    return 'no-sidebar';
}

function mbc_render_header() {
    get_template_part( 'template-parts/header' );
}

function mbc_render_footer() {
    get_template_part( 'template-parts/footer' );
}

/**
 * Site logo <img> (Customizer > Site Identity), or '' if none is set.
 */
function mbc_logo_image( $class ) {
    $logo_id = (int) get_theme_mod( 'custom_logo' );
    if ( ! $logo_id ) {
        return '';
    }
    return wp_get_attachment_image( $logo_id, 'full', false, array(
        'class' => $class,
        'alt'   => '',
    ) );
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
    wp_enqueue_style( 'mbc-layout', $uri . '/css/layout.css', array( 'mbc-base' ), filemtime( $dir . '/css/layout.css' ) );
    wp_enqueue_style( 'mbc-patterns', $uri . '/css/patterns.css', array( 'mbc-base' ), filemtime( $dir . '/css/patterns.css' ) );
    wp_enqueue_style( 'mbc-footer', $uri . '/css/footer.css', array( 'mbc-base' ), filemtime( $dir . '/css/footer.css' ) );
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

// Block patterns live in patterns/*.php (registered by WordPress from their
// file headers); this adds their "MBC" category.
add_action( 'init', 'mbc_register_pattern_category' );
function mbc_register_pattern_category() {
    register_block_pattern_category( 'mbc', array( 'label' => __( 'MBC', 'generatepress-child' ) ) );
}

// The block editor uses the same tokens and styles as the front end.
add_action( 'after_setup_theme', 'mbc_editor_styles' );
function mbc_editor_styles() {
    add_theme_support( 'editor-styles' );
    add_editor_style( array( 'css/tokens.css', 'css/base.css', 'css/patterns.css' ) );
}
