<?php

defined('ABSPATH') || exit;

/**
 * Load an email template from inc/email-templates. Variables in $args are extracted for the template.
 *
 * @param string $name Template name (file name without .php).
 * @param array  $args Variables to pass to the template (e.g. site_name, to_email).
 * @return string Rendered HTML or empty string if template not found.
 */
function fs_get_email_template(string $name, array $args = []): string
{
	$path = get_template_directory() . '/inc/email-templates/' . $name . '.php';
	if (!is_readable($path)) {
		return '';
	}
	extract($args, EXTR_SKIP);
	ob_start();
	include $path;
	return (string) ob_get_clean();
}

/**
 * From address for all outgoing mail. Fallback to WordPress default when empty.
 */
add_filter('wp_mail_from', function ($email) {
	$custom = get_option('fromscratch_email_from', '');
	if ($custom !== '' && is_email($custom)) {
		return $custom;
	}
	return get_option('admin_email', '');
});

add_filter('wp_mail_from_name', function ($name) {
	$custom = get_option('fromscratch_email_from_name', '');
	if ($custom !== '') {
		return $custom;
	}
	return get_bloginfo('name', 'display');
});

/**
 * Hook into WordPress mail to use Developer › System mail delivery settings (SMTP or SendGrid).
 * From address is set via wp_mail_from / wp_mail_from_name filters above.
 */
add_action('phpmailer_init', 'fs_phpmailer_init_from_settings', 20, 1);

function fs_phpmailer_init_from_settings($phpmailer): void
{
	$mailer = get_option('fromscratch_mailer', 'php');

	if ($mailer === 'php' || $mailer === '') {
		return;
	}

	if ($mailer === 'smtp') {
		$host = get_option('fromscratch_smtp_host', '');
		if ($host === '') {
			return;
		}
		$phpmailer->isSMTP();
		$phpmailer->Host = $host;
		$phpmailer->Port = (int) get_option('fromscratch_smtp_port', 587);
		$enc = get_option('fromscratch_smtp_encryption', 'tls');
		$phpmailer->SMTPSecure = $enc === 'none' ? '' : $enc;
		$user = get_option('fromscratch_smtp_user', '');
		$phpmailer->SMTPAuth = $user !== '';
		if ($user !== '') {
			$phpmailer->Username = $user;
			$phpmailer->Password = get_option('fromscratch_smtp_pass', '');
		}
		return;
	}

	if ($mailer === 'sendgrid') {
		$api_key = get_option('fromscratch_sendgrid_api_key', '');
		if ($api_key === '') {
			return;
		}
		$phpmailer->isSMTP();
		$phpmailer->Host = 'smtp.sendgrid.net';
		$phpmailer->Port = 587;
		$phpmailer->SMTPSecure = 'tls';
		$phpmailer->SMTPAuth = true;
		$phpmailer->Username = 'apikey';
		$phpmailer->Password = $api_key;
	}
}
