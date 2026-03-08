/**
 * Tabs: binds tab switching for [data-fs-tabs] containers.
 * Structure: [data-fs-tabs] > [data-fs-tabs-nav] > .fs-tabs-btn[data-tab],
 *            [data-fs-tabs] > [data-fs-tabs-panels] > [data-fs-tabs-panel][data-tab]
 * Button and panel are matched by data-tab value. Active state: .active on btn, data-fs-tabs-panel-active on panel.
 */
document.addEventListener('DOMContentLoaded', function () {
  const roots = document.querySelectorAll('[data-fs-tabs]');
  roots.forEach(function (root) {
    const nav = root.querySelector('[data-fs-tabs-nav]');
    const panels = root.querySelectorAll('[data-fs-tabs-panel]');
    if (!nav || !panels.length) return;

    nav.addEventListener('click', function (e) {
      const btn = e.target.closest('.fs-tabs-btn');
      if (!btn) return;
      const tabId = btn.getAttribute('data-tab');
      if (!tabId) return;

      nav.querySelectorAll('.fs-tabs-btn').forEach(function (b) {
        b.classList.remove('active');
        b.setAttribute('aria-selected', 'false');
      });
      btn.classList.add('active');
      btn.setAttribute('aria-selected', 'true');

      panels.forEach(function (p) {
        if (p.getAttribute('data-tab') === tabId) {
          p.setAttribute('data-fs-tabs-panel-active', '1');
          p.classList.add('fs-tabs-panel--active');
        } else {
          p.removeAttribute('data-fs-tabs-panel-active');
          p.classList.remove('fs-tabs-panel--active');
        }
      });
    });
  });
});
