<?php
/**
 * Archives: /category/news/ goes to /news/, and the other archives use the News listing's cards.
 */

defined( 'ABSPATH' ) || exit;

/**
 * The News page (the page with the slug "news", or the Posts page if one is set).
 *
 * @return WP_Post|null
 */
function mbc_news_page() {
    $posts_page = (int) get_option( 'page_for_posts' );
    $page       = $posts_page ? get_post( $posts_page ) : get_page_by_path( 'news' );
    return ( $page && 'publish' === $page->post_status ) ? $page : null;
}

// 301: /category/news/ (also /category/news/page/N/) to /news/ (?query-1-page=N).
add_action( 'template_redirect', 'mbc_redirect_news_category' );
function mbc_redirect_news_category() {
    if ( ! is_category( 'news' ) ) {
        return;
    }
    $news = mbc_news_page();
    if ( ! $news ) {
        return;
    }
    $url   = get_permalink( $news );
    $paged = (int) get_query_var( 'paged' );
    if ( $paged > 1 ) {
        $url = add_query_arg( 'query-1-page', $paged, $url );
    }
    wp_safe_redirect( $url, 301 );
    exit;
}

// Same page size as the News listing.
add_action( 'pre_get_posts', 'mbc_archive_page_size' );
function mbc_archive_page_size( $query ) {
    if ( $query->is_main_query() && ! is_admin() && $query->is_archive() && ! $query->is_post_type_archive() ) {
        $query->set( 'posts_per_page', 6 );
    }
}

/**
 * The News listing pattern, rendered against the current archive query.
 */
function mbc_archive_listing_html() {
    $pattern = WP_Block_Patterns_Registry::get_instance()->get_registered( 'mbc/news-listing' );
    if ( ! $pattern ) {
        return '';
    }
    $markup = str_replace( '"inherit":false', '"inherit":true', $pattern['content'] );
    $markup = preg_replace( '/<!-- wp:paragraph \{"className":"mbc-news__archive"\} -->.*?<!-- \/wp:paragraph -->/s', '', $markup );
    return do_blocks( $markup );
}
