<?php
/**
 * Single post: categories, title, date, featured image, content, previous/next.
 *
 * The featured image is not shown when the post's first content image is the
 * same picture (see inc/post-helpers.php).
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main id="main" class="mbc-page mbc-page--post">
    <?php
    while ( have_posts() ) :
        the_post();
        ?>
        <article id="post-<?php the_ID(); ?>" <?php post_class( 'mbc-page__article' ); ?>>
            <?php mbc_breadcrumbs(); ?>
            <header class="mbc-post__header">
                <p class="mbc-post__cats"><?php echo wp_kses_post( get_the_category_list( ', ' ) ); ?></p>
                <h1 class="mbc-page__title"><?php the_title(); ?></h1>
                <p class="mbc-post__meta">
                    <time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
                </p>
            </header>

            <?php if ( has_post_thumbnail() && ! mbc_featured_repeats_first_content_image() ) : ?>
                <figure class="mbc-post__figure">
                    <?php the_post_thumbnail( 'large', array( 'class' => 'mbc-post__image' ) ); ?>
                </figure>
            <?php endif; ?>

            <div class="mbc-post__content">
                <?php the_content(); ?>
            </div>

            <?php
            the_post_navigation( array(
                'prev_text' => '<span class="nav-subtitle">' . esc_html__( 'Older', 'generatepress-child' ) . '</span> %title',
                'next_text' => '<span class="nav-subtitle">' . esc_html__( 'Newer', 'generatepress-child' ) . '</span> %title',
            ) );
            ?>
        </article>
    <?php endwhile; ?>
</main>

<?php
get_footer();
