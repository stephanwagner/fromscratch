import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);

function parseJsonAttr(value) {
  if (!value || typeof value !== 'string') return null;
  try {
    return JSON.parse(value);
  } catch (e) {
    return null;
  }
}

function initCharts() {
  var nodes = document.querySelectorAll('[data-chart]');
  if (!nodes.length) return;

  nodes.forEach(function (el) {
    var type = el.getAttribute('data-chart');
    var config = parseJsonAttr(el.getAttribute('data-chart-config'));
    if (!type || !config || typeof config !== 'object') return;

    if (!config.type) {
      config.type = type;
    }
    if (!config.options) {
      config.options = {};
    }
    if (typeof config.options.responsive === 'undefined') {
      config.options.responsive = true;
    }
    if (typeof config.options.maintainAspectRatio === 'undefined') {
      config.options.maintainAspectRatio = true;
    }

    var ctx = el.getContext ? el.getContext('2d') : null;
    if (!ctx) return;
    // eslint-disable-next-line no-new
    new Chart(ctx, config);
  });
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initCharts);
} else {
  initCharts();
}

