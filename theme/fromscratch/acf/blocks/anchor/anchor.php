<?php
// Fields
$id = get_field('id');
?>

<?php if (is_admin()) { ?>
    <div class="admin-block-preview">
        <b>Anker Link:</b> <code>#<?= $id ?></code>
    </div>
<?php } else { ?>
    <div data-anchor-id="<?= $id ?>"></div>
<?php } ?>
