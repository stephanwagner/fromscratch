<?php

// Block name
$blockName = 'slider';

// ID for specific styling
$classNames = [$block['id']];

// Align class ("alignwide") from block setting ("wide")
if (!empty($block['align'])) {
	$classNames[] = 'align' . $block['align'];
}

// Add class provided via class_field in WP Backend
if (!empty($block['className'])) {
	$classNames[] = $block['className'];
}

// Add wrapper class
$classNames[] = 'slider__wrapper';

// Fields
$slidesPerView = get_field('slides-per-view') ?? 1;
$slidesPerGroup = get_field('slides-per-group') ?? 1;
$animation = get_field('animation') ?? 'slide';
$spaceBetween = get_field('space-between') ?? 16;
$loop = get_field('loop') ?? false;
$autoplay = get_field('autoplay') ?? false;
$autoplayDelay = get_field('autoplay-delay') ?? 6;
$pagination = get_field('pagination') ?? false;
$navigation = get_field('navigation') ?? false;
$ratio = get_field('ratio');
$ratioX = get_field('ratio-x');
$ratioY = get_field('ratio-y');

$paddingTop = 100;
if ($ratio == 'custom' && $ratioX > 0 && $ratioY > 0) {
	$paddingTop = $ratioY / $ratioX * 100;
} else {
	$ratioArr = explode('-', $ratio);
	if (count($ratioArr) === 2) {
		$paddingTop = floatval($ratioArr[1]) / floatval($ratioArr[0]) * 100;
	}
}
$paddingTop = floatval($paddingTop);

$spaceBetween = max(0, (int) $spaceBetween);
?>

<div
	class="<?= implode(' ', $classNames) ?>"
	style="--slide-padding-top: <?= $paddingTop ?>%; --slider-editor-slide-gap: <?= $spaceBetween ?>px;"

	data-slider-id="<?= $block['id'] ?>"
	data-slider-slides-per-view="<?= $slidesPerView ?>"
	data-slider-slides-per-group="<?= $slidesPerGroup ?>"
	data-slider-animation="<?= $animation ?>"
	data-slider-space-between="<?= $spaceBetween ?>"
	data-slider-loop="<?= $loop ? 'true' : 'false' ?>"
	data-slider-autoplay="<?= $autoplay ? 'true' : 'false' ?>"
	data-slider-autoplay-delay="<?= $autoplayDelay ?>"
	data-slider-pagination="<?= $pagination ? 'true' : 'false' ?>"
	data-slider-navigation="<?= $navigation ? 'true' : 'false' ?>"
>
	<div class="slider__container">
		<div class="slider__slides">
			<div class="swiper">
				<InnerBlocks
					allowedBlocks="<?php echo esc_attr(wp_json_encode([
					'acf/slider-slide'
				])); ?>" />
			</div>
		</div>
		<div class="slider__navigation">
			<button
				class="slider__button-next"
				aria-label="<?= __('Next slide', 'fromscratch') ?>"
				aria-controls="slider-<?= $block['id'] ?>"
			>
				<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor">
					<path d="M664.46-450H210q-12.77 0-21.38-8.62Q180-467.23 180-480t8.62-21.38Q197.23-510 210-510h454.46L532.77-641.69q-8.92-8.93-8.81-20.89.12-11.96 8.81-21.27 9.31-9.3 21.38-9.61 12.08-.31 21.39 9l179.15 179.15q5.62 5.62 7.92 11.85 2.31 6.23 2.31 13.46t-2.31 13.46q-2.3 6.23-7.92 11.85L575.54-275.54q-8.93 8.92-21.19 8.81-12.27-.12-21.58-9.42-8.69-9.31-9-21.08-.31-11.77 9-21.08L664.46-450Z" />
				</svg>
			</button>
			<button class="slider__button-prev"
				aria-label="<?= __('Previous slide', 'fromscratch') ?>"
				aria-controls="slider-<?= $block['id'] ?>"
			>
				<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor">
					<path d="m287.46-450 131.69 131.69q8.93 8.93 8.81 20.89-.11 11.96-8.81 21.27-9.3 9.3-21.38 9.61-12.08.31-21.38-9L197.23-454.69q-10.84-10.85-10.84-25.31 0-14.46 10.84-25.31l179.16-179.15q8.92-8.92 21.19-8.81 12.27.12 21.57 9.42 8.7 9.31 9 21.08.31 11.77-9 21.08L287.46-510h470.62q12.77 0 21.38 8.62 8.62 8.61 8.62 21.38t-8.62 21.38q-8.61 8.62-21.38 8.62H287.46Z" />
				</svg>
			</button>
			<div class="slider__pagination" aria-hidden="true"></div>
		</div>
	</div>
</div>