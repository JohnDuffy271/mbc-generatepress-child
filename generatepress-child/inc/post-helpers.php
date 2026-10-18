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
 * True when any image in the post's content is the same picture as its featured image,
 * so the featured image should not be shown again above the text.
 */
function mbc_featured_repeats_content_image( $post = null ) {
    $post     = get_post( $post );
    $thumb_id = $post ? get_post_thumbnail_id( $post ) : 0;
    if ( ! $thumb_id || ! preg_match_all( '/<img\b[^>]*>/i', $post->post_content, $tags ) ) {
        return false;
    }
    $featured = wp_get_attachment_url( $thumb_id );
    $identity = $featured ? mbc_image_identity( $featured ) : '';
    foreach ( $tags[0] as $tag ) {
        if ( preg_match( '/\bwp-image-' . (int) $thumb_id . '\b/', $tag ) ) {
            return true; // Same attachment.
        }
        if ( '' !== $identity && preg_match( '/\bsrc\s*=\s*["\']([^"\']+)["\']/i', $tag, $src ) && mbc_image_identity( html_entity_decode( $src[1] ) ) === $identity ) {
            return true;
        }
    }
    return false;
}
