<?php
/**
 * Category, tag, date and author archives: the same styled card listing as the News page.
 *
 * The cards are the News listing pattern (patterns/news-listing.php) with its Query Loop set to
 * inherit the archive's own query, so the markup, styles and pagination are the News ones.
 * The News category itself redirects to /news/ (inc/archives.php).
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main id="main" class="mbc-page mbc-page--listing mbc-page--archive">
    <header class="mbc-page__header">
        <h1 class="mbc-page__title"><?php echo esc_html( is_category() ? single_cat_title( '', false ) : wp_strip_all_tags( get_the_archive_title() ) ); ?></h1>
    </header>
    <div class="mbc-page__content">
        <?php echo mbc_archive_listing_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered blocks. ?>
    </div>
</main>

<?php
get_footer();
