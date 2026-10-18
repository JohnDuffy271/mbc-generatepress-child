<?php
/**
 * Newsletter issues: the ordered post list on the edit screen.
 *
 * Stored as post IDs (meta mbc_nl_posts), so an issue is a snapshot: posts published later
 * never change it. Pre-filled on first save; "Refill" replaces the list on request only.
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/prefill.php';

add_action( 'add_meta_boxes_' . MBC_NL_TYPE, 'mbc_nl_add_post_list_box' );
function mbc_nl_add_post_list_box() {
    add_meta_box( 'mbc_nl_posts', __( 'Posts in this issue', 'generatepress-child' ), 'mbc_nl_render_post_list_box', MBC_NL_TYPE, 'normal', 'default' );
}

/**
 * One list row (also used as the template for a post added from the menu).
 */
function mbc_nl_list_item_html( $post_id ) {
    $post  = get_post( $post_id );
    $ok    = $post && 'post' === $post->post_type && 'publish' === $post->post_status;
    $title = $ok ? get_the_title( $post ) : sprintf(
        /* translators: %d: post ID */
        __( 'Post %d (no longer published: it will be skipped)', 'generatepress-child' ),
        (int) $post_id
    );
    $thumb  = $ok ? get_the_post_thumbnail_url( $post, 'thumbnail' ) : '';
    $date   = $ok ? get_the_date( '', $post ) : '';
    $sticky = $ok && is_sticky( $post->ID );

    ob_start();
    ?>
    <li class="mbc-nl-item<?php echo $ok ? '' : ' mbc-nl-item--missing'; ?>" data-id="<?php echo (int) $post_id; ?>">
        <span class="mbc-nl-thumb"><?php if ( $thumb ) : ?><img src="<?php echo esc_url( $thumb ); ?>" alt="" width="48" height="48"><?php endif; ?></span>
        <span class="mbc-nl-text">
            <strong><?php echo esc_html( $title ); ?></strong>
            <?php if ( $date ) : ?>
                <span class="mbc-nl-meta"><?php echo esc_html( $date ); ?><?php echo $sticky ? ' · ' . esc_html__( 'Sticky', 'generatepress-child' ) : ''; ?></span>
            <?php endif; ?>
        </span>
        <span class="mbc-nl-actions">
            <button type="button" class="button" data-act="up" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: post title */ __( 'Move up: %s', 'generatepress-child' ), $title ) ); ?>"><?php esc_html_e( 'Up', 'generatepress-child' ); ?></button>
            <button type="button" class="button" data-act="down" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: post title */ __( 'Move down: %s', 'generatepress-child' ), $title ) ); ?>"><?php esc_html_e( 'Down', 'generatepress-child' ); ?></button>
            <button type="button" class="button" data-act="remove" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: post title */ __( 'Remove: %s', 'generatepress-child' ), $title ) ); ?>"><?php esc_html_e( 'Remove', 'generatepress-child' ); ?></button>
        </span>
    </li>
    <?php
    return trim( (string) ob_get_clean() );
}

function mbc_nl_render_post_list_box( $post ) {
    $stored  = metadata_exists( 'post', $post->ID, 'mbc_nl_posts' );
    $fill    = $stored ? null : mbc_nl_prefill( $post->ID );
    $ids     = $stored ? mbc_nl_get_post_ids( $post->ID ) : $fill['ids'];
    $since   = $stored ? (string) get_post_meta( $post->ID, '_mbc_nl_since', true ) : $fill['since'];
    $recent  = get_posts( array(
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => 60,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'no_found_rows'  => true,
        'has_password'   => false,
    ) );
    $default_date = get_date_from_gmt( mbc_nl_default_since( $post->ID ), 'Y-m-d' );
    $refill = wp_nonce_url( admin_url( 'admin-post.php?action=mbc_nl_refill&issue=' . (int) $post->ID ), 'mbc_nl_refill_' . (int) $post->ID );
    ?>
    <p class="description">
        <?php
        if ( $since ) {
            printf(
                /* translators: %s: date and time */
                esc_html__( 'Filled from posts published since %s, with sticky posts first.', 'generatepress-child' ),
                esc_html( get_date_from_gmt( $since, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) )
            );
        }
        echo ' ' . esc_html__( 'Order them with Up and Down, then Update. Posts published later do not change a saved issue.', 'generatepress-child' );
        ?>
    </p>
    <ul class="mbc-nl-list" id="mbc-nl-list">
        <?php foreach ( $ids as $post_id ) : ?>
            <?php echo mbc_nl_list_item_html( $post_id ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the function. ?>
        <?php endforeach; ?>
    </ul>
    <p id="mbc-nl-empty" class="description"<?php echo $ids ? ' hidden' : ''; ?>><?php esc_html_e( 'No posts yet. Add some below, or use Refill from recent posts.', 'generatepress-child' ); ?></p>
    <input type="hidden" name="mbc_nl_posts" id="mbc-nl-posts-field" value="<?php echo esc_attr( implode( ',', $ids ) ); ?>">
    <p id="mbc-nl-count" class="description" role="status" aria-live="polite"></p>

    <div class="mbc-nl-controls">
        <p class="mbc-nl-add">
            <label for="mbc-nl-add"><strong><?php esc_html_e( 'Add a post', 'generatepress-child' ); ?></strong></label><br>
            <select id="mbc-nl-add">
                <option value=""><?php esc_html_e( 'Choose a recent post…', 'generatepress-child' ); ?></option>
                <?php foreach ( $recent as $p ) : ?>
                    <option value="<?php echo (int) $p->ID; ?>" data-item="<?php echo esc_attr( mbc_nl_list_item_html( $p->ID ) ); ?>">
                        <?php echo esc_html( get_the_date( 'j M Y', $p ) . ': ' . get_the_title( $p ) ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="button" class="button" id="mbc-nl-add-button"><?php esc_html_e( 'Add', 'generatepress-child' ); ?></button>
        </p>
        <?php if ( 'auto-draft' !== $post->post_status ) : ?>
            <p class="mbc-nl-refill">
                <label for="mbc-nl-since"><strong><?php esc_html_e( 'Posts since', 'generatepress-child' ); ?></strong></label><br>
                <input type="date" id="mbc-nl-since" value="<?php echo esc_attr( $default_date ); ?>" max="<?php echo esc_attr( wp_date( 'Y-m-d' ) ); ?>" data-default="<?php echo esc_attr( $default_date ); ?>">
                <a class="button button-secondary" id="mbc-nl-refill" href="<?php echo esc_url( $refill ); ?>" data-base="<?php echo esc_url( $refill ); ?>"><?php esc_html_e( 'Refill from recent posts', 'generatepress-child' ); ?></a>
            </p>
        <?php endif; ?>
    </div>
    <?php if ( 'auto-draft' !== $post->post_status ) : ?>
        <p class="description"><?php esc_html_e( 'Refill replaces the list above with the posts published since the chosen date (the date of the last issue to start with): sticky posts first, then the newest, up to 6. Unsaved changes on this screen are lost.', 'generatepress-child' ); ?></p>
    <?php endif; ?>
    <?php
}

// Save the list the editor arranged (runs after the nonce and capability checks).
add_action( 'mbc_nl_save_issue', 'mbc_nl_save_post_list' );
function mbc_nl_save_post_list( $post_id ) {
    if ( ! isset( $_POST['mbc_nl_posts'] ) ) {
        return;
    }
    $raw = sanitize_text_field( wp_unslash( $_POST['mbc_nl_posts'] ) );
    $ids = '' === $raw ? array() : array_map( 'absint', explode( ',', $raw ) );
    update_post_meta( $post_id, 'mbc_nl_posts', mbc_nl_sanitise_post_ids( $ids ) );
}

// First save without a list (including an issue made over REST): pre-fill once.
add_action( 'save_post_' . MBC_NL_TYPE, 'mbc_nl_first_fill', 20, 2 );
function mbc_nl_first_fill( $post_id, $post ) {
    if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) || 'auto-draft' === $post->post_status ) {
        return;
    }
    if ( metadata_exists( 'post', $post_id, 'mbc_nl_posts' ) || isset( $_POST['mbc_nl_posts'] ) || ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }
    $fill = mbc_nl_prefill( $post_id );
    update_post_meta( $post_id, 'mbc_nl_posts', $fill['ids'] );
    update_post_meta( $post_id, '_mbc_nl_since', $fill['since'] );
}

// Refill on request: replaces the list with the current pre-fill.
add_action( 'admin_post_mbc_nl_refill', 'mbc_nl_handle_refill' );
function mbc_nl_handle_refill() {
    $issue_id = isset( $_GET['issue'] ) ? absint( $_GET['issue'] ) : 0;
    check_admin_referer( 'mbc_nl_refill_' . $issue_id );
    if ( ! $issue_id || MBC_NL_TYPE !== get_post_type( $issue_id ) || ! current_user_can( 'edit_post', $issue_id ) ) {
        wp_die( esc_html__( 'You cannot do that.', 'generatepress-child' ), '', array( 'response' => 403 ) );
    }
    $date     = isset( $_GET['since'] ) ? sanitize_text_field( wp_unslash( $_GET['since'] ) ) : '';
    $fill     = mbc_nl_prefill( $issue_id, mbc_nl_since_from_date( $date, $issue_id ) );
    $args     = array( 'mbc_nl_notice' => 'refill', 'mbc_nl_n' => count( $fill['ids'] ) );
    if ( $fill['ids'] ) {
        update_post_meta( $issue_id, 'mbc_nl_posts', $fill['ids'] );
        update_post_meta( $issue_id, '_mbc_nl_since', $fill['since'] );
    } else { // Nothing found: keep the current list rather than emptying it.
        $args['mbc_nl_notice'] = 'refill-empty';
        $args['mbc_nl_since']  = rawurlencode( get_date_from_gmt( $fill['since'], 'Y-m-d' ) );
    }
    wp_safe_redirect( add_query_arg( $args, get_edit_post_link( $issue_id, 'url' ) ) );
    exit;
}

add_action( 'admin_notices', 'mbc_nl_refill_notice' );
function mbc_nl_refill_notice() {
    if ( mbc_nl_is_issue_screen() && isset( $_GET['mbc_nl_notice'], $_GET['mbc_nl_since'] ) && 'refill-empty' === $_GET['mbc_nl_notice'] ) {
        $d = sanitize_text_field( wp_unslash( $_GET['mbc_nl_since'] ) );
        $t = 1 === preg_match( '/^\d{4}-\d{2}-\d{2}$/D', $d ) ? strtotime( $d ) : false;
        echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html( sprintf(
            /* translators: %s: date */
            __( 'No new posts since %s. Choose an earlier date or add posts below.', 'generatepress-child' ),
            false !== $t ? wp_date( get_option( 'date_format' ), $t ) : $d
        ) ) . '</p></div>';
        return;
    }
    if ( mbc_nl_is_issue_screen() && isset( $_GET['mbc_nl_notice'] ) && 'refill' === $_GET['mbc_nl_notice'] ) {
        $n = isset( $_GET['mbc_nl_n'] ) ? absint( $_GET['mbc_nl_n'] ) : 0;
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sprintf(
            /* translators: %d: number of posts */
            _n( 'List refilled: %d post.', 'List refilled: %d posts.', $n, 'generatepress-child' ),
            $n
        ) ) . '</p></div>';
    }
}
