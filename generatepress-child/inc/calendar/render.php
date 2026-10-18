<?php
/**
 * Events calendar: the [mbc_calendar] shortcode, an agenda grouped by day.
 *
 *   [mbc_calendar weeks="8" limit="0" heading="3" links="yes" recurring="yes" view="list|cards"
 *                 more="/whats-on/events/" more_label="See all events" empty="Nothing planned."]
 *
 * links="no" hides the Subscribe/.ics links. recurring="no" leaves out instances of repeating events.
 * more="URL" adds a "See all events" link (also when the list is empty or the calendar is unavailable)
 * and then never links out to Google. empty="..." replaces the "no events" line.
 * filters="yes" adds a no-JavaScript form: ?show=one-off|regular and ?weeks=2|4|8 (anything else is ignored).
 * hide_empty="yes" outputs nothing at all (no wrapper, no heading) when there is nothing to show or the
 * calendar is unavailable (the failure is still logged by fetch.php). heading_text="..." puts a section
 * heading (h2) inside the output so it disappears with it. strip="yes" gives a full-width section look.
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
        'view'       => 'list',
        'hide_empty' => 'no',
        'heading_text' => '',
        'strip'      => 'no',
        'filters'    => 'no',
    ), $atts, 'mbc_calendar' );
    $h_day   = max( 2, min( 5, (int) $atts['heading'] ) );
    $h_event = min( 6, $h_day + 1 );
    $limit   = max( 0, (int) $atts['limit'] );

    // filters="yes": server-rendered choices in the query string (?show=one-off|regular&weeks=2|4|8), validated.
    $filters = 'yes' === strtolower( (string) $atts['filters'] );
    $show    = 'all';
    $chosen  = false; // True when the visitor picked a range.
    $weeks   = (int) $atts['weeks'];
    if ( $filters ) {
        $q_show = isset( $_GET['show'] ) ? sanitize_key( wp_unslash( $_GET['show'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view choice.
        $q_wk   = isset( $_GET['weeks'] ) ? (int) $_GET['weeks'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $show   = in_array( $q_show, array( 'one-off', 'regular' ), true ) ? $q_show : 'all';
        $chosen = in_array( $q_wk, array( 2, 4, 8 ), true );
        $weeks  = $chosen ? $q_wk : $weeks;
    }

    $data  = mbc_calendar_get( $weeks );
    $now   = mbc_calendar_now();
    $today = $now->format( 'Y-m-d' );
    $links = mbc_calendar_id_links();
    $tz    = mbc_calendar_tz();

    // Drop what has already finished (a last-good copy may be hours old), then apply the limit.
    $no_recurring = 'no' === strtolower( (string) $atts['recurring'] );
    $events       = array_values( array_filter( $data['events'], static function ( $e ) use ( $now, $today, $no_recurring, $show ) {
        if ( ( $no_recurring || 'one-off' === $show ) && ! empty( $e['recurring'] ) ) {
            return false;
        }
        if ( 'regular' === $show && empty( $e['recurring'] ) ) {
            return false;
        }
        return $e['all_day'] ? $e['end_date'] >= $today : $e['end_ts'] >= $now->getTimestamp();
    } ) );
    if ( $chosen ) { // The feed is asked for this range already; this also trims a stored copy.
        $until  = $now->modify( '+' . $weeks . ' weeks' )->format( 'Y-m-d' );
        $events = array_values( array_filter( $events, static function ( $e ) use ( $until ) {
            return $e['start_date'] <= $until;
        } ) );
    }
    if ( $limit > 0 ) {
        $events = array_slice( $events, 0, $limit );
    }

    $more_url   = trim( (string) $atts['more'] );
    $more_label = '' !== trim( (string) $atts['more_label'] ) ? trim( (string) $atts['more_label'] ) : __( 'See all events', 'generatepress-child' );
    $empty_text = '' !== trim( (string) $atts['empty'] ) ? trim( (string) $atts['empty'] ) : __( 'There are no events in the diary just now. Please check again soon.', 'generatepress-child' );

    if ( 'yes' === strtolower( (string) $atts['hide_empty'] ) && ( 'none' === $data['source'] || ! $events ) ) {
        return '';
    }

    $cards  = 'cards' === strtolower( (string) $atts['view'] );
    $source = 'fresh' === $data['source'] ? 'fresh' : ( 'last-good' === $data['source'] ? 'last-good' : 'fallback' );
    $out    = '<div class="mbc-cal' . ( $cards ? ' mbc-cal--cards' : '' ) . ( 'yes' === strtolower( (string) $atts['strip'] ) ? ' mbc-cal--strip' : '' ) . '" data-source="' . esc_attr( $source ) . '">';
    // With a section heading the See all events link sits on the right of the heading row; without one it follows the list.
    $more_in_head = '' !== $more_url && '' !== trim( (string) $atts['heading_text'] );
    if ( '' !== trim( (string) $atts['heading_text'] ) ) {
        $out .= '<div class="mbc-cal__head"><h2 class="mbc-cal__heading">' . esc_html( trim( (string) $atts['heading_text'] ) ) . '</h2>';
        if ( $more_in_head ) {
            $out .= '<a class="mbc-cal__all-link" href="' . esc_url( $more_url ) . '">' . esc_html( $more_label ) . ' <span aria-hidden="true">&rarr;</span></a>';
        }
        $out .= '</div>';
    }

    if ( $filters ) {
        $out .= mbc_calendar_filters_html( $show, $weeks );
        if ( 'all' !== $show || (int) $atts['weeks'] !== $weeks ) {
            $empty_text = __( 'No events match these choices.', 'generatepress-child' );
        }
    }

    if ( 'none' === $data['source'] ) {
        $out .= '<p class="mbc-cal__empty">' . esc_html__( 'Our events calendar is not available just now.', 'generatepress-child' );
        if ( $links && '' === $more_url ) {
            $out .= ' <a href="' . esc_url( $links['view'] ) . '" rel="noopener">' . esc_html__( 'See the calendar on Google', 'generatepress-child' ) . '</a>.';
        }
        $out .= '</p>';
    } elseif ( ! $events ) {
        $out .= '<p class="mbc-cal__empty">' . esc_html( $empty_text ) . '</p>';
    } elseif ( $cards ) {
        $out .= mbc_calendar_cards_html( $events, $h_day, $now, $more_url );
    } else {
        $days = array();
        foreach ( $events as $e ) {
            // Something already under way (a multi-day event) is listed under today.
            $days[ max( $e['start_date'], $today ) ][] = $e;
        }
        $out .= '<ol class="mbc-cal__days">';
        foreach ( $days as $date => $list ) {
            $d    = new DateTimeImmutable( $date, $tz );
            $out .= '<li class="mbc-cal__day"><h' . $h_day . ' class="mbc-cal__date" id="d-' . esc_attr( $date ) . '"><time datetime="' . esc_attr( $date ) . '">'
                . esc_html( mbc_calendar_day_label( $d, $now ) ) . '</time></h' . $h_day . '><ul class="mbc-cal__events">';
            foreach ( $list as $e ) {
                $out .= mbc_calendar_event_html( $e, $h_event, $now );
            }
            $out .= '</ul></li>';
        }
        $out .= '</ol>';
    }

    if ( '' !== $more_url && ! $more_in_head ) {
        $out .= '<p class="mbc-cal__all"><a href="' . esc_url( $more_url ) . '">' . esc_html( $more_label ) . '</a></p>';
    }

    if ( $links && 'no' !== strtolower( (string) $atts['links'] ) && 'none' !== $data['source'] ) {
        $out .= '<p class="mbc-cal__links"><a href="' . esc_url( $links['subscribe'] ) . '" rel="noopener">' . esc_html__( 'Subscribe in Google Calendar', 'generatepress-child' ) . '</a>'
            . ' <a href="' . esc_url( $links['ics'] ) . '" rel="noopener">' . esc_html__( 'Add to other calendars (.ics)', 'generatepress-child' ) . '</a></p>';
    }
    return $out . '</div>';
}

/**
 * The filter form: a plain GET form (works without JavaScript). Two radio groups and a button.
 */
function mbc_calendar_filters_html( $show, $weeks ) {
    $shows = array(
        'all'     => __( 'All', 'generatepress-child' ),
        'one-off' => __( 'One-off events', 'generatepress-child' ),
        'regular' => __( 'Regular activities', 'generatepress-child' ),
    );
    $ranges = array(
        2 => __( 'Next 2 weeks', 'generatepress-child' ),
        4 => __( 'Next 4 weeks', 'generatepress-child' ),
        8 => __( 'Next 8 weeks', 'generatepress-child' ),
    );
    $out = '<form class="mbc-cal__filters" method="get" action="' . esc_url( get_permalink() ) . '">';
    $out .= '<fieldset class="mbc-cal__filter"><legend>' . esc_html__( 'Show', 'generatepress-child' ) . '</legend>';
    foreach ( $shows as $value => $label ) {
        $out .= '<label class="mbc-cal__choice"><input type="radio" name="show" value="' . esc_attr( $value ) . '"' . checked( $show, $value, false ) . '> <span>' . esc_html( $label ) . '</span></label>';
    }
    $out .= '</fieldset><fieldset class="mbc-cal__filter"><legend>' . esc_html__( 'How far ahead', 'generatepress-child' ) . '</legend>';
    foreach ( $ranges as $value => $label ) {
        $out .= '<label class="mbc-cal__choice"><input type="radio" name="weeks" value="' . (int) $value . '"' . checked( $weeks, $value, false ) . '> <span>' . esc_html( $label ) . '</span></label>';
    }
    $out .= '</fieldset><p class="mbc-cal__filter-actions"><input type="submit" value="' . esc_attr__( 'Update list', 'generatepress-child' ) . '"> '
        . '<a href="' . esc_url( get_permalink() ) . '">' . esc_html__( 'Reset', 'generatepress-child' ) . '</a></p></form>';
    return $out;
}

/** The "10am – 12pm" / "All day, until ..." line for an event, as safe HTML. */
function mbc_calendar_when_html( array $e, DateTimeInterface $now ) {
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
    return $when;
}

function mbc_calendar_event_html( array $e, $h, DateTimeInterface $now ) {
    $when = mbc_calendar_when_html( $e, $now );
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

/**
 * view="cards": the next events as cards (date badge, title, time, place). With a `more` URL each title
 * links to that page at the event's day (id="d-YYYY-MM-DD" on the agenda's day headings); the whole card
 * is clickable through the title link (CSS), so there is one link per card and the place is plain text.
 */
function mbc_calendar_cards_html( array $events, $h, DateTimeInterface $now, $more_url ) {
    $tz    = mbc_calendar_tz();
    $today = $now->format( 'Y-m-d' );
    $out   = '<ul class="mbc-cal__cards">';
    foreach ( $events as $e ) {
        $date  = max( $e['start_date'], $today ); // The same rule as the agenda: under way means today.
        $d     = new DateTimeImmutable( $date, $tz );
        $title = esc_html( $e['title'] );
        if ( '' !== $more_url ) {
            $title = '<a href="' . esc_url( $more_url . '#d-' . $date ) . '">' . $title . '</a>';
        }
        $out .= '<li class="mbc-card mbc-cal__card">'
            . '<time class="mbc-cal__badge" datetime="' . esc_attr( $date ) . '" aria-hidden="true">'
            . '<span class="mbc-cal__badge-day">' . esc_html( $d->format( 'D' ) ) . '</span>'
            . '<span class="mbc-cal__badge-num">' . esc_html( $d->format( 'j' ) ) . '</span>'
            . '<span class="mbc-cal__badge-month">' . esc_html( $d->format( 'M' ) ) . '</span></time>'
            . '<div class="mbc-cal__card-body"><h' . (int) $h . ' class="mbc-cal__title">' . $title . '</h' . (int) $h . '>'
            . '<p class="mbc-cal__when"><span class="screen-reader-text">' . esc_html( $d->format( 'l j F' ) ) . ', </span>'
            . mbc_calendar_when_html( $e, $now ) . '</p>';
        // The venue name only (the part before the first comma); the agenda keeps the full address and map link.
        $venue = trim( explode( ',', $e['location'] )[0] );
        if ( '' !== $venue ) {
            $out .= '<p class="mbc-cal__where">' . esc_html( $venue ) . '</p>';
        }
        $words = wp_trim_words( html_entity_decode( wp_strip_all_tags( str_ireplace( array( '<br>', '<br/>', '<br />' ), ' ', $e['description'] ) ), ENT_QUOTES, 'UTF-8' ), 15, '…' );
        if ( '' !== trim( $words ) ) {
            $out .= '<p class="mbc-cal__desc">' . esc_html( $words ) . '</p>';
        }
        $out .= '</div></li>';
    }
    return $out . '</ul>';
}
