<?php
/**
 * Newsletter archive: published issues, newest first.
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main id="main" class="mbc-page mbc-page--newsletter-archive">
    <header class="mbc-post__header">
        <h1 class="mbc-page__title"><?php esc_html_e( 'Newsletter archive', 'generatepress-child' ); ?></h1>
    </header>

    <?php if ( have_posts() ) : ?>
        <ul class="mbc-nl__list">
            <?php
            while ( have_posts() ) :
                the_post();
                $mbc_subtitle = (string) get_post_meta( get_the_ID(), 'mbc_nl_subtitle', true );
                ?>
                <li class="mbc-card">
                    <?php if ( has_post_thumbnail() ) : ?>
                        <a class="mbc-card__image" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
                            <?php the_post_thumbnail( 'medium_large' ); ?>
                        </a>
                    <?php endif; ?>
                    <p class="mbc-card__date"><?php echo esc_html( get_the_date() ); ?></p>
                    <h2 class="mbc-card__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
                    <?php if ( '' !== trim( $mbc_subtitle ) ) : ?>
                        <p class="mbc-card__excerpt"><?php echo esc_html( $mbc_subtitle ); ?></p>
                    <?php endif; ?>
                </li>
            <?php endwhile; ?>
        </ul>
        <?php
        the_posts_pagination( array(
            'class'     => 'mbc-pagination',
            'prev_text' => esc_html__( 'Newer', 'generatepress-child' ),
            'next_text' => esc_html__( 'Older', 'generatepress-child' ),
        ) );
        ?>
    <?php else : ?>
        <p><?php esc_html_e( 'There are no newsletters yet. Please check back soon.', 'generatepress-child' ); ?></p>
    <?php endif; ?>
</main>

<?php
get_footer();
