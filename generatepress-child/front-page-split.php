<?php
/**
 * Template Name: MBC Split Layout
 * Template Post Type: page
 *
 * Full-screen split layout — hero left, live news right.
 * Bypasses GeneratePress header/footer entirely.
 */
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;0,900;1,400;1,700&family=Lato:wght@300;400;700&family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
<?php wp_head(); ?>
</head>
<body <?php body_class( 'mbc-split-page' ); ?>>
<?php wp_body_open(); ?>

<?php include get_stylesheet_directory() . '/includes/sidebar-left.php'; ?>

<div class="mbc-screen" id="mbc-screen">

  <!-- ══ LEFT: HERO PANEL ══ -->
  <div class="mbc-hero-panel" id="mbc-hero-panel">

    <!-- Navigation -->
    <nav class="mbc-nav">
      <div class="mbc-nav-logo">
        <div>
        <p class="mbc-site-name"><?php bloginfo( 'name' ); ?> &mdash; Leeds</p>
        <!-- <p class="mbc-site-name"><?php bloginfo( 'name' ); ?></p>
          <p class="mbc-site-sub">Leeds</p> -->
        </div>
      </div>
      <?php
      wp_nav_menu( array(
        'theme_location' => 'primary',
        'menu_class'     => 'mbc-nav-links',
        'container'      => false,
        'depth'          => 2,
        'fallback_cb'    => false,
      ) );
      ?>
      <!-- Burger button — mobile only -->
      <button class="mbc-burger" id="mbc-burger" aria-label="Open menu" aria-expanded="false">
        <span></span>
        <span></span>
        <span></span>
      </button>
    </nav>

    <!-- Mobile menu overlay -->
    <div class="mbc-mobile-menu" id="mbc-mobile-menu">
      <?php
      wp_nav_menu( array(
        'theme_location' => 'primary',
        'menu_class'     => 'mbc-mobile-nav-links',
        'container'      => false,
        'depth'          => 2,
        'fallback_cb'    => false,
      ) );
      ?>
    </div>

    <!-- Hero background image layer -->
    <div class="mbc-hero-bg" id="mbc-hero-bg"></div>
    <div class="mbc-hero-overlay" id="mbc-hero-overlay"></div>

    <!-- Hero content -->
    <div class="mbc-hero-content">

      <div class="mbc-hashtag-wrap">
        <span class="mbc-hashtag">#GraceAndTruth</span>
      </div>

      <h1 class="mbc-headline" id="mbc-hero-h1">
        Loving God.<br><em>Living</em><br>Generously.
      </h1>

      <p class="mbc-strapline">A Community Church with World-Wide Vision</p>

      <p class="mbc-mission">A warm, welcoming Baptist church in the heart of Moortown, Leeds. Everyone is welcome here &mdash; whoever you are, wherever you&rsquo;re from.</p>

      <div class="mbc-ctas">
        <button class="mbc-cta-secondary mbc-carousel-trigger" id="mbc-explore-btn" onclick="mbcOpenCarousel()">
          Find Out More
          <span class="mbc-cta-arrow">&#8594;</span>
        </button>
      </div>

      <div class="mbc-slide-dots" id="mbc-slide-dots"></div>

      <div class="mbc-social-links">
        <a href="https://www.facebook.com/MoortownBaptistChurch" target="_blank" rel="noopener" class="mbc-social-link mbc-social-fb" aria-label="Facebook">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
            <path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z"/>
          </svg>
          <span>Facebook</span>
        </a>
        <a href="https://www.youtube.com/@MoortownBaptistChurch" target="_blank" rel="noopener" class="mbc-social-link mbc-social-yt" aria-label="YouTube">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
            <path d="M22.54 6.42a2.78 2.78 0 00-1.95-1.96C18.88 4 12 4 12 4s-6.88 0-8.59.46a2.78 2.78 0 00-1.95 1.96A29 29 0 001 12a29 29 0 00.46 5.58A2.78 2.78 0 003.41 19.6C5.12 20 12 20 12 20s6.88 0 8.59-.46a2.78 2.78 0 001.95-1.95A29 29 0 0023 12a29 29 0 00-.46-5.58z"/>
            <polygon points="9.75 15.02 15.5 12 9.75 8.98 9.75 15.02" fill="#111"/>
          </svg>
          <span>YouTube</span>
        </a>
      </div>

    </div>

    <!-- Carousel overlay -->
    <div class="mbc-carousel-overlay" id="mbc-carousel-overlay" onclick="mbcCloseCarousel(event)">
      <div class="mbc-carousel-panel">
        <button class="mbc-carousel-close" onclick="mbcCloseCarousel()">&times;</button>
        <h2 class="mbc-carousel-title">Explore <em>Moortown Baptist</em></h2>
        <div class="mbc-carousel-track-wrap">
          <button class="mbc-carousel-prev" onclick="mbcCarouselStep(-1)">&#8592;</button>
          <div class="mbc-carousel-track" id="mbc-carousel-track">

            <a class="mbc-carousel-card" href="<?php echo esc_url( home_url( '/about/' ) ); ?>">
              <div class="mbc-cc-img" style="background:linear-gradient(135deg,#1a1a2e,#2d2d5e)">
                <svg width="48" height="48" viewBox="0 0 48 48" fill="none"><circle cx="24" cy="24" r="20" stroke="#c9a84c" stroke-width="1.5"/><ellipse cx="24" cy="24" rx="9" ry="20" stroke="rgba(201,168,76,.4)" stroke-width="1"/><line x1="4" y1="24" x2="44" y2="24" stroke="rgba(201,168,76,.4)" stroke-width="1"/></svg>
              </div>
              <div class="mbc-cc-body"><h3>About Us</h3><p>Who we are, what we believe, and what makes MBC tick.</p></div>
            </a>

            <a class="mbc-carousel-card" href="<?php echo esc_url( home_url( '/church-life/' ) ); ?>">
              <div class="mbc-cc-img" style="background:linear-gradient(135deg,#1a2e1a,#2d5e2d)">
                <svg width="48" height="48" viewBox="0 0 48 48" fill="none"><path d="M24 8 L40 40 L8 40 Z" stroke="#c9a84c" stroke-width="1.5" fill="none"/><path d="M24 18 L34 36 L14 36 Z" fill="rgba(201,168,76,.15)"/></svg>
              </div>
              <div class="mbc-cc-body"><h3>Church Life</h3><p>Small groups, activities, and community life at MBC.</p></div>
            </a>

            <a class="mbc-carousel-card" href="<?php echo esc_url( home_url( '/posts/' ) ); ?>">
              <div class="mbc-cc-img" style="background:linear-gradient(135deg,#2e1a1a,#5e2d2d)">
                <svg width="48" height="48" viewBox="0 0 48 48" fill="none"><rect x="8" y="10" width="32" height="28" rx="2" stroke="#c9a84c" stroke-width="1.5"/><line x1="14" y1="18" x2="34" y2="18" stroke="rgba(201,168,76,.5)" stroke-width="1"/><line x1="14" y1="24" x2="34" y2="24" stroke="rgba(201,168,76,.5)" stroke-width="1"/><line x1="14" y1="30" x2="26" y2="30" stroke="rgba(201,168,76,.5)" stroke-width="1"/></svg>
              </div>
              <div class="mbc-cc-body"><h3>Latest News</h3><p>Stories, updates and reflections from our community.</p></div>
            </a>

            <a class="mbc-carousel-card" href="https://www.youtube.com/@MoortownBaptistChurch" target="_blank" rel="noopener">
              <div class="mbc-cc-img" style="background:linear-gradient(135deg,#2e1a0a,#5e3a0a)">
                <svg width="48" height="48" viewBox="0 0 48 48" fill="none"><rect x="6" y="12" width="36" height="24" rx="4" stroke="#c9a84c" stroke-width="1.5"/><polygon points="20,18 20,30 32,24" fill="#c9a84c" opacity=".7"/></svg>
              </div>
              <div class="mbc-cc-body"><h3>Watch Online</h3><p>Catch up on Sunday services and special events on YouTube.</p></div>
            </a>

            <a class="mbc-carousel-card" href="<?php echo esc_url( home_url( '/contact-us/' ) ); ?>">
              <div class="mbc-cc-img" style="background:linear-gradient(135deg,#1a1a2e,#1a2e2e)">
                <svg width="48" height="48" viewBox="0 0 48 48" fill="none"><path d="M8 12 L24 26 L40 12" stroke="#c9a84c" stroke-width="1.5" fill="none"/><rect x="6" y="10" width="36" height="28" rx="2" stroke="#c9a84c" stroke-width="1.5" fill="none"/></svg>
              </div>
              <div class="mbc-cc-body"><h3>Get in Touch</h3><p>Questions? Planning a visit? We'd love to hear from you.</p></div>
            </a>

            <a class="mbc-carousel-card" href="https://www.facebook.com/MoortownBaptistChurch" target="_blank" rel="noopener">
              <div class="mbc-cc-img" style="background:linear-gradient(135deg,#0a1a2e,#0a2e4a)">
                <svg width="48" height="48" viewBox="0 0 48 48" fill="none"><rect x="6" y="6" width="36" height="36" rx="8" stroke="#c9a84c" stroke-width="1.5"/><path d="M26 38V25h4l1-5h-5v-2c0-1.5.4-2.5 2.5-2.5H31V11a30 30 0 00-4-.3c-4 0-6.7 2.4-6.7 6.8V20h-4v5h4v13z" fill="rgba(201,168,76,.6)"/></svg>
              </div>
              <div class="mbc-cc-body"><h3>Follow Us</h3><p>Join our Facebook community for news, prayer and events.</p></div>
            </a>

          </div><!-- end track -->
          <button class="mbc-carousel-next" onclick="mbcCarouselStep(1)">&#8594;</button>
        </div>
        <div class="mbc-carousel-dots" id="mbc-carousel-dots"></div>
      </div>
    </div>

    <!-- Services strip -->
    <div class="mbc-services-strip">
      <div class="mbc-svc"><p>Sunday Worship</p><p>· 11:00am</p></div>
      <div class="mbc-svc"><p>204 King Lane</p><p>· Leeds LS17 6AA</p></div>
      <div class="mbc-svc"><p>YouTube Live</p><p>· Every Sunday</p></div>
    </div>

    <!-- Hero image controls -->
    <div class="mbc-img-controls" id="mbc-img-controls">
      <button class="mbc-btn-sm" onclick="document.getElementById('mbc-img-file').click()">⬆ Change photo…</button>
      <input type="file" id="mbc-img-file" accept="image/*" style="display:none" onchange="mbcLoadImage(this)">
      <span class="mbc-sep">|</span>
      <label>Overlay:</label>
      <input type="range" id="mbc-sl-overlay" min="0" max="95" value="72" step="5" oninput="mbcUpdateOverlay(this.value)">
      <span class="mbc-val" id="mbc-val-overlay">72%</span>
      <span class="mbc-sep">|</span>
      <label>Zoom:</label>
      <input type="range" id="mbc-sl-zoom" min="20" max="300" value="100" step="5" oninput="mbcUpdateZoom(this.value)">
      <span class="mbc-val" id="mbc-val-zoom">100%</span>
      <span class="mbc-sep">|</span>
      <label>Pan X:</label>
      <input type="range" id="mbc-sl-panx" min="0" max="100" value="50" step="1" oninput="mbcApplyBgPosition()">
      <span class="mbc-sep">|</span>
      <label>Pan Y:</label>
      <input type="range" id="mbc-sl-pany" min="0" max="100" value="50" step="1" oninput="mbcApplyBgPosition()">
      <span class="mbc-sep">|</span>
      <button class="mbc-btn-sm mbc-btn-danger" id="mbc-img-clear" onclick="mbcClearImage()" style="display:none">✕ Remove</button>
    </div>

    <div id="mbc-img-load-bar">
      <button class="mbc-btn-sm" onclick="document.getElementById('mbc-img-file').click()">+ Load hero photo…</button>
    </div>

  </div><!-- end hero panel -->

  <!-- ══ DIVIDER ══ -->
  <div class="mbc-divider" id="mbc-divider" title="Drag to resize"></div>

  <!-- ══ RIGHT: NEWS PANEL ══ -->
  <div class="mbc-news-panel" id="mbc-news-panel">

    <div class="mbc-news-header">
      <h2>Latest <em>News</em></h2>
      <div class="mbc-news-header-right">
        <span class="mbc-live-dot"></span>
        <span class="mbc-live-label">LIVE</span>
        <a href="<?php echo esc_url( get_permalink( get_option( 'page_for_posts' ) ) ); ?>">View all →</a>
      </div>
    </div>

    <div class="mbc-news-grid loading" id="mbc-news-grid">
      <div class="mbc-loading-msg">
        <span class="mbc-spinner"></span>
        Loading latest posts…
      </div>
    </div>

    <div class="mbc-news-footer">
      <span><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php bloginfo( 'url' ); ?></a></span>
      <span id="mbc-post-count"></span>
      <span>&copy; <?php echo date( 'Y' ); ?> <?php bloginfo( 'name' ); ?>. Registered Charity.</span>
    </div>

  </div><!-- end news panel -->

</div><!-- end screen -->

<script>
// Mobile burger menu
(function() {
  var burger = document.getElementById('mbc-burger');
  var menu   = document.getElementById('mbc-mobile-menu');
  if (!burger || !menu) return;
  burger.addEventListener('click', function() {
    var open = menu.classList.toggle('open');
    burger.classList.toggle('open', open);
    burger.setAttribute('aria-expanded', open);
  });
  // Close on link click
  menu.querySelectorAll('a').forEach(function(a) {
    a.addEventListener('click', function() {
      menu.classList.remove('open');
      burger.classList.remove('open');
      burger.setAttribute('aria-expanded', false);
    });
  });
})();
</script>

<?php wp_footer(); ?>
</body>
</html>
