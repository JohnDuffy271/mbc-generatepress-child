<?php
/**
 * MBC Left Sidebar
 * Include with: <?php include get_stylesheet_directory() . '/includes/sidebar-left.php'; ?>
 */
?>
<aside class="mbc-sidebar-left">

  <!-- Service Times -->
  <div class="mbc-sb-card">
    <h4 class="mbc-sb-card-title">Service <em>Times</em></h4>
    <div class="mbc-sb-service">
      <span class="mbc-sb-day">Sunday</span>
      <span class="mbc-sb-time">11:00am</span>
    </div>
    <p class="mbc-sb-note">In person &amp; live on YouTube</p>
  </div>

  <!-- Social Links -->
  <div class="mbc-sb-card mbc-sb-social-card">
    <h4 class="mbc-sb-card-title">Follow <em>Us</em></h4>
    <a href="https://www.facebook.com/MoortownBaptistChurch"
       target="_blank" rel="noopener" class="mbc-sb-social-link mbc-sb-fb">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
        <path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z"/>
      </svg>
      <span>Facebook</span>
    </a>
    <a href="https://www.youtube.com/@MoortownBaptistChurch"
       target="_blank" rel="noopener" class="mbc-sb-social-link mbc-sb-yt">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
        <path d="M22.54 6.42a2.78 2.78 0 00-1.95-1.96C18.88 4 12 4 12 4s-6.88 0-8.59.46a2.78 2.78 0 00-1.95 1.96A29 29 0 001 12a29 29 0 00.46 5.58A2.78 2.78 0 003.41 19.6C5.12 20 12 20 12 20s6.88 0 8.59-.46a2.78 2.78 0 001.95-1.95A29 29 0 0023 12a29 29 0 00-.46-5.58z"/>
        <polygon points="9.75 15.02 15.5 12 9.75 8.98 9.75 15.02" fill="#0a0a0a"/>
      </svg>
      <span>YouTube</span>
    </a>
  </div>

  <!-- Find Us -->
  <div class="mbc-sb-card">
    <h4 class="mbc-sb-card-title">Find <em>Us</em></h4>
    <address class="mbc-sb-address">
      Moortown Baptist Church<br>
      204 King Lane<br>
      Leeds LS17 6AA
    </address>
    <a href="https://maps.google.com/?q=Moortown+Baptist+Church+204+King+Lane+Leeds+LS17+6AA"
       target="_blank" rel="noopener" class="mbc-sb-map-link">
      Open in Maps &#8599;
    </a>
    <!-- Embedded map -->
    <div class="mbc-sb-map">
      <iframe
        src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d2353.4!2d-1.5385!3d53.8385!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x48795c3f1234abcd%3A0x1234!2sMoortown%20Baptist%20Church!5e0!3m2!1sen!2suk!4v1234567890"
        width="100%" height="160" style="border:0;filter:grayscale(1) invert(0.85) contrast(0.9)"
        allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade">
      </iframe>
    </div>
  </div>

  <!-- Latest Post teaser -->
  <?php
  $latest = get_posts( array( 'numberposts' => 1 ) );
  if ( $latest ) :
    $post = $latest[0];
    $thumb = get_the_post_thumbnail_url( $post->ID, 'medium' );
  ?>
  <div class="mbc-sb-card mbc-sb-latest">
    <h4 class="mbc-sb-card-title">Latest <em>News</em></h4>
    <?php if ( $thumb ) : ?>
      <a href="<?php echo get_permalink( $post->ID ); ?>" class="mbc-sb-latest-img-link">
        <div class="mbc-sb-latest-img" style="background-image:url(<?php echo esc_url( $thumb ); ?>)"></div>
      </a>
    <?php endif; ?>
    <a href="<?php echo get_permalink( $post->ID ); ?>" class="mbc-sb-latest-title">
      <?php echo esc_html( $post->post_title ); ?>
    </a>
    <span class="mbc-sb-latest-date">
      <?php echo get_the_date( 'j M Y', $post->ID ); ?>
    </span>
  </div>
  <?php endif; ?>

</aside>
