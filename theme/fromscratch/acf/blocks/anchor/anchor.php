<?php

// Block name
$blockName = 'anchor';

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

// Fields
$id = get_field('id');
$offset = get_field('offset');
$offset = $offset || $offset === -1 ? $offset : 0;
?>

<?php if (is_admin()) { ?>
    <div class="admin-block-preview">
        <b>Anker:</b> <code>#<?= $id ?></code>
    </div>
<?php } else { ?>
    <div
        class="<?= implode(' ', $classNames) ?>"
        data-anchor-id="<?= $id ?>"
        data-anchor-offset="<?= $offset ?>"
    ></div>
<?php } ?>
