<?php
/**
 * Logo flag: a media item can be marked "This is a logo" (a checkbox on the attachment screen and in the media
 * dialog, stored as the post meta _mbc_is_logo). Wherever the theme prints a flagged image (section cards, the
 * Image and Media & Text blocks) it gets the class mbc-is-logo, which css/patterns.css shows whole (object-fit:
 * contain) on a neutral background (--mbc-logo-bg), where photos keep cropping to fill their frame.
 */

defined( 'ABSPATH' ) || exit;

const MBC_LOGO_META = '_mbc_is_logo';

add_action( 'init', 'mbc_logo_register_meta' );
function mbc_logo_register_meta() {
    register_post_meta(
        'attachment',
        MBC_LOGO_META,
        array(
            'type'          => 'boolean',
            'single'        => true,
            'default'       => false,
            'show_in_rest'  => true,
            'auth_callback' => static function ( $allowed, $meta_key, $post_id ) {
                return current_user_can( 'edit_post', $post_id );
            },
        )
    );
}

function mbc_is_logo( $attachment_id ) {
    return $attachment_id && (bool) get_post_meta( (int) $attachment_id, MBC_LOGO_META, true );
}

// The checkbox, on the attachment edit screen and in the media dialog.
add_filter( 'attachment_fields_to_edit', 'mbc_logo_field', 10, 2 );
function mbc_logo_field( $fields, $post ) {
    if ( ! wp_attachment_is_image( $post ) ) {
        return $fields;
    }
    $id               = (int) $post->ID;
    $fields['mbc_is_logo'] = array(
        'label' => __( 'Logo', 'generatepress-child' ),
        'input' => 'html',
        'html'  => '<input type="hidden" name="attachments[' . $id . '][mbc_is_logo]" value="0" />'
            . '<label><input type="checkbox" name="attachments[' . $id . '][mbc_is_logo]" value="1"' . checked( mbc_is_logo( $id ), true, false ) . ' /> '
            . esc_html__( 'This is a logo (show it whole, never cropped)', 'generatepress-child' ) . '</label>',
        'helps' => __( 'Logos are shown in full on a plain background in cards and bands. Leave unticked for photos.', 'generatepress-child' ),
    );
    return $fields;
}

// A hidden 0 comes before the box with the same name, so an unticked box still saves (the last value wins).
add_filter( 'attachment_fields_to_save', 'mbc_logo_field_save', 10, 2 );
function mbc_logo_field_save( $post, $attachment ) {
    if ( isset( $attachment['mbc_is_logo'] ) ) {
        update_post_meta( $post['ID'], MBC_LOGO_META, '1' === (string) $attachment['mbc_is_logo'] ? 1 : 0 );
    }
    return $post;
}

// Images printed by WordPress functions (cards, featured images).
add_filter( 'wp_get_attachment_image_attributes', 'mbc_logo_image_class', 10, 2 );
function mbc_logo_image_class( $attr, $attachment ) {
    if ( mbc_is_logo( $attachment->ID ) ) {
        $attr['class'] = trim( ( isset( $attr['class'] ) ? $attr['class'] : '' ) . ' mbc-is-logo' );
    }
    return $attr;
}

// Images in block content carry wp-image-<ID>; flag the <img> of the Image and Media & Text blocks.
add_filter( 'render_block_core/image', 'mbc_logo_block_class' );
add_filter( 'render_block_core/media-text', 'mbc_logo_block_class' );
function mbc_logo_block_class( $html ) {
    if ( false === strpos( $html, 'wp-image-' ) ) {
        return $html;
    }
    $tags = new WP_HTML_Tag_Processor( $html );
    while ( $tags->next_tag( 'img' ) ) {
        $class = (string) $tags->get_attribute( 'class' );
        if ( preg_match( '/\bwp-image-(\d+)\b/', $class, $m ) && mbc_is_logo( (int) $m[1] ) ) {
            $tags->add_class( 'mbc-is-logo' );
        }
    }
    return $tags->get_updated_html();
}
