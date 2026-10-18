<?php
/**
 * Title: Events calendar
 * Slug: mbc/events-calendar
 * Categories: mbc
 * Description: The agenda from the "MBC Events" Google calendar for the next 8 weeks (shortcode). For the Events page.
 * Keywords: events, calendar, agenda
 * Viewport Width: 1280
 */

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:paragraph {"className":"mbc-lead"} -->
<p class="mbc-lead">What’s coming up at Moortown Baptist Church.</p>
<!-- /wp:paragraph -->

<!-- wp:shortcode -->
[mbc_calendar weeks="8" filters="yes" toggle="yes"]
<!-- /wp:shortcode -->
