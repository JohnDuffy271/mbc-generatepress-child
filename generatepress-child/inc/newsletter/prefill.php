<?php
/**
 * Newsletter issues: which posts go in (pre-fill), the stored list, and clean excerpts.
 *
 * Categories are referenced by SLUG, never by ID (the live and sandbox IDs differ).
 * Filters: mbc_newsletter_category_slugs (default: news), mbc_newsletter_exclude_category_slugs
 * (default: ministers-message).
 */

defined( 'ABSPATH' ) || exit;

const MBC_NL_MAX_POSTS    = 12; // Pre-fill cap.
const MBC_NL_FIRST_WINDOW = 14; // Days, when there is no previous issue.

/**
 * @param string $filter Filter name giving the slugs.
 * @param string[] $default Default slugs.
 * @return int[] IDs of the categories that exist (missing slugs are ignored).
 */
function mbc_nl_category_ids( $filter, $default ) {
    $ids = array();
    foreach ( (array) apply_filters( $filter, $default ) as $slug ) {
        $term = get_term_by( 'slug', sanitize_title( $slug ), 'category' );
        if ( $term && ! is_wp_error( $term ) ) {
            $ids[] = (int) $term->term_id;
        }
    }
    return $ids;
}

/**
 * Base query arguments for posts that can appear in a newsletter.
 *
 * @return array<string,mixed>|null Null when none of the included categories exist.
 */
function mbc_nl_post_query_args() {
    $included = mbc_nl_category_ids( 'mbc_newsletter_category_slugs', array( 'news' ) );
    if ( ! $included ) {
        return null;
    }
    $args = array(
        'post_type'           => 'post',
        'post_status'         => 'publish',
        'ignore_sticky_posts' => true,
        'cat'                 => implode( ',', $included ),
        'orderby'             => 'date',
        'order'               => 'DESC',
        'fields'              => 'ids',
        'no_found_rows'       => true,
        'has_password'        => false,
    );
    $excluded = mbc_nl_category_ids( 'mbc_newsletter_exclude_category_slugs', array( 'ministers-message' ) );
    if ( $excluded ) {
        $args['category__not_in'] = $excluded;
    }
    return $args;
}

/**
 * Publish date (GMT) of the issue before this one, or '' if there is none.
 */
function mbc_nl_previous_issue_gmt( $issue_id = 0 ) {
    $issue_id = (int) $issue_id;
    $own      = $issue_id ? (string) get_post_field( 'post_date_gmt', $issue_id ) : '';
    $before   = ( $own && '0000-00-00 00:00:00' !== $own && 'publish' === get_post_status( $issue_id ) ) ? $own : gmdate( 'Y-m-d H:i:s' );
    $found    = get_posts( array(
        'post_type'      => MBC_NL_TYPE,
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'post__not_in'   => $issue_id ? array( $issue_id ) : array(),
        'fields'         => 'ids',
        'no_found_rows'  => true,
        'date_query'     => array( array( 'column' => 'post_date_gmt', 'before' => $before ) ),
    ) );
    return $found ? (string) get_post_field( 'post_date_gmt', $found[0] ) : '';
}

/**
 * Posts for an issue: sticky posts first (whatever their age, as they are pinned), then
 * everything published since the previous issue, newest first. Capped.
 *
 * @return array{ids:int[],since:string} since is a GMT datetime.
 */
function mbc_nl_prefill( $issue_id = 0 ) {
    $since = mbc_nl_previous_issue_gmt( $issue_id );
    if ( '' === $since ) {
        $since = gmdate( 'Y-m-d H:i:s', time() - MBC_NL_FIRST_WINDOW * DAY_IN_SECONDS );
    }
    $base = mbc_nl_post_query_args();
    if ( null === $base ) {
        return array( 'ids' => array(), 'since' => $since );
    }

    $sticky_ids = array();
    $sticky     = array_filter( array_map( 'intval', (array) get_option( 'sticky_posts', array() ) ) );
    if ( $sticky ) {
        $sticky_ids = get_posts( array_merge( $base, array( 'post__in' => $sticky, 'posts_per_page' => MBC_NL_MAX_POSTS ) ) );
    }
    $recent = get_posts( array_merge( $base, array(
        'posts_per_page' => MBC_NL_MAX_POSTS,
        'post__not_in'   => $sticky_ids,
        'date_query'     => array( array( 'column' => 'post_date_gmt', 'after' => $since, 'inclusive' => false ) ),
    ) ) );

    $ids = array_slice( array_merge( array_map( 'intval', $sticky_ids ), array_map( 'intval', $recent ) ), 0, MBC_NL_MAX_POSTS );
    return array( 'ids' => $ids, 'since' => $since );
}

/**
 * The issue's posts as WP_Post objects, in stored order. A post that has been deleted,
 * unpublished or password-protected since is skipped.
 *
 * @return WP_Post[]
 */
function mbc_nl_issue_posts( $issue_id ) {
    $out = array();
    foreach ( mbc_nl_get_post_ids( $issue_id ) as $post_id ) {
        $post = get_post( $post_id );
        if ( $post && 'post' === $post->post_type && 'publish' === $post->post_status && ! post_password_required( $post ) ) {
            $out[] = $post;
        }
    }
    return $out;
}

// ---------------------------------------------------------------------------
// Clean excerpts. WordPress's automatic excerpt keeps the text inside <style>
// and <script> blocks, which is how CSS ends up in an Elementor-built post's
// excerpt, so this strips whole elements first.
// ---------------------------------------------------------------------------

/**
 * Plain-prose excerpt of a post.
 *
 * @param WP_Post|int $post  Post.
 * @param int         $words Maximum words for a generated excerpt.
 */
function mbc_nl_excerpt( $post, $words = 40 ) {
    $post = get_post( $post );
    if ( ! $post ) {
        return '';
    }
    $manual = trim( wp_strip_all_tags( (string) $post->post_excerpt ) );
    $text   = '' !== $manual ? $manual : mbc_nl_plain_text( (string) $post->post_content );
    $text   = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
    $text   = trim( mbc_nl_re( '/\s+/u', ' ', $text ) );
    return wp_trim_words( $text, (int) $words, '…' );
}

/**
 * preg_replace that returns the subject unchanged if the regex fails (it returns null on error,
 * which a (string) cast would turn into an empty excerpt).
 */
function mbc_nl_re( $pattern, $replacement, $subject ) {
    $result = preg_replace( $pattern, $replacement, $subject );
    return null === $result ? $subject : $result;
}

/**
 * Visible prose from stored post HTML: no styles, scripts, widget chrome or shortcodes.
 */
function mbc_nl_plain_text( $html ) {
    $html = strip_shortcodes( $html );
    $html = mbc_nl_re( '#<(style|script|noscript|svg|template)\b[^>]*>.*?</\1>#is', ' ', $html );
    $html = mbc_nl_re( '#<!--.*?-->#s', ' ', $html );

    // Drop widget furniture by class (carousel arrows, screen-reader and button text, menus).
    if ( class_exists( 'DOMDocument' ) && '' !== trim( $html ) ) {
        $dom  = new DOMDocument();
        $prev = libxml_use_internal_errors( true );
        $dom->loadHTML( '<?xml encoding="utf-8" ?><div id="mbc-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
        libxml_clear_errors();
        libxml_use_internal_errors( $prev );
        $xpath = new DOMXPath( $dom );
        $junk  = '//*[self::button or self::nav or self::form or self::select or self::option or self::iframe'
            . ' or contains(concat(" ", normalize-space(@class), " "), " screen-reader-text ")'
            . ' or contains(@class, "elementor-screen-only") or contains(@class, "swiper-")'
            . ' or contains(@class, "elementor-button") or contains(@class, "elementor-nav")'
            . ' or contains(@class, "elementor-widget-spacer") or contains(@class, "elementor-widget-divider")'
            . ' or contains(@class, "elementor-share") or contains(@class, "elementor-social")]';
        foreach ( iterator_to_array( $xpath->query( $junk ) ) as $node ) {
            if ( $node->parentNode ) {
                $node->parentNode->removeChild( $node );
            }
        }
        $root = $dom->getElementById( 'mbc-root' );
        if ( $root ) {
            $inner = '';
            foreach ( $root->childNodes as $child ) {
                $inner .= $dom->saveHTML( $child );
            }
            $html = $inner;
        }
    }

    // Block-level ends become spaces so words do not run together, then tags go.
    $html = mbc_nl_re( '#</(p|div|li|h[1-6]|tr|br)>|<br\s*/?>#i', ' ', $html );
    $text = wp_strip_all_tags( $html );

    // Anything that still reads like a CSS rule or an at-rule block (only if there is a brace to find).
    if ( false !== strpos( $text, '{' ) ) {
        $text = mbc_nl_re( '#@(media|font-face|keyframes|import|charset)[^{;]*(\{[^{}]*(\{[^{}]*\}[^{}]*)*\}|;)#i', ' ', $text );
        $text = mbc_nl_re( '%[.#]?[A-Za-z_][\w\-.#:>\s,\[\]="\'~*+]*\{[^{}]*:[^{}]*\}%', ' ', $text );
    }
    return $text;
}
