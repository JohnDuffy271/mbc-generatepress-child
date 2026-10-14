<?php
/**
 * Site facts used by the header and footer, kept in one place.
 */

defined( 'ABSPATH' ) || exit;

/**
 * @return array<string,mixed>
 */
function mbc_site_info() {
    return array(
        'name'               => 'Moortown Baptist Church',
        'address'            => '204 King Lane, Leeds LS17 6AA',
        'charity_number'     => '1128960',
        'facebook_url'       => 'https://www.facebook.com/MoortownBaptistChurch',
        // The channel (footer icon) and the Live tab, which lists the past
        // Sunday services streams (the "Services on YouTube" menu item;
        // bin/scaffold_site.py YOUTUBE_SERVICES_URL must match). The channel
        // has no public playlists.
        'youtube_channel_url'  => 'https://www.youtube.com/@MoortownBaptistChurch',
        'youtube_services_url' => 'https://www.youtube.com/@MoortownBaptistChurch/streams',
        'newsletter_form_id' => 206, // MC4WP form.
    );
}
