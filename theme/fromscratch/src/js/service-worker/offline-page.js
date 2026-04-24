/**
 * Offline fallback: show offline.html / offline-de.html (injected by PHP when the worker is served).
 * No Cache API — network-first; HTML is embedded in the worker script so it works with no network.
 *
 * @param {FetchEvent} event
 * @returns {boolean} True if this feature handled the event.
 */
const FALLBACK_HTML =
  '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>Offline</title></head><body><p>Offline</p></body></html>';

function pickOfflineHtml() {
  const map = self.__FS_OFFLINE_HTML__;
  const pref = self.__FS_OFFLINE_LANG__;
  if (!map || typeof map !== 'object') {
    return FALLBACK_HTML;
  }
  if (pref === 'de' && typeof map.de === 'string' && map.de.trim() !== '') {
    return map.de;
  }
  if (typeof map.en === 'string' && map.en.trim() !== '') {
    return map.en;
  }
  return FALLBACK_HTML;
}

export function handleOfflineNavigation(event) {
  if (event.request.method !== 'GET' || event.request.mode !== 'navigate') {
    return false;
  }

  event.respondWith(
    fetch(event.request).catch(() => {
      return new Response(pickOfflineHtml(), {
        status: 503,
        statusText: 'Offline',
        headers: {
          'Content-Type': 'text/html; charset=utf-8',
        },
      });
    }),
  );
  return true;
}
