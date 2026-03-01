<?php

defined('ABSPATH') || exit;

/**
 * Load the translations for the theme.
 * Must run on init or later (WordPress 6.7+); do not call __() with domain 'fromscratch' before init.
 */
add_action('init', function () {
	load_theme_textdomain('fromscratch');

	if (!is_textdomain_loaded('fromscratch')) {
		$mofile = get_template_directory() . '/languages/fromscratch-' . determine_locale() . '.mo';
		if (file_exists($mofile)) {
			load_textdomain('fromscratch', $mofile);
		}
	}
}, 1);
