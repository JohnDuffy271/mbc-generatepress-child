<?php
/**
 * Template Name: MBC Standard Page
 * Template Post Type: page
 *
 * Used for: About, Church Life, Mission, What's On, sub-pages
 * Layout: fixed nav | banner image | title block | left sidebar + main content | footer
 */
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php wp_head(); ?>
</head>
<body <?php body_class( 'mbc-page-standard' ); ?>>
<?php wp_body_open(); ?>

<?php while ( have_posts() ) : the_post(); ?>

<!-- NAV -->
<?php include get_stylesheet_directory() . '/includes/nav.php'; ?>

<!-- PAGE WRAP — pushed below fixed nav -->
<div class="mbc-page-wrap">

  <!-- BANNER IMAGE -->
  <div class="mbc-banner">
    <div class="mbc-banner-bg" <?php
      $thumb = get_the_post_thumbnail_url( get_the_ID(), 'full' );
      if ( $thumb ) echo 'style="background-image:url(' . esc_url( $thumb ) . ')"';
    ?>></div>
    <div class="mbc-banner-overlay"></div>
  </div>

  <!-- PAGE TITLE -->
  <div class="mbc-page-title-block">
    <span class="mbc-page-eyebrow">Moortown Baptist Church</span>
    <h1 class="mbc-page-title"><?php the_title(); ?></h1>
    <p class="mbc-breadcrumb">
      <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Home</a>
      <?php if ( $post->post_parent ) : ?>
        <span>›</span>
        <a href="<?php echo get_permalink( $post->post_parent ); ?>"><?php echo get_the_title( $post->post_parent ); ?></a>
      <?php endif; ?>
      <span>›</span>
      <?php the_title(); ?>
    </p>
  </div>

  <!-- TWO-COLUMN LAYOUT -->
  <div class="mbc-page-layout">

    <!-- LEFT SIDEBAR -->
    <?php include get_stylesheet_directory() . '/includes/sidebar-left.php'; ?>

    <!-- MAIN CONTENT -->
    <div class="mbc-main-content">
      <div class="mbc-content-inner">
        <?php the_content(); ?>
      </div>
    </div>

  </div><!-- end mbc-page-layout -->

</div><!-- end mbc-page-wrap -->

<!-- FOOTER -->
<?php include get_stylesheet_directory() . '/includes/footer.php'; ?>

<?php endwhile; ?>

<?php wp_footer(); ?>
</body>
</html>
