<?php
/**
 * Newsletter settings: the default header image, and its preview on the issue edit screen.
 *
 * The option is MBC_NL_DEFAULT_HEADER_OPTION (registered in cpt.php, also readable and writable
 * over REST /wp/v2/settings by administrators). The settings page is Newsletter > Settings,
 * for manage_options only (the Newsletter editor role cannot reach it).
 */

defined( 'ABSPATH' ) || exit;

/**
 * Small preview of an attachment, or an empty string if it is not an image.
 *
 * @param int $attachment_id Attachment.
 * @return string HTML.
 */
function mbc_nl_header_preview( $attachment_id ) {
    if ( ! $attachment_id || ! wp_attachment_is_image( $attachment_id ) ) {
        return '';
    }
    return wp_get_attachment_image( $attachment_id, 'medium', false, array(
        'class'    => 'mbc-nl-header-preview__img',
        'sizes'    => '300px',
        'loading'  => 'lazy',
        'decoding' => 'async',
    ) );
}

// Header image box on the issue edit screen: with no image of its own, show the default.
add_filter( 'admin_post_thumbnail_html', 'mbc_nl_default_header_notice', 10, 3 );
function mbc_nl_default_header_notice( $content, $post_id, $thumbnail_id ) {
    if ( MBC_NL_TYPE !== get_post_type( $post_id ) || $thumbnail_id ) {
        return $content;
    }
    $default = (int) apply_filters( 'mbc_newsletter_default_header_id', (int) get_option( MBC_NL_DEFAULT_HEADER_OPTION, 0 ) );
    $preview = mbc_nl_header_preview( $default );
    if ( '' === $preview ) {
        return '<p class="mbc-nl-header-default description">' . esc_html__( 'No default header is set, so the site logo is used. Set a header image to use one for this issue.', 'generatepress-child' ) . '</p>' . $content;
    }
    return '<div class="mbc-nl-header-default">'
        . '<p class="mbc-nl-header-preview">' . $preview . '</p>'
        . '<p class="description">' . esc_html__( 'Using the default header. Set a header image to override it for this issue.', 'generatepress-child' ) . '</p>'
        . '</div>' . $content;
}

// Newsletter > Settings.
add_action( 'admin_menu', 'mbc_nl_settings_menu' );
function mbc_nl_settings_menu() {
    add_submenu_page(
        'edit.php?post_type=' . MBC_NL_TYPE,
        __( 'Newsletter settings', 'generatepress-child' ),
        __( 'Settings', 'generatepress-child' ),
        'manage_options',
        'mbc-newsletter-settings',
        'mbc_nl_render_settings'
    );
}

// An ID that is not an image becomes 0 (no default).
add_filter( 'sanitize_option_' . MBC_NL_DEFAULT_HEADER_OPTION, 'mbc_nl_sanitize_default_header' );
function mbc_nl_sanitize_default_header( $value ) {
    $id = absint( $value );
    return ( $id && wp_attachment_is_image( $id ) ) ? $id : 0;
}

function mbc_nl_render_settings() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You cannot do that.', 'generatepress-child' ), '', array( 'response' => 403 ) );
    }
    $id = (int) get_option( MBC_NL_DEFAULT_HEADER_OPTION, 0 );
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Newsletter settings', 'generatepress-child' ); ?></h1>
        <?php settings_errors(); ?>
        <form method="post" action="options.php">
            <?php settings_fields( 'mbc_newsletter' ); ?>
            <h2><?php esc_html_e( 'Default header image', 'generatepress-child' ); ?></h2>
            <p><?php esc_html_e( 'Used at the top of every issue that has no header image of its own, on the web version and in the email.', 'generatepress-child' ); ?></p>
            <input type="hidden" id="mbc-nl-default-header" name="<?php echo esc_attr( MBC_NL_DEFAULT_HEADER_OPTION ); ?>" value="<?php echo esc_attr( $id ); ?>">
            <div id="mbc-nl-default-header-preview" class="mbc-nl-header-preview"><?php echo mbc_nl_header_preview( $id ); // phpcs:ignore WordPress.Security.EscapeOutput -- built by wp_get_attachment_image(). ?></div>
            <p id="mbc-nl-default-header-none" class="description"<?php echo $id && wp_attachment_is_image( $id ) ? ' hidden' : ''; ?>><?php esc_html_e( 'No default header is set: the site logo is used.', 'generatepress-child' ); ?></p>
            <p>
                <button type="button" class="button" id="mbc-nl-default-header-choose"><?php esc_html_e( 'Choose from the media library', 'generatepress-child' ); ?></button>
                <button type="button" class="button" id="mbc-nl-default-header-remove"<?php echo $id ? '' : ' hidden'; ?>><?php esc_html_e( 'Remove', 'generatepress-child' ); ?></button>
            </p>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

add_action( 'admin_enqueue_scripts', 'mbc_nl_settings_assets' );
function mbc_nl_settings_assets( $hook ) {
    if ( 'mbc_newsletter_page_mbc-newsletter-settings' !== $hook ) {
        return;
    }
    wp_enqueue_media();
    $js = get_stylesheet_directory() . '/js/newsletter-settings.js';
    wp_enqueue_script( 'mbc-nl-settings', get_stylesheet_directory_uri() . '/js/newsletter-settings.js', array(), filemtime( $js ), true );
    wp_localize_script( 'mbc-nl-settings', 'mbcNlSettings', array(
        'title'  => __( 'Choose the default header image', 'generatepress-child' ),
        'button' => __( 'Use as default header', 'generatepress-child' ),
    ) );
    $css = get_stylesheet_directory() . '/css/newsletter-admin.css';
    wp_enqueue_style( 'mbc-nl-admin', get_stylesheet_directory_uri() . '/css/newsletter-admin.css', array(), filemtime( $css ) );
}

