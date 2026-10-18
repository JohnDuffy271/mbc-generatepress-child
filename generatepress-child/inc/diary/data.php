<?php
/**
 * Weekly diary: capability, members-only notes, and building the diary from the calendar.
 *
 * Notes live in one non-autoloaded option (`mbc_diary_notes`). It is deliberately not registered
 * with register_setting(), so it is not in the public REST settings; nothing public reads it.
 * A note is keyed `r:<recurringEventId>` (every week of a repeating event) or `e:<event id>` (a
 * one-off event).
 */

defined( 'ABSPATH' ) || exit;

const MBC_DIARY_CAP         = 'mbc_weekly_diary';
const MBC_DIARY_NOTES       = 'mbc_diary_notes';
const MBC_DIARY_NOTE_MAX    = 500;
const MBC_DIARY_MAX_DAYS    = 31;
const MBC_DIARY_COMING_WEEKS = 6;

/** Administrators have the capability in code; another role can be given it later. */
add_filter( 'user_has_cap', 'mbc_diary_grant_cap' );
function mbc_diary_grant_cap( $caps ) {
    if ( ! empty( $caps['manage_options'] ) ) {
        $caps[ MBC_DIARY_CAP ] = true;
    }
    return $caps;
}

/** @return array<string, array{text: string, title: string, kind: string, updated: int}> */
function mbc_diary_notes() {
    $notes = get_option( MBC_DIARY_NOTES, array() );
    return is_array( $notes ) ? $notes : array();
}

/** True for `r:<id>` or `e:<id>` keys of a sensible length. */
function mbc_diary_valid_key( $key ) {
    return is_string( $key ) && (bool) preg_match( '/^[re]:[A-Za-z0-9_@.\-]{1,200}$/', $key );
}

/** Saves a note; empty text deletes it. Returns the stored text. */
function mbc_diary_save_note( $key, $text, $title = '' ) {
    $text  = mb_substr( trim( sanitize_text_field( (string) $text ) ), 0, MBC_DIARY_NOTE_MAX );
    $notes = mbc_diary_notes();
    if ( '' === $text ) {
        unset( $notes[ $key ] );
    } else {
        $notes[ $key ] = array(
            'text'    => $text,
            'title'   => mb_substr( sanitize_text_field( (string) $title ), 0, 200 ),
            'kind'    => 'r' === $key[0] ? 'series' : 'event',
            'updated' => time(),
        );
    }
    update_option( MBC_DIARY_NOTES, $notes, false );
    return $text;
}

function mbc_diary_delete_note( $key ) {
    $notes = mbc_diary_notes();
    $had   = isset( $notes[ $key ] );
    unset( $notes[ $key ] );
    update_option( MBC_DIARY_NOTES, $notes, false );
    return $had;
}

/** The note key for an event: its series for a repeating event, else the event itself. '' if it has no id. */
function mbc_diary_event_key( array $e ) {
    if ( ! empty( $e['series_id'] ) ) {
        return 'r:' . $e['series_id'];
    }
    return ! empty( $e['id'] ) ? 'e:' . $e['id'] : '';
}

/** Default range: the Sunday after today to the Sunday after that, inclusive. */
function mbc_diary_default_range() {
    $today = mbc_calendar_now()->setTime( 0, 0 );
    $from  = $today->modify( 'next sunday' );
    return array( $from, $from->modify( '+7 days' ) );
}

/** Y-m-d to a London midnight, or null if it is not a real date. */
function mbc_diary_parse_date( $s ) {
    if ( ! is_string( $s ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $s ) ) {
        return null;
    }
    $d = DateTimeImmutable::createFromFormat( '!Y-m-d', $s, mbc_calendar_tz() );
    return ( $d && $d->format( 'Y-m-d' ) === $s ) ? $d : null;
}

/**
 * Builds the diary for from..to (inclusive dates; invalid or reversed values fall back to the
 * default range, and a range is capped at MBC_DIARY_MAX_DAYS days).
 *
 * @return array{from: string, to: string, source: string, week: array, coming: array, orphans: ?array, text: string, html: string}
 */
function mbc_diary_build( $from = null, $to = null, $refresh = false ) {
    $tz = mbc_calendar_tz();
    $f  = is_string( $from ) ? mbc_diary_parse_date( $from ) : $from;
    $t  = is_string( $to ) ? mbc_diary_parse_date( $to ) : $to;
    if ( ! $f || ! $t || $t < $f ) {
        list( $f, $t ) = mbc_diary_default_range();
    }
    if ( $t > $f->modify( '+' . ( MBC_DIARY_MAX_DAYS - 1 ) . ' days' ) ) {
        $t = $f->modify( '+' . ( MBC_DIARY_MAX_DAYS - 1 ) . ' days' );
    }
    $end_excl    = $t->modify( '+1 day' );
    $coming_end  = $end_excl->modify( '+' . MBC_DIARY_COMING_WEEKS . ' weeks' );
    $today       = mbc_calendar_now()->setTime( 0, 0 );
    $known_from  = $today < $f ? $today : $f;

    $week_data   = mbc_calendar_get_range( $f, $end_excl, array( 'refresh' => $refresh ) );
    $coming_data = mbc_calendar_get_range( $end_excl, $coming_end, array( 'refresh' => $refresh ) );
    $known_data  = mbc_calendar_get_range( $known_from, $today->modify( '+13 months' ), array( 'refresh' => $refresh, 'key' => 'diary-known' ) );

    $in_range = static function ( array $e, $a, $b ) {
        return $e['start_date'] >= $a && $e['start_date'] < $b;
    };
    $notes  = mbc_diary_notes();
    $entry  = static function ( array $e, $year_from ) use ( $notes ) {
        $key  = mbc_diary_event_key( $e );
        $note = ( '' !== $key && isset( $notes[ $key ]['text'] ) ) ? (string) $notes[ $key ]['text'] : '';
        $line = mbc_diary_event_line( $e, substr( $e['start_date'], 0, 4 ) !== $year_from );
        return array(
            'key'       => $key,
            'kind'      => '' === $key ? '' : ( 'r' === $key[0] ? 'series' : 'event' ),
            'title'     => $e['title'],
            'date'      => $e['start_date'],
            'recurring' => ! empty( $e['recurring'] ),
            'line'      => $line,
            'note'      => $note,
            'text'      => '' === $note ? $line : $line . ' ' . $note,
        );
    };
    $fa = $f->format( 'Y-m-d' );
    $fe = $end_excl->format( 'Y-m-d' );
    $fc = $coming_end->format( 'Y-m-d' );
    $year = $f->format( 'Y' );

    $week = array();
    foreach ( $week_data['events'] as $e ) {
        if ( $in_range( $e, $fa, $fe ) ) {
            $week[] = $entry( $e, $year );
        }
    }
    $coming = array();
    foreach ( $coming_data['events'] as $e ) {
        if ( empty( $e['recurring'] ) && $in_range( $e, $fe, $fc ) ) {
            $coming[] = $entry( $e, $year );
        }
    }

    // Notes whose series or event is no longer in the calendar. Not judged if the calendar could not be read.
    $orphans = null;
    if ( 'none' !== $known_data['source'] ) {
        $known = array();
        foreach ( array_merge( $known_data['events'], $week_data['events'], $coming_data['events'] ) as $e ) {
            $known[ mbc_diary_event_key( $e ) ] = true;
        }
        $orphans = array();
        foreach ( $notes as $key => $n ) {
            if ( ! isset( $known[ $key ] ) ) {
                $orphans[] = array( 'key' => $key, 'title' => (string) ( $n['title'] ?? '' ), 'kind' => (string) ( $n['kind'] ?? '' ), 'note' => (string) ( $n['text'] ?? '' ) );
            }
        }
    }

    $sources = array( $week_data['source'], $coming_data['source'] );
    $source  = in_array( 'none', $sources, true ) ? 'none' : ( in_array( 'last-good', $sources, true ) ? 'last-good' : 'fresh' );
    $out     = array(
        'from'    => $fa,
        'to'      => $t->format( 'Y-m-d' ),
        'source'  => $source,
        'week'    => $week,
        'coming'  => $coming,
        'orphans' => $orphans,
    );
    return $out + mbc_diary_output( $week, $coming );
}

/** Plain text and HTML for pasting into an email. */
function mbc_diary_output( array $week, array $coming ) {
    $sections = array( 'This week' => $week, 'Coming up' => $coming );
    $text     = array();
    $html     = '';
    foreach ( $sections as $heading => $entries ) {
        if ( ! $entries ) {
            continue;
        }
        $text[] = $heading . "\n" . implode( "\n", wp_list_pluck( $entries, 'text' ) );
        $html  .= '<p><strong>' . esc_html( $heading ) . '</strong></p>';
        foreach ( $entries as $en ) {
            $html .= '<p>' . esc_html( $en['text'] ) . '</p>';
        }
    }
    return array( 'text' => implode( "\n\n", $text ), 'html' => $html );
}
