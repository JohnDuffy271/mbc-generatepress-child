<?php
/**
 * Image sizes: accurate "sizes" attributes for the image slots that WordPress cannot work out for itself, and one
 * intermediate image size. See docs/image-standards.md.
 *
 * WordPress writes sizes="(max-width: Wpx) 100vw, Wpx" from the file's own width, which tells the browser every
 * image fills the screen up to its full size. In a 3-column card grid that makes it fetch a 1536px file for a
 * 355px box. The values below come from `~/src/mbc-tests/bin/measure_slots.py` and a width sweep (CSS px):
 *
 *   slot                          360   600   768  1024  1280
 *   News and listing cards        326   566   354   313   355
 *   Slideshow slides              328   568   352   480   544
 *   Team grid photo               156   179   172   152   146
 *   Team minister photo           328   568   221   298   336
 *   Newsletter list thumbnail     326 (stacked below 512px) then 180
 *
 * When a layout changes, re-measure and update this table and `mbc_image_sizes_map()`.
 */

defined( 'ABSPATH' ) || exit;

// One intermediate size, uncropped, so a ~330px slot is not served the 768px file (WordPress's own steps jump from
// 300 to 768). New uploads only: existing images are not regenerated.
add_action( 'after_setup_theme', 'mbc_add_image_sizes' );
function mbc_add_image_sizes() {
    add_image_size( 'mbc-600', 600, 0, false );
}

/**
 * The sizes attribute for each slot, by name.
 *
 * @return string[]
 */
function mbc_image_sizes_map() {
    return array(
        'card'      => '(max-width: 600px) calc(100vw - 2rem), 360px',
        'slide'     => '(max-width: 600px) calc(100vw - 2rem), (max-width: 1184px) calc(50vw - 2rem), 544px',
        'person'    => '180px',
        'minister'  => '(max-width: 600px) calc(100vw - 2rem), 340px',
        'nl-thumb'  => '(max-width: 511px) calc(100vw - 2rem), ' . MBC_NL_THUMB_WIDTH . 'px',
    );
}

/**
 * Sets sizes="..." on every <img> in $html (adds it when missing; WordPress then adds srcset and keeps it).
 */
function mbc_set_img_sizes( $html, $sizes ) {
    return preg_replace_callback(
        '/<img\b[^>]*>/i',
        static function ( $m ) use ( $sizes ) {
            $attr = ' sizes="' . esc_attr( $sizes ) . '"';
            if ( preg_match( '/\ssizes=("[^"]*"|\'[^\']*\')/i', $m[0] ) ) {
                return preg_replace( '/\ssizes=("[^"]*"|\'[^\']*\')/i', $attr, $m[0], 1 );
            }
            return preg_replace( '/<img\b/i', '<img' . $attr, $m[0], 1 );
        },
        $html
    );
}

// Images inside a post's text sit in the single-post column: 328px at 360px wide, up to 704px (docs/image-standards.md, `post`).
// WordPress would say "(max-width: <file width>px) 100vw, <file width>px" and the browser would fetch the biggest file.
add_filter( 'wp_calculate_image_sizes', 'mbc_post_content_image_sizes', 10, 1 );
function mbc_post_content_image_sizes( $sizes ) {
    if ( is_singular( 'post' ) && doing_filter( 'the_content' ) ) {
        return '(max-width: 760px) calc(100vw - 2rem), 704px';
    }
    return $sizes;
}

add_filter( 'render_block', 'mbc_image_sizes_for_block', 10, 2 );
function mbc_image_sizes_for_block( $html, $block ) {
    if ( '' === $html || empty( $block['blockName'] ) || false === stripos( $html, '<img' ) ) {
        return $html;
    }
    $class = isset( $block['attrs']['className'] ) ? ' ' . $block['attrs']['className'] . ' ' : '';
    if ( '' === trim( $class ) ) {
        return $html;
    }
    $map = mbc_image_sizes_map();
    $has = static function ( $name ) use ( $class ) {
        return false !== strpos( $class, ' ' . $name . ' ' );
    };
    switch ( $block['blockName'] ) {
        case 'core/post-featured-image':
            if ( $has( 'mbc-card__image' ) ) {
                return mbc_set_img_sizes( $html, $map['card'] );
            }
            break;
        case 'core/gallery':
            if ( $has( 'mbc-slideshow' ) ) {
                return mbc_set_img_sizes( $html, $map['slide'] );
            }
            break;
        case 'core/group':
            if ( $has( 'mbc-person' ) ) {
                return mbc_set_img_sizes( $html, $map['person'] );
            }
            break;
        case 'core/media-text':
            if ( $has( 'mbc-team__minister' ) ) {
                return mbc_set_img_sizes( $html, $map['minister'] );
            }
            break;
    }
    return $html;
}
