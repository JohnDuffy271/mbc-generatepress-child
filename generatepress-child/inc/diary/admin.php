<?php
/**
 * Weekly diary: the top-level Weekly diary screen and its REST routes (capability mbc_weekly_diary).
 * The routes are what the screen's script uses to save notes and refresh the output; they are
 * admin-only and nothing here is reachable by visitors.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', 'mbc_diary_menu' );
function mbc_diary_menu() {
    $hook = add_menu_page( 'Weekly diary', 'Weekly diary', MBC_DIARY_CAP, 'mbc-weekly-diary', 'mbc_diary_screen', 'dashicons-calendar-alt', 3 );
    add_action( 'admin_print_scripts-' . $hook, 'mbc_diary_assets' );
}

function mbc_diary_assets() {
    $dir = get_stylesheet_directory();
    $uri = get_stylesheet_directory_uri();
    wp_enqueue_style( 'mbc-diary-admin', $uri . '/css/diary-admin.css', array(), filemtime( $dir . '/css/diary-admin.css' ) );
    wp_enqueue_script( 'mbc-diary-admin', $uri . '/js/diary-admin.js', array(), filemtime( $dir . '/js/diary-admin.js' ), true );
    $fixture = isset( $_GET['mbc_cal_fixture'] ) ? sanitize_text_field( wp_unslash( $_GET['mbc_cal_fixture'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
    wp_add_inline_script( 'mbc-diary-admin', 'window.mbcDiary = ' . wp_json_encode( array(
        'root'    => esc_url_raw( rest_url( 'mbc/v1/' ) ),
        'nonce'   => wp_create_nonce( 'wp_rest' ),
        'fixture' => $fixture,
    ) ) . ';', 'before' );
}

add_action( 'rest_api_init', 'mbc_diary_routes' );
function mbc_diary_routes() {
    $can = static function () {
        return current_user_can( MBC_DIARY_CAP );
    };
    register_rest_route( 'mbc/v1', '/diary', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => static function ( WP_REST_Request $r ) {
            return rest_ensure_response( mbc_diary_build( $r->get_param( 'from' ), $r->get_param( 'to' ), (bool) $r->get_param( 'refresh' ) ) );
        },
        'permission_callback' => $can,
    ) );
    register_rest_route( 'mbc/v1', '/diary/note', array(
        array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => static function ( WP_REST_Request $r ) {
                $key = (string) $r->get_param( 'key' );
                if ( ! mbc_diary_valid_key( $key ) ) {
                    return new WP_Error( 'mbc_diary_key', 'Not a valid note key.', array( 'status' => 400 ) );
                }
                $text = mbc_diary_save_note( $key, (string) $r->get_param( 'text' ), (string) $r->get_param( 'title' ) );
                return rest_ensure_response( array( 'key' => $key, 'text' => $text ) );
            },
            'permission_callback' => $can,
        ),
        array(
            'methods'             => WP_REST_Server::DELETABLE,
            'callback'            => static function ( WP_REST_Request $r ) {
                $key = (string) $r->get_param( 'key' );
                if ( ! mbc_diary_valid_key( $key ) ) {
                    return new WP_Error( 'mbc_diary_key', 'Not a valid note key.', array( 'status' => 400 ) );
                }
                return rest_ensure_response( array( 'key' => $key, 'deleted' => mbc_diary_delete_note( $key ) ) );
            },
            'permission_callback' => $can,
        ),
    ) );
}

/** One section's rows: the line, and an inline note box. */
function mbc_diary_rows_html( array $entries ) {
    if ( ! $entries ) {
        return '<p class="mbc-diary__none">Nothing in this period.</p>';
    }
    $h = '<ul class="mbc-diary__list">';
    foreach ( $entries as $en ) {
        $h .= '<li class="mbc-diary__row"><p class="mbc-diary__line">' . esc_html( $en['line'] ) . '</p>';
        if ( '' === $en['key'] ) {
            $h .= '<p class="description">This event has no id, so a note cannot be kept for it.</p>';
        } else {
            $id    = 'mbc-note-' . md5( $en['key'] );
            $scope = 'series' === $en['kind'] ? 'every week of this repeating event' : 'this event only';
            $h    .= '<label class="mbc-diary__label" for="' . esc_attr( $id ) . '">Note for ' . esc_html( $en['title'] ) . ' <span class="description">(' . esc_html( $scope ) . ', members only)</span></label>'
                . '<input type="text" class="large-text mbc-diary__note" id="' . esc_attr( $id ) . '" maxlength="' . (int) MBC_DIARY_NOTE_MAX . '" value="' . esc_attr( $en['note'] ) . '" data-key="' . esc_attr( $en['key'] ) . '" data-title="' . esc_attr( $en['title'] ) . '" data-saved="' . esc_attr( $en['note'] ) . '" />'
                . '<span class="mbc-diary__status" aria-live="polite"></span>';
        }
        $h .= '</li>';
    }
    return $h . '</ul>';
}

function mbc_diary_orphans_html( $orphans ) {
    if ( null === $orphans ) {
        return '<p class="description">The calendar could not be read, so unused notes cannot be checked just now.</p>';
    }
    if ( ! $orphans ) {
        return '<p class="mbc-diary__none">No unused notes.</p>';
    }
    $h = '<ul class="mbc-diary__orphans">';
    foreach ( $orphans as $o ) {
        $h .= '<li><strong>' . esc_html( '' !== $o['title'] ? $o['title'] : $o['key'] ) . '</strong>: ' . esc_html( $o['note'] )
            . ' <button type="button" class="button-link-delete mbc-diary__delete" data-key="' . esc_attr( $o['key'] ) . '">Delete note</button></li>';
    }
    return $h . '</ul>';
}

function mbc_diary_screen() {
    if ( ! current_user_can( MBC_DIARY_CAP ) ) {
        wp_die( esc_html__( 'You do not have permission to open this page.', 'generatepress-child' ), 403 );
    }
    // phpcs:disable WordPress.Security.NonceVerification -- read-only filters; "refresh" only empties a cache.
    $from    = isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : null;
    $to      = isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : null;
    $refresh = ! empty( $_GET['refresh'] );
    $fixture = isset( $_GET['mbc_cal_fixture'] ) ? sanitize_text_field( wp_unslash( $_GET['mbc_cal_fixture'] ) ) : '';
    // phpcs:enable
    $d = mbc_diary_build( $from, $to, $refresh );

    echo '<div class="wrap mbc-diary"><h1>Weekly diary</h1>';
    echo '<p>For the weekly members\' email. Notes are for members and staff only: they are kept on this website, never sent to Google and never shown to the public.</p>';
    echo '<form method="get" class="mbc-diary__range"><input type="hidden" name="page" value="mbc-weekly-diary" />';
    if ( '' !== $fixture ) {
        echo '<input type="hidden" name="mbc_cal_fixture" value="' . esc_attr( $fixture ) . '" />';
    }
    echo '<label for="mbc-from">From</label> <input type="date" id="mbc-from" name="from" value="' . esc_attr( $d['from'] ) . '" /> ';
    echo '<label for="mbc-to">To (included)</label> <input type="date" id="mbc-to" name="to" value="' . esc_attr( $d['to'] ) . '" /> ';
    echo '<button type="submit" class="button button-primary">Show</button> ';
    echo '<button type="submit" name="refresh" value="1" class="button">Refresh from the calendar</button></form>';

    if ( 'fresh' !== $d['source'] ) {
        $msg = 'none' === $d['source'] ? 'The calendar could not be read and there is no earlier copy.' : 'The calendar could not be reached, so this is the last copy that was saved. It may be out of date.';
        echo '<div class="notice notice-warning inline"><p>' . esc_html( $msg ) . '</p></div>';
    }

    echo '<div id="mbc-diary-body">';
    echo '<h2>This week</h2>' . mbc_diary_rows_html( $d['week'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside.
    echo '<h2>Coming up</h2><p class="description">One-off events from the day after the range, for the next six weeks.</p>' . mbc_diary_rows_html( $d['coming'] ); // phpcs:ignore WordPress.Security.EscapeOutput
    echo '</div>';

    echo '<h2>For the email</h2><p><button type="button" class="button button-primary" id="mbc-diary-copy">Copy for email</button> <span id="mbc-diary-copy-status" aria-live="polite"></span></p>';
    echo '<div id="mbc-diary-rich" class="mbc-diary__rich">' . $d['html'] . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- built from esc_html in mbc_diary_output().
    echo '<p><label for="mbc-diary-plain">Plain text</label></p><textarea id="mbc-diary-plain" class="large-text code" rows="12" readonly>' . esc_textarea( $d['text'] ) . '</textarea>';

    echo '<h2>Notes no longer used</h2><p class="description">These notes belong to events or repeating events that are no longer in the calendar.</p>';
    echo '<div id="mbc-diary-orphans" data-from="' . esc_attr( $d['from'] ) . '" data-to="' . esc_attr( $d['to'] ) . '">' . mbc_diary_orphans_html( $d['orphans'] ) . '</div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
}
