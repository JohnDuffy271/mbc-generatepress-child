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
 * Events from now to `$weeks` weeks ahead (filter `mbc_calendar_weeks`). A wrapper over
 * mbc_calendar_get_range(); "now" moves, so its cache is keyed by the number of weeks.
 *
 * @param int $weeks How far ahead to look.
 * @return array{events: array, source: string} source is fresh, last-good or none.
 */
function mbc_calendar_get( $weeks = 8 ) {
    $weeks = (int) apply_filters( 'mbc_calendar_weeks', $weeks );
    $weeks = max( 1, min( 52, $weeks ) );
    $now   = mbc_calendar_now();
    return mbc_calendar_get_range( $now, $now->modify( '+' . $weeks . ' weeks' ), array( 'key' => (string) $weeks ) );
}

/**
 * Events overlapping [$from, $to) (Europe/London; singleEvents, ordered by start, `recurring`
 * set from recurringEventId, cancelled instances dropped). Own cache key per range, with the
 * same last-good and failure handling as the agenda.
 *
 * @param DateTimeInterface $from Start of the range.
 * @param DateTimeInterface $to   End of the range (exclusive).
 * @param array             $args `key` cache identity (default: the two dates, so use a fixed
 *                                key for ranges that start at "now"); `refresh` bypasses the cache.
 * @return array{events: array, source: string} source is fresh, last-good or none.
 */
function mbc_calendar_get_range( DateTimeInterface $from, DateTimeInterface $to, array $args = array() ) {
    $tz      = mbc_calendar_tz();
    $from    = DateTimeImmutable::createFromInterface( $from )->setTimezone( $tz );
    $to      = DateTimeImmutable::createFromInterface( $to )->setTimezone( $tz );
    $refresh = ! empty( $args['refresh'] );
    $range   = isset( $args['key'] ) ? (string) $args['key'] : $from->format( 'Y-m-d\TH:i' ) . '/' . $to->format( 'Y-m-d\TH:i' );
    $fx      = mbc_calendar_fixture();
    $hash    = $fx ? 'fx' : substr( md5( get_option( MBC_GCAL_ID_OPTION, '' ) . '|' . $range . '|v3' ), 0, 12 );
    $tkey    = 'mbc_gcal_' . $hash;
    $lkey    = 'mbc_gcal_last_' . $hash;
    $fkey    = 'mbc_gcal_failed_' . $hash;

    if ( $refresh ) {
        delete_transient( $tkey );
        delete_transient( $fkey );
    }
    if ( ! $fx ) { // Fixture requests skip the transients so tests control every step.
        $cached = get_transient( $tkey );
        if ( is_array( $cached ) ) {
            return array( 'events' => $cached, 'source' => 'fresh' );
        }
    }

    $items = ( $fx || ! get_transient( $fkey ) ) ? mbc_calendar_request( $from, $to, $fx ) : new WP_Error( 'mbc_gcal_backoff', 'Skipped: a request failed recently.' );
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

/** The raw `items` from events.list for [$from, $to), or a WP_Error. Follows up to 4 further pages. */
function mbc_calendar_request( DateTimeInterface $from, DateTimeInterface $to, $fx = null ) {
    if ( $fx ) {
        return empty( $fx['fail'] ) ? ( isset( $fx['items'] ) && is_array( $fx['items'] ) ? $fx['items'] : array() ) : new WP_Error( 'mbc_gcal_fixture', 'Simulated failure (fixture).' );
    }
    $key = (string) get_option( MBC_GCAL_KEY_OPTION, '' );
    $id  = (string) get_option( MBC_GCAL_ID_OPTION, '' );
    if ( '' === $key || '' === $id ) {
        return new WP_Error( 'mbc_gcal_unconfigured', 'API key or calendar ID is not set.' );
    }
    $items = array();
    $token = '';
    for ( $page = 0; $page < 5; $page++ ) {
        $query = array(
            'key'          => $key,
            'singleEvents' => 'true',
            'orderBy'      => 'startTime',
            'timeMin'      => $from->format( DATE_ATOM ),
            'timeMax'      => $to->format( DATE_ATOM ),
            'timeZone'     => MBC_GCAL_TZ,
            'maxResults'   => 250,
            'fields'       => 'nextPageToken,items(id,status,summary,description,location,start,end,recurringEventId)',
        );
        if ( '' !== $token ) {
            $query['pageToken'] = $token;
        }
        $url = 'https://www.googleapis.com/calendar/v3/calendars/' . rawurlencode( $id ) . '/events?' . http_build_query( $query );
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
        if ( isset( $json['items'] ) && is_array( $json['items'] ) ) {
            $items = array_merge( $items, $json['items'] );
        }
        $token = isset( $json['nextPageToken'] ) ? (string) $json['nextPageToken'] : '';
        if ( '' === $token ) {
            return $items;
        }
    }
    mbc_calendar_log( 'Range has more than five pages of events; the rest was left out.' );
    return $items;
}

/**
 * Raw Google items to plain arrays (safe to store), sorted. Times are Europe/London.
 * Timed: start_ts/end_ts, start_date/end_date (Y-m-d, the last day it touches), start_iso/end_iso.
 * All-day: the same dates (Google's end date is exclusive; ours is the last day), no times.
 * `recurring` is true for an instance of a repeating event (Google sets recurringEventId on those).
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
                'recurring'   => ! empty( $item['recurringEventId'] ),
                'id'          => isset( $item['id'] ) ? (string) $item['id'] : '',
                'series_id'   => ! empty( $item['recurringEventId'] ) ? (string) $item['recurringEventId'] : '',
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
