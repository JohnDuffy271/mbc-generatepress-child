<?php
/**
 * Demo post import support (TEMPORARY).
 *
 * Registers the meta keys bin/import_live_demo_posts.py reads and writes over
 * REST. Remove this file, its require in functions.php and the demo posts
 * before launch (see the CLAUDE.md cutover checklist).
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', 'mbc_register_demo_import_meta' );
function mbc_register_demo_import_meta() {
    $auth = static function () {
        return current_user_can( 'edit_posts' );
    };

    register_post_meta( 'post', 'mbc_demo_import', array(
        'type'              => 'integer',
        'single'            => true,
        'show_in_rest'      => true,
        'sanitize_callback' => 'absint',
        'auth_callback'     => $auth,
    ) );
    register_post_meta( 'post', 'mbc_live_post_id', array(
        'type'              => 'integer',
        'single'            => true,
        'show_in_rest'      => true,
        'sanitize_callback' => 'absint',
        'auth_callback'     => $auth,
    ) );
}
