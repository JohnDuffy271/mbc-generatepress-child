<?php
/**
 * Events calendar: the [mbc_calendar] shortcode, an agenda grouped by day.
 *
 *   [mbc_calendar weeks="8" limit="0" heading="3" links="yes" recurring="yes"
 *                 more="/whats-on/events/" more_label="See all events" empty="Nothing planned."]
 *
 * links="no" hides the Subscribe/.ics links. recurring="no" leaves out instances of repeating events.
 * more="URL" adds a "See all events" link (also when the list is empty or the calendar is unavailable)
 * and then never links out to Google. empty="..." replaces the "no events" line.
 *
 * Server-rendered from cached data (see fetch.php), so nothing loads or shifts in the browser.
 * Styled by css/calendar.css (tokens only); that file loads on pages whose content has the shortcode.
 */

defined( 'ABSPATH' ) || exit;

add_shortcode( 'mbc_calendar', 'mbc_calendar_shortcode' );

add_action( 'wp_enqueue_scripts', 'mbc_calendar_enqueue', 20 );
function mbc_calendar_enqueue() {
    $post = is_singular() ? get_queried_object() : null;
    if ( ! ( $post instanceof WP_Post ) || false === strpos( $post->post_content, '[mbc_calendar' ) ) {
        return;
    }
    $dir = get_stylesheet_directory();
    wp_enqueue_style( 'mbc-calendar', get_stylesheet_directory_uri() . '/css/calendar.css', array( 'mbc-patterns' ), filemtime( $dir . '/css/calendar.css' ) );
}

/** "10:30am", or "10am" on the hour. */
function mbc_calendar_time( DateTimeInterface $d ) {
    return $d->format( 'i' ) === '00' ? $d->format( 'ga' ) : $d->format( 'g:ia' );
}

function mbc_calendar_day_label( DateTimeInterface $d, DateTimeInterface $now ) {
    return $d->format( 'Y' ) === $now->format( 'Y' ) ? $d->format( 'l j F' ) : $d->format( 'l j F Y' );
}

function mbc_calendar_id_links() {
    $id = (string) get_option( MBC_GCAL_ID_OPTION, '' );
    if ( '' === $id ) {
        return null;
    }
    return array(
        'view'      => 'https://calendar.google.com/calendar/embed?src=' . rawurlencode( $id ),
        'subscribe' => 'https://calendar.google.com/calendar/u/0?cid=' . rawurlencode( base64_encode( $id ) ),
        'ics'       => 'https://calendar.google.com/calendar/ical/' . rawurlencode( $id ) . '/public/basic.ics',
    );
}

function mbc_calendar_shortcode( $atts ) {
    $atts = shortcode_atts( array(
        'weeks'   => 8,
        'limit'   => 0,
        'heading' => 3,
        'links'   => 'yes',
        'recurring'  => 'yes',
        'more'       => '',
        'more_label' => '',
        'empty'      => '',
    ), $atts, 'mbc_calendar' );
    $h_day   = max( 2, min( 5, (int) $atts['heading'] ) );
    $h_event = min( 6, $h_day + 1 );
    $limit   = max( 0, (int) $atts['limit'] );

    $data  = mbc_calendar_get( (int) $atts['weeks'] );
    $now   = mbc_calendar_now();
    $today = $now->format( 'Y-m-d' );
    $links = mbc_calendar_id_links();
    $tz    = mbc_calendar_tz();

    // Drop what has already finished (a last-good copy may be hours old), then apply the limit.
    $no_recurring = 'no' === strtolower( (string) $atts['recurring'] );
    $events       = array_values( array_filter( $data['events'], static function ( $e ) use ( $now, $today, $no_recurring ) {
        if ( $no_recurring && ! empty( $e['recurring'] ) ) {
            return false;
        }
        return $e['all_day'] ? $e['end_date'] >= $today : $e['end_ts'] >= $now->getTimestamp();
    } ) );
    if ( $limit > 0 ) {
        $events = array_slice( $events, 0, $limit );
    }

    $more_url   = trim( (string) $atts['more'] );
    $more_label = '' !== trim( (string) $atts['more_label'] ) ? trim( (string) $atts['more_label'] ) : __( 'See all events', 'generatepress-child' );
    $empty_text = '' !== trim( (string) $atts['empty'] ) ? trim( (string) $atts['empty'] ) : __( 'There are no events in the diary just now. Please check again soon.', 'generatepress-child' );

    $source = 'fresh' === $data['source'] ? 'fresh' : ( 'last-good' === $data['source'] ? 'last-good' : 'fallback' );
    $out    = '<div class="mbc-cal" data-source="' . esc_attr( $source ) . '">';

    if ( 'none' === $data['source'] ) {
        $out .= '<p class="mbc-cal__empty">' . esc_html__( 'Our events calendar is not available just now.', 'generatepress-child' );
        if ( $links && '' === $more_url ) {
            $out .= ' <a href="' . esc_url( $links['view'] ) . '" rel="noopener">' . esc_html__( 'See the calendar on Google', 'generatepress-child' ) . '</a>.';
        }
        $out .= '</p>';
    } elseif ( ! $events ) {
        $out .= '<p class="mbc-cal__empty">' . esc_html( $empty_text ) . '</p>';
    } else {
        $days = array();
        foreach ( $events as $e ) {
            // Something already under way (a multi-day event) is listed under today.
            $days[ max( $e['start_date'], $today ) ][] = $e;
        }
        $out .= '<ol class="mbc-cal__days">';
        foreach ( $days as $date => $list ) {
            $d    = new DateTimeImmutable( $date, $tz );
            $out .= '<li class="mbc-cal__day"><h' . $h_day . ' class="mbc-cal__date"><time datetime="' . esc_attr( $date ) . '">'
                . esc_html( mbc_calendar_day_label( $d, $now ) ) . '</time></h' . $h_day . '><ul class="mbc-cal__events">';
            foreach ( $list as $e ) {
                $out .= mbc_calendar_event_html( $e, $h_event, $now );
            }
            $out .= '</ul></li>';
        }
        $out .= '</ol>';
    }

    if ( '' !== $more_url ) {
        $out .= '<p class="mbc-cal__all"><a href="' . esc_url( $more_url ) . '">' . esc_html( $more_label ) . '</a></p>';
    }

    if ( $links && 'no' !== strtolower( (string) $atts['links'] ) && 'none' !== $data['source'] ) {
        $out .= '<p class="mbc-cal__links"><a href="' . esc_url( $links['subscribe'] ) . '" rel="noopener">' . esc_html__( 'Subscribe in Google Calendar', 'generatepress-child' ) . '</a>'
            . ' <a href="' . esc_url( $links['ics'] ) . '" rel="noopener">' . esc_html__( 'Add to other calendars (.ics)', 'generatepress-child' ) . '</a></p>';
    }
    return $out . '</div>';
}

function mbc_calendar_event_html( array $e, $h, DateTimeInterface $now ) {
    $tz   = mbc_calendar_tz();
    $when = '';
    if ( $e['all_day'] ) {
        $when = '<span class="mbc-cal__allday">' . esc_html__( 'All day', 'generatepress-child' ) . '</span>';
        if ( $e['end_date'] > $e['start_date'] ) {
            $until = new DateTimeImmutable( $e['end_date'], $tz );
            $when .= ', ' . esc_html__( 'until', 'generatepress-child' ) . ' <time datetime="' . esc_attr( $e['end_iso'] ) . '">' . esc_html( mbc_calendar_day_label( $until, $now ) ) . '</time>';
        }
    } else {
        $start = ( new DateTimeImmutable( '@' . $e['start_ts'] ) )->setTimezone( $tz );
        $end   = ( new DateTimeImmutable( '@' . $e['end_ts'] ) )->setTimezone( $tz );
        $when  = '<time datetime="' . esc_attr( $e['start_iso'] ) . '">' . esc_html( mbc_calendar_time( $start ) ) . '</time>';
        if ( $e['end_ts'] > $e['start_ts'] ) {
            if ( $e['end_date'] === $e['start_date'] ) {
                $when .= ' – <time datetime="' . esc_attr( $e['end_iso'] ) . '">' . esc_html( mbc_calendar_time( $end ) ) . '</time>';
            } else {
                $when .= ' ' . esc_html__( 'to', 'generatepress-child' ) . ' <time datetime="' . esc_attr( $e['end_iso'] ) . '">'
                    . esc_html( $end->format( 'D j M' ) . ' ' . mbc_calendar_time( $end ) ) . '</time>';
            }
        }
    }

    $out  = '<li class="mbc-cal__event"><p class="mbc-cal__when">' . $when . '</p>';
    $out .= '<h' . (int) $h . ' class="mbc-cal__title">' . esc_html( $e['title'] ) . '</h' . (int) $h . '>';
    if ( '' !== $e['location'] ) {
        $maps = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $e['location'] );
        $out .= '<p class="mbc-cal__where"><a href="' . esc_url( $maps ) . '" rel="noopener">' . esc_html( $e['location'] ) . '</a></p>';
    }
    if ( '' !== $e['description'] ) {
        $plain = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( str_ireplace( '<br>', ' ', $e['description'] ) ) ) ?? '' );
        if ( mb_strlen( $plain ) <= 160 ) {
            $out .= '<div class="mbc-cal__desc">' . $e['description'] . '</div>';
        } else {
            $out .= '<p class="mbc-cal__desc">' . esc_html( wp_html_excerpt( $plain, 160, '…' ) ) . '</p>'
                . '<details class="mbc-cal__more"><summary>' . esc_html__( 'More', 'generatepress-child' )
                . '<span class="screen-reader-text"> ' . esc_html( sprintf( /* translators: %s: event title */ __( 'about %s', 'generatepress-child' ), $e['title'] ) ) . '</span></summary>'
                . '<div class="mbc-cal__full">' . $e['description'] . '</div></details>';
        }
    }
    return $out . '</li>';
}
