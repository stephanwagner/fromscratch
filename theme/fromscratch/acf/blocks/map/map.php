<?php

// Block name
$blockName = 'map';

// Class name
$classNames = 'block__' . $blockName;

// ID for specific styling
$classNames .= ' block__' . $blockName . '-' . $block['id'];

// Align class ("alignwide") from block setting ("wide")
$classNames .= $block['align'] ? ' align' . $block['align'] : '';

// Class provided via class_field in WP Backend
$classNames .= !empty($block['className']) ? ' ' . $block['className'] : '';

// Fields
$type = get_field('type');
$address = get_field('address');
$lat = get_field('lat');
$lng = get_field('lng');
$zoom = get_field('zoom');

if ($address) {
	$address = htmlspecialchars($address, ENT_QUOTES, 'UTF-8');
}

// Images
$image = get_field('image');
if ($image) {
	$imageId = $image['id'];
	$imageSrc = wp_get_attachment_image_url($imageId, 'full');
}
?>

<div class="<?= $classNames ?>">
	<div
		class="map__wrapper"
		data-google-maps-wrapper
		data-type="<?= $type ?>"
		data-address="<?= $address ?>"
		data-lat="<?= $lat ?>"
		data-lng="<?= $lng ?>"
		data-zoom="<?= $zoom ?>"
	>
		<div class="map__container">
			<div class="map__notice-container" style="background-image: url('<?= !empty($imageSrc) ? $imageSrc : '' ?>')" data-google-maps-dsgvo-container>
				<div class="map__notice">
					<div class="map__notice-title">Karte laden</div>
					<div class="map__notice-text">
						Mit Klick auf "Karte anzeigen" wird eine Verbindung zu Google Maps aufgebaut. Dabei werden Daten an Google übertragen.
						<br>
						<a href="/datenschutz">Datenschutz</a>
					</div>
					<div class="map__notice-button-container">
						<button class="map__notice-button b5-button -no-pointer" data-google-maps-accept-button>Karte anzeigen</button>
					</div>
				</div>
			</div>
			<div class="map__canvas-container" data-google-maps-canvas></div>
		</div>
	</div>
</div>
