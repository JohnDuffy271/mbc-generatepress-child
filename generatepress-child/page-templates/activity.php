<?php
/**
 * Template Name: Activity page
 * Template Post Type: page
 *
 * A What's On activity (When, Where, Who it's for, Contact, image,
 * description). The content is the "Activity page" block pattern
 * (patterns/activity-details.php), so every activity has the same structure
 * and is edited in the block editor.
 */

defined( 'ABSPATH' ) || exit;

get_header();
get_template_part( 'template-parts/page', null, array( 'variant' => 'activity' ) );
get_footer();
