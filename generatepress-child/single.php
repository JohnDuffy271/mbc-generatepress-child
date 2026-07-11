<?php
/**
 * MBC Single Post Template
 *
 * Replaces WordPress default single.php for all posts.
 * Automatically uses carousel if post has the 'carousel' tag
 * or if a custom field 'mbc_carousel' is set to 'yes'.
 * Layout: fixed nav | hero image (or carousel) | title block | left sidebar + content | comments | footer
 */

// Detect carousel mode — tag 'carousel' or custom field
$use_carousel = has_tag( 'carousel' ) || get_post_meta( get_the_ID(), 'mbc_carousel', true ) === 'yes';

// Get all attached images for carousel mode
$carousel_images = array();
if ( $use_carousel ) {
  $attachments = get_posts( array(
    'post_type'      => 'attachment',
    'post_parent'    => get_the_ID(),
    'post_mime_type' => 'image',
    'posts_per_page' => -1,
    'orderby'        => 'menu_order',
    'order'          => 'ASC',
  ) );
  foreach ( $attachments as $att ) {
    $carousel_images[] = wp_get_attachment_image_url( $att->ID, 'large' );
  }
  // Fall back to featured image if no attachments
  if ( empty( $carousel_images ) ) {
    $thumb = get_the_post_thumbnail_url( get_the_ID(), 'large' );
    if ( $thumb ) $carousel_images[] = $thumb;
  }
}
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php wp_head(); ?>
</head>
<body <?php body_class( 'mbc-page-post' ); ?>>
<?php wp_body_open(); ?>

<?php while ( have_posts() ) : the_post(); ?>

<!-- NAV -->
<?php include get_stylesheet_directory() . '/includes/nav.php'; ?>

<div class="mbc-page-wrap">

  <?php if ( $use_carousel && ! empty( $carousel_images ) ) : ?>
  <!-- CAROUSEL HERO -->
  <div class="mbc-carousel-hero">
    <div class="mbc-carousel-hero-track">
      <?php foreach ( $carousel_images as $img_url ) : ?>
        <div class="mbc-carousel-hero-slide" style="background-image:url(<?php echo esc_url( $img_url ); ?>)"></div>
      <?php endforeach; ?>
    </div>
    <div class="mbc-carousel-hero-controls">
      <button class="mbc-ch-prev" aria-label="Previous">&#8592;</button>
      <div class="mbc-ch-dots"></div>
      <button class="mbc-ch-next" aria-label="Next">&#8594;</button>
    </div>
  </div>

  <?php else : ?>
  <!-- SINGLE HERO IMAGE -->
  <div class="mbc-post-hero">
    <div class="mbc-post-hero-bg" <?php
      $thumb = get_the_post_thumbnail_url( get_the_ID(), 'full' );
      if ( $thumb ) echo 'style="background-image:url(' . esc_url( $thumb ) . ')"';
    ?>></div>
    <div class="mbc-post-hero-overlay"></div>
  </div>
  <?php endif; ?>

  <!-- POST TITLE BLOCK -->
  <div class="mbc-post-title-block">
    <?php
    $cats = get_the_category();
    if ( $cats ) :
      echo '<span class="mbc-post-category">' . esc_html( $cats[0]->name ) . '</span>';
    endif;
    ?>
    <h1 class="mbc-post-title"><?php the_title(); ?></h1>
    <div class="mbc-post-meta">
      <span><?php echo get_the_date( 'j F Y' ); ?></span>
      <span>By <?php the_author(); ?></span>
      <?php if ( $use_carousel ) : ?>
        <span>&#9654; Gallery post</span>
      <?php endif; ?>
    </div>
  </div>

  <!-- TWO-COLUMN LAYOUT -->
  <div class="mbc-page-layout">

    <!-- LEFT SIDEBAR -->
    <?php include get_stylesheet_directory() . '/includes/sidebar-left.php'; ?>

    <!-- MAIN CONTENT -->
    <div class="mbc-main-content">
      <div class="mbc-content-inner">
        <?php the_content(); ?>

        <!-- Post navigation -->
        <div style="margin-top:48px; padding-top:24px; border-top:1px solid #1e1e1e; display:flex; justify-content:space-between; flex-wrap:wrap; gap:16px;">
          <?php
          $prev = get_previous_post();
          $next = get_next_post();
          if ( $prev ) echo '<a href="' . get_permalink($prev) . '" style="font-family:\'Outfit\',sans-serif;font-size:.75rem;color:rgba(201,168,76,.6);text-decoration:none;font-weight:600;letter-spacing:.04em;" onmouseover="this.style.color=\'#c9a84c\'" onmouseout="this.style.color=\'rgba(201,168,76,.6)\'">&larr; ' . esc_html(get_the_title($prev)) . '</a>';
          if ( $next ) echo '<a href="' . get_permalink($next) . '" style="font-family:\'Outfit\',sans-serif;font-size:.75rem;color:rgba(201,168,76,.6);text-decoration:none;font-weight:600;letter-spacing:.04em;margin-left:auto;" onmouseover="this.style.color=\'#c9a84c\'" onmouseout="this.style.color=\'rgba(201,168,76,.6)\'">' . esc_html(get_the_title($next)) . ' &rarr;</a>';
          ?>
        </div>
      </div>

      <!-- COMMENTS -->
      <?php if ( comments_open() || get_comments_number() ) : ?>
      <div class="mbc-comments">
        <h2>Responses <em>&amp; Comments</em></h2>
        <?php comments_template(); ?>
      </div>
      <?php endif; ?>

    </div><!-- end mbc-main-content -->

  </div><!-- end mbc-page-layout -->

</div><!-- end mbc-page-wrap -->

<!-- FOOTER -->
<?php include get_stylesheet_directory() . '/includes/footer.php'; ?>

<?php endwhile; ?>

<?php wp_footer(); ?>
</body>
</html>
