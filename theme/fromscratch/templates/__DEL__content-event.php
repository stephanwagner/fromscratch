<?php

defined('ABSPATH') || exit;

$range = function_exists('fs_event_format_range_text') ? fs_event_format_range_text(get_the_ID()) : '';

fs_render_template('post-preview.php', [
	'range'             => $range,
	'post_class_extra'  => 'event-archive__item',
]);
