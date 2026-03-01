<?php

defined('ABSPATH') || exit;

/**
 * Load the translations for the theme.
 */
add_action('after_setup_theme', function () {
	load_theme_textdomain(
		'fromscratch'
	);

	// Fallback
	if (!is_textdomain_loaded('fromscratch')) {

		$mofile = get_template_directory() . '/languages/fromscratch-' . determine_locale() . '.mo';

		if (file_exists($mofile)) {
			load_textdomain('fromscratch', $mofile);
		}
	}
});
