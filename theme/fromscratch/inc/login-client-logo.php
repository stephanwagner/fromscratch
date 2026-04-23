<?php

defined('ABSPATH') || exit;

/**
 * Login page: logo link goes to site home, link text is site name.
 */
add_filter('login_headerurl', function () {
	return home_url('/');
}, 10);

add_filter('login_headertext', function () {
	return get_bloginfo('name');
}, 10);

/**
 * Show client logo on the WordPress login page when set in Theme settings → General.
 */
add_action('login_head', function () {
	$logo_id = (int) get_option('fromscratch_client_logo', 0);
	if ($logo_id <= 0) {
		return;
	}
	$url = wp_get_attachment_image_url($logo_id, 'medium');
	if (!$url) {
		return;
	}
	$url = esc_url($url);
	echo '<style type="text/css">';
	echo '#login h1 a {';
	echo ' background: no-repeat center center / contain;';
	echo ' background-image: url(' . $url . ');';
	echo ' width: 100%;';
	echo ' max-width: 240px;';
	echo ' height: 80px;';
	echo ' margin-bottom: 16px;';
	echo '}';
	echo '</style>';
}, 10);
