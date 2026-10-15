<?php
/**
 * The old newsletter address, /mbc-newsletter-articles/, which Mailchimp has been told to import.
 *
 *   - If the latest published issue is MBC_NL_LEGACY_DAYS old or less, redirect (302) to its
 *     email version, so the saved issue (not a moving list) is what gets imported.
 *   - Otherwise show a fresh, unsaved pre-fill (the posts since the last issue) as an email,
 *     so the old habit of importing that address still produces a sensible newsletter.
 * Neither is cached or indexed.
 */

defined( 'ABSPATH' ) || exit;

const MBC_NL_LEGACY_PATH = 'mbc-newsletter-articles';
const MBC_NL_LEGACY_DAYS = 10;

add_action( 'template_redirect', 'mbc_nl_legacy_address', 1 );
function mbc_nl_legacy_address() {
    $uri  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- only compared, never output.
    $path = trim( (string) wp_parse_url( $uri, PHP_URL_PATH ), '/' );
    if ( MBC_NL_LEGACY_PATH !== $path ) {
        return;
    }

    nocache_headers();
    header( 'X-Robots-Tag: noindex, nofollow' );

    $latest = get_posts( array(
        'post_type'      => MBC_NL_TYPE,
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'no_found_rows'  => true,
    ) );
    $days = (int) apply_filters( 'mbc_newsletter_legacy_days', MBC_NL_LEGACY_DAYS );
    if ( $latest ) {
        $age = time() - (int) strtotime( $latest[0]->post_date_gmt . ' UTC' );
        $url = mbc_nl_email_url( $latest[0]->ID );
        if ( $age <= $days * DAY_IN_SECONDS && '' !== $url ) {
            wp_safe_redirect( $url, 302 );
            exit;
        }
    }

    // A fresh pre-fill, not saved anywhere.
    $fill  = mbc_nl_prefill( 0 );
    $posts = array_filter( array_map( 'get_post', $fill['ids'] ) );
    $issue = new WP_Post( (object) array(
        'ID'           => 0,
        'post_type'    => MBC_NL_TYPE,
        'post_status'  => 'draft',
        'post_title'   => get_bloginfo( 'name' ) . ' newsletter',
        'post_content' => '',
    ) );
    status_header( 200 );
    header( 'Content-Type: text/html; charset=utf-8' );
    get_template_part( 'template-parts/newsletter-email', null, array( 'issue' => $issue, 'posts' => array_values( $posts ) ) );
    exit;
}
