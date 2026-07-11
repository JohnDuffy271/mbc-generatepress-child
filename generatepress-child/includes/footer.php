<?php
/**
 * MBC Shared Footer
 * Include with: <?php include get_stylesheet_directory() . '/includes/footer.php'; ?>
 */
?>
<footer class="mbc-footer">
  <div class="mbc-footer-grid">

    <!-- Left: Logo + tagline + socials -->
    <div>
      <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="mbc-footer-logo">
        <svg width="30" height="30" viewBox="0 0 30 30" aria-hidden="true">
          <circle cx="15" cy="15" r="13" fill="none" stroke="#c9a84c" stroke-width="1"/>
          <ellipse cx="15" cy="15" rx="5.5" ry="13" fill="none" stroke="rgba(201,168,76,.38)" stroke-width="1"/>
          <line x1="2" y1="15" x2="28" y2="15" stroke="rgba(201,168,76,.38)" stroke-width="1"/>
        </svg>
        <div>
          <span class="mbc-footer-logo-name"><?php bloginfo( 'name' ); ?></span>
          <span class="mbc-footer-logo-sub">Leeds</span>
        </div>
      </a>
      <p class="mbc-footer-tagline">A Community Church with World-Wide Vision.<br>Everyone is welcome here.</p>
      <div class="mbc-footer-socials">
        <a href="https://www.facebook.com/MoortownBaptistChurch" target="_blank" rel="noopener" class="mbc-footer-social">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor">
            <path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z"/>
          </svg>
          Facebook
        </a>
        <a href="https://www.youtube.com/@MoortownBaptistChurch" target="_blank" rel="noopener" class="mbc-footer-social">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor">
            <path d="M22.54 6.42a2.78 2.78 0 00-1.95-1.96C18.88 4 12 4 12 4s-6.88 0-8.59.46a2.78 2.78 0 00-1.95 1.96A29 29 0 001 12a29 29 0 00.46 5.58A2.78 2.78 0 003.41 19.6C5.12 20 12 20 12 20s6.88 0 8.59-.46a2.78 2.78 0 001.95-1.95A29 29 0 0023 12a29 29 0 00-.46-5.58z"/>
            <polygon points="9.75 15.02 15.5 12 9.75 8.98 9.75 15.02" fill="#0a0a0a"/>
          </svg>
          YouTube
        </a>
      </div>
    </div>

    <!-- Right: Newsletter -->
    <div class="mbc-footer-newsletter">
      <h4>Stay <em>Connected</em></h4>
      <p>Weekly news and stories from Moortown Baptist Church — straight to your inbox.</p>
      <?php echo do_shortcode( '[mc4wp_form id="206"]' ); ?>
    </div>

  </div>

  <div class="mbc-footer-bottom">
    <span>&copy; <?php echo date( 'Y' ); ?> <?php bloginfo( 'name' ); ?>. Registered Charity No. 1128960.</span>
    <span>204 King Lane, Leeds LS17 6AA</span>
    <span><a href="<?php echo esc_url( home_url( '/privacy-policy/' ) ); ?>">Privacy Policy</a></span>
  </div>
</footer>
