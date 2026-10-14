<?php
/**
 * Site header: brand, burger (below the desktop breakpoint) and primary nav.
 * Menu items with class "mbc-menu-toggle" render as buttons (see
 * inc/class-mbc-nav-walker.php).
 */

defined( 'ABSPATH' ) || exit;

$mbc_logo_id = (int) get_theme_mod( 'custom_logo' );
?>
<header class="mbc-header" id="masthead">
    <div class="mbc-header__bar">
        <a class="mbc-header__brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
            <?php if ( $mbc_logo_id ) : ?>
                <?php
                echo wp_get_attachment_image( $mbc_logo_id, 'full', false, array(
                    'class' => 'mbc-header__logo',
                    'alt'   => get_bloginfo( 'name' ),
                ) );
                ?>
            <?php else : ?>
                <span class="mbc-header__name"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></span>
            <?php endif; ?>
        </a>

        <button type="button" class="mbc-header__back" aria-label="<?php esc_attr_e( 'Back to main menu', 'generatepress-child' ); ?>">
            <svg class="mbc-chevron" width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M15 6l-6 6 6 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            <span><?php esc_html_e( 'Back', 'generatepress-child' ); ?></span>
        </button>

        <button type="button" class="mbc-header__burger" aria-expanded="false" aria-controls="mbc-nav"
                data-open-label="<?php esc_attr_e( 'Open menu', 'generatepress-child' ); ?>"
                data-close-label="<?php esc_attr_e( 'Close menu', 'generatepress-child' ); ?>">
            <span class="screen-reader-text"><?php esc_html_e( 'Open menu', 'generatepress-child' ); ?></span>
            <span class="mbc-burger-icon" aria-hidden="true"></span>
        </button>

        <nav id="mbc-nav" class="mbc-nav" aria-label="<?php esc_attr_e( 'Main', 'generatepress-child' ); ?>">
            <?php
            wp_nav_menu( array(
                'theme_location' => 'primary',
                'container'      => false,
                'menu_class'     => 'mbc-menu',
                'items_wrap'     => '<ul class="%2$s">%3$s</ul>',
                'walker'         => new MBC_Nav_Walker(),
                'fallback_cb'    => false,
            ) );
            ?>
        </nav>
    </div>
</header>
