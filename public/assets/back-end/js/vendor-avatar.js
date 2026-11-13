/* Vendor panel: change profile photo from any avatar + copy account id. No dependencies. */
(function () {
  'use strict';
  var cfg = document.getElementById('sv-avatar-config');
  if (!cfg) return;
  var url = cfg.getAttribute('data-url');
  var msg = {
    ok: cfg.getAttribute('data-msg-ok'), bad: cfg.getAttribute('data-msg-invalid'),
    fail: cfg.getAttribute('data-msg-fail'), copied: cfg.getAttribute('data-msg-copied')
  };
  var input = document.createElement('input');
  input.type = 'file'; input.accept = 'image/jpeg,image/png,image/webp'; input.hidden = true;
  document.body.appendChild(input);
  var toastEl = null, toastTimer = null;
  function toast(text, isError) {
    if (!toastEl) { toastEl = document.createElement('div'); toastEl.className = 'sv-toast'; toastEl.setAttribute('role', 'status'); toastEl.setAttribute('aria-live', 'polite'); document.body.appendChild(toastEl); }
    toastEl.textContent = text; toastEl.classList.toggle('is-error', !!isError);
    requestAnimationFrame(function () { toastEl.classList.add('is-on'); });
    clearTimeout(toastTimer); toastTimer = setTimeout(function () { toastEl.classList.remove('is-on'); }, 2600);
  }
  function csrf() { var m = document.querySelector('meta[name="csrf-token"]'); return m ? m.content : ''; }

  // Hide a failed image and fall back to the initial.
  function wireImg(img) {
    img.addEventListener('error', function () { var a = img.closest('.sv-avatar'); if (a) a.classList.add('is-broken'); });
    if (img.complete && img.naturalWidth === 0 && img.src) { var a = img.closest('.sv-avatar'); if (a) a.classList.add('is-broken'); }
  }
  document.querySelectorAll('.sv-avatar img').forEach(wireImg);

  function applyNewPhoto(src) {
    document.querySelectorAll('.sv-avatar').forEach(function (a) {
      var img = a.querySelector('img[data-sv-avatar-img]');
      if (!img) { img = document.createElement('img'); img.setAttribute('data-sv-avatar-img', ''); img.alt = ''; a.insertBefore(img, a.querySelector('.sv-avatar__cam')); wireImg(img); }
      a.classList.remove('is-broken', 'is-empty'); img.src = src;
    });
    // Non-interactive copies (top bar, account page preview) that only share the data attribute.
    document.querySelectorAll('img[data-sv-photo]').forEach(function (img) { img.src = src; });
  }

  var activeBtn = null;
  document.addEventListener('click', function (e) {
    var copy = e.target.closest('[data-sv-copy]');
    if (copy) { e.preventDefault(); doCopy(copy); return; }
    var btn = e.target.closest('[data-sv-avatar]');
    if (btn) { e.preventDefault(); e.stopPropagation(); activeBtn = btn; input.value = ''; input.click(); }
  });

  input.addEventListener('change', function () {
    var file = input.files && input.files[0]; if (!file) return;
    if (!/^image\/(jpeg|png|webp)$/.test(file.type) || file.size > 5 * 1024 * 1024) { toast(msg.bad, true); return; }
    var all = document.querySelectorAll('.sv-avatar'); all.forEach(function (a) { a.classList.add('is-busy'); });
    var fd = new FormData(); fd.append('image', file); fd.append('_token', csrf());
    fetch(url, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf() } })
      .then(function (r) { return r.json().catch(function () { return {}; }).then(function (j) { return { ok: r.ok, j: j }; }); })
      .then(function (res) {
        if (!res.ok || !res.j.url) { var m = res.j && res.j.errors && res.j.errors.image ? res.j.errors.image[0] : (res.j && res.j.message) || msg.fail; toast(m, true); return; }
        applyNewPhoto(res.j.url); toast(msg.ok, false);
      })
      .catch(function () { toast(msg.fail, true); })
      .then(function () { all.forEach(function (a) { a.classList.remove('is-busy'); }); });
  });

  function doCopy(btn) {
    var text = btn.getAttribute('data-sv-copy') || '';
    function done() { btn.classList.add('is-copied'); toast(msg.copied, false); setTimeout(function () { btn.classList.remove('is-copied'); }, 1600); }
    function legacy() {
      var t = document.createElement('textarea'); t.value = text; t.setAttribute('readonly', ''); t.style.position = 'fixed'; t.style.opacity = '0'; document.body.appendChild(t); t.select();
      var ok = false; try { ok = document.execCommand('copy'); } catch (e) {} document.body.removeChild(t); if (ok) done(); else toast(msg.fail, true);
    }
    if (navigator.clipboard && window.isSecureContext) navigator.clipboard.writeText(text).then(done, legacy); else legacy();
  }
})();
