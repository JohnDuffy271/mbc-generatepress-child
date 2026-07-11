<?php
/**
 * Template Name: MBC Page — Banner Hero
 * Template Post Type: page
 *
 * Option B: Wide banner image, title sits below on dark background.
 * Cleaner for text-heavy pages, image less dominant.
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
<body <?php body_class( 'mbc-page-banner' ); ?>>
<?php wp_body_open(); ?>
<style>
/* ── ELEMENTOR CONTAINER RESET ── */
/* Force Elementor footer widget to be truly full width */
.mbc-pb-content-wrap .elementor,
.elementor-widget-html {
  width: 100% !important;
  max-width: 100% !important;
}
/* Ensure get_footer() output aligns with page content */
.site-footer {
  width: 100%;
  box-sizing: border-box;
}

/* ── BANNER HERO PAGE TEMPLATE ── */
body.mbc-page-banner {
  background:#111; color:rgba(255,255,255,.78);
  display:flex; flex-direction:column; min-height:100vh;
}
/* Ensure layout and footer stack vertically, never side by side */
.mbc-pb-layout,
.mbc-footer { width:100%; flex-shrink:0; }

/* Nav — same as Option A */
.mbc-pb-nav {
  position: fixed;
  top: 0; left: 0; right: 0; z-index: 1000;
  display: flex; align-items: center; justify-content: space-between;
  padding: 0 48px; height: 58px;
  background: rgba(11,11,11,.92);
  backdrop-filter: blur(8px);
  border-bottom: 1px solid rgba(255,255,255,.06);
}
.mbc-pb-nav-logo {
  display: flex; align-items: center; gap: 10px;
  text-decoration: none;
}
.mbc-pb-nav-logo .name {
  font-family: 'Playfair Display', serif;
  color: #fff; font-size: .88rem; line-height: 1;
}
.mbc-pb-nav-logo .sub {
  font-family: 'Outfit', sans-serif;
  font-size: .52rem; color: #c9a84c;
  letter-spacing: .14em; text-transform: uppercase; margin-top: 2px;
}
.mbc-pb-nav-links {
  display: flex; list-style: none; margin: 0; padding: 0;
}
.mbc-pb-nav-links li a {
  font-family: 'Outfit', sans-serif;
  color: rgba(255,255,255,.45); text-decoration: none;
  font-size: .7rem; font-weight: 600;
  letter-spacing: .08em; text-transform: uppercase;
  margin-left: 20px; transition: color .2s;
}
.mbc-pb-nav-links li a:hover,
.mbc-pb-nav-links li.current-menu-item a { color: #c9a84c; }

/* Banner image */
.mbc-pb-banner {
  margin-top: 58px;
  width: 100%;
  height: 40vh;
  min-height: 240px;
  max-height: 420px;
  position: relative;
  overflow: hidden;
}
.mbc-pb-banner-bg {
  position: absolute; inset: 0;
  background-size: cover;
  background-position: center;
  background-repeat: no-repeat;
  background-color: #1a1a2e;
  transition: transform 8s ease;
}
/* Subtle parallax feel on load */
.mbc-pb-banner:hover .mbc-pb-banner-bg { transform: scale(1.03); }
.mbc-pb-banner-overlay {
  position: absolute; inset: 0;
  background: linear-gradient(
    to bottom,
    rgba(11,11,11,.2) 0%,
    rgba(11,11,11,.5) 100%
  );
}
/* Gold bottom edge */
.mbc-pb-banner::after {
  content: '';
  position: absolute; bottom: 0; left: 0; right: 0;
  height: 3px;
  background: linear-gradient(90deg, transparent, #c9a84c 30%, #c9a84c 70%, transparent);
}

/* Title block — sits below banner on dark bg */
.mbc-pb-title-block {
  background: #111;
  padding: 40px 56px 32px;
  border-bottom: 1px solid #1a1a1a;
}
.mbc-pb-eyebrow {
  font-family: 'Outfit', sans-serif;
  font-size: .68rem; font-weight: 700;
  letter-spacing: .2em; text-transform: uppercase;
  color: #c9a84c; margin-bottom: 10px; display: block;
}
.mbc-pb-title {
  font-family: 'Playfair Display', serif;
  font-size: clamp(2rem, 4vw, 3.2rem);
  font-weight: 900; color: #fff;
  line-height: 1.05; margin: 0 0 14px;
}
.mbc-pb-title em { color: #c9a84c; font-style: italic; font-weight: 400; }
.mbc-pb-excerpt {
  font-family: 'Lato', sans-serif;
  font-size: .95rem; color: rgba(255,255,255,.45);
  line-height: 1.7; font-weight: 300;
  max-width: 620px; margin: 0;
}
.mbc-pb-title-meta {
  display: flex; align-items: center; gap: 16px;
  margin-top: 18px;
}
.mbc-pb-rule {
  width: 40px; height: 2px; background: #c9a84c;
}
.mbc-pb-breadcrumb {
  font-family: 'Outfit', sans-serif;
  font-size: .68rem; color: rgba(255,255,255,.25);
}
.mbc-pb-breadcrumb a {
  color: rgba(201,168,76,.5); text-decoration: none;
}
.mbc-pb-breadcrumb a:hover { color: #c9a84c; }
.mbc-pb-breadcrumb span { margin: 0 6px; }

/* Two-column layout */
.mbc-pb-layout {
  display: grid;
  grid-template-columns: 1fr 280px;
  gap: 48px;
  max-width: 1100px;
  margin: 0 auto;
  padding: 56px 40px 80px;
  align-items: start;
  width: 100%;
  box-sizing: border-box;
}
/* Full-width footer below the grid */
.mbc-footer {
  width: 100% !important;
  display: block !important;
  position: relative;
  z-index: 1;
}

/* Main content */
.mbc-pb-main h1,
.mbc-pb-main h2 {
  font-family: 'Playfair Display', serif;
  color: #fff; font-weight: 700; line-height: 1.2;
  margin: 0 0 20px;
}
.mbc-pb-main h1 { font-size: 1.8rem; }
.mbc-pb-main h2 { font-size: 1.4rem; margin-top: 44px; }
.mbc-pb-main h2 em { color: #c9a84c; font-style: italic; font-weight: 400; }
.mbc-pb-main h3 {
  font-family: 'Outfit', sans-serif;
  color: #c9a84c; font-size: .9rem;
  font-weight: 700; letter-spacing: .06em;
  text-transform: uppercase; margin: 32px 0 10px;
}
.mbc-pb-main p {
  font-family: 'Lato', sans-serif;
  font-size: 1rem; line-height: 1.82;
  color: rgba(255,255,255,.62);
  margin-bottom: 20px; font-weight: 300;
}
.mbc-pb-main a {
  color: #c9a84c; text-decoration: none;
  border-bottom: 1px solid rgba(201,168,76,.3);
  transition: border-color .2s;
}
.mbc-pb-main a:hover { border-color: #c9a84c; }
.mbc-pb-main blockquote {
  border-left: 3px solid #c9a84c;
  margin: 32px 0; padding: 4px 0 4px 24px;
}
.mbc-pb-main blockquote p {
  font-family: 'Playfair Display', serif;
  font-style: italic; font-size: 1.1rem;
  color: rgba(255,255,255,.55); font-weight: 400;
}
.mbc-pb-main img { max-width: 100%; height: auto; margin: 24px 0; }
.mbc-pb-main ul,
.mbc-pb-main ol {
  font-family: 'Lato', sans-serif;
  font-size: 1rem; line-height: 1.8;
  color: rgba(255,255,255,.62);
  padding-left: 24px; margin-bottom: 20px; font-weight: 300;
}
.mbc-pb-main li { margin-bottom: 6px; }
.mbc-pb-main hr {
  border: none; border-top: 1px solid #2a2a2a; margin: 44px 0;
}

/* Sidebar */
.mbc-pb-sidebar {
  position: sticky; top: 78px;
}
.mbc-pb-sidebar-card {
  background: #1a1a1a;
  border: 1px solid #222;
  border-top: 2px solid #c9a84c;
  padding: 22px 20px;
  margin-bottom: 20px;
}
.mbc-pb-sidebar-card h4 {
  font-family: 'Playfair Display', serif;
  color: #fff; font-size: .95rem;
  font-weight: 700; margin-bottom: 14px;
}
.mbc-pb-sidebar-card h4 em { color: #c9a84c; font-style: italic; font-weight: 400; }
.mbc-pb-sidebar-card p {
  font-family: 'Lato', sans-serif;
  font-size: .82rem; line-height: 1.65;
  color: rgba(255,255,255,.45); font-weight: 300;
  margin-bottom: 14px;
}
.mbc-pb-sidebar-card a.mbc-sb-btn {
  display: block; text-align: center;
  background: #c9a84c; color: #111;
  padding: 10px 16px; text-decoration: none;
  font-family: 'Outfit', sans-serif;
  font-size: .72rem; font-weight: 700;
  letter-spacing: .1em; text-transform: uppercase;
  transition: background .2s;
}
.mbc-pb-sidebar-card a.mbc-sb-btn:hover { background: #b8943d; }
.mbc-pb-sidebar-card a.mbc-sb-link {
  display: block; color: rgba(201,168,76,.6);
  text-decoration: none; font-family: 'Outfit', sans-serif;
  font-size: .72rem; font-weight: 600;
  letter-spacing: .06em; text-transform: uppercase;
  margin-bottom: 8px; transition: color .2s;
  padding-left: 12px; border-left: 2px solid #2a2a2a;
}
.mbc-pb-sidebar-card a.mbc-sb-link:hover {
  color: #c9a84c; border-left-color: #c9a84c;
}

@media (max-width: 900px) {
  .mbc-pb-layout {
    grid-template-columns: 1fr;
    padding: 40px 24px 60px;
  }
  .mbc-pb-sidebar { position: static; }
  .mbc-pb-title-block { padding: 28px 24px 22px; }
  .mbc-pb-nav { padding: 0 20px; }
  .mbc-pb-nav-links { display: none; }
}
</style>


<?php while ( have_posts() ) : the_post(); ?>

<!-- FIXED NAV -->
<nav class="mbc-pb-nav">
  <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="mbc-pb-nav-logo">
    <svg width="28" height="28" viewBox="0 0 28 28" aria-hidden="true">
      <circle cx="14" cy="14" r="12" fill="none" stroke="#c9a84c" stroke-width="1"/>
      <ellipse cx="14" cy="14" rx="5" ry="12" fill="none" stroke="rgba(201,168,76,.38)" stroke-width="1"/>
      <line x1="2" y1="14" x2="26" y2="14" stroke="rgba(201,168,76,.38)" stroke-width="1"/>
    </svg>
    <div>
      <p class="name"><?php bloginfo( 'name' ); ?></p>
      <p class="sub">Leeds</p>
    </div>
  </a>
  <?php wp_nav_menu( array(
    'theme_location' => 'primary',
    'menu_class'     => 'mbc-pb-nav-links',
    'container'      => false,
    'depth'          => 1,
    'fallback_cb'    => false,
  ) ); ?>
</nav>

<!-- BANNER IMAGE -->
<div class="mbc-pb-banner">
  <div class="mbc-pb-banner-bg" <?php
    $thumb = get_the_post_thumbnail_url( get_the_ID(), 'full' );
    if ( $thumb ) echo 'style="background-image:url(' . esc_url( $thumb ) . ')"';
  ?>></div>
  <div class="mbc-pb-banner-overlay"></div>
</div>

<!-- TITLE BLOCK -->
<div class="mbc-pb-title-block">
  <span class="mbc-pb-eyebrow">Moortown Baptist Church</span>
  <h1 class="mbc-pb-title"><?php the_title(); ?></h1>
  <?php if ( has_excerpt() ) : ?>
    <p class="mbc-pb-excerpt"><?php the_excerpt(); ?></p>
  <?php endif; ?>
  <div class="mbc-pb-title-meta">
    <div class="mbc-pb-rule"></div>
    <p class="mbc-pb-breadcrumb">
      <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Home</a>
      <span>›</span>
      <?php the_title(); ?>
    </p>
  </div>
</div>

<!-- CONTENT + SIDEBAR -->
<div class="mbc-pb-layout">
  <main class="mbc-pb-main">
    <?php the_content(); ?>
  </main>

  <aside class="mbc-pb-sidebar">

    <div class="mbc-pb-sidebar-card">
      <h4>Plan Your <em>Visit</em></h4>
      <p>We meet every Sunday at 11:00am at 204 King Lane, Leeds LS17 6AA. You are welcome — come as you are.</p>
      <a href="<?php echo esc_url( home_url( '/contact-us/' ) ); ?>" class="mbc-sb-btn">Get in Touch</a>
    </div>

    <div class="mbc-pb-sidebar-card">
      <h4>Explore <em>MBC</em></h4>
      <a href="<?php echo esc_url( home_url( '/about/' ) ); ?>" class="mbc-sb-link">About Us</a>
      <a href="<?php echo esc_url( home_url( '/church-life/' ) ); ?>" class="mbc-sb-link">Church Life</a>
      <a href="<?php echo esc_url( home_url( '/posts/' ) ); ?>" class="mbc-sb-link">Latest News</a>
      <a href="https://www.youtube.com/@MoortownBaptistChurch" target="_blank" rel="noopener" class="mbc-sb-link">Watch Online</a>
      <a href="https://www.facebook.com/MoortownBaptistChurch" target="_blank" rel="noopener" class="mbc-sb-link">Facebook</a>
    </div>

  </aside>
</div>

<?php endwhile; ?>

<!-- ══ MBC FOOTER ══ -->
<style>
.mbc-footer {
  background:#0a0a0a; border-top:1px solid #1e1e1e;
  font-family:'Outfit',sans-serif; color:rgba(255,255,255,.55);
  width:calc(100% - 220px); box-sizing:border-box;
  display:block; clear:both;
  margin-left:220px;
}

.mbc-footer-grid {
  display:grid; grid-template-columns:1fr 1fr;
  gap:40px; padding:32px 56px 28px; align-items:center;
}
.mbc-footer-logo {
  display:flex; align-items:center; gap:10px;
  text-decoration:none; margin-bottom:10px;
}
.mbc-footer-logo-name {
  font-family:'Playfair Display',serif; color:#fff;
  font-size:.9rem; line-height:1.2; display:block;
}
.mbc-footer-logo-sub {
  font-family:'Outfit',sans-serif; font-size:.52rem; color:#c9a84c;
  letter-spacing:.14em; text-transform:uppercase; display:block; margin-top:1px;
}
.mbc-footer-tagline {
  font-size:.78rem; line-height:1.6;
  color:rgba(255,255,255,.35); margin:0 0 14px;
}
.mbc-footer-socials { display:flex; gap:8px; flex-wrap:wrap; }
.mbc-footer-social {
  display:inline-flex; align-items:center; gap:6px;
  color:rgba(255,255,255,.38); text-decoration:none;
  font-size:.68rem; font-weight:600; letter-spacing:.06em;
  text-transform:uppercase; padding:5px 10px;
  border:1px solid rgba(255,255,255,.1);
  transition:color .2s, border-color .2s; white-space:nowrap;
}
.mbc-footer-social:hover { color:#c9a84c; border-color:rgba(201,168,76,.4); }
.mbc-footer-newsletter h4 {
  font-family:'Playfair Display',serif; color:#fff;
  font-size:.88rem; font-weight:700; margin:0 0 6px;
}
.mbc-footer-newsletter h4 em { color:#c9a84c; font-style:italic; font-weight:400; }
.mbc-footer-newsletter p {
  font-size:.76rem; color:rgba(255,255,255,.35);
  line-height:1.55; margin:0 0 12px;
}
.mbc-footer .mc4wp-form-fields {
  display:flex; flex-wrap:wrap; gap:8px; align-items:flex-end;
}
.mbc-footer .mc4wp-form-fields p { margin:0; flex:1; min-width:130px; }
.mbc-footer .mc4wp-form-fields label {
  display:block; font-size:.6rem; color:rgba(255,255,255,.28);
  letter-spacing:.06em; text-transform:uppercase; margin-bottom:3px;
}
.mbc-footer .mc4wp-form-fields input[type=email],
.mbc-footer .mc4wp-form-fields input[type=text] {
  width:100%; background:#181818; border:1px solid #2a2a2a;
  color:#fff; padding:8px 10px; font-family:'Outfit',sans-serif;
  font-size:.8rem; outline:none; transition:border-color .2s; box-sizing:border-box;
}
.mbc-footer .mc4wp-form-fields input[type=email]:focus,
.mbc-footer .mc4wp-form-fields input[type=text]:focus { border-color:#c9a84c; }
.mbc-footer .mc4wp-form-fields input[type=submit] {
  background:#c9a84c; color:#111; border:none; padding:9px 20px;
  font-family:'Outfit',sans-serif; font-size:.72rem; font-weight:700;
  letter-spacing:.1em; text-transform:uppercase; cursor:pointer;
  white-space:nowrap; transition:background .2s; flex-shrink:0;
}
.mbc-footer .mc4wp-form-fields input[type=submit]:hover { background:#b8943d; }
.mbc-footer .mc4wp-response { font-size:.75rem; color:#4ade80; width:100%; margin-top:4px; }
.mbc-footer-bottom {
  border-top:1px solid #1a1a1a; padding:10px 56px;
  display:flex; justify-content:space-between; align-items:center;
  font-size:.65rem; color:rgba(255,255,255,.2); flex-wrap:wrap; gap:6px;
}
.mbc-footer-bottom a { color:rgba(201,168,76,.4); text-decoration:none; transition:color .2s; }
.mbc-footer-bottom a:hover { color:#c9a84c; }
@media (max-width:700px) {
  .mbc-footer-grid { grid-template-columns:1fr; padding:28px 24px 20px; gap:28px; }
  .mbc-footer-bottom { padding:10px 24px; flex-direction:column; text-align:center; gap:4px; }
  .mbc-footer .mc4wp-form-fields { flex-direction:column; }
}
</style>

<footer class="mbc-footer">
  <div class="mbc-footer-grid">
    <div>
      <a href="<?php echo esc_url( home_url( "/" ) ); ?>" class="mbc-footer-logo">
        <svg width="30" height="30" viewBox="0 0 30 30" aria-hidden="true">
          <circle cx="15" cy="15" r="13" fill="none" stroke="#c9a84c" stroke-width="1"/>
          <ellipse cx="15" cy="15" rx="5.5" ry="13" fill="none" stroke="rgba(201,168,76,.38)" stroke-width="1"/>
          <line x1="2" y1="15" x2="28" y2="15" stroke="rgba(201,168,76,.38)" stroke-width="1"/>
        </svg>
        <div>
          <span class="mbc-footer-logo-name"><?php bloginfo("name"); ?></span>
          <span class="mbc-footer-logo-sub">Leeds</span>
        </div>
      </a>
      <p class="mbc-footer-tagline">A Community Church with World-Wide Vision.<br>Everyone is welcome here.</p>
      <div class="mbc-footer-socials">
        <a href="https://www.facebook.com/MoortownBaptistChurch" target="_blank" rel="noopener" class="mbc-footer-social">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z"/></svg>
          Facebook
        </a>
        <a href="https://www.youtube.com/@MoortownBaptistChurch" target="_blank" rel="noopener" class="mbc-footer-social">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M22.54 6.42a2.78 2.78 0 00-1.95-1.96C18.88 4 12 4 12 4s-6.88 0-8.59.46a2.78 2.78 0 00-1.95 1.96A29 29 0 001 12a29 29 0 00.46 5.58A2.78 2.78 0 003.41 19.6C5.12 20 12 20 12 20s6.88 0 8.59-.46a2.78 2.78 0 001.95-1.95A29 29 0 0023 12a29 29 0 00-.46-5.58z"/><polygon points="9.75 15.02 15.5 12 9.75 8.98 9.75 15.02" fill="#0a0a0a"/></svg>
          YouTube
        </a>
      </div>
    </div>
    <div class="mbc-footer-newsletter">
      <h4>Stay <em>Connected</em></h4>
      <p>Weekly news and stories from Moortown Baptist Church — straight to your inbox.</p>
      <?php echo do_shortcode( "[mc4wp_form id=\"206\"]" ); ?>
    </div>
  </div>
  <div class="mbc-footer-bottom">
    <span>&copy; <?php echo date("Y"); ?> <?php bloginfo("name"); ?>. Registered Charity No. 1128960.</span>
    <span>204 King Lane, Leeds LS17 6AA</span>
    <span><a href="/privacy-policy/">Privacy Policy</a></span>
  </div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
