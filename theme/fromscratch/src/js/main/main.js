// Utils
import '../utils/animations';

// Service worker
import './service-worker';

// Main
import './menu';
import './scrolled';

// Components
import '../components/modal';

// Blocks
import '../../../acf/blocks/blocks.js';

// Delay initial animations
setTimeout(function () {
  document.body.classList.add('-transition-init');
}, 128);
