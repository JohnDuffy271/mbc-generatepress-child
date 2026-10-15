<?php
/**
 * Newsletter issues: the edit screen (subtitle, intro, preview key).
 *
 * The header image is the featured image; the intro is the classic editor limited
 * to what an email can show; the ordered post list is in post-list.php.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Tags allowed in the intro: prose, links, lists.
 *
 * @return array<string,array<string,bool>>
 */
function mbc_nl_intro_allowed_html() {
    return array(
        'p'      => array(),
        'br'     => array(),
        'strong' => array(),
        'b'      => array(),
        'em'     => array(),
        'i'      => array(),
        'a'      => array( 'href' => true, 'title' => true ),
        'ul'     => array(),
        'ol'     => array(),
        'li'     => array(),
    );
}

// A draft needs a slug too (its email URL is /newsletter/<slug>/email/), and the intro is
// cleaned to the tags above whoever saves it.
add_filter( 'wp_insert_post_data', 'mbc_nl_prepare_post_data', 10, 2 );
function mbc_nl_prepare_post_data( $data, $postarr ) {
    if ( MBC_NL_TYPE !== $data['post_type'] ) {
        return $data;
    }
    if ( '' === $data['post_name'] && '' !== trim( (string) $data['post_title'] ) && 'auto-draft' !== $data['post_status'] ) {
        $post_id = isset( $postarr['ID'] ) ? (int) $postarr['ID'] : 0;
        // Ask as if published, so two drafts never share a slug.
        $data['post_name'] = wp_unique_post_slug( sanitize_title( wp_unslash( $data['post_title'] ) ), $post_id, 'publish', MBC_NL_TYPE, (int) $data['post_parent'] );
    }
    $data['post_content'] = wp_slash( wp_kses( wp_unslash( $data['post_content'] ), mbc_nl_intro_allowed_html() ) );
    return $data;
}

function mbc_nl_is_issue_screen() {
    $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
    return $screen && MBC_NL_TYPE === $screen->post_type;
}

// The intro editor: no media buttons, a short toolbar.
add_filter( 'wp_editor_settings', 'mbc_nl_editor_settings', 10, 2 );
function mbc_nl_editor_settings( $settings, $editor_id ) {
    if ( 'content' === $editor_id && mbc_nl_is_issue_screen() ) {
        $settings['media_buttons'] = false;
        $settings['quicktags']     = array( 'buttons' => 'strong,em,link,ul,ol,li' );
        $settings['textarea_rows'] = 8;
    }
    return $settings;
}

add_filter( 'mce_buttons', 'mbc_nl_mce_buttons' );
function mbc_nl_mce_buttons( $buttons ) {
    return mbc_nl_is_issue_screen() ? array( 'bold', 'italic', 'bullist', 'numlist', 'link', 'unlink', 'undo', 'redo' ) : $buttons;
}

add_filter( 'mce_buttons_2', 'mbc_nl_mce_buttons_2' );
function mbc_nl_mce_buttons_2( $buttons ) {
    return mbc_nl_is_issue_screen() ? array() : $buttons;
}

// Subtitle, directly under the title.
add_action( 'edit_form_after_title', 'mbc_nl_render_subtitle_field' );
function mbc_nl_render_subtitle_field( $post ) {
    if ( MBC_NL_TYPE !== $post->post_type ) {
        return;
    }
    wp_nonce_field( 'mbc_nl_save', 'mbc_nl_nonce' );
    $subtitle = (string) get_post_meta( $post->ID, 'mbc_nl_subtitle', true );
    ?>
    <div class="mbc-nl-subtitle" style="margin:12px 0;">
        <label for="mbc-nl-subtitle" style="display:block;font-weight:600;margin-bottom:4px;"><?php esc_html_e( 'Subtitle (optional, shown under the title)', 'generatepress-child' ); ?></label>
        <input type="text" id="mbc-nl-subtitle" name="mbc_nl_subtitle" value="<?php echo esc_attr( $subtitle ); ?>" class="widefat" maxlength="160" autocomplete="off">
        <p class="description"><?php esc_html_e( 'Write the intro in the box below (paragraphs, links and lists only). Set the header image on the right.', 'generatepress-child' ); ?></p>
    </div>
    <?php
}

add_action( 'save_post_' . MBC_NL_TYPE, 'mbc_nl_save_issue', 10, 2 );
function mbc_nl_save_issue( $post_id, $post ) {
    if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) || 'auto-draft' === $post->post_status ) {
        return;
    }
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }
    mbc_nl_get_key( $post_id ); // Creates the preview key on first save.
    if ( ! isset( $_POST['mbc_nl_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mbc_nl_nonce'] ) ), 'mbc_nl_save' ) ) {
        return;
    }
    if ( isset( $_POST['mbc_nl_subtitle'] ) ) {
        update_post_meta( $post_id, 'mbc_nl_subtitle', sanitize_text_field( wp_unslash( $_POST['mbc_nl_subtitle'] ) ) );
    }
    do_action( 'mbc_nl_save_issue', $post_id );
}

// The email link panel (side).
add_action( 'add_meta_boxes_' . MBC_NL_TYPE, 'mbc_nl_add_meta_boxes' );
function mbc_nl_add_meta_boxes() {
    remove_meta_box( 'postcustom', MBC_NL_TYPE, 'normal' ); // Subtitle and post list have their own fields.
    add_meta_box( 'mbc_nl_links', __( 'Email link', 'generatepress-child' ), 'mbc_nl_render_links_box', MBC_NL_TYPE, 'side', 'high' );
}

function mbc_nl_render_links_box( $post ) {
    if ( '' === $post->post_name || 'auto-draft' === $post->post_status ) {
        echo '<p>' . esc_html__( 'Give the issue a title and save the draft: its links appear here.', 'generatepress-child' ) . '</p>';
        return;
    }
    $url   = mbc_nl_email_url( $post->ID, true );
    $regen = wp_nonce_url( admin_url( 'admin-post.php?action=mbc_nl_regen_key&issue=' . (int) $post->ID ), 'mbc_nl_regen_' . (int) $post->ID );
    ?>
    <p><label for="mbc-nl-email-url"><strong><?php esc_html_e( 'Email URL for Mailchimp', 'generatepress-child' ); ?></strong></label></p>
    <input type="text" id="mbc-nl-email-url" class="widefat" readonly value="<?php echo esc_attr( $url ); ?>" onfocus="this.select()">
    <p>
        <button type="button" class="button" id="mbc-nl-copy"><?php esc_html_e( 'Copy email URL', 'generatepress-child' ); ?></button>
        <a class="button" id="mbc-nl-regen" href="<?php echo esc_url( $regen ); ?>"><?php esc_html_e( 'Regenerate key', 'generatepress-child' ); ?></a>
    </p>
    <p id="mbc-nl-copy-status" class="description" role="status" aria-live="polite"></p>
    <p class="description"><?php esc_html_e( 'Import this link in Mailchimp. Anyone who has it can see the email, even before the issue is published. A new key stops the old link working.', 'generatepress-child' ); ?></p>
    <?php if ( 'publish' === $post->post_status ) : ?>
        <p><a href="<?php echo esc_url( get_permalink( $post ) ); ?>"><?php esc_html_e( 'View web version', 'generatepress-child' ); ?></a></p>
    <?php endif; ?>
    <?php
}

add_action( 'admin_post_mbc_nl_regen_key', 'mbc_nl_handle_regen_key' );
function mbc_nl_handle_regen_key() {
    $issue_id = isset( $_GET['issue'] ) ? absint( $_GET['issue'] ) : 0;
    check_admin_referer( 'mbc_nl_regen_' . $issue_id );
    if ( ! $issue_id || MBC_NL_TYPE !== get_post_type( $issue_id ) || ! current_user_can( 'edit_post', $issue_id ) ) {
        wp_die( esc_html__( 'You cannot do that.', 'generatepress-child' ), '', array( 'response' => 403 ) );
    }
    mbc_nl_regenerate_key( $issue_id );
    wp_safe_redirect( add_query_arg( 'mbc_nl_notice', 'key', get_edit_post_link( $issue_id, 'url' ) ) );
    exit;
}

add_action( 'admin_notices', 'mbc_nl_admin_notices' );
function mbc_nl_admin_notices() {
    if ( mbc_nl_is_issue_screen() && isset( $_GET['mbc_nl_notice'] ) && 'key' === $_GET['mbc_nl_notice'] ) {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'New key made. The old email link no longer works: copy the new one into Mailchimp.', 'generatepress-child' ) . '</p></div>';
    }
}

add_action( 'admin_enqueue_scripts', 'mbc_nl_admin_assets' );
function mbc_nl_admin_assets( $hook ) {
    if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) || ! mbc_nl_is_issue_screen() ) {
        return;
    }
    $file = get_stylesheet_directory() . '/js/newsletter-admin.js';
    wp_enqueue_script( 'mbc-nl-admin', get_stylesheet_directory_uri() . '/js/newsletter-admin.js', array(), filemtime( $file ), true );
    wp_localize_script( 'mbc-nl-admin', 'mbcNl', array(
        'copied'  => __( 'Copied.', 'generatepress-child' ),
        'failed'  => __( 'Could not copy: select the link and copy it by hand.', 'generatepress-child' ),
        'confirm' => __( 'Make a new key? The old email link will stop working.', 'generatepress-child' ),
    ) );
}
