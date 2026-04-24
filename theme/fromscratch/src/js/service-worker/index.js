/**
 * FromScratch service worker — base lifecycle + feature modules.
 *
 * Offline shells: assets/html/offline.html + offline-de.html (locale via PHP). Add more modules here as needed.
 * (e.g. caching, background sync). Keep each feature in its own file.
 */

import { handleOfflineNavigation } from './offline-page.js';

self.addEventListener('install', () => {
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(self.clients.claim());
});

self.addEventListener('fetch', (event) => {
  // First handler that recognises the request wins.
  if (handleOfflineNavigation(event)) {
    return;
  }

  // Future: if (handleSomeOtherFeature(event)) return;
});
