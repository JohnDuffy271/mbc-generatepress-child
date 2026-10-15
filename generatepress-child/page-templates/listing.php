<?php
/**
 * Template Name: Listing page
 * Template Post Type: page
 *
 * Listing pages: News (a Query Loop of posts), Events and Contact. The
 * content is a block pattern (patterns/news-listing.php,
 * events-placeholder.php, contact-placeholder.php), edited in the block
 * editor.
 */

defined( 'ABSPATH' ) || exit;

get_header();
get_template_part( 'template-parts/page', null, array( 'variant' => 'listing' ) );
get_footer();
