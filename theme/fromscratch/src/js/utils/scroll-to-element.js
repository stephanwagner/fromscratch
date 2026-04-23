import config from '../config';

/**
 * Scroll to element
 * @param {Element} element - The element to scroll to
 * @param {number} offset - The offset to scroll to
 * @returns {void}
 */
export function scrollToElement(element, offset = 0) {
  if (!element) return;

  const elementTop =
    element.getBoundingClientRect().top + window.pageYOffset;

  const scrollTop = elementTop + offset;

  window.scrollTo({
    top: scrollTop,
    behavior: 'smooth'
  });
}

/**
 * Get offset
 * @returns {number} The offset
 */
export function getOffset() {
  let offset = config.defaultScrollOffset * -1;

  offset -= config.scrolledHeaderHeight;

  const adminBar = document.getElementById('wpadminbar');
  if (adminBar) {
    offset -= adminBar.offsetHeight;
  }

  return offset;
}
