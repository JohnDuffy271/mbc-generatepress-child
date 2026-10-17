<?php
/**
 * Events calendar: fetching from the Google Calendar API v3 (server side only), caching and
 * normalising. The browser never talks to Google.
 *
 * Cache: a 15 minute transient plus a "last good" copy. If Google fails (or the key is missing)
 * the last good copy is used and the failure is logged; visitors never see an error. A short
 * "failed recently" transient stops every page view retrying a dead service.
 */

defined( 'ABSPATH' ) || exit;

const MBC_GCAL_TZ = 'Europe/London';

function mbc_calendar_tz() {
    return new DateTimeZone( MBC_GCAL_TZ );
}

/** The active test fixture (option plus the matching secret in ?mbc_cal_fixture=), else null. */
function mbc_calendar_fixture() {
    $fx = get_option( MBC_GCAL_FIXTURE_OPTION );
    if ( ! is_array( $fx ) || empty( $fx['token'] ) || ! isset( $_GET['mbc_cal_fixture'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
        return null;
    }
    $given = sanitize_text_field( wp_unslash( $_GET['mbc_cal_fixture'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
    return hash_equals( (string) $fx['token'], $given ) ? $fx : null;
}

function mbc_calendar_now() {
    $fx = mbc_calendar_fixture();
    if ( $fx && ! empty( $fx['now'] ) ) {
        try {
            return ( new DateTimeImmutable( $fx['now'] ) )->setTimezone( mbc_calendar_tz() );
        } catch ( Exception $e ) { // Fall through to the real time.
            unset( $e );
        }
    }
    return new DateTimeImmutable( 'now', mbc_calendar_tz() );
}

function mbc_calendar_log( $message ) {
    $key = (string) get_option( MBC_GCAL_KEY_OPTION, '' );
    if ( '' !== $key ) {
        $message = str_replace( $key, '[key]', $message );
    }
    error_log( 'MBC calendar: ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
}

/**
 * @param int $weeks How far ahead to look (filter `mbc_calendar_weeks`).
 * @return array{events: array, source: string} source is fresh, last-good or none.
 */
function mbc_calendar_get( $weeks = 8 ) {
    $weeks = (int) apply_filters( 'mbc_calendar_weeks', $weeks );
    $weeks = max( 1, min( 52, $weeks ) );
    $fx    = mbc_calendar_fixture();
    $hash  = $fx ? 'fx' : substr( md5( get_option( MBC_GCAL_ID_OPTION, '' ) . '|' . $weeks ), 0, 12 );
    $tkey  = 'mbc_gcal_' . $hash;
    $lkey  = 'mbc_gcal_last_' . $hash;
    $fkey  = 'mbc_gcal_failed_' . $hash;

    if ( ! $fx ) { // Fixture requests skip the transients so tests control every step.
        $cached = get_transient( $tkey );
        if ( is_array( $cached ) ) {
            return array( 'events' => $cached, 'source' => 'fresh' );
        }
    }

    $items = ( $fx || ! get_transient( $fkey ) ) ? mbc_calendar_request( $weeks, $fx ) : new WP_Error( 'mbc_gcal_backoff', 'Skipped: a request failed recently.' );
    if ( ! is_wp_error( $items ) ) {
        $events = mbc_calendar_normalise( $items );
        if ( ! $fx ) {
            set_transient( $tkey, $events, (int) apply_filters( 'mbc_calendar_cache_seconds', 15 * MINUTE_IN_SECONDS ) );
        }
        update_option( $lkey, array( 'time' => time(), 'events' => $events ), false );
        return array( 'events' => $events, 'source' => 'fresh' );
    }

    if ( 'mbc_gcal_backoff' !== $items->get_error_code() ) {
        mbc_calendar_log( $items->get_error_message() );
        if ( ! $fx ) {
            set_transient( $fkey, 1, 2 * MINUTE_IN_SECONDS );
        }
    }
    $last = get_option( $lkey );
    if ( is_array( $last ) && isset( $last['events'] ) && is_array( $last['events'] ) ) {
        return array( 'events' => $last['events'], 'source' => 'last-good' );
    }
    return array( 'events' => array(), 'source' => 'none' );
}

/** The raw `items` from events.list, or a WP_Error. */
function mbc_calendar_request( $weeks, $fx = null ) {
    if ( $fx ) {
        return empty( $fx['fail'] ) ? ( isset( $fx['items'] ) && is_array( $fx['items'] ) ? $fx['items'] : array() ) : new WP_Error( 'mbc_gcal_fixture', 'Simulated failure (fixture).' );
    }
    $key = (string) get_option( MBC_GCAL_KEY_OPTION, '' );
    $id  = (string) get_option( MBC_GCAL_ID_OPTION, '' );
    if ( '' === $key || '' === $id ) {
        return new WP_Error( 'mbc_gcal_unconfigured', 'API key or calendar ID is not set.' );
    }
    $now = mbc_calendar_now();
    $url = 'https://www.googleapis.com/calendar/v3/calendars/' . rawurlencode( $id ) . '/events?' . http_build_query( array(
        'key'          => $key,
        'singleEvents' => 'true',
        'orderBy'      => 'startTime',
        'timeMin'      => $now->format( DATE_ATOM ),
        'timeMax'      => $now->modify( '+' . (int) $weeks . ' weeks' )->format( DATE_ATOM ),
        'timeZone'     => MBC_GCAL_TZ,
        'maxResults'   => 250,
        'fields'       => 'items(id,status,summary,description,location,start,end)',
    ) );
    $res = wp_remote_get( $url, array( 'timeout' => 6, 'redirection' => 0 ) );
    if ( is_wp_error( $res ) ) {
        return new WP_Error( 'mbc_gcal_http', 'Request failed: ' . $res->get_error_message() );
    }
    $code = (int) wp_remote_retrieve_response_code( $res );
    $json = json_decode( wp_remote_retrieve_body( $res ), true );
    if ( 200 !== $code || ! is_array( $json ) ) {
        $msg = is_array( $json ) && isset( $json['error']['message'] ) ? (string) $json['error']['message'] : 'no detail';
        return new WP_Error( 'mbc_gcal_status', "HTTP $code from Google: $msg" );
    }
    return isset( $json['items'] ) && is_array( $json['items'] ) ? $json['items'] : array();
}

/**
 * Raw Google items to plain arrays (safe to store), sorted. Times are Europe/London.
 * Timed: start_ts/end_ts, start_date/end_date (Y-m-d, the last day it touches), start_iso/end_iso.
 * All-day: the same dates (Google's end date is exclusive; ours is the last day), no times.
 */
function mbc_calendar_normalise( array $items ) {
    $tz     = mbc_calendar_tz();
    $events = array();
    foreach ( $items as $item ) {
        if ( ! is_array( $item ) || ( isset( $item['status'] ) && 'cancelled' === $item['status'] ) || empty( $item['start'] ) ) {
            continue;
        }
        try {
            $e = array(
                'title'       => isset( $item['summary'] ) ? trim( wp_strip_all_tags( (string) $item['summary'] ) ) : '',
                'location'    => isset( $item['location'] ) ? trim( wp_strip_all_tags( (string) $item['location'] ) ) : '',
                'description' => isset( $item['description'] ) ? mbc_calendar_clean_description( (string) $item['description'] ) : '',
            );
            if ( '' === $e['title'] ) {
                $e['title'] = __( '(Untitled event)', 'generatepress-child' );
            }
            if ( ! empty( $item['start']['date'] ) ) {
                $start = new DateTimeImmutable( $item['start']['date'], $tz );
                $end   = ! empty( $item['end']['date'] ) ? new DateTimeImmutable( $item['end']['date'], $tz ) : $start->modify( '+1 day' );
                $last  = $end->modify( '-1 day' );
                if ( $last < $start ) {
                    $last = $start;
                }
                $e += array(
                    'all_day'    => true,
                    'start_ts'   => $start->getTimestamp(),
                    'end_ts'     => $last->modify( '+1 day' )->getTimestamp(),
                    'start_date' => $start->format( 'Y-m-d' ),
                    'end_date'   => $last->format( 'Y-m-d' ),
                    'start_iso'  => $start->format( 'Y-m-d' ),
                    'end_iso'    => $last->format( 'Y-m-d' ),
                );
            } elseif ( ! empty( $item['start']['dateTime'] ) ) {
                $start = ( new DateTimeImmutable( $item['start']['dateTime'] ) )->setTimezone( $tz );
                $end   = ! empty( $item['end']['dateTime'] ) ? ( new DateTimeImmutable( $item['end']['dateTime'] ) )->setTimezone( $tz ) : $start;
                if ( $end < $start ) {
                    $end = $start;
                }
                // An event ending exactly at midnight belongs to the day before.
                $last = ( $end > $start && '00:00:00' === $end->format( 'H:i:s' ) ) ? $end->modify( '-1 second' ) : $end;
                $e += array(
                    'all_day'    => false,
                    'start_ts'   => $start->getTimestamp(),
                    'end_ts'     => $end->getTimestamp(),
                    'start_date' => $start->format( 'Y-m-d' ),
                    'end_date'   => $last->format( 'Y-m-d' ),
                    'start_iso'  => $start->format( DATE_ATOM ),
                    'end_iso'    => $end->format( DATE_ATOM ),
                );
            } else {
                continue;
            }
        } catch ( Exception $ex ) {
            continue; // A malformed date: skip the event rather than fail the page.
        }
        $events[] = $e;
    }
    usort( $events, static function ( $a, $b ) {
        return array( $a['start_date'], $a['all_day'] ? 0 : 1, $a['start_ts'] ) <=> array( $b['start_date'], $b['all_day'] ? 0 : 1, $b['start_ts'] );
    } );
    return $events;
}

/** Google descriptions are HTML or plain text. Keeps links (rel="noopener"), line breaks, bold and italic only. */
function mbc_calendar_clean_description( $html ) {
    $html = preg_replace( '#<(script|style|iframe|object|embed|noscript|template)\b[^>]*>.*?</\1\s*>#is', '', $html ) ?? '';
    $html = preg_replace( '#</?(script|style|iframe|object|embed|noscript|template)\b[^>]*>#i', '', $html ) ?? '';
    if ( ! preg_match( '#<[a-z/][^>]*>#i', $html ) ) {
        $html = nl2br( esc_html( $html ), false );
    } else {
        $html = preg_replace( '#</(p|div|li|h[1-6])\s*>#i', '<br>', $html ) ?? '';
    }
    $html = wp_kses(
        $html,
        array(
            'a'      => array( 'href' => true ),
            'br'     => array(),
            'strong' => array(),
            'b'      => array(),
            'em'     => array(),
            'i'      => array(),
        ),
        array( 'http', 'https', 'mailto', 'tel' )
    );
    $html = preg_replace( '#<a\s#i', '<a rel="noopener" ', $html ) ?? '';
    $html = preg_replace( '#(?:\s*<br\s*/?>\s*){3,}#i', '<br><br>', $html ) ?? '';
    $html = preg_replace( '#^(?:\s*<br\s*/?>)+|(?:<br\s*/?>\s*)+$#i', '', $html ) ?? '';
    return trim( $html );
}
