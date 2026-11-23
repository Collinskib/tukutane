// Nipange — progressive enhancement (no build step, shared-hosting friendly)
(function () {
  'use strict';

  // Sticky header shadow
  var hdr = document.querySelector('header.site');
  if (hdr) {
    var onScroll = function () { hdr.classList.toggle('scrolled', window.scrollY > 8); };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }

  // Scroll reveals — IntersectionObserver, once per element
  var io = 'IntersectionObserver' in window
    ? new IntersectionObserver(function (entries) {
        entries.forEach(function (en) {
          if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); }
        });
      }, { rootMargin: '0px 0px -8% 0px' })
    : null;
  document.querySelectorAll('.rv').forEach(function (el) {
    if (io) io.observe(el); else el.classList.add('in');
  });
  // Safety net: IO never fires in hidden tabs/webviews — reveal everything
  // rather than leave the page blank.
  setTimeout(function () {
    if (!document.querySelector('.rv.in')) {
      document.querySelectorAll('.rv').forEach(function (el) { el.classList.add('in'); });
    }
  }, 1600);

  // Auto-open dialogs marked [data-open]
  document.querySelectorAll('dialog[data-open]').forEach(function (d) {
    if (typeof d.showModal === 'function') d.showModal();
  });

  // Any button/link with [data-dialog="#id"] opens that dialog; [data-close] closes parent
  document.addEventListener('click', function (ev) {
    var t = ev.target.closest('[data-dialog]');
    if (t) {
      var dlg = document.querySelector(t.getAttribute('data-dialog'));
      if (dlg && typeof dlg.showModal === 'function') dlg.showModal();
    }
    var c = ev.target.closest('[data-close]');
    if (c) {
      var p = c.closest('dialog');
      if (p) p.close();
    }
  });

  // Save/bookmark + RSVP via fetch where enhanced (progressive: forms still POST)
  document.addEventListener('submit', function (ev) {
    var f = ev.target.closest('form[data-ajax]');
    if (!f) return;
    ev.preventDefault();
    var btn = f.querySelector('[type=submit]');
    if (btn) { btn.disabled = true; btn.dataset.orig = btn.textContent; btn.textContent = '…'; }
    fetch(f.action, { method: 'POST', body: new FormData(f), headers: { 'X-Requested-With': 'fetch' } })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (j.reload) { location.reload(); return; }
        if (j.redirect) { location.href = j.redirect; return; }
        if (btn) { btn.disabled = false; btn.textContent = j.label || btn.dataset.orig; }
        if (j.label && f.dataset.swapLabel !== undefined) btn.textContent = j.label;
        if (j.message) toast(j.message, j.ok === false);
        if (j.ok === false && btn) btn.textContent = btn.dataset.orig;
      })
      .catch(function () { if (btn) { btn.disabled = false; btn.textContent = btn.dataset.orig; } f.submit(); });
  });

  // Toast helper
  window.toast = function (msg, bad) {
    var t = document.createElement('div');
    t.className = 'toast' + (bad ? ' err' : '');
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(function () { t.style.opacity = '0'; }, 3800);
    setTimeout(function () { t.remove(); }, 4300);
  };

  // Badge polling (45s — light on shared hosting; skip when tab hidden)
  function pollBadges() {
    if (document.hidden) return;
    if (document.body.dataset.signedIn !== '1') return;
    fetch('/api/notifications.php?count=1', { headers: { 'X-Requested-With': 'fetch' } })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        bump('bell-badge', j.unread);
        bump('msg-badge', j.unreadMsg);
        var dot = document.getElementById('msg-dot');
        if (dot) dot.hidden = !j.unreadMsg;
        friendReq(j.friendReqs || 0);
      })
      .catch(function () {});
  }
  function bump(id, n) {
    var el = document.getElementById(id);
    if (!el) return;
    var prev = parseInt(el.textContent || '0', 10);
    el.hidden = !n;
    el.textContent = n > 99 ? '99+' : n;
    if (n > prev) { el.classList.add('bump'); setTimeout(function () { el.classList.remove('bump'); }, 400); }
  }
  // Friend-request icon — only visible while requests are pending
  function friendReq(n) {
    var ic = document.getElementById('freq');
    if (ic) ic.hidden = !n;
    bump('freq-badge', n);
    var d = document.getElementById('freq-dot');
    if (d) d.hidden = !n;
  }
  // seed from server-rendered values
  bump('bell-badge', parseInt(document.body.dataset.unread || '0', 10));
  bump('msg-badge', parseInt(document.body.dataset.unreadMsg || '0', 10));
  var dot0 = document.getElementById('msg-dot');
  if (dot0) dot0.hidden = !(parseInt(document.body.dataset.unreadMsg || '0', 10) > 0);
  friendReq(parseInt(document.body.dataset.friendReqs || '0', 10));
  setInterval(pollBadges, 45000);

  // Image preview for file inputs [data-preview]
  document.querySelectorAll('input[type=file][data-preview]').forEach(function (inp) {
    inp.addEventListener('change', function () {
      var img = document.querySelector(inp.dataset.preview);
      if (!img || !inp.files[0]) return;
      img.src = URL.createObjectURL(inp.files[0]);
      img.hidden = false;
    });
  });

  // Password visibility toggles
  document.querySelectorAll('.pw-wrap .peek').forEach(function (b) {
    b.addEventListener('click', function () {
      var i = b.parentElement.querySelector('input');
      i.type = i.type === 'password' ? 'text' : 'password';
      b.textContent = i.type === 'password' ? 'Show' : 'Hide';
    });
  });
})();
