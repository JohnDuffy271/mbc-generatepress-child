<?php
/**
 * Newsletter issue, web version: header image, title, optional subtitle, intro,
 * then the issue's stored posts as cards (see inc/newsletter/).
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main id="main" class="mbc-page mbc-page--newsletter">
    <?php
    while ( have_posts() ) :
        the_post();
        $mbc_subtitle = (string) get_post_meta( get_the_ID(), 'mbc_nl_subtitle', true );
        $mbc_posts    = mbc_nl_issue_posts( get_the_ID() );
        ?>
        <article id="post-<?php the_ID(); ?>" <?php post_class( 'mbc-page__article' ); ?>>
            <header class="mbc-post__header">
                <p class="mbc-post__cats"><a href="<?php echo esc_url( get_post_type_archive_link( MBC_NL_TYPE ) ); ?>"><?php esc_html_e( 'Newsletter archive', 'generatepress-child' ); ?></a></p>
                <h1 class="mbc-page__title"><?php the_title(); ?></h1>
                <?php if ( '' !== trim( $mbc_subtitle ) ) : ?>
                    <p class="mbc-nl__subtitle"><?php echo esc_html( $mbc_subtitle ); ?></p>
                <?php endif; ?>
                <p class="mbc-post__meta">
                    <time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
                </p>
            </header>

            <?php $mbc_header_id = mbc_nl_header_id( get_the_ID() ); ?>
            <?php if ( $mbc_header_id ) : ?>
                <figure class="mbc-post__figure">
                    <?php echo wp_get_attachment_image( $mbc_header_id, 'large', false, array( 'class' => 'mbc-post__image' ) ); ?>
                </figure>
            <?php endif; ?>

            <?php if ( '' !== trim( get_the_content() ) ) : ?>
                <div class="mbc-post__content mbc-nl__intro">
                    <?php the_content(); ?>
                </div>
            <?php endif; ?>

            <?php if ( $mbc_posts ) : ?>
                <ul class="mbc-nl__list">
                    <?php foreach ( $mbc_posts as $mbc_item ) : ?>
                        <li class="mbc-card">
                            <?php if ( has_post_thumbnail( $mbc_item ) ) : ?>
                                <a class="mbc-card__image" href="<?php echo esc_url( get_permalink( $mbc_item ) ); ?>" tabindex="-1" aria-hidden="true">
                                    <?php echo get_the_post_thumbnail( $mbc_item, 'medium_large' ); ?>
                                </a>
                            <?php endif; ?>
                            <p class="mbc-card__date"><?php echo esc_html( get_the_date( '', $mbc_item ) ); ?></p>
                            <h2 class="mbc-card__title"><a href="<?php echo esc_url( get_permalink( $mbc_item ) ); ?>"><?php echo esc_html( get_the_title( $mbc_item ) ); ?></a></h2>
                            <p class="mbc-card__excerpt"><?php echo esc_html( mbc_nl_excerpt( $mbc_item ) ); ?></p>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else : ?>
                <p><?php esc_html_e( 'This issue has no posts.', 'generatepress-child' ); ?></p>
            <?php endif; ?>
        </article>
    <?php endwhile; ?>
</main>

<?php
get_footer();
