<?php
/**
 * Helpers for the single post template.
 */

defined( 'ABSPATH' ) || exit;

/**
 * A comparable identity for an image URL: the file name without extension
 * and without WordPress's "-scaled" and "-WIDTHxHEIGHT" suffixes (and the
 * "-N" it adds when a file name clashes), so a resized copy and its original
 * count as the same picture.
 */
function mbc_image_identity( $url ) {
    $name = strtolower( pathinfo( (string) wp_parse_url( $url, PHP_URL_PATH ), PATHINFO_FILENAME ) );
    do {
        $before = $name;
        $name   = preg_replace( '/-(?:scaled|\d+x\d+)(?:-\d+)?$/', '', $name ); // Optional -N: WordPress's rename on a file name clash.
    } while ( $name !== $before );
    return $name;
}

/**
 * True when the post's first content image is the same picture as its
 * featured image, so the featured image should not be shown again.
 */
function mbc_featured_repeats_first_content_image( $post = null ) {
    $post     = get_post( $post );
    $thumb_id = $post ? get_post_thumbnail_id( $post ) : 0;
    if ( ! $thumb_id || ! preg_match( '/<img\b[^>]*>/i', $post->post_content, $tag ) ) {
        return false;
    }
    if ( preg_match( '/\bwp-image-' . (int) $thumb_id . '\b/', $tag[0] ) ) {
        return true; // Same attachment.
    }
    $featured = wp_get_attachment_url( $thumb_id );
    if ( ! $featured || ! preg_match( '/\bsrc\s*=\s*["\']([^"\']+)["\']/i', $tag[0], $src ) ) {
        return false;
    }
    return mbc_image_identity( $featured ) === mbc_image_identity( html_entity_decode( $src[1] ) );
}
