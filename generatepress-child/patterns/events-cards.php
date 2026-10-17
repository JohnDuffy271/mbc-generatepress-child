<?php
/**
 * Title: Coming up (event cards)
 * Slug: mbc/events-cards
 * Categories: mbc
 * Description: The next three one-off events as cards (date, title, time, place), each linking to its day on the Events page, with a See all events link. For the front page or any other page.
 * Keywords: events, calendar, cards, coming up
 * Viewport Width: 1280
 */

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:heading -->
<h2 class="wp-block-heading">Coming up</h2>
<!-- /wp:heading -->

<!-- wp:shortcode -->
[mbc_calendar view="cards" weeks="8" limit="3" heading="3" links="no" recurring="no" more="/whats-on/events/" more_label="See all events" empty="Nothing is planned just now. Take a look at what’s coming up further ahead."]
<!-- /wp:shortcode -->
