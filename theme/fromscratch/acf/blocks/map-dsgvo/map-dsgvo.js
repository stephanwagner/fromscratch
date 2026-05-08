/**
 * Initialize the Google Maps consent block
 */
function initGoogleMapsConsentBlock() {
  if ($('[data-google-maps-consent-container]').length) {
    const hasAcceptedTitle =
      'Sie haben zugestimmt, dass Daten an Google gesendet werden, um Google Maps anzuzeigen.';
    const hasNotAcceptedTitle =
      'Sie haben nicht zugestimmt, dass Daten an Google gesendet werden, um Google Maps anzuzeigen.';

    const hasAcceptedText =
      'Wenn Sie auf "Verbindung zu Google Maps trennen" klicken, wird die Verbindung zu Google Maps getrennt und es werden keine Daten mehr an Google übertragen.';
    const hasNotAcceptedText =
      'Wenn Sie auf "Verbindung zu Google Maps erlauben" klicken, wird eine Verbindung zu Google hergestellt, um Google Maps auf der Seite anzuzeigen. Dabei werden Daten an Google übertragen. Weitere Informationen finden Sie auf dieser Seite sowie in der <a href="https://policies.google.com/privacy?hl=de" target="_blank">Datenschutzerklärung von Google</a>.';

    const hasAcceptedButtonText = 'Verbindung zu Google Maps trennen';
    const hasNotAcceptedButtonText = 'Verbindung zu Google Maps erlauben';

    let html = '';
    html += '<div class="google-maps-consent__title">';
    html += isGoogleMapsAccepted() ? hasAcceptedTitle : hasNotAcceptedTitle;
    html += '</div>';

    html += '<div class="google-maps-consent__text">';
    html += isGoogleMapsAccepted() ? hasAcceptedText : hasNotAcceptedText;
    html += '</div>';

    html += '<div class="google-maps-consent__link-container">';
    html += '  <span class="google-maps-consent__link link">';
    html += isGoogleMapsAccepted()
      ? hasAcceptedButtonText
      : hasNotAcceptedButtonText;
    html += '  </span>';
    html += '</div>';

    $('[data-google-maps-consent-container]').html(html);

    $('.google-maps-consent__link').on('click', function () {
      if (isGoogleMapsAccepted()) {
        removeGoogleMapsAccepted();
        $('.google-maps-consent__title').html(hasNotAcceptedTitle);
        $('.google-maps-consent__text').html(hasNotAcceptedText);
        $('.google-maps-consent__link').html(hasNotAcceptedButtonText);
      } else {
        setGoogleMapsAccepted();
        $('.google-maps-consent__title').html(hasAcceptedTitle);
        $('.google-maps-consent__text').html(hasAcceptedText);
        $('.google-maps-consent__link').html(hasAcceptedButtonText);
      }
    });
  }
}

// Consent block
initGoogleMapsConsentBlock();
