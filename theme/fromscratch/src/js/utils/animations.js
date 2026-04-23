import { onEnterViewport } from './viewport';
import config from '../config';

/**
 * On enter viewport
 * @param {Element} el - The element to animate
 * @returns {void}
 */
document.addEventListener('DOMContentLoaded', () => {
  onEnterViewport('[data-animation]', (el, index) => {
    // Delay
    let delay = 0;

    if (el.hasAttribute('data-animation-delay')) {
      delay = parseInt(el.getAttribute('data-animation-delay'));

      let delayColumns = -1;
      let delayColumnsXL = -1;
      let delayColumnsL = -1;
      let delayColumnsM = -1;
      let delayColumnsS = -1;
      let delayColumnsXS = -1;

      if (el.hasAttribute('data-animation-delay-columns')) {
        delayColumns = parseInt(el.getAttribute('data-animation-delay-columns'));
      }

      if (el.hasAttribute('data-animation-delay-columns-xl')) {
        delayColumnsXL = parseInt(el.getAttribute('data-animation-delay-columns-xl'));
      }

      if (el.hasAttribute('data-animation-delay-columns-l')) {
        delayColumnsL = parseInt(el.getAttribute('data-animation-delay-columns-l'));
      }

      if (el.hasAttribute('data-animation-delay-columns-m')) {
        delayColumnsM = parseInt(el.getAttribute('data-animation-delay-columns-m'));
      }

      if (el.hasAttribute('data-animation-delay-columns-s')) {
        delayColumnsS = parseInt(el.getAttribute('data-animation-delay-columns-s'));
      }

      if (el.hasAttribute('data-animation-delay-columns-xs')) {
        delayColumnsXS = parseInt(el.getAttribute('data-animation-delay-columns-xs'));
      }

      if (delayColumns > 0) {
        delay = (index % delayColumns) * delay;
      }

      if (window.innerWidth <= config.breakpointXS && delayColumnsXS > 0) {
        delay = (index % delayColumnsXS) * delay;
      } else if (window.innerWidth <= config.breakpointS && delayColumnsS > 0) {
        delay = (index % delayColumnsS) * delay;
      } else if (window.innerWidth <= config.breakpointM && delayColumnsM > 0) {
        delay = (index % delayColumnsM) * delay;
      } else if (window.innerWidth <= config.breakpointL && delayColumnsL > 0) {
        delay = (index % delayColumnsL) * delay;
      } else if (window.innerWidth <= config.breakpointXL && delayColumnsXL > 0) {
        delay = (index % delayColumnsXL) * delay;
      }

      if (delay > 0) {
        el.style.setProperty('--animation-delay', `${delay}ms`);
      }
    }

    el.setAttribute('data-animation-active', '');
  }, {
    rootMargin: '0px',
    threshold: 0.1
  });
});
