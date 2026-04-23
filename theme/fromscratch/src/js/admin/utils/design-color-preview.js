/**
 * Design color fields: sync preview box with input value.
 * Preview shows (in order) current input value, or placeholder (default).
 */
function updateColorPreview(input, preview) {
  const raw = (input.value && input.value.trim()) || (input.placeholder && input.placeholder.trim()) || '';
  const color = raw.trim();
  if (color) {
    preview.style.backgroundColor = color;
    preview.classList.remove('fromscratch-design-color-preview--empty');
  } else {
    preview.style.backgroundColor = '';
    preview.classList.add('fromscratch-design-color-preview--empty');
  }
}

function initDesignColorPreviews() {
  const fields = document.querySelectorAll('.fromscratch-design-field--color');
  fields.forEach(function (field) {
    const input = field.querySelector('[data-design-color-input]');
    const preview = field.querySelector('[data-design-color-preview]');
    if (!input || !preview) return;

    updateColorPreview(input, preview);
    input.addEventListener('input', function () {
      updateColorPreview(input, preview);
    });
    input.addEventListener('change', function () {
      updateColorPreview(input, preview);
    });
  });
}

document.addEventListener('DOMContentLoaded', initDesignColorPreviews);
