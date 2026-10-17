<?php
/**
 * Template Name: Section hub
 * Template Post Type: page
 *
 * A section's own page (About Us, What's On, Mission, Resources): banner (the featured image), title, a
 * two-sentence introduction, the section's cards ([mbc_section_index], built from the Primary menu), then
 * whatever is specific to the section. The content is the "Section hub" block pattern
 * (patterns/section-hub.php), edited in the block editor.
 */

defined( 'ABSPATH' ) || exit;

get_header();
get_template_part( 'template-parts/page', null, array( 'variant' => 'hub' ) );
get_footer();
