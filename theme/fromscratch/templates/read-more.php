<?php

defined('ABSPATH') || exit;

$url = isset($url) ? (string) $url : '';
$label = isset($label) && is_string($label) ? $label : __('Read more', 'fromscratch');
$class = isset($class) && is_string($class) ? $class : '';
$link_tag = isset($link_tag) && in_array($link_tag, ['a', 'button', 'div', 'span']) ? $link_tag : (!empty($url) ? 'a' : 'span');
?>
<<?= $link_tag ?>
	class="read-more-link <?= esc_attr($class) ?>"
	<?php if ($url !== '') { ?>
		href="<?= esc_url($url) ?>"
		<?php if (!empty($target)) { ?>
			target="<?= esc_attr($target) ?>"
		<?php } ?>
	<?php } ?>
>
	<?= esc_html($label); ?>
</<?= $link_tag ?>>
