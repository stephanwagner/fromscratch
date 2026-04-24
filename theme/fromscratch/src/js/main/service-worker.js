const cfg =
  typeof window !== 'undefined' ? window.fromscratchServiceWorker : null;

if (cfg && cfg.url && 'serviceWorker' in navigator) {
  const scope =
    typeof cfg.scope === 'string' && cfg.scope !== '' ? cfg.scope : '/';

  window.addEventListener('load', () => {
    navigator.serviceWorker.register(cfg.url, { scope }).catch(() => {});
  });
}
