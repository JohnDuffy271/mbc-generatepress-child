<?php
/**
 * Template Name: MBC Section Index
 * Template Post Type: page
 *
 * Displays a curated grid of cards linking to sub-pages.
 * Cards are pulled automatically from child pages of this page,
 * with a fallback to WordPress menu children if no child pages exist.
 * Featured image on each sub-page becomes the card thumbnail.
 */
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php wp_head(); ?>
</head>
<body <?php body_class( 'mbc-page-standard mbc-page-index' ); ?>>
<?php wp_body_open(); ?>

<?php while ( have_posts() ) : the_post();

  // ── Get child pages of this page ─────────────────────────────────────
  $child_pages = get_pages( array(
    'parent'      => get_the_ID(),
    'sort_column' => 'menu_order',
    'sort_order'  => 'ASC',
    'post_status' => 'publish',
  ) );

  // ── Fallback: get children from the primary nav menu ─────────────────
  $menu_children = array();
  if ( empty( $child_pages ) ) {
    $locations = get_nav_menu_locations();
    if ( isset( $locations['primary'] ) ) {
      $menu_items = wp_get_nav_menu_items( $locations['primary'] );
      if ( $menu_items ) {
        $this_item_id = null;
        foreach ( $menu_items as $item ) {
          if ( (int) $item->object_id === get_the_ID() ) {
            $this_item_id = $item->ID;
            break;
          }
        }
        if ( $this_item_id ) {
          foreach ( $menu_items as $item ) {
            if ( (int) $item->menu_item_parent === $this_item_id ) {
              $menu_children[] = array(
                'title' => $item->title,
                'url'   => $item->url,
                'id'    => (int) $item->object_id,
              );
            }
          }
        }
      }
    }
  }
?>

<!-- NAV -->
<?php include get_stylesheet_directory() . '/includes/nav.php'; ?>

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

      <!-- Intro text — always call the_content() so Elementor can edit it -->
      <div class="mbc-content-inner mbc-index-intro">
        <?php the_content(); ?>
      </div>

      <!-- SUB-PAGE CARDS GRID -->
      <div class="mbc-index-grid">

        <?php if ( ! empty( $child_pages ) ) :
          foreach ( $child_pages as $child ) :
            $child_thumb   = get_the_post_thumbnail_url( $child->ID, 'large' );
            $child_excerpt = get_post_field( 'post_excerpt', $child->ID );
            if ( ! $child_excerpt ) {
              // Strip style/script blocks before generating excerpt
              $raw = preg_replace( '/<style[^>]*>.*?<\/style>/si', '', $child->post_content );
              $raw = preg_replace( '/<script[^>]*>.*?<\/script>/si', '', $raw );
              $raw = wp_strip_all_tags( $raw );
              $raw = preg_replace( '/\s+/', ' ', trim( $raw ) );
              $child_excerpt = $raw ? wp_trim_words( $raw, 20, '…' ) : '';
            }
        ?>
        <a href="<?php echo get_permalink( $child->ID ); ?>" class="mbc-index-card">
          <div class="mbc-index-card-img" <?php
            if ( $child_thumb ) echo 'style="background-image:url(' . esc_url( $child_thumb ) . ')"';
          ?>></div>
          <div class="mbc-index-card-body">
            <h3 class="mbc-index-card-title"><?php echo esc_html( $child->post_title ); ?></h3>
            <?php if ( $child_excerpt ) : ?>
              <p class="mbc-index-card-excerpt"><?php echo esc_html( $child_excerpt ); ?></p>
            <?php endif; ?>
            <span class="mbc-index-card-cta">Find out more &#8594;</span>
          </div>
        </a>
        <?php endforeach; ?>

        <?php elseif ( ! empty( $menu_children ) ) :
          foreach ( $menu_children as $child ) :
            $child_thumb   = $child['id'] ? get_the_post_thumbnail_url( $child['id'], 'large' ) : false;
            if ( $child['id'] ) {
              $child_excerpt = get_post_field( 'post_excerpt', $child['id'] );
              if ( ! $child_excerpt ) {
                $raw = preg_replace( '/<style[^>]*>.*?<\/style>/si', '', get_post_field( 'post_content', $child['id'] ) );
                $raw = preg_replace( '/<script[^>]*>.*?<\/script>/si', '', $raw );
                $raw = wp_strip_all_tags( $raw );
                $raw = preg_replace( '/\s+/', ' ', trim( $raw ) );
                $child_excerpt = $raw ? wp_trim_words( $raw, 20, '…' ) : '';
              }
            } else {
              $child_excerpt = '';
            }
        ?>
        <a href="<?php echo esc_url( $child['url'] ); ?>" class="mbc-index-card">
          <div class="mbc-index-card-img" <?php
            if ( $child_thumb ) echo 'style="background-image:url(' . esc_url( $child_thumb ) . ')"';
          ?>></div>
          <div class="mbc-index-card-body">
            <h3 class="mbc-index-card-title"><?php echo esc_html( $child['title'] ); ?></h3>
            <?php if ( $child_excerpt ) : ?>
              <p class="mbc-index-card-excerpt"><?php echo esc_html( $child_excerpt ); ?></p>
            <?php endif; ?>
            <span class="mbc-index-card-cta">Find out more &#8594;</span>
          </div>
        </a>
        <?php endforeach; ?>

        <?php else : ?>
        <div class="mbc-index-empty">
          <p>Sub-pages will appear here once they are added as child pages or menu items.</p>
        </div>
        <?php endif; ?>

      </div><!-- end mbc-index-grid -->

    </div><!-- end mbc-main-content -->

  </div><!-- end mbc-page-layout -->

</div><!-- end mbc-page-wrap -->

<!-- FOOTER -->
<?php include get_stylesheet_directory() . '/includes/footer.php'; ?>

<?php endwhile; ?>

<?php wp_footer(); ?>
</body>
</html>
