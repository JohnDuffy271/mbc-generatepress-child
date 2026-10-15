<?php
/**
 * Shared wrapper for the content page templates (Activity, Info, Mission
 * partner, Listing): page title, then the page's block content.
 *
 * Usage: get_template_part( 'template-parts/page', null, array( 'variant' => 'activity' ) );
 */

defined( 'ABSPATH' ) || exit;

$mbc_variant = isset( $args['variant'] ) ? sanitize_html_class( $args['variant'] ) : 'default';
?>
<main id="main" class="mbc-page mbc-page--<?php echo esc_attr( $mbc_variant ); ?>">
    <?php
    while ( have_posts() ) :
        the_post();
        ?>
        <article id="post-<?php the_ID(); ?>" <?php post_class( 'mbc-page__article' ); ?>>
            <header class="mbc-page__header">
                <h1 class="mbc-page__title"><?php the_title(); ?></h1>
            </header>
            <div class="mbc-page__content">
                <?php the_content(); ?>
            </div>
        </article>
    <?php endwhile; ?>
</main>
