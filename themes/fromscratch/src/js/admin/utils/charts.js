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

    if (!config.options.plugins) {
      config.options.plugins = {};
    }
    if (!config.options.plugins.tooltip) {
      config.options.plugins.tooltip = {};
    }
    if (!config.options.plugins.tooltip.callbacks) {
      config.options.plugins.tooltip.callbacks = {};
    }
    if (!config.options.plugins.tooltip.callbacks.title) {
      config.options.plugins.tooltip.callbacks.title = function (items) {
        if (!items || !items.length) return '';
        var idx = items[0].dataIndex;
        var labels = items[0].chart && items[0].chart.data ? items[0].chart.data.labels : null;
        var raw = labels && typeof idx === 'number' ? labels[idx] : items[0].label;
        return raw || '';
      };
    }
    if (!config.options.plugins.tooltip.callbacks.label) {
      config.options.plugins.tooltip.callbacks.label = function (item) {
        var dsLabel = item && item.dataset && item.dataset.label ? item.dataset.label : '';
        var val =
          item && typeof item.formattedValue !== 'undefined'
            ? item.formattedValue
            : item && typeof item.raw !== 'undefined'
            ? item.raw
            : '';
        return dsLabel ? dsLabel + ': ' + val : val;
      };
    }

    var ctx = el.getContext ? el.getContext('2d') : null;
    if (!ctx) return;
    
    // Prevent multiple Chart instances on same canvas (can cause weird resizing/growth).
    var existing = Chart.getChart ? Chart.getChart(el) : null;
    if (existing) {
      existing.destroy();
    }

    new Chart(ctx, config);
  });
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initCharts);
} else {
  initCharts();
}
