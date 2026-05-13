<?php

defined('ABSPATH') || exit;

$url = isset($url) ? (string) $url : '';
if ($url === '') {
	return;
}

$label = isset($label) && is_string($label) ? $label : __('Read more', 'fromscratch');
$class = isset($class) && is_string($class) ? $class : 'archive__readmore';
?>
<a class="<?php echo esc_attr($class); ?>" href="<?php echo esc_url($url); ?>"><?php echo esc_html($label); ?></a>
