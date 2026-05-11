<?php

// Block name
$blockName = 'slider-slide';

// Class names
$classNames = ['fs-wp-block'];

// ID for specific styling
$classNames[] = $block['id'];

// Add class provided via class_field in WP Backend
if (!empty($block['className'])) {
	$classNames[] = $block['className'];
}

// Add wrapper class
$classNames[] = $blockName . '__wrapper';

// Add swiper class
$classNames[] = 'swiper-slide';

// Fields
$type = get_field('type') ?? 'image';

$imageId = get_field('image');

$videoId = get_field('video');
if ($videoId) {
	$videoUrl = wp_get_attachment_url($videoId);
	$videoMimeType = get_post_mime_type($videoId);
}

// Add type class name
$classNames[] = '-type-' . $type;
?>

<div class="<?= implode(' ', $classNames) ?>">
	<div class="slider-slide__container">
		<?php if ($type === 'image' && $imageId) { ?>
			<div class="slider-slide__image-container">
				<?= fs_img($imageId, 'large', ['class' => 'slider-slide__image']) ?>
			</div>
		<?php } ?>
		<?php if ($type === 'video' && !empty($videoUrl)) { ?>
			<div class="slider-slide__video-container">
				<video controls class="slider-slide__video">
					<source src="<?= $videoUrl ?>" type="<?= $videoMimeType ?? 'video/mp4' ?>">
				</video>
			</div>
		<?php } ?>
	</div>
</div>