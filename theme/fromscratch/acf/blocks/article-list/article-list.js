document.querySelectorAll('[data-article-list-filter]').forEach((form) => {
  const select = form.querySelector('.article-list__filter-select');
  if (!select) {
    return;
  }

  const ensureFormActionAnchor = () => {
    const anchor = form.getAttribute('data-scroll-anchor');
    if (!anchor) {
      return;
    }
    const base = form.action.split('#')[0];
    form.action = `${base}#${anchor}`;
  };

  select.addEventListener('change', () => {
    ensureFormActionAnchor();
    form.submit();
  });
});
