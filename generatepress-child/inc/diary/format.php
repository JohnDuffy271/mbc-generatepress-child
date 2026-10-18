<?php
/**
 * Weekly diary: pure formatting (no WordPress state), in the style of the minister's email.
 *
 * Time rule (applied everywhere in the diary):
 *  - 12-hour clock, a full stop between hours and minutes, minutes always shown, am/pm written
 *    once, after the end time: "7.00 - 8.00pm", "10.00 - 11.30am", "10.00 - 1.30pm";
 *  - 12.00 noon is "midday" and 12.00 at night "midnight" ("10.00 - 12.00 midday");
 *  - one time only: "7.30pm", "10.00am", "midday";
 *  - all-day events show no time; a timed event that runs over several days shows its start time
 *    and "until <date>".
 */

defined( 'ABSPATH' ) || exit;

/** 1 to 1st, 2 to 2nd, 11 to 11th, 22 to 22nd. */
function mbc_diary_ordinal( $n ) {
    $n      = (int) $n;
    $suffix = 'th';
    if ( $n % 100 < 11 || $n % 100 > 13 ) {
        $suffix = array( 1 => 'st', 2 => 'nd', 3 => 'rd' )[ $n % 10 ] ?? 'th';
    }
    return $n . $suffix;
}

/** "Wednesday 21st October", plus the year when asked. DateTime::format() is always English. */
function mbc_diary_date_label( DateTimeInterface $d, $with_year = false ) {
    return $d->format( 'l' ) . ' ' . mbc_diary_ordinal( $d->format( 'j' ) ) . ' ' . $d->format( 'F' ) . ( $with_year ? ' ' . $d->format( 'Y' ) : '' );
}

/** @return array{h: int, m: int, mer: string, special: string} */
function mbc_diary_clock( DateTimeInterface $d ) {
    $h   = (int) $d->format( 'G' );
    $m   = (int) $d->format( 'i' );
    $h12 = $h % 12 ? $h % 12 : 12;
    $sp  = '';
    if ( 0 === $m && 12 === $h ) {
        $sp = 'midday';
    } elseif ( 0 === $m && 0 === $h ) {
        $sp = 'midnight';
    }
    return array( 'h' => $h12, 'm' => $m, 'mer' => $h >= 12 ? 'pm' : 'am', 'special' => $sp );
}

/** "10.00am", "10.30am", "midday". */
function mbc_diary_time_single( DateTimeInterface $d ) {
    $c = mbc_diary_clock( $d );
    if ( '' !== $c['special'] ) {
        return $c['special'];
    }
    return sprintf( '%d.%02d', $c['h'], $c['m'] ) . $c['mer'];
}

/** The time rule above for a start and an end on the same day (end may be null or equal to start). */
function mbc_diary_time_range( DateTimeInterface $start, ?DateTimeInterface $end = null ) {
    if ( null === $end || $end->getTimestamp() <= $start->getTimestamp() ) {
        return mbc_diary_time_single( $start );
    }
    $s     = mbc_diary_clock( $start );
    $e     = mbc_diary_clock( $end );
    $label = '' !== $e['special'] ? ' ' . $e['special'] : $e['mer'];
    return sprintf( '%d.%02d - %d.%02d', $s['h'], $s['m'], $e['h'], $e['m'] ) . $label;
}

/** Ends the text with a full stop unless it already ends in . ! ? or an ellipsis. */
function mbc_diary_stop( $s ) {
    $s = trim( (string) $s );
    if ( '' === $s ) {
        return '';
    }
    return preg_match( '/[.!?…]$/u', $s ) ? $s : $s . '.';
}

/** The first sentence of a (possibly HTML) description as plain text; '' if there is none. */
function mbc_diary_first_sentence( $html ) {
    $t = preg_replace( '#<br\s*/?>#i', ' ', (string) $html ) ?? '';
    $t = wp_strip_all_tags( html_entity_decode( $t, ENT_QUOTES, 'UTF-8' ) );
    $t = trim( preg_replace( '/\s+/u', ' ', $t ) ?? '' );
    if ( '' === $t ) {
        return '';
    }
    if ( preg_match_all( '/[.!?](?=\s|$)/u', $t, $m, PREG_OFFSET_CAPTURE ) ) {
        foreach ( $m[0] as $hit ) {
            $head = substr( $t, 0, $hit[1] + 1 );
            if ( preg_match( '/\b(?:Mr|Mrs|Ms|Dr|St|Rev|Revd|approx|etc)\.$/i', $head ) ) {
                continue; // An abbreviation, not the end of the sentence.
            }
            return mb_strlen( $head ) > 240 ? mb_substr( $head, 0, 239 ) . '…' : $head;
        }
    }
    return mbc_diary_stop( mb_strlen( $t ) > 160 ? rtrim( mb_substr( $t, 0, 159 ) ) . '…' : $t );
}

/** '' for the church itself (or no location), else the venue name (text before the first comma). */
function mbc_diary_place( $location ) {
    $location = trim( (string) $location );
    if ( '' === $location ) {
        return '';
    }
    $church = (array) apply_filters( 'mbc_diary_church_patterns', array( '/moortown\s+baptist/i', '/^(the\s+)?church\b/i', '/^mbc\b/i', '/\bLS17\s*6AA\b/i' ) );
    foreach ( $church as $pattern ) {
        if ( preg_match( $pattern, $location ) ) {
            return '';
        }
    }
    return trim( explode( ',', $location )[0] );
}

/**
 * One diary line (without the note): "<Weekday> <ordinal> <Month> <Title> <time> [at <place>]. [First sentence.]"
 *
 * @param array $e A normalised calendar event (see mbc_calendar_normalise()).
 */
function mbc_diary_event_line( array $e, $with_year = false ) {
    $tz    = new DateTimeZone( MBC_GCAL_TZ );
    $first = new DateTimeImmutable( $e['start_date'], $tz );
    $parts = array( mbc_diary_date_label( $first, $with_year ), rtrim( $e['title'], " \t.!?" ) );
    $multi = $e['end_date'] > $e['start_date'];
    if ( empty( $e['all_day'] ) ) {
        $start = ( new DateTimeImmutable( '@' . $e['start_ts'] ) )->setTimezone( $tz );
        $end   = ( new DateTimeImmutable( '@' . $e['end_ts'] ) )->setTimezone( $tz );
        $parts[] = $multi ? mbc_diary_time_single( $start ) : mbc_diary_time_range( $start, $end );
    }
    if ( $multi ) {
        $parts[] = 'until ' . mbc_diary_date_label( new DateTimeImmutable( $e['end_date'], $tz ) );
    }
    $place = mbc_diary_place( $e['location'] ?? '' );
    if ( '' !== $place ) {
        $parts[] = 'at ' . rtrim( $place, '.' );
    }
    $line = mbc_diary_stop( implode( ' ', $parts ) );
    $desc = mbc_diary_first_sentence( $e['description'] ?? '' );
    return '' === $desc ? $line : $line . ' ' . $desc;
}
