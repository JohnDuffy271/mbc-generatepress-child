<?php
/**
 * Template Name: Front page
 * Template Post Type: page
 *
 * Hero and four zigzag bands. The content is the "Front page" block pattern
 * (patterns/front-page.php) saved into the page, so it stays editable in the
 * block editor.
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main id="main" class="mbc-front">
    <?php
    while ( have_posts() ) :
        the_post();
        the_content();
    endwhile;
    ?>
</main>

<?php
get_footer();
