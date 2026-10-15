<?php
/**
 * Newsletter email: /newsletter/<issue>/email/ serves a standalone, email-safe HTML document
 * that Mailchimp imports by URL. Access:
 *   - the issue's preview key (?key=...) works for any status, without a login;
 *   - without a key, a published issue is public, and anything else needs the capability.
 * Everyone else gets a plain 404, so a draft's existence is not revealed.
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/email-style.php';

add_action( 'template_redirect', 'mbc_nl_serve_email', 1 );
function mbc_nl_serve_email() {
    if ( ! get_query_var( 'mbc_nl_email' ) ) {
        return;
    }
    $slug  = sanitize_title( (string) get_query_var( MBC_NL_TYPE ) );
    $found = '' === $slug ? array() : get_posts( array(
        'post_type'      => MBC_NL_TYPE,
        'name'           => $slug,
        'post_status'    => array( 'publish', 'future', 'draft', 'pending', 'private' ),
        'posts_per_page' => 1,
        'no_found_rows'  => true,
    ) );
    $issue = $found ? $found[0] : null;

    $supplied = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a bearer key, not a form.
    $has_key  = $issue && '' !== $supplied && mbc_nl_key_matches( $issue->ID, $supplied );
    $public   = $issue && 'publish' === $issue->post_status;
    $allowed  = $issue && ( $has_key || $public || current_user_can( 'edit_post', $issue->ID ) );

    nocache_headers();
    header( 'X-Robots-Tag: noindex, nofollow' );
    header( 'Referrer-Policy: no-referrer' );

    if ( ! $allowed ) {
        status_header( 404 );
        header( 'Content-Type: text/html; charset=utf-8' );
        echo '<!doctype html><meta charset="utf-8"><title>Not found</title><p>Not found.</p>';
        exit;
    }

    if ( ! $public || $has_key ) {
        header( 'Cache-Control: no-store, private, max-age=0' );
    }
    status_header( 200 );
    header( 'Content-Type: text/html; charset=utf-8' );
    get_template_part( 'template-parts/newsletter-email', null, array( 'issue' => $issue ) );
    exit;
}

/**
 * Absolute https URL (email clients and Mailchimp need full addresses).
 */
function mbc_nl_abs_url( $url ) {
    $url = (string) $url;
    if ( '' === $url ) {
        return '';
    }
    if ( 0 === strpos( $url, '//' ) ) {
        $url = 'https:' . $url;
    } elseif ( '/' === $url[0] ) {
        $url = home_url( $url );
    }
    return set_url_scheme( $url, 'https' );
}

/**
 * The intro as email HTML: paragraphs, links and lists with inline styles and absolute links.
 */
function mbc_nl_email_intro_html( $content, array $s ) {
    $html = wpautop( wp_kses( (string) $content, mbc_nl_intro_allowed_html() ) );
    $html = preg_replace_callback( '/<a\s+([^>]*)>/i', static function ( $m ) use ( $s ) {
        $attrs = preg_replace_callback( '/href="([^"]*)"/i', static function ( $h ) {
            return 'href="' . esc_url( mbc_nl_abs_url( html_entity_decode( $h[1] ) ) ) . '"';
        }, $m[1] );
        return '<a ' . $attrs . ' style="' . esc_attr( $s['a'] ) . '">';
    }, (string) $html );
    $html = preg_replace( '/<p>/i', '<p style="' . esc_attr( $s['p'] ) . '">', (string) $html );
    $html = preg_replace( '/<(ul|ol)>/i', '<$1 style="' . esc_attr( $s['list'] ) . '">', (string) $html );
    $html = preg_replace( '/<li>/i', '<li style="' . esc_attr( $s['li'] ) . '">', (string) $html );
    return (string) $html;
}

/**
 * The issue's header image: its own featured image, else the default header image (the media item
 * whose ID is in the option mbc_nl_default_header_id, filter mbc_newsletter_default_header_id).
 *
 * @param int|WP_Post $issue Issue.
 * @return int Attachment ID, or 0 for none (callers then fall back to the logo).
 */
function mbc_nl_header_id( $issue ) {
    $id = (int) get_post_thumbnail_id( $issue );
    if ( ! $id ) {
        $id = (int) apply_filters( 'mbc_newsletter_default_header_id', (int) get_option( MBC_NL_DEFAULT_HEADER_OPTION, 0 ) );
    }
    return ( $id && wp_attachment_is_image( $id ) ) ? $id : 0;
}

/**
 * Image data for an email: absolute https URL, and the width/height to show it at.
 *
 * @return array{src:string,width:int,height:int}|null
 */
function mbc_nl_email_image( $attachment_id, $max_width ) {
    $img = $attachment_id ? wp_get_attachment_image_src( $attachment_id, 'large' ) : false;
    if ( ! $img || empty( $img[1] ) || empty( $img[2] ) ) {
        return null;
    }
    $w = min( (int) $img[1], (int) $max_width );
    $h = (int) round( $img[2] * $w / $img[1] );
    return array( 'src' => mbc_nl_abs_url( $img[0] ), 'width' => $w, 'height' => $h );
}
