<?php
/**
 * Site footer: brand, social links, newsletter signup (MC4WP), small links
 * (the "footer" menu location) and copyright.
 */

defined( 'ABSPATH' ) || exit;

$mbc_info = mbc_site_info();
$mbc_logo = mbc_logo_image( 'mbc-footer__logo' );
?>
<footer class="mbc-footer">
    <div class="mbc-footer__inner">
        <div class="mbc-footer__about">
            <a class="mbc-footer__brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
                <?php echo $mbc_logo; // phpcs:ignore WordPress.Security.EscapeOutput -- core image markup. ?>
                <span class="mbc-footer__name"><?php echo esc_html( $mbc_info['name'] ); ?></span>
            </a>
            <ul class="mbc-footer__social">
                <li><a class="mbc-footer__social-link" href="<?php echo esc_url( $mbc_info['facebook_url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Facebook', 'generatepress-child' ); ?></a></li>
                <li><a class="mbc-footer__social-link" href="<?php echo esc_url( $mbc_info['youtube_channel_url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'YouTube', 'generatepress-child' ); ?></a></li>
            </ul>
            <address class="mbc-footer__address"><?php echo esc_html( $mbc_info['address'] ); ?></address>
            <p class="mbc-footer__charity">
                <?php
                /* translators: %s: registered charity number */
                printf( esc_html__( 'Registered Charity No. %s', 'generatepress-child' ), esc_html( $mbc_info['charity_number'] ) );
                ?>
            </p>
        </div>

        <section class="mbc-footer__newsletter" aria-labelledby="mbc-newsletter-title">
            <h2 id="mbc-newsletter-title" class="mbc-footer__heading"><?php esc_html_e( 'Stay Connected', 'generatepress-child' ); ?></h2>
            <p class="mbc-footer__lead"><?php esc_html_e( 'Subscribe to the MBC newsletter', 'generatepress-child' ); ?></p>
            <p><?php esc_html_e( 'Weekly news and stories about everything that is happening at Moortown Baptist Church.', 'generatepress-child' ); ?></p>
            <?php
            if ( function_exists( 'mc4wp_show_form' ) ) {
                mc4wp_show_form( (int) $mbc_info['newsletter_form_id'] );
            }
            ?>
        </section>
    </div>

    <div class="mbc-footer__bottom">
        <?php if ( has_nav_menu( 'footer' ) ) : ?>
            <nav class="mbc-footer__nav" aria-label="<?php esc_attr_e( 'Footer', 'generatepress-child' ); ?>">
                <?php
                wp_nav_menu( array(
                    'theme_location' => 'footer',
                    'container'      => false,
                    'menu_class'     => 'mbc-footer__links',
                    'depth'          => 1,
                    'fallback_cb'    => false,
                ) );
                ?>
            </nav>
        <?php endif; ?>
        <p class="mbc-footer__copyright">
            &copy; <?php echo esc_html( wp_date( 'Y' ) ); ?> <?php echo esc_html( $mbc_info['name'] ); ?>
        </p>
    </div>
</footer>
