<?php
/**
 * Events calendar: settings and the admin-only REST route that sets them.
 *
 * The Google Calendar API key and calendar ID are plain options, deliberately NOT registered with
 * register_setting(), so they never appear in the public REST settings. Nothing here is in git.
 * Set them with bin/set_gcal_options.py (reads ~/.config/mbc/gcal.env, never prints the key).
 */

defined( 'ABSPATH' ) || exit;

const MBC_GCAL_KEY_OPTION     = 'mbc_gcal_api_key';
const MBC_GCAL_ID_OPTION      = 'mbc_gcal_calendar_id';
const MBC_GCAL_FIXTURE_OPTION = 'mbc_gcal_fixture'; // Test data, honoured only with its secret token in the URL.

add_action( 'rest_api_init', 'mbc_calendar_register_routes' );
function mbc_calendar_register_routes() {
    register_rest_route( 'mbc/v1', '/calendar-config', array(
        array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => 'mbc_calendar_rest_status',
            'permission_callback' => 'mbc_calendar_rest_can',
        ),
        array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => 'mbc_calendar_rest_update',
            'permission_callback' => 'mbc_calendar_rest_can',
        ),
    ) );
}

function mbc_calendar_rest_can() {
    return current_user_can( 'manage_options' );
}

/** Says whether it is configured; never returns the key. `?outbound_ip=1` asks the server for its outbound address. */
function mbc_calendar_rest_status( WP_REST_Request $request ) {
    $data = array(
        'has_key'     => '' !== (string) get_option( MBC_GCAL_KEY_OPTION, '' ),
        'calendar_id' => (string) get_option( MBC_GCAL_ID_OPTION, '' ),
        'has_fixture' => is_array( get_option( MBC_GCAL_FIXTURE_OPTION ) ),
    );
    if ( $request->get_param( 'outbound_ip' ) ) {
        $res = wp_remote_get( 'https://api.ipify.org', array( 'timeout' => 8 ) );
        $data['outbound_ip'] = is_wp_error( $res ) ? null : trim( wp_remote_retrieve_body( $res ) );
    }
    return rest_ensure_response( $data );
}

/**
 * Body (JSON), all optional: api_key, calendar_id, purge (clear cached and last-good copies),
 * fixture (object, sets test data) or fixture:null (clears it).
 */
function mbc_calendar_rest_update( WP_REST_Request $request ) {
    $body = $request->get_json_params();
    $body = is_array( $body ) ? $body : array();
    if ( isset( $body['api_key'] ) ) {
        update_option( MBC_GCAL_KEY_OPTION, sanitize_text_field( $body['api_key'] ), false );
    }
    if ( isset( $body['calendar_id'] ) ) {
        update_option( MBC_GCAL_ID_OPTION, sanitize_text_field( $body['calendar_id'] ), false );
    }
    if ( array_key_exists( 'fixture', $body ) ) {
        if ( is_array( $body['fixture'] ) ) {
            update_option( MBC_GCAL_FIXTURE_OPTION, $body['fixture'], false );
        } else {
            delete_option( MBC_GCAL_FIXTURE_OPTION );
        }
    }
    if ( ! empty( $body['purge'] ) || isset( $body['api_key'] ) || isset( $body['calendar_id'] ) ) {
        mbc_calendar_purge();
    }
    return mbc_calendar_rest_status( $request );
}

/** Deletes every cached and last-good copy (including the test fixture's). */
function mbc_calendar_purge() {
    global $wpdb;
    $like = $wpdb->esc_like( 'mbc_gcal_last_' ) . '%';
    $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like ) );
    foreach ( array( '_transient_mbc_gcal_', '_transient_timeout_mbc_gcal_' ) as $prefix ) {
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( $prefix ) . '%' ) );
    }
    wp_cache_flush();
}
