<?php
/**
 * Template Name: MBC E2E Test
 *
 * Smoke test for the edit → push → deploy → REST → render chain.
 */

get_header();
?>

<div class="mbc-e2e" style="border: 4px solid var(--mbc-e2e-colour); padding: 1rem;">
	<?php
	while ( have_posts() ) :
		the_post();
		?>
		<h1><?php the_title(); ?></h1>
		<?php the_content(); ?>
	<?php endwhile; ?>
</div>

<?php
get_footer();
