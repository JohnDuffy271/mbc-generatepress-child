<?php
/**
 * Title: This week (compact events)
 * Slug: mbc/this-week
 * Categories: mbc
 * Description: Up to five events from the next week, for the front page or any other page. Change weeks and limit in the shortcode.
 * Keywords: events, calendar, this week
 * Viewport Width: 1280
 */

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:heading -->
<h2 class="wp-block-heading">This week</h2>
<!-- /wp:heading -->

<!-- wp:shortcode -->
[mbc_calendar weeks="1" limit="5" heading="3" links="no"]
<!-- /wp:shortcode -->
