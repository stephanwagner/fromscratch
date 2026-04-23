<?php
defined('ABSPATH') || exit;

$email_show_page_title = isset($email_page_title) && is_string($email_page_title) && trim($email_page_title) !== '';
$email_html_lang = isset($email_html_lang) && is_string($email_html_lang) && $email_html_lang !== ''
	? $email_html_lang
	: str_replace('_', '-', determine_locale());
?>
<!DOCTYPE html>
<html lang="<?= esc_attr($email_html_lang) ?>">

<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php if ($email_show_page_title) : ?>
		<title><?= esc_html(trim($email_page_title)) ?></title>
	<?php endif; ?>

	<style type="text/css">
		body,
		table,
		td {
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
			-webkit-font-smoothing: antialiased;
			-moz-osx-font-smoothing: grayscale;
			-webkit-tap-highlight-color: rgba(0, 0, 0, 0);
		}

		.fs-mail-weekly-report-wrapper {
			background: #f1f5f9;
			padding: 32px;
		}

		.fs-mail-weekly-report-container {
			padding: 32px
		}

		.fs-mail-weekly-report-has-link a {
			color: #2b6cb0;
			text-decoration: none;
			transition: color 280ms;
		}

		.fs-mail-weekly-report-has-link a:hover {
			text-decoration: underline;
			color: #2b6cb0;
		}

		.fs-mail__button:hover {
			text-decoration: none !important;
			color: #fff !important;
			background: #2b6cb0 !important;
		}

		.fs-mail-weekly-report-footer-text a {
			color: #94a3b8;
			text-decoration: underline;
			transition: color 280ms;
		}

		.fs-mail-weekly-report-footer-text a:hover {
			color: #2b6cb0;
		}

		@media (max-width: 900px) {
			.fs-mail-weekly-report-wrapper {
				padding: 24px;
			}

			.fs-mail-weekly-report-container {
				padding: 32px 24px;
			}
		}

		@media (max-width: 600px) {

			.fs-mail-weekly-report-wrapper {
				padding: 16px;
			}

			.fs-mail-weekly-report-container {
				padding: 24px 16px 32px;
			}
		}

		@media (max-width: 400px) {
			.fs-mail-weekly-report-wrapper {
				padding: 16px 12px;
			}

			.fs-mail-weekly-report-container {
				padding: 24px 12px 32px;
			}

			.fs-mail__small-mobile-inline {
				display: inline !important;
			}
		}
	</style>
</head>

<body style="
	margin: 0;
	padding: 0;
	background: #f1f5f9;
	color: #1f2937;
	font-family: -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;
	font-size: 16px;
	line-height: 1.5;
	-webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
    -webkit-tap-highlight-color: rgba(0, 0, 0, 0);
">
	<table
		class="fs-mail-weekly-report-wrapper"
		role="presentation"
		width="100%"
		cellpadding="0"
		cellspacing="0"
		align="center"
		style="
			border: 0;
			word-break: break-word;
		">
		<tr>
			<td>
				<table
					class="fs-mail-weekly-report-container"
					role="presentation"
					width="100%"
					cellpadding="0"
					cellspacing="0"
					style="
						border: 0;
						max-width: 600px;
						background: #fff;
						border: 2px solid #e2e8f0;
						border-radius: 16px;
						overflow: hidden;
						margin: 0 auto;
					">
					<tr>
						<td>