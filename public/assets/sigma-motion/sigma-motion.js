/* =====================================================================
   SIGMA motion layer — behaviour for sigma-motion.css.
   Presentation only: it never submits, cancels or rewrites a request,
   never calls preventDefault, and every step is wrapped so a failure
   here cannot stop page scripts. Does nothing visual when the user
   asks for reduced motion.
   ===================================================================== */
(function () {
  'use strict';

  var doc = document;
  var root = doc.documentElement;
  var body = doc.body;
  if (!body || root.hasAttribute('data-sm-ready')) return;
  root.setAttribute('data-sm-ready', '1');

  var reduce = false;
  try { reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches; } catch (e) {}
  var isPanel = body.classList.contains('v2-active');
  var isStore = body.classList.contains('toolbar-enabled');
  var hasViewTransitions = typeof window.CSSViewTransitionRule !== 'undefined';

  function safe(fn) { try { fn(); } catch (e) { /* presentation only */ } }

  /* ---------- 1. Navigation progress bar ---------- */
  var bar = null;
  var barTimer = null;

  function showBar(fade) {
    if (reduce) return;
    if (!bar) {
      bar = doc.createElement('div');
      bar.className = 'sm-navbar';
      bar.setAttribute('aria-hidden', 'true');
      body.appendChild(bar);
      void bar.offsetWidth;
    }
    bar.classList.add('sm-on');
    if (fade && !hasViewTransitions) body.classList.add('sm-leaving');
    clearTimeout(barTimer);
    barTimer = setTimeout(hideBar, 8000);
  }

  function hideBar() {
    clearTimeout(barTimer);
    if (bar) bar.classList.remove('sm-on');
    body.classList.remove('sm-leaving');
  }

  function isPlainNavigation(a, e) {
    if (!a || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return false;
    if (a.target && a.target !== '_self') return false;
    if (a.hasAttribute('download') || a.hasAttribute('data-bs-toggle') || a.hasAttribute('data-toggle')) return false;
    var raw = a.getAttribute('href') || '';
    if (!raw || raw.charAt(0) === '#' || /^(javascript:|mailto:|tel:|blob:|data:)/i.test(raw)) return false;
    if (a.origin !== window.location.origin) return false;
    if (a.pathname === window.location.pathname && a.search === window.location.search) return false;
    if (/(export|download|invoice|print|pdf|excel|csv|proof|logout)/i.test(a.pathname + a.search)) return false;
    return true;
  }

  doc.addEventListener('click', function (e) {
    var a = e.target && e.target.closest ? e.target.closest('a[href]') : null;
    if (!isPlainNavigation(a, e)) return;
    // Let every other handler decide first; only then react.
    setTimeout(function () { if (!e.defaultPrevented) showBar(true); }, 0);
  });

  doc.addEventListener('submit', function (e) {
    var form = e.target;
    if (!form || form.target && form.target !== '_self') return;
    if (/(export|download|invoice|print|pdf|excel|csv)/i.test(form.getAttribute('action') || '')) return;
    setTimeout(function () { if (!e.defaultPrevented) showBar(false); }, 0);
  });

  window.addEventListener('pageshow', hideBar);
  window.addEventListener('pagehide', function () { clearTimeout(barTimer); });

  /* ---------- 2. Panels: context-panel section switch ---------- */
  if (isPanel && !reduce) {
    doc.addEventListener('click', function (e) {
      var btn = e.target && e.target.closest ? e.target.closest('.v2-rail-btn[data-section]') : null;
      if (!btn) return;
      requestAnimationFrame(function () {
        safe(function () {
          var on = doc.querySelector('.v2-ctx-section.v2-is-on');
          if (!on) return;
          on.classList.remove('sm-switch');
          void on.offsetWidth;
          on.classList.add('sm-switch');
          setTimeout(function () { on.classList.remove('sm-switch'); }, 400);
        });
      });
    });
  }

  /* ---------- 3. Storefront ---------- */
  if (!isStore) return;

  safe(function () {
    var circles = doc.querySelectorAll('.care-category-circle');
    for (var i = 0; i < circles.length; i++) circles[i].style.setProperty('--sm-i', Math.min(i, 14));
  });

  // Header depth on scroll.
  safe(function () {
    var header = doc.querySelector('.care-header');
    if (!header) return;
    var ticking = false;
    function update() {
      ticking = false;
      header.classList.toggle('sm-scrolled', (window.pageYOffset || root.scrollTop) > 8);
    }
    window.addEventListener('scroll', function () {
      if (!ticking) { ticking = true; requestAnimationFrame(update); }
    }, { passive: true });
    update();
  });

  var home = doc.querySelector('main.care-home');
  if (!home || reduce) return;

  // Reading progress. CSS scroll timelines drive it where available.
  safe(function () {
    var progress = doc.createElement('div');
    progress.className = 'sm-scrollbar';
    progress.setAttribute('aria-hidden', 'true');
    body.appendChild(progress);
    var cssDriven = window.CSS && CSS.supports && CSS.supports('animation-timeline: scroll()');
    if (cssDriven) return;
    var queued = false;
    function paint() {
      queued = false;
      var max = root.scrollHeight - window.innerHeight;
      progress.style.setProperty('--sm-scroll', max > 0 ? Math.min(1, (window.pageYOffset || root.scrollTop) / max) : 0);
    }
    window.addEventListener('scroll', function () {
      if (!queued) { queued = true; requestAnimationFrame(paint); }
    }, { passive: true });
    paint();
  });

  // Scroll reveal for everything below the first screen.
  safe(function () {
    if (!('IntersectionObserver' in window)) return;
    var blocks = [].slice.call(home.querySelectorAll('.care-product-section, .care-promotion-section, .care-contact-section'));
    var services = doc.querySelector('.care-services');
    if (services) {
      [].forEach.call(services.querySelectorAll(':scope > * > *, :scope > *'), function (el) {
        if (el.children.length && el.offsetHeight) el.classList.add('sm-item');
      });
      blocks.push(services);
    }
    var footer = doc.querySelector('footer.footer');
    if (footer) {
      var cols = footer.querySelectorAll('[class*="col-"]');
      [].forEach.call(cols, function (el) { el.classList.add('sm-item'); });
      if (cols.length) blocks.push(footer);
    }

    var fold = window.innerHeight * 0.92;
    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        var el = entry.target;
        observer.unobserve(el);
        el.classList.add('sm-in');
        // Hand hover transitions back once the entrance has played.
        setTimeout(function () {
          el.classList.remove('sm-sec', 'sm-in');
          [].forEach.call(el.querySelectorAll('[style*="--sm-i"]'), function (n) { n.style.removeProperty('--sm-i'); });
        }, 1500);
      });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });

    blocks.forEach(function (block) {
      if (block.getBoundingClientRect().top < fold) return; // already on screen: leave untouched
      var items = block.querySelectorAll('.care-home-product, .care-promo, .sm-item');
      for (var i = 0; i < items.length; i++) items[i].style.setProperty('--sm-i', Math.min(i, 7) + 1);
      block.classList.add('sm-sec');
      observer.observe(block);
    });

    // Never leave content hidden if something prevents the observer firing.
    window.addEventListener('load', function () {
      setTimeout(function () {
        if (doc.visibilityState === 'hidden') return;
        blocks.forEach(function (block) {
          if (block.classList.contains('sm-sec') && block.getBoundingClientRect().top < window.innerHeight) block.classList.add('sm-in');
        });
      }, 1200);
    });
  });
})();
