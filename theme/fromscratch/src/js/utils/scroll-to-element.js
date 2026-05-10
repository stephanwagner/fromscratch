import config from '../config';

/**
 * Scroll to element
 * @param {Element} element - The element to scroll to
 * @param {number} offset - The offset to scroll to
 * @param {function} completeCallback - The callback function to call when the scroll is complete
 * @returns {void}
 */
export function scrollToElement(element, offset = 0, completeCallback = null) {
  if (!element) return;

  const elementTop = element.getBoundingClientRect().top + window.pageYOffset;

  const scrollTop = elementTop + offset;

  window.scrollTo({
    top: scrollTop,
    behavior: 'smooth'
  });

  const onScrollEnd = () => {
    clearTimeout(scrollTimeout);

    scrollTimeout = setTimeout(() => {
      window.removeEventListener('scroll', onScrollEnd);

      if (completeCallback) {
        completeCallback();
      }
    }, 64);
  };

  let scrollTimeout;

  window.addEventListener('scroll', onScrollEnd);
}

/**
 * Get offset
 * @returns {number} The offset
 */
export function getOffset() {
  let offset = config.scrollOffset * -1;

  offset -= config.headerHeightScrolled;

  const adminBar = document.getElementById('wpadminbar');
  if (adminBar) {
    offset -= adminBar.offsetHeight;
  }

  return offset;
}
