/**
 * MBC Split Layout — split-layout.js
 * Handles: draggable divider, dynamic grid recalc, live post fetch, image controls
 */

document.addEventListener('DOMContentLoaded', function () {

  // ── ELEMENTS ─────────────────────────────────────
  const screen    = document.getElementById('mbc-screen');
  const heroPanel = document.getElementById('mbc-hero-panel');
  const divider   = document.getElementById('mbc-divider');
  const grid      = document.getElementById('mbc-news-grid');

  // ── SLIDING HEADLINES ────────────────────────────
  var slides = [
	  'Loving God<br><em>Living</em><br>Generously',
	  'Following<br><em>Jesus</em>',
	  'Rooted in Leeds<br><em>Reaching</em><br>the world',
	  'All are<br><em>welcome</em><br>always',
  ];  
  var slideIdx = 0;
  var headline = document.getElementById('mbc-hero-h1');
  var dotsContainer = document.getElementById('mbc-slide-dots');

  if (headline && dotsContainer) {
    headline.style.transition = 'opacity .38s';

    // Build dots
    slides.forEach(function (_, i) {
      var d = document.createElement('div');
      d.className = 'mbc-sdot' + (i === 0 ? ' on' : '');
      d.style.width = (i === 0 ? 28 : 8) + 'px';
      d.addEventListener('click', function () { mbcGoTo(i); });
      dotsContainer.appendChild(d);
    });

    function mbcUpdateDots(idx) {
      var dots = dotsContainer.querySelectorAll('.mbc-sdot');
      dots.forEach(function (d, i) {
        d.classList.toggle('on', i === idx);
        d.style.width  = (i === idx ? 28 : 8) + 'px';
        d.style.background = i === idx ? '#c9a84c' : 'rgba(201,168,76,.28)';
      });
    }

    function mbcGoTo(idx) {
      headline.style.opacity = 0;
      setTimeout(function () {
        headline.innerHTML = slides[idx];
        headline.style.opacity = 1;
      }, 380);
      slideIdx = idx;
      mbcUpdateDots(idx);
    }

    setInterval(function () {
      mbcGoTo((slideIdx + 1) % slides.length);
    }, 5000);
  }

  // ── DRAGGABLE DIVIDER ─────────────────────────────
  const MIN_HERO = 260;
  const MAX_HERO = 0.88;
  let dragging = false, startX = 0, startW = 0;

  divider.addEventListener('mousedown', function (e) {
    dragging = true;
    startX   = e.clientX;
    startW   = heroPanel.offsetWidth;
    divider.classList.add('dragging');
    document.body.style.cursor     = 'col-resize';
    document.body.style.userSelect = 'none';
  });

  document.addEventListener('mousemove', function (e) {
    if (!dragging) return;
    const dx   = e.clientX - startX;
    const total = screen.offsetWidth;
    let newW   = Math.max(MIN_HERO, Math.min(total * MAX_HERO - divider.offsetWidth, startW + dx));
    heroPanel.style.width = newW + 'px';
    heroPanel.style.flex  = 'none';
    recalcGrid();
  });

  document.addEventListener('mouseup', function () {
    if (!dragging) return;
    dragging = false;
    divider.classList.remove('dragging');
    document.body.style.cursor     = '';
    document.body.style.userSelect = '';
  });

  // Touch support
  divider.addEventListener('touchstart', function (e) {
    dragging = true;
    startX   = e.touches[0].clientX;
    startW   = heroPanel.offsetWidth;
  }, { passive: true });

  document.addEventListener('touchmove', function (e) {
    if (!dragging) return;
    const dx    = e.touches[0].clientX - startX;
    const total = screen.offsetWidth;
    let newW    = Math.max(MIN_HERO, Math.min(total * MAX_HERO, startW + dx));
    heroPanel.style.width = newW + 'px';
    heroPanel.style.flex  = 'none';
    recalcGrid();
  }, { passive: true });

  document.addEventListener('touchend', function () { dragging = false; });

  // Set initial split
  // heroPanel.style.width = (screen.offsetWidth * 0.58) + 'px';
  heroPanel.style.width = (screen.offsetWidth * 0.72) + 'px';

  heroPanel.style.flex  = 'none';

  // ── GRID RECALC ───────────────────────────────────
  const TARGET_RATIO = 1.15;
  const CARD_MIN_PX  = 110;

  function recalcGrid() {
    if (grid.classList.contains('loading')) return;
    const newsPanel = document.getElementById('mbc-news-panel');
    const header    = document.querySelector('.mbc-news-header');
    const footer    = document.querySelector('.mbc-news-footer');
    const panelW    = newsPanel.offsetWidth;
    const panelH    = newsPanel.offsetHeight - header.offsetHeight - footer.offsetHeight - 6;
    if (panelW < 10 || panelH < 10) return;

    let bestCols = 1, bestRows = 1, bestDelta = Infinity;
    for (let cols = 1; cols <= 8; cols++) {
      const cw = (panelW - (cols + 1) * 3) / cols;
      if (cw < CARD_MIN_PX) break;
      for (let rows = 1; rows <= 8; rows++) {
        const ch = (panelH - (rows + 1) * 3) / rows;
        if (ch < CARD_MIN_PX) break;
        const delta = Math.abs(cw / ch - TARGET_RATIO);
        if (delta < bestDelta) { bestDelta = delta; bestCols = cols; bestRows = rows; }
      }
    }

    const needed = bestCols * bestRows;
    const cards  = grid.querySelectorAll('.mbc-post-card');
    cards.forEach(function (c, i) { c.style.display = i < needed ? '' : 'none'; });
    grid.style.gridTemplateColumns = 'repeat(' + bestCols + ', 1fr)';
    grid.style.gridTemplateRows    = 'repeat(' + bestRows + ', 1fr)';
  }

  window.addEventListener('resize', function () {
    heroPanel.style.width = (screen.offsetWidth * 0.58) + 'px';
    recalcGrid();
  });

  // ── LIVE POST FETCHER ─────────────────────────────
  // Use the site's own REST API — same origin, no CORS issue
  var apiBase = 'https://moortownbaptistchurch.org.uk'; // live site
  // var apiBase = window.location.origin;               // sandbox (uncomment when sandbox has its own posts)
  // This is where the per_page number is to be found CHANGE NUMBER PER PAGE
  var apiUrl  = apiBase + '/wp-json/wp/v2/posts?per_page=20&_embed=1&_fields=id,title,date,link,_embedded,_links';

  function formatDate(iso) {
    var d = new Date(iso);
    return d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
  }

  function getThumb(post) {
    try {
      var media = post._embedded['wp:featuredmedia'];
      if (media && media[0] && media[0].source_url) return media[0].source_url;
    } catch (e) {}
    return null;
  }

  function getCategory(post) {
    try {
      var terms = post._embedded['wp:term'];
      if (terms && terms[0] && terms[0][0]) return terms[0][0].name;
    } catch (e) {}
    return 'News';
  }

  function decodeHtml(str) {
    var txt = document.createElement('textarea');
    txt.innerHTML = str;
    return txt.value;
  }

  function buildCards(posts) {
    grid.classList.remove('loading');
    grid.style.background = '#222';
    grid.innerHTML = '';

    posts.forEach(function (post) {
      var thumb = getThumb(post);
      var date  = formatDate(post.date);
      var cat   = getCategory(post);
      var title = decodeHtml(post.title.rendered);
      var url   = post.link;

      var card = document.createElement('a');
      card.className = 'mbc-post-card';
      card.href      = url;
      card.target    = '_blank';
      card.rel       = 'noopener';

      var thumbDiv = document.createElement('div');
      thumbDiv.className = 'mbc-post-thumb';
      if (thumb) thumbDiv.style.backgroundImage = 'url(' + thumb + ')';

      var meta = document.createElement('div');
      meta.className = 'mbc-post-meta';
      meta.innerHTML =
        '<h3>' + title + '</h3>' +
        '<div class="mbc-post-info">' +
          '<span class="mbc-post-date">' + date + '</span>' +
          '<span class="mbc-post-cat">' + cat + '</span>' +
        '</div>';

      card.appendChild(thumbDiv);
      card.appendChild(meta);
      grid.appendChild(card);
    });

    var countEl = document.getElementById('mbc-post-count');
    if (countEl) countEl.textContent = posts.length + ' posts loaded';
    recalcGrid();
  }

  function showError(msg) {
    grid.classList.remove('loading');
    grid.innerHTML = '<div class="mbc-loading-msg" style="color:rgba(248,113,113,.6)">' + msg + '</div>';
  }

  fetch(apiUrl)
    .then(function (r) {
      if (!r.ok) throw new Error('HTTP ' + r.status);
      return r.json();
    })
    .then(function (posts) {
      if (!posts.length) throw new Error('No posts returned');
      buildCards(posts);
    })
    .catch(function (err) {
      showError('Could not load posts: ' + err.message);
    });

  // ── IMAGE CONTROLS ────────────────────────────────
  function makeOverlay(pct) {
    var hi = (pct / 100).toFixed(2);
    var lo = (pct / 100 * 0.45).toFixed(2);
    return 'linear-gradient(120deg,rgba(11,11,11,' + hi + ') 45%,rgba(11,11,11,' + lo + '))';
  }

  window.mbcUpdateOverlay = function (val) {
    document.getElementById('mbc-val-overlay').textContent = val + '%';
    document.getElementById('mbc-hero-overlay').style.background = makeOverlay(parseInt(val));
  };

  window.mbcUpdateZoom = function (val) {
    document.getElementById('mbc-val-zoom').textContent = val + '%';
    var bg = document.getElementById('mbc-hero-bg');
    bg.style.backgroundSize   = val + '%';
    bg.style.backgroundRepeat = 'no-repeat';
  };

  window.mbcApplyBgPosition = function () {
    var x  = document.getElementById('mbc-sl-panx').value;
    var y  = document.getElementById('mbc-sl-pany').value;
    document.getElementById('mbc-hero-bg').style.backgroundPosition = x + '% ' + y + '%';
  };

  window.mbcLoadImage = function (input) {
    var file = input.files[0];
    if (!file) return;
    var reader = new FileReader();
    reader.onload = function (e) {
      var bg  = document.getElementById('mbc-hero-bg');
      bg.style.backgroundImage    = 'url(' + e.target.result + ')';
      bg.style.backgroundSize     = '100%';
      bg.style.backgroundPosition = '50% 50%';
      bg.style.backgroundRepeat   = 'no-repeat';
      var pct = parseInt(document.getElementById('mbc-sl-overlay').value);
      document.getElementById('mbc-hero-overlay').style.background = makeOverlay(pct);
      document.getElementById('mbc-img-controls').classList.add('visible');
      document.getElementById('mbc-img-load-bar').style.display  = 'none';
      document.getElementById('mbc-img-clear').style.display     = 'inline-block';
    };
    reader.readAsDataURL(file);
    input.value = '';
  };

  window.mbcClearImage = function () {
    document.getElementById('mbc-hero-bg').style.backgroundImage  = '';
    document.getElementById('mbc-hero-overlay').style.background  = '';
    document.getElementById('mbc-img-controls').classList.remove('visible');
    document.getElementById('mbc-img-load-bar').style.display     = 'block';
    document.getElementById('mbc-img-clear').style.display        = 'none';
    document.getElementById('mbc-sl-zoom').value                  = 100;
    document.getElementById('mbc-val-zoom').textContent           = '100%';
    document.getElementById('mbc-sl-panx').value                  = 50;
    document.getElementById('mbc-sl-pany').value                  = 50;
  };

  // ── CAROUSEL ──────────────────────────────────────
  var carouselOffset = 0;
  var carouselCards  = [];
  var carouselVisible = 3;

  function mbcCarouselInit() {
    var track = document.getElementById('mbc-carousel-track');
    if (!track) return;
    carouselCards = Array.from(track.querySelectorAll('.mbc-carousel-card'));
    var dotsWrap  = document.getElementById('mbc-carousel-dots');
    if (!dotsWrap) return;
    dotsWrap.innerHTML = '';
    // one dot per visible window position
    var pages = Math.ceil(carouselCards.length / carouselVisible);
    for (var i = 0; i < pages; i++) {
      var d = document.createElement('button');
      d.className = 'mbc-cdot' + (i === 0 ? ' on' : '');
      d.setAttribute('data-page', i);
      d.addEventListener('click', (function(idx){ return function(){ mbcCarouselGoTo(idx); }; })(i));
      dotsWrap.appendChild(d);
    }
    mbcCarouselRender();
  }

  function mbcCarouselRender() {
    var track = document.getElementById('mbc-carousel-track');
    if (!track) return;
    // Calculate card width including gap
    var trackW   = track.offsetWidth;
    var gap      = 16;
    var cardW    = (trackW - gap * (carouselVisible - 1)) / carouselVisible;
    var maxOffset = Math.max(0, carouselCards.length - carouselVisible);
    carouselOffset = Math.min(carouselOffset, maxOffset);
    carouselOffset = Math.max(0, carouselOffset);
    var translateX = -(carouselOffset * (cardW + gap));
    carouselCards.forEach(function(c) {
      c.style.flex    = '0 0 ' + cardW + 'px';
      c.style.transform = 'translateX(' + translateX + 'px)';
      c.style.transition = 'transform .35s ease';
    });
    // Update dots
    var page = Math.round(carouselOffset / carouselVisible);
    document.querySelectorAll('.mbc-cdot').forEach(function(d, i) {
      d.classList.toggle('on', i === page);
    });
  }

  window.mbcCarouselStep = function(dir) {
    carouselOffset += dir;
    mbcCarouselRender();
  };

  function mbcCarouselGoTo(page) {
    carouselOffset = page * carouselVisible;
    mbcCarouselRender();
  }

  window.mbcOpenCarousel = function() {
    var overlay = document.getElementById('mbc-carousel-overlay');
    if (overlay) {
      overlay.classList.add('open');
      carouselOffset = 0;
      // adjust visible cards based on panel width
      var panel = overlay.querySelector('.mbc-carousel-panel');
      if (panel && panel.offsetWidth < 500) carouselVisible = 1;
      else if (panel && panel.offsetWidth < 700) carouselVisible = 2;
      else carouselVisible = 3;
      mbcCarouselInit();
    }
  };

  window.mbcCloseCarousel = function(e) {
    // close only if clicking overlay background or close button
    if (e && e.target !== document.getElementById('mbc-carousel-overlay') &&
        !e.target.classList.contains('mbc-carousel-close')) return;
    var overlay = document.getElementById('mbc-carousel-overlay');
    if (overlay) overlay.classList.remove('open');
  };

  // Close on Escape key
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
      var overlay = document.getElementById('mbc-carousel-overlay');
      if (overlay) overlay.classList.remove('open');
    }
  });

}); // end DOMContentLoaded
