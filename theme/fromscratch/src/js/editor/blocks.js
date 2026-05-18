wp.domReady(() => {
  // Image
  wp.blocks.unregisterBlockStyle('core/image', 'rounded');

  // Separator
  wp.blocks.unregisterBlockStyle('core/separator', 'default');
  wp.blocks.unregisterBlockStyle('core/separator', 'wide');
  wp.blocks.unregisterBlockStyle('core/separator', 'dots');
});
