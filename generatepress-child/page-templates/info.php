<?php
/**
 * Template Name: Info page
 * Template Post Type: page
 *
 * Prose with an optional image and call to action (About Us and Resources
 * pages). The content is the "Info page" block pattern
 * (patterns/info-page.php), edited in the block editor.
 */

defined( 'ABSPATH' ) || exit;

get_header();
get_template_part( 'template-parts/page', null, array( 'variant' => 'info' ) );
get_footer();
