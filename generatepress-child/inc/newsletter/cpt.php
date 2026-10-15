<?php
/**
 * Newsletter issues: the content type, its capability and role, and its URLs.
 *
 * An issue is a stored snapshot (title, subtitle, header image, intro and an
 * ordered list of post IDs). It is rendered as a web version, an archive and
 * email-safe HTML for Mailchimp (see email.php). Everything is in git: no code
 * snippets, no shortcodes.
 */

defined( 'ABSPATH' ) || exit;

const MBC_NL_TYPE    = 'mbc_newsletter';
const MBC_NL_SLUG    = 'newsletter';
const MBC_NL_CAP     = 'mbc_manage_newsletter';
const MBC_NL_ROLE    = 'mbc_newsletter_editor';
const MBC_NL_VERSION = '1'; // Bump to re-sync the role's capabilities and flush the rewrite rules once.

add_action( 'init', 'mbc_nl_register_type' );
function mbc_nl_register_type() {
    register_post_type( MBC_NL_TYPE, array(
        'labels'              => array(
            'name'               => __( 'Newsletter issues', 'generatepress-child' ),
            'singular_name'      => __( 'Newsletter issue', 'generatepress-child' ),
            'add_new'            => __( 'Add new issue', 'generatepress-child' ),
            'add_new_item'       => __( 'Add new newsletter issue', 'generatepress-child' ),
            'edit_item'          => __( 'Edit newsletter issue', 'generatepress-child' ),
            'new_item'           => __( 'New newsletter issue', 'generatepress-child' ),
            'view_item'          => __( 'View web version', 'generatepress-child' ),
            'search_items'       => __( 'Search issues', 'generatepress-child' ),
            'not_found'          => __( 'No issues found.', 'generatepress-child' ),
            'not_found_in_trash' => __( 'No issues in the bin.', 'generatepress-child' ),
            'all_items'          => __( 'All issues', 'generatepress-child' ),
            'menu_name'          => __( 'Newsletter', 'generatepress-child' ),
            'featured_image'     => __( 'Header image', 'generatepress-child' ),
            'set_featured_image' => __( 'Set header image', 'generatepress-child' ),
            'remove_featured_image' => __( 'Remove header image', 'generatepress-child' ),
            'use_featured_image' => __( 'Use as header image', 'generatepress-child' ),
        ),
        'public'              => true,
        'exclude_from_search' => true,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'menu_position'       => 25,
        'menu_icon'           => 'dashicons-email-alt',
        'has_archive'         => MBC_NL_SLUG,
        'rewrite'             => array( 'slug' => MBC_NL_SLUG, 'with_front' => false ),
        // custom-fields: REST only exposes registered meta for types that support it (the raw
        // Custom Fields box is removed from the edit screen; see admin.php).
        'supports'            => array( 'title', 'editor', 'thumbnail', 'custom-fields' ),
        'show_in_rest'        => true,
        'rest_base'           => 'newsletters',
        // Only holders of the capability can manage issues. "read" is deliberately
        // NOT mapped: logged-out visitors can still read the archive and published issues.
        'map_meta_cap'        => true,
        'capability_type'     => array( 'mbc_newsletter', 'mbc_newsletters' ),
        'capabilities'        => mbc_nl_type_capabilities(),
    ) );
}

/**
 * Every primitive capability of the type maps to the one custom capability.
 *
 * @return array<string,string>
 */
function mbc_nl_type_capabilities() {
    $primitives = array(
        'create_posts',
        'edit_posts',
        'edit_others_posts',
        'edit_published_posts',
        'edit_private_posts',
        'publish_posts',
        'delete_posts',
        'delete_others_posts',
        'delete_published_posts',
        'delete_private_posts',
        'read_private_posts',
    );
    return array_fill_keys( $primitives, MBC_NL_CAP );
}

// The intro is plain prose (paragraphs, links, lists): the classic editor, not blocks, so nothing
// in an issue can fail to render in an email.
add_filter( 'use_block_editor_for_post_type', 'mbc_nl_no_block_editor', 10, 2 );
function mbc_nl_no_block_editor( $use, $post_type ) {
    return MBC_NL_TYPE === $post_type ? false : $use;
}

// /newsletter/<issue>/email/ (the email version; see email.php).
add_action( 'init', 'mbc_nl_register_email_rule' );
function mbc_nl_register_email_rule() {
    add_rewrite_rule(
        '^' . MBC_NL_SLUG . '/([^/]+)/email/?$',
        'index.php?' . MBC_NL_TYPE . '=$matches[1]&mbc_nl_email=1',
        'top'
    );
}

add_filter( 'query_vars', 'mbc_nl_query_vars' );
function mbc_nl_query_vars( $vars ) {
    $vars[] = 'mbc_nl_email';
    return $vars;
}

/**
 * One-off set-up per MBC_NL_VERSION: the capability for administrators, the
 * "Newsletter editor" role, and a soft rewrite flush (which leaves .htaccess alone).
 * Idempotent; nothing runs on a request once the version matches.
 */
add_action( 'init', 'mbc_nl_install', 99 );
function mbc_nl_install() {
    if ( get_option( 'mbc_nl_version' ) === MBC_NL_VERSION ) {
        return;
    }

    $admin = get_role( 'administrator' );
    if ( $admin ) {
        $admin->add_cap( MBC_NL_CAP );
    }

    // Newsletter editor = everything an Editor can do, the newsletter capability, and
    // menu management (edit_theme_options). Elementor needs nothing more for an Editor:
    // it checks edit_posts / edit_post and that the role is not in its exclusion list.
    $editor = get_role( 'editor' );
    $caps   = $editor ? $editor->capabilities : array( 'read' => true );
    $caps[ MBC_NL_CAP ]       = true;
    $caps['edit_theme_options'] = true;

    $role = get_role( MBC_NL_ROLE );
    if ( $role ) {
        foreach ( $caps as $cap => $grant ) {
            $role->add_cap( $cap, $grant );
        }
    } else {
        add_role( MBC_NL_ROLE, __( 'Newsletter editor', 'generatepress-child' ), $caps );
    }

    flush_rewrite_rules( false );
    update_option( 'mbc_nl_version', MBC_NL_VERSION );
}

// ---------------------------------------------------------------------------
// Issue data. Subtitle and post list are registered meta; the preview key is
// protected meta that is deliberately never exposed over REST.
// ---------------------------------------------------------------------------

add_action( 'init', 'mbc_nl_register_meta' );
function mbc_nl_register_meta() {
    $auth = static function () {
        return current_user_can( MBC_NL_CAP );
    };
    register_post_meta( MBC_NL_TYPE, 'mbc_nl_subtitle', array(
        'type'              => 'string',
        'single'            => true,
        'show_in_rest'      => true,
        'sanitize_callback' => 'sanitize_text_field',
        'auth_callback'     => $auth,
    ) );
    register_post_meta( MBC_NL_TYPE, 'mbc_nl_posts', array(
        'type'              => 'array',
        'single'            => true,
        'show_in_rest'      => array(
            'schema' => array(
                'type'  => 'array',
                'items' => array( 'type' => 'integer' ),
            ),
        ),
        'sanitize_callback' => 'mbc_nl_sanitise_post_ids',
        'auth_callback'     => $auth,
    ) );
}

/**
 * @param mixed $value Raw list of post IDs.
 * @return int[] Unique positive integers, order kept.
 */
function mbc_nl_sanitise_post_ids( $value ) {
    $ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $value ) ) ) );
    return array_slice( $ids, 0, 50 );
}

/**
 * @param int $issue_id Issue post ID.
 * @return int[] The issue's stored post IDs, in order.
 */
function mbc_nl_get_post_ids( $issue_id ) {
    return mbc_nl_sanitise_post_ids( get_post_meta( (int) $issue_id, 'mbc_nl_posts', true ) );
}

// Preview key: lets Mailchimp (or anyone holding the link) fetch the email for any status.

/**
 * @return string The issue's key, created on first use.
 */
function mbc_nl_get_key( $issue_id ) {
    $key = (string) get_post_meta( (int) $issue_id, '_mbc_nl_key', true );
    if ( strlen( $key ) < 32 ) {
        $key = mbc_nl_regenerate_key( $issue_id );
    }
    return $key;
}

function mbc_nl_regenerate_key( $issue_id ) {
    $key = bin2hex( random_bytes( 20 ) ); // 40 hex characters.
    update_post_meta( (int) $issue_id, '_mbc_nl_key', $key );
    return $key;
}

/**
 * Constant-time check of a supplied key against the issue's key.
 */
function mbc_nl_key_matches( $issue_id, $supplied ) {
    $key = (string) get_post_meta( (int) $issue_id, '_mbc_nl_key', true );
    return strlen( $key ) >= 32 && is_string( $supplied ) && hash_equals( $key, $supplied );
}

/**
 * URL of an issue's email version, with the key if asked.
 */
function mbc_nl_email_url( $issue_id, $with_key = false ) {
    $post = get_post( $issue_id );
    if ( ! $post || '' === $post->post_name ) {
        return '';
    }
    $url = home_url( user_trailingslashit( MBC_NL_SLUG . '/' . $post->post_name . '/email' ) );
    return $with_key ? add_query_arg( 'key', mbc_nl_get_key( $post->ID ), $url ) : $url;
}

require_once __DIR__ . '/admin.php';
