/**
 * MBC Shared JS — mbc-shared.js
 * Handles: burger menu, post image carousel
 */
document.addEventListener('DOMContentLoaded', function () {

  // ── BURGER MENU ──────────────────────────────────
  var burger = document.getElementById('mbc-burger');
  var navLinks = document.querySelector('.mbc-nav-links');

  if (burger && navLinks) {
    burger.addEventListener('click', function () {
      var open = navLinks.classList.toggle('open');
      burger.classList.toggle('open', open);
      burger.setAttribute('aria-expanded', open);
    });
    // Close on outside click
    document.addEventListener('click', function (e) {
      if (!burger.contains(e.target) && !navLinks.contains(e.target)) {
        navLinks.classList.remove('open');
        burger.classList.remove('open');
        burger.setAttribute('aria-expanded', 'false');
      }
    });
  }

  // ── POST IMAGE CAROUSEL ───────────────────────────
  var track    = document.querySelector('.mbc-carousel-hero-track');
  var prevBtn  = document.querySelector('.mbc-ch-prev');
  var nextBtn  = document.querySelector('.mbc-ch-next');
  var dotsWrap = document.querySelector('.mbc-carousel-hero-controls .mbc-ch-dots');

  if (track) {
    var slides  = Array.from(track.querySelectorAll('.mbc-carousel-hero-slide'));
    var current = 0;

    // Build dots
    if (dotsWrap) {
      slides.forEach(function (_, i) {
        var d = document.createElement('button');
        d.className = 'mbc-ch-dot' + (i === 0 ? ' on' : '');
        d.setAttribute('aria-label', 'Slide ' + (i + 1));
        d.addEventListener('click', function () { goTo(i); });
        dotsWrap.appendChild(d);
      });
    }

    function updateDots(idx) {
      if (!dotsWrap) return;
      Array.from(dotsWrap.querySelectorAll('.mbc-ch-dot')).forEach(function (d, i) {
        d.classList.toggle('on', i === idx);
      });
    }

    function goTo(idx) {
      current = (idx + slides.length) % slides.length;
      track.style.transform = 'translateX(-' + (current * 100) + '%)';
      track.style.transition = 'transform .45s ease';
      updateDots(current);
    }

    // Make track a flex container
    track.style.display = 'flex';
    track.style.width   = (slides.length * 100) + '%';
    slides.forEach(function (s) { s.style.flex = '0 0 ' + (100 / slides.length) + '%'; });

    if (prevBtn) prevBtn.addEventListener('click', function () { goTo(current - 1); });
    if (nextBtn) nextBtn.addEventListener('click', function () { goTo(current + 1); });

    // Auto advance every 5s
    setInterval(function () { goTo(current + 1); }, 5000);

    // Touch swipe
    var startX = 0;
    track.addEventListener('touchstart', function (e) { startX = e.touches[0].clientX; }, { passive: true });
    track.addEventListener('touchend', function (e) {
      var diff = startX - e.changedTouches[0].clientX;
      if (Math.abs(diff) > 50) goTo(diff > 0 ? current + 1 : current - 1);
    });
  }

}); // end DOMContentLoaded
