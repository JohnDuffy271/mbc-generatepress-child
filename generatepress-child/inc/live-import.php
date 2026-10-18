<?php
/**
 * Live-post import support: the meta keys bin/import_live_posts.py reads and writes over REST.
 * Kept until the content migration is finished (the demo import has its own file, inc/demo-import.php).
 */

defined( 'ABSPATH' ) || exit;

// Attachments do not support custom fields (and so REST meta) by default.
add_post_type_support( 'attachment', 'custom-fields' );

add_action( 'init', 'mbc_register_live_import_meta' );
function mbc_register_live_import_meta() {
    $auth = static function () {
        return current_user_can( 'edit_posts' );
    };

    // Posts: the live post's ID and modified date (GMT), and the consent-review flag
    // (a protected key, hence the auth callback) for the pass before cutover.
    register_post_meta( 'post', 'mbc_live_id', array(
        'type'              => 'integer',
        'single'            => true,
        'show_in_rest'      => true,
        'sanitize_callback' => 'absint',
        'auth_callback'     => $auth,
    ) );
    register_post_meta( 'post', 'mbc_live_modified', array(
        'type'              => 'string',
        'single'            => true,
        'show_in_rest'      => true,
        'sanitize_callback' => 'sanitize_text_field',
        'auth_callback'     => $auth,
    ) );
    register_post_meta( 'post', '_mbc_needs_consent_review', array(
        'type'              => 'integer',
        'single'            => true,
        'show_in_rest'      => true,
        'sanitize_callback' => 'absint',
        'auth_callback'     => $auth,
    ) );
    // Media: alt text drafted automatically (to be reviewed in the consent pass), a protected key like the flag above.
    register_post_meta( 'attachment', '_mbc_alt_auto', array(
        'type'              => 'integer',
        'single'            => true,
        'show_in_rest'      => true,
        'sanitize_callback' => 'absint',
        'auth_callback'     => $auth,
    ) );
    // Media: where the file came from on live, so it is never uploaded twice.
    register_post_meta( 'attachment', 'mbc_live_source_url', array(
        'type'              => 'string',
        'single'            => true,
        'show_in_rest'      => true,
        'sanitize_callback' => 'esc_url_raw',
        'auth_callback'     => $auth,
    ) );
}
