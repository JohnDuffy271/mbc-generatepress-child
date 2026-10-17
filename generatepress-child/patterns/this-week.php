<?php
/**
 * Title: Coming up (compact events)
 * Slug: mbc/this-week
 * Categories: mbc
 * Description: Up to five events from the next seven days, with a See all events link and a friendly line when there are none. For the front page or any other page; change the attributes in the shortcode.
 * Keywords: events, calendar, coming up, this week
 * Viewport Width: 1280
 */

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:heading -->
<h2 class="wp-block-heading">Coming up</h2>
<!-- /wp:heading -->

<!-- wp:shortcode -->
[mbc_calendar weeks="1" limit="5" heading="3" links="no" more="/whats-on/events/" more_label="See all events" empty="Nothing is planned this week. Take a look at what’s coming up further ahead."]
<!-- /wp:shortcode -->
