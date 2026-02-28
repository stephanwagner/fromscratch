/**
 * Copy text from a source element to clipboard.
 * Trigger has data-fs-copy-from-source="ID" (id of the source element).
 * Optional data-fs-copy-feedback-text="Copied" (shown after copy; default "Copied").
 * Source can be pre, textarea, input, or any element (uses textContent or value).
 *
 * @param {HTMLElement} [root=document] - Root to query within.
 */
function initCopyFromSource(root = document) {
  const scope = root || document;
  const triggers = scope.querySelectorAll('[data-fs-copy-from-source]');
  triggers.forEach((trigger) => {
    const sourceId = trigger.getAttribute('data-fs-copy-from-source');
    if (!sourceId) return;

    const source = scope.querySelector(`#${CSS.escape(sourceId)}`);
    if (!source) return;

    const feedbackText =
      trigger.getAttribute('data-fs-copy-feedback-text') || 'Copied';
    const defaultLabel = trigger.textContent.trim();

    trigger.addEventListener('click', () => {
      const text =
        source.value !== undefined ? source.value : source.textContent;
      if (text == null) return;

      navigator.clipboard.writeText(text).then(() => {
        trigger.textContent = feedbackText;
        setTimeout(() => {
          trigger.textContent = defaultLabel;
        }, 2000);
      });
    });
  });
}

// Expose for reuse (e.g. after dynamic content)
window.fromscratchInitCopyFromSource = initCopyFromSource;

document.addEventListener('DOMContentLoaded', () => {
  initCopyFromSource();
});
