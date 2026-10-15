<?php
/**
 * Template Name: Mission partner page
 * Template Post Type: page
 *
 * A mission partner: logo, summary, external link, how to support. The
 * content is the "Mission partner page" block pattern
 * (patterns/mission-partner.php), edited in the block editor.
 */

defined( 'ABSPATH' ) || exit;

get_header();
get_template_part( 'template-parts/page', null, array( 'variant' => 'mission-partner' ) );
get_footer();
