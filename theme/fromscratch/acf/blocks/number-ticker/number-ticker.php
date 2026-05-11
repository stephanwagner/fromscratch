<?php

// Block name
$blockName = 'number-ticker';

// Class names
$classNames = ['fs-wp-block'];

// ID for specific styling
$classNames[] = $block['id'];

// Align class ("alignwide") from block setting ("wide")
if (!empty($block['align'])) {
	$classNames[] = 'align' . $block['align'];
}

// Add class provided via class_field in WP Backend
if (!empty($block['className'])) {
	$classNames[] = $block['className'];
}

// Add wrapper class
$classNames[] = $blockName . '__wrapper';

// Items
$items = get_field('items');
?>

<div class="<?= implode(' ', $classNames) ?>">
    <div class="number-ticker__container">

        <div class="number-ticker__items">
            <?php
            foreach ($items as $index => $item) {
                if ($item['number']) {
            ?>
                    <div class="number-ticker__item -col<?= $index + 1 ?>">
                        <div class="number-ticker__number-container">
                            <?php if ($item['prefix']) { ?>
                                <span class="number-ticker__prefix"><?= $item['prefix'] ?></span>
                            <?php } ?>
                            <span class="number-ticker__number" data-countup="<?= $item['number'] ?>"><?= is_admin() ? $item['number'] : 0 ?></span>
                            <?php if ($item['suffix']) { ?>
                                <span class="number-ticker__suffix"><?= $item['suffix'] ?></span>
                            <?php } ?>
                        </div>
                        <div class="number-ticker__label">
                            <?= $item['label'] ?>
                        </div>
                    </div>
            <?php
                }
            }
            ?>
        </div>

    </div>
</div>