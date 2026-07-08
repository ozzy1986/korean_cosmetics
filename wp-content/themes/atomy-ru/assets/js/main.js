document.addEventListener('DOMContentLoaded', function () {
  var mega = document.querySelector('[data-mega]');
  var toggle = document.querySelector('[data-menu-toggle]');
  if (mega && toggle) {
    toggle.addEventListener('click', function (e) {
      e.stopPropagation();
      var open = mega.classList.toggle('is-open');
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    document.addEventListener('click', function (e) {
      if (!mega.contains(e.target)) {
        mega.classList.remove('is-open');
        toggle.setAttribute('aria-expanded', 'false');
      }
    });
  }

  var slider = document.querySelector('[data-hero-slider]');
  if (slider) {
    var slides = Array.prototype.slice.call(slider.querySelectorAll('[data-slide]'));
    var dotsWrap = slider.querySelector('[data-slide-dots]');
    var index = 0;
    var timer;

    function show(i) {
      index = (i + slides.length) % slides.length;
      slides.forEach(function (slide, n) {
        slide.classList.toggle('is-active', n === index);
      });
      if (dotsWrap) {
        var dots = dotsWrap.querySelectorAll('button');
        dots.forEach(function (dot, n) {
          dot.classList.toggle('is-active', n === index);
        });
      }
    }

    if (dotsWrap) {
      slides.forEach(function (_, n) {
        var dot = document.createElement('button');
        dot.type = 'button';
        dot.setAttribute('aria-label', 'Слайд ' + (n + 1));
        dot.addEventListener('click', function () {
          show(n);
          restart();
        });
        dotsWrap.appendChild(dot);
      });
    }

    function restart() {
      clearInterval(timer);
      timer = setInterval(function () {
        show(index + 1);
      }, 5000);
    }

    var prev = slider.querySelector('[data-slide-prev]');
    var next = slider.querySelector('[data-slide-next]');
    if (prev) {
      prev.addEventListener('click', function () {
        show(index - 1);
        restart();
      });
    }
    if (next) {
      next.addEventListener('click', function () {
        show(index + 1);
        restart();
      });
    }

    show(0);
    restart();
  }

  document.querySelectorAll('.woocommerce-product-gallery').forEach(function (gallery) {
    gallery.style.opacity = '1';
  });

  initWishlist();
});

function initWishlist() {
  var KEY = 'atomy_wishlist';

  function read() {
    try { return JSON.parse(localStorage.getItem(KEY)) || []; } catch (e) { return []; }
  }
  function write(arr) {
    try { localStorage.setItem(KEY, JSON.stringify(arr)); } catch (e) {}
  }
  function has(id) { return read().indexOf(String(id)) > -1; }
  function toggle(id) {
    id = String(id);
    var arr = read();
    var i = arr.indexOf(id);
    if (i > -1) { arr.splice(i, 1); } else { arr.push(id); }
    write(arr);
    return i === -1;
  }

  function refreshCounts() {
    var n = read().length;
    document.querySelectorAll('[data-wish-count]').forEach(function (el) {
      el.textContent = String(n);
      el.classList.toggle('is-empty', n === 0);
    });
  }
  function refreshButtons() {
    document.querySelectorAll('[data-wish-toggle]').forEach(function (btn) {
      btn.classList.toggle('is-active', has(btn.getAttribute('data-product-id')));
    });
  }

  function syncWishlistGridEmpty() {
    var grid = document.querySelector('[data-wishlist-grid]');
    var empty = document.querySelector('[data-wishlist-empty]');
    if (grid && empty) { empty.hidden = grid.children.length > 0; }
  }

  document.addEventListener('click', function (e) {
    var btn = e.target.closest ? e.target.closest('[data-wish-toggle]') : null;
    if (!btn) { return; }
    e.preventDefault();
    var active = toggle(btn.getAttribute('data-product-id'));
    btn.classList.toggle('is-active', active);
    refreshCounts();
    if (!active) {
      var page = document.querySelector('[data-wishlist-page]');
      if (page) {
        var card = btn.closest('li.product');
        if (card) { card.remove(); syncWishlistGridEmpty(); }
      }
    }
  });

  function renderWishlistPage() {
    var page = document.querySelector('[data-wishlist-page]');
    if (!page) { return; }
    var grid = page.querySelector('[data-wishlist-grid]');
    var empty = page.querySelector('[data-wishlist-empty]');
    var loading = page.querySelector('[data-wishlist-loading]');
    var ids = read();

    if (!ids.length) {
      if (loading) { loading.hidden = true; }
      if (empty) { empty.hidden = false; }
      return;
    }
    if (typeof atomyTheme === 'undefined') {
      if (loading) { loading.hidden = true; }
      return;
    }

    var fd = new FormData();
    fd.append('action', 'atomy_wishlist_cards');
    fd.append('nonce', atomyTheme.nonce);
    ids.forEach(function (id) { fd.append('ids[]', id); });

    fetch(atomyTheme.ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (loading) { loading.hidden = true; }
        if (res && res.success && res.data && res.data.html) {
          grid.innerHTML = res.data.html;
          refreshButtons();
          refreshCounts();
        } else if (empty) {
          empty.hidden = false;
        }
        syncWishlistGridEmpty();
      })
      .catch(function () { if (loading) { loading.hidden = true; } });
  }

  refreshCounts();
  refreshButtons();
  renderWishlistPage();
}
