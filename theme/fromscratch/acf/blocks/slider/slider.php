<?php

// Block name
$blockName = 'image-slider';

// Class name
$classNames = 'block-' . $blockName;

// ID for specific styling
$classNames .= ' block-' . $blockName . '-' . $block['id'];

// Align class ("alignwide") from block setting ("wide")
$classNames .= $block['align'] ? ' align' . $block['align'] : '';

// Class provided via class_field in WP Backend
$classNames .= !empty($block['className']) ? ' ' . $block['className'] : '';

// Add wrapper class
$classNames .= ' image-slider__wrapper';

// Fields
$amount = get_field('amount');
$ratio = get_field('ratio');
$x = !empty(get_field('x')) ? get_field('x') : 3;
$y = !empty(get_field('y')) ? get_field('y') : 2;
?>

<div class="<?= $classNames ?>">
	<div class="image-slider__container">
		<div class="image-slider__slides" data-amount="<?= !empty($amount) ? $amount : 1 ?>" data-ratio="<?= !empty($ratio) ? $ratio : '3-2' ?>">
			<?php
			if (have_rows('slides')) :
				while (have_rows('slides')) :
					the_row();

					$image = get_sub_field('image');
					$imageId = $image['id'];
					$imageSrc = wp_get_attachment_image_url($imageId, 'medium');
			?>
					<div class="image-slider__slide" style="background-image: url('<?= $imageSrc ?>')">
						<div class="image-slider__image-spacer"<?= $ratio == 'custom' ? ' style="padding-top: ' . ($y / $x * 100) . '%"' : '' ?>></div>
					</div>
			<?php
				endwhile;
			endif;
			?>
		</div>

	</div>
</div>
