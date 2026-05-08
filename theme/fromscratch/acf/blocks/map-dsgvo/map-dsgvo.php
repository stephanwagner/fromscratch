<?php

// Block name
$blockName = 'map-dsgvo';

// Class name
$classNames = 'block__' . $blockName;

// ID for specific styling
$classNames .= ' block__' . $blockName . '-' . $block['id'];

// Align class ("alignwide") from block setting ("wide")
$classNames .= $block['align'] ? ' align' . $block['align'] : '';

// Class provided via class_field in WP Backend
$classNames .= !empty($block['className']) ? ' ' . $block['className'] : '';
?>

<div class="<?= $classNames ?>" data-google-maps-consent-container></div>
