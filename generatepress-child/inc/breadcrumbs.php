<?php
/**
 * Breadcrumbs: "Section > Page" on child pages, "News > Post" (or "News > Category > Post") on news posts.
 *
 * Not on the front page, the top-level pages (the four section hubs, News,
 * Contact Us) or anything else with nothing above it. A nav landmark with an
 * ordered list, plus BreadcrumbList structured data. Styles: css/layout.css.
 */

defined( 'ABSPATH' ) || exit;

/**
 * The trail for the current page or post, ancestors first, the current page
 * last. Each item is array( 'name' => string, 'url' => string ).
 * Empty when no breadcrumb should be shown.
 *
 * @param WP_Post|int|null $post The post (default: the current one).
 * @return array[]
 */
function mbc_breadcrumb_trail( $post = null ) {
    $post = get_post( $post );
    if ( ! $post ) {
        return array();
    }
    $trail = array();
    if ( 'page' === $post->post_type ) {
        if ( ! $post->post_parent || (int) get_option( 'page_on_front' ) === (int) $post->ID ) {
            return array();
        }
        foreach ( array_reverse( get_post_ancestors( $post ) ) as $ancestor_id ) {
            $trail[] = array(
                'name' => wp_strip_all_tags( get_the_title( $ancestor_id ) ),
                'url'  => get_permalink( $ancestor_id ),
            );
        }
    } elseif ( 'post' === $post->post_type ) {
        $posts_page = (int) get_option( 'page_for_posts' );
        $news       = $posts_page ? get_post( $posts_page ) : get_page_by_path( 'news' );
        if ( ! $news || 'publish' !== $news->post_status ) {
            return array();
        }
        $trail[] = array(
            'name' => wp_strip_all_tags( get_the_title( $news ) ),
            'url'  => get_permalink( $news ),
        );
        $category = mbc_breadcrumb_category( $post );
        if ( $category ) {
            $trail[] = array(
                'name' => $category->name,
                'url'  => get_category_link( $category ),
            );
        }
    } else {
        return array();
    }
    $trail[] = array(
        'name' => wp_strip_all_tags( get_the_title( $post ) ),
        'url'  => get_permalink( $post ),
    );
    return $trail;
}

/**
 * The post's category for the trail: its lowest-ID category other than News and Uncategorized, or null.
 *
 * @param WP_Post $post The post.
 * @return WP_Term|null
 */
function mbc_breadcrumb_category( $post ) {
    $terms = array_filter( (array) get_the_category( $post->ID ), static function ( $t ) {
        return ! in_array( $t->slug, array( 'news', 'uncategorized' ), true );
    } );
    if ( ! $terms ) {
        return null;
    }
    usort( $terms, static function ( $a, $b ) {
        return $a->term_id <=> $b->term_id;
    } );
    return $terms[0];
}

/**
 * Print the breadcrumb nav and its structured data.
 */
function mbc_breadcrumbs() {
    $trail = mbc_breadcrumb_trail();
    if ( count( $trail ) < 2 ) {
        return;
    }
    $last = count( $trail ) - 1;
    echo '<nav class="mbc-breadcrumbs" aria-label="' . esc_attr__( 'Breadcrumb', 'generatepress-child' ) . '"><ol class="mbc-breadcrumbs__list">';
    foreach ( $trail as $i => $crumb ) {
        if ( $i === $last ) {
            echo '<li class="mbc-breadcrumbs__item" aria-current="page">' . esc_html( $crumb['name'] ) . '</li>';
        } else {
            echo '<li class="mbc-breadcrumbs__item"><a href="' . esc_url( $crumb['url'] ) . '">' . esc_html( $crumb['name'] ) . '</a></li>';
        }
    }
    echo '</ol></nav>';

    $items = array();
    foreach ( $trail as $i => $crumb ) {
        $items[] = array(
            '@type'    => 'ListItem',
            'position' => $i + 1,
            'name'     => $crumb['name'],
            'item'     => $crumb['url'],
        );
    }
    $data = array(
        '@context'        => 'https://schema.org',
        '@type'           => 'BreadcrumbList',
        'itemListElement' => $items,
    );
    echo '<script type="application/ld+json">' . wp_json_encode( $data, JSON_HEX_TAG | JSON_HEX_AMP ) . "</script>\n";
}

// Legacy (Elementor) pages use GeneratePress's own templates, which fire this
// hook inside the article. Our templates call mbc_breadcrumbs() directly.
add_action( 'generate_before_content', 'mbc_breadcrumbs_legacy', 5 );
function mbc_breadcrumbs_legacy() {
    if ( is_page() && ! is_page_template( mbc_page_templates() ) ) {
        mbc_breadcrumbs();
    }
}
