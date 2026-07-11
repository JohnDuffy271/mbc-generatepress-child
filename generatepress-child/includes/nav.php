<?php
/**
 * MBC Shared Navigation
 * Include with: <?php include get_stylesheet_directory() . '/includes/nav.php'; ?>
 */
?>
<nav class="mbc-nav" id="mbc-nav">
  <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="mbc-nav-logo">
    <svg width="30" height="30" viewBox="0 0 30 30" aria-hidden="true">
      <circle cx="15" cy="15" r="13" fill="none" stroke="#c9a84c" stroke-width="1"/>
      <ellipse cx="15" cy="15" rx="5.5" ry="13" fill="none" stroke="rgba(201,168,76,.38)" stroke-width="1"/>
      <line x1="2" y1="15" x2="28" y2="15" stroke="rgba(201,168,76,.38)" stroke-width="1"/>
    </svg>
    <div>
      <span class="mbc-nav-site-name"><?php bloginfo( 'name' ); ?></span>
      <span class="mbc-nav-site-sub">Leeds</span>
    </div>
  </a>

  <?php wp_nav_menu( array(
    'theme_location' => 'primary',
    'menu_class'     => 'mbc-nav-links',
    'container'      => false,
    'depth'          => 2,
    'fallback_cb'    => false,
    'walker'         => false,
  ) ); ?>

  <!-- Mobile burger -->
  <button class="mbc-burger" id="mbc-burger" aria-label="Toggle menu" aria-expanded="false">
    <span></span><span></span><span></span>
  </button>
</nav>
