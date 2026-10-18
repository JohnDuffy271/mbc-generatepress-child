<?php
/**
 * Weekly diary: the "Diary editor" role and the stripped-down admin for it.
 *
 * The role has `read` and `mbc_weekly_diary` only, so wp-admin offers nothing else. A user whose only
 * "real" capability is `mbc_weekly_diary` (see mbc_diary_only_user()) logs in straight to the diary,
 * and sees a quiet admin bar and no Dashboard. Administrators and everyone else are unaffected.
 */

defined( 'ABSPATH' ) || exit;

const MBC_DIARY_ROLE    = 'mbc_diary_editor';
const MBC_DIARY_VERSION = '1'; // Bump to re-sync the role's capabilities once.

/** The role is made once per version; its capabilities are set to exactly read + mbc_weekly_diary. */
add_action( 'init', 'mbc_diary_install', 99 );
function mbc_diary_install() {
    if ( get_option( 'mbc_diary_version' ) === MBC_DIARY_VERSION ) {
        return;
    }
    $caps = array( 'read' => true, MBC_DIARY_CAP => true );
    $role = get_role( MBC_DIARY_ROLE );
    if ( $role ) {
        foreach ( array_keys( $role->capabilities ) as $cap ) {
            if ( ! isset( $caps[ $cap ] ) ) {
                $role->remove_cap( $cap );
            }
        }
        foreach ( $caps as $cap => $grant ) {
            $role->add_cap( $cap, $grant );
        }
    } else {
        add_role( MBC_DIARY_ROLE, __( 'Diary editor', 'generatepress-child' ), $caps );
    }
    update_option( 'mbc_diary_version', MBC_DIARY_VERSION );
}

/** The diary screen's address. */
function mbc_diary_url() {
    return admin_url( 'admin.php?page=mbc-weekly-diary' );
}

/** True when the user can use the diary but has none of the capabilities that make someone an editor or administrator. */
function mbc_diary_only_user( $user = null ) {
    $user = $user instanceof WP_User ? $user : wp_get_current_user();
    if ( ! $user->exists() || ! user_can( $user, MBC_DIARY_CAP ) ) {
        return false;
    }
    foreach ( array( 'manage_options', 'edit_posts', 'edit_pages', 'upload_files', 'edit_theme_options', 'list_users', 'moderate_comments' ) as $cap ) {
        if ( user_can( $user, $cap ) ) {
            return false;
        }
    }
    return true;
}

/** Log in straight to the diary. */
add_filter( 'login_redirect', 'mbc_diary_login_redirect', 10, 3 );
function mbc_diary_login_redirect( $redirect_to, $requested, $user ) {
    return ( $user instanceof WP_User && mbc_diary_only_user( $user ) ) ? mbc_diary_url() : $redirect_to;
}

add_action( 'admin_menu', 'mbc_diary_trim_menu', 999 );
function mbc_diary_trim_menu() {
    if ( mbc_diary_only_user() ) {
        remove_menu_page( 'index.php' );
    }
}

/** The Dashboard screen itself goes to the diary, and has no widgets if it is ever reached. */
add_action( 'load-index.php', 'mbc_diary_skip_dashboard' );
function mbc_diary_skip_dashboard() {
    if ( mbc_diary_only_user() ) {
        wp_safe_redirect( mbc_diary_url() );
        exit;
    }
}

add_action( 'wp_dashboard_setup', 'mbc_diary_clear_widgets', 999 );
function mbc_diary_clear_widgets() {
    global $wp_meta_boxes;
    if ( mbc_diary_only_user() ) {
        $wp_meta_boxes['dashboard'] = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
    }
}

/** Admin bar: the site name (visit the site, and a Dashboard link that goes to the diary), Profile and Log out only. */
add_action( 'admin_bar_menu', 'mbc_diary_trim_admin_bar', 999 );
function mbc_diary_trim_admin_bar( WP_Admin_Bar $bar ) {
    if ( ! mbc_diary_only_user() ) {
        return;
    }
    $keep = array( 'menu-toggle', 'site-name', 'view-site', 'dashboard', 'top-secondary', 'my-account', 'user-actions', 'user-info', 'edit-profile', 'logout' );
    foreach ( $bar->get_nodes() as $id => $node ) {
        if ( ! in_array( $id, $keep, true ) ) {
            $bar->remove_node( $id );
        }
    }
    $dash = $bar->get_node( 'dashboard' );
    if ( $dash ) {
        $dash->href = mbc_diary_url();
        $bar->add_node( $dash );
    }
}
