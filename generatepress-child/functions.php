<?php
/**
 * GeneratePress Child Theme — functions.php
 * Moortown Baptist Church sandbox
 * Edited by Claude 11 July 2026
 * Further changes made (dummy) 
 * Last chance!
 */

require_once get_stylesheet_directory() . '/includes/mbc-youtube-feed.php';

// Add the Youtube code to the sandbox
add_action( 'wp_enqueue_scripts', function() {
      wp_enqueue_style(
          'mbc-youtube-feed',
          get_stylesheet_directory_uri() . '/assets/css/mbc-youtube-feed.css',
          array(),
          '1.0'
      );
  } );


// ── ENQUEUE PARENT + CHILD STYLESHEETS ───────────────────────────────────
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
}

// ── GOOGLE FONTS — loaded on all MBC custom templates ────────────────────
add_action( 'wp_enqueue_scripts', 'mbc_enqueue_fonts' );
function mbc_enqueue_fonts() {
    $mbc_templates = array(
        'front-page-split.php',
        'page-standard.php',
        'page-section-index.php',
        'page-hero-full.php',
        'page-hero-banner.php',
    );
    $is_mbc = false;
    foreach ( $mbc_templates as $tpl ) {
        if ( is_page_template( $tpl ) ) { $is_mbc = true; break; }
    }
    if ( $is_mbc || is_single() ) {
        wp_enqueue_style(
            'mbc-google-fonts',
            'https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;0,900;1,400;1,700&family=Lato:wght@300;400;700&family=Outfit:wght@300;400;600;700&display=swap',
            array(),
            null
        );
    }
}

// ── SHARED CSS + JS — loaded on standard page, post, and split layout ─────
add_action( 'wp_enqueue_scripts', 'mbc_enqueue_shared_assets' );
function mbc_enqueue_shared_assets() {
    $shared_templates = array(
        'front-page-split.php',
        'page-standard.php',
        'page-section-index.php',
        'page-hero-full.php',
        'page-hero-banner.php',
    );
    $needs_shared = false;
    foreach ( $shared_templates as $tpl ) {
        if ( is_page_template( $tpl ) ) { $needs_shared = true; break; }
    }
    if ( $needs_shared || is_single() ) {
        wp_enqueue_style(
            'mbc-shared',
            get_stylesheet_directory_uri() . '/assets/mbc-shared.css',
            array(),
            '1.0.2'
        );
        wp_enqueue_script(
            'mbc-shared',
            get_stylesheet_directory_uri() . '/assets/mbc-shared.js',
            array(),
            '1.0.0',
            true
        );
    }
}

// ── SPLIT LAYOUT — only on home page ──────────────────────────────────────
add_action( 'wp_enqueue_scripts', 'mbc_front_page_assets' );
function mbc_front_page_assets() {
    if ( is_page_template( 'front-page-split.php' ) ) {
        wp_enqueue_style(
            'mbc-split-layout',
            get_stylesheet_directory_uri() . '/assets/split-layout.css',
            array( 'mbc-shared' ),
            '1.0.10'
        );
        wp_enqueue_script(
            'mbc-split-layout',
            get_stylesheet_directory_uri() . '/assets/split-layout.js',
            array( 'mbc-shared' ),
            '1.0.10',
            true
        );
    }
}

// ── SUPPRESS GP HEADER + ELEMENTOR HEADER on all MBC templates ───────────
add_action( 'wp_head', 'mbc_suppress_elementor_header' );
function mbc_suppress_elementor_header() {
    $templates = array(
        'front-page-split.php',
        'page-standard.php',
        'page-section-index.php',
        'page-hero-full.php',
        'page-hero-banner.php',
    );
    $suppress = false;
    foreach ( $templates as $tpl ) {
        if ( is_page_template( $tpl ) ) { $suppress = true; break; }
    }
    // Also suppress on single posts
    if ( $suppress || is_single() ) {
        echo '<style>
            /* Kill all white backgrounds from GeneratePress */
            html, body,
            .site, .site-content, #page, #content,
            .hfeed, .generate-columns-container,
            .inside-page-header, .page-header-image,
            .generate-page-header { background: #0d0d0d !important; }

            /* Hide ALL GeneratePress and Elementor header elements */
            .site-header,
            .site-branding,
            #masthead,
            #site-header,
            .main-navigation,
            .generate-menu-container,
            .elementor-location-header,
            .elementor-template-canvas .site-header,
            header.site-header,
            [data-elementor-type="header"],
            .e-con-full[data-elementor-type="header"] { display:none !important; visibility:hidden !important; height:0 !important; overflow:hidden !important; }
            html { margin-top:0 !important; }
            #wpadminbar { position:fixed !important; }
        </style>';

        /* Also remove the Elementor header via PHP filter */
    }
}

// Remove Elementor Theme Builder header/footer output on MBC templates
add_action( 'elementor/theme/before_do_header', 'mbc_maybe_cancel_elementor_header' );
function mbc_maybe_cancel_elementor_header() {
    $templates = array(
        'front-page-split.php',
        'page-standard.php',
        'page-section-index.php',
        'page-hero-full.php',
        'page-hero-banner.php',
    );
    foreach ( $templates as $tpl ) {
        if ( is_page_template( $tpl ) || is_single() ) {
            // Remove the action that outputs the Elementor header
            remove_all_actions( 'elementor/theme/do_header' );
            break;
        }
    }
}

// ── REGISTER NAV MENUS ────────────────────────────────────────────────────
add_action( 'after_setup_theme', 'mbc_register_menus' );
function mbc_register_menus() {
    register_nav_menus( array(
        'primary' => __( 'Primary Navigation', 'generatepress-child' ),
    ) );
}

// ── SUPPORT FEATURES ─────────────────────────────────────────────────────
add_action( 'after_setup_theme', 'mbc_theme_support' );
function mbc_theme_support() {
    add_theme_support( 'post-thumbnails' );
    add_theme_support( 'title-tag' );
    add_theme_support( 'html5', array( 'comment-list', 'comment-form', 'search-form' ) );
}
