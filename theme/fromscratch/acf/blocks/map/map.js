import $ from 'jquery';

const googleMapsLocalStorageKey = 'google-maps-accepted';

$(function ($) {

  const googleMapsWrappers = $('[data-google-maps-wrapper]');
  const googleMapsInitButtons = $('[data-google-maps-accept-button]');

  if (googleMapsWrappers.length) {
    if (isGoogleMapsAccepted()) {
      initGoogleMaps();
    } else {
      showGoogleMapsDsgvo();
    }

    googleMapsInitButtons.on('click', function (ev) {
      ev.preventDefault();
      setGoogleMapsAccepted();
      initGoogleMaps();
    })
  }
});

/**
 * Checks if Google Maps was accepted
 *
 * @returns {bool} - True if accepted, false otherwise
 */
function isGoogleMapsAccepted() {
  return localStorage.getItem(googleMapsLocalStorageKey) === '1' || (typeof window.BorlabsCookie !== 'undefined' && window.BorlabsCookie.Consents.hasConsent('maps'));
}
window.isGoogleMapsAccepted = isGoogleMapsAccepted;

/**
 * Marks that google Maps was accepted
 */
function setGoogleMapsAccepted() {
  localStorage.setItem(googleMapsLocalStorageKey, '1');
}
window.setGoogleMapsAccepted = setGoogleMapsAccepted;

/**
 * Removes that google Maps was accepted
 */
function removeGoogleMapsAccepted() {
  localStorage.removeItem(googleMapsLocalStorageKey);
}
window.removeGoogleMapsAccepted = removeGoogleMapsAccepted;

/**
 * Creates the HTML of the Google maps iFrame
 *
 * @param {number} lat - The latitude of the location
 * @param {number} lng - The longitude of the location
 * @param {number} [zoom=14] - The zoom level (optional, default is 14)
 * @returns {HTMLIFrameElement} The generated iframe element
 */
function createGoogleMapsEmbed(type, lat, lng, address, zoom = 14) {

  let url;

  if (type == 'address') {
    address = encodeURIComponent(address);
    url = `https://www.google.com/maps?q=${address}&z=${zoom}&output=embed`;
  } else {
    url = `https://www.google.com/maps?q=${lat},${lng}&z=${zoom}&output=embed`;
  }

  const iframe = document.createElement('iframe');
  iframe.src = url;
  iframe.width = "600";
  iframe.height = "450";
  iframe.style.border = "0";
  iframe.allowFullscreen = true;
  iframe.loading = "lazy";
  iframe.referrerPolicy = "no-referrer-when-downgrade";

  return iframe;
}

/**
 * Show the DSGVO overlay
 */
function showGoogleMapsDsgvo() {
  $('[data-google-maps-dsgvo-container]').addClass('-active');
}

/**
 * Hide the DSGVO overlay
 */
function hideGoogleMapsDsgvo() {
  $('[data-google-maps-dsgvo-container]').removeClass('-active');
}

/**
 * Show the Google Maps canvas
 */
function showGoogleMapsCanvas() {
  $('[data-google-maps-canvas]').addClass('-active');
}

/**
 * Add the Google Maps iFrame
 */
function initGoogleMaps() {
  hideGoogleMapsDsgvo();

  const googleMapsWrappers = $('[data-google-maps-wrapper]');

  googleMapsWrappers.each(function (index, item) {
    const wrapper = $(item);
    const type = wrapper.attr('data-type');
    const address = wrapper.attr('data-address');
    const lat = wrapper.attr('data-lat') || 0;
    const lng = wrapper.attr('data-lng') || 0;
    const zoom = wrapper.attr('data-zoom') || 14;

    const iFrameHtml = createGoogleMapsEmbed(type, lat, lng, address, zoom);
    const target = wrapper.find('[data-google-maps-canvas]');

    target.html(iFrameHtml);
    showGoogleMapsCanvas();
  });
}
