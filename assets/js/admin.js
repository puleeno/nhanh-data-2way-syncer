(function () {
  var STORAGE_KEY = 'nhanh_syncer_sidebar_collapsed';

  function applyState(wrap, collapsed) {
    if (!wrap) return;
    wrap.classList.toggle('is-sidebar-collapsed', collapsed);
    var btn = wrap.querySelector('.nhanh-sidebar-toggle');
    if (btn) {
      btn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
      btn.setAttribute('title', collapsed ? 'Mở rộng menu' : 'Thu gọn menu');
      var text = btn.querySelector('.nhanh-sidebar-toggle-text');
      if (text) text.textContent = collapsed ? 'Mở menu' : 'Thu gọn menu';
      var icon = btn.querySelector('.dashicons');
      if (icon) {
        icon.classList.toggle('dashicons-menu-alt2', !collapsed);
        icon.classList.toggle('dashicons-menu', collapsed);
      }
    }
  }

  function init() {
    var wrap = document.querySelector('.nhanh-syncer-wrap');
    var btn = wrap ? wrap.querySelector('.nhanh-sidebar-toggle') : null;
    if (!wrap || !btn) return;

    var collapsed = false;
    try {
      collapsed = window.localStorage.getItem(STORAGE_KEY) === '1';
    } catch (e) {}
    applyState(wrap, collapsed);

    btn.addEventListener('click', function (e) {
      e.preventDefault();
      collapsed = !wrap.classList.contains('is-sidebar-collapsed');
      applyState(wrap, collapsed);
      try {
        window.localStorage.setItem(STORAGE_KEY, collapsed ? '1' : '0');
      } catch (e) {}
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
