<?php

defined('ABSPATH') || exit;

/**
 * Register extra image sizes on Settings → Media.
 * Config key: image_sizes_extra (slug, name, width, height). Options: {slug}_size_w, {slug}_size_h. width/height = fallback when not set.
 */
add_action('admin_init', function () {
	$extra = fs_config('image_sizes_extra');
	if (!is_array($extra)) {
		return;
	}
	foreach ($extra as $size) {
		$slug = isset($size['slug']) ? $size['slug'] : '';
		if ($slug === '' || !preg_match('/^[a-z0-9_]+$/', $slug)) {
			continue;
		}
		$name = isset($size['name']) ? $size['name'] : $slug;
		$default_w = isset($size['width']) ? (int) $size['width'] : 0;
		$default_h = isset($size['height']) ? (int) $size['height'] : 0;

		$opt_w = $slug . '_size_w';
		$opt_h = $slug . '_size_h';

		register_setting('media', $opt_w, [
			'type' => 'integer',
			'default' => $default_w,
			'sanitize_callback' => 'absint',
		]);
		register_setting('media', $opt_h, [
			'type' => 'integer',
			'default' => $default_h,
			'sanitize_callback' => 'absint',
		]);

		add_settings_field(
			$opt_w,
			$name,
			function () use ($name, $opt_w, $opt_h, $default_w, $default_h) {
				$w = (int) get_option($opt_w, $default_w);
				$h = (int) get_option($opt_h, $default_h);
				echo '<fieldset><legend class="screen-reader-text"><span>' . esc_html($name) . '</span></legend>';
				echo '<label for="' . esc_attr($opt_w) . '">' . esc_html__('Max Width', 'fromscratch') . '</label> ';
				echo '<input name="' . esc_attr($opt_w) . '" type="number" step="1" min="0" id="' . esc_attr($opt_w) . '" value="' . esc_attr($w) . '" class="small-text" />';
				echo '<br />';
				echo '<label for="' . esc_attr($opt_h) . '">' . esc_html__('Max Height', 'fromscratch') . '</label> ';
				echo '<input name="' . esc_attr($opt_h) . '" type="number" step="1" min="0" id="' . esc_attr($opt_h) . '" value="' . esc_attr($h) . '" class="small-text" />';
				echo '</fieldset>';
			},
			'media',
			'default'
		);
	}
});
