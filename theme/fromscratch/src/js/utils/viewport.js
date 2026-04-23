/**
 * On enter viewport
 * @param {string} selector - The selector to observe
 * @param {function} callback - The callback function to call when the element enters the viewport
 * @param {object} options - The options for the Intersection Observer
 * @returns {void}
 */
export function onEnterViewport(selector, callback, options = {}) {
  const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry, index) => {
      if (entry.isIntersecting) {
        callback(entry.target, index);
        observer.unobserve(entry.target);
      }
    });
  }, options);

  document.querySelectorAll(selector).forEach((el) => observer.observe(el));
}
