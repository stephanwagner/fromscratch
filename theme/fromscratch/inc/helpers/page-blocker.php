<?php

defined('ABSPATH') || exit;

/**
 * Output a minimal full-page template with centered box and exit.
 *
 * @param string $title Page title (used in <title> and <h1>).
 * @param string $body_html HTML content for inside the box.
 * @param array{status?: int, extra_css?: string, extra_headers?: array<string, string>} $args Optional.
 */
function fs_block_page(string $title, string $body_html, array $args = []): void
{
	$status = (int) ($args['status'] ?? 200);
	$extra_css = isset($args['extra_css']) ? (string) $args['extra_css'] : '';
	$extra_headers = isset($args['extra_headers']) && is_array($args['extra_headers']) ? $args['extra_headers'] : [];

	if ($status === 503) {
		header('HTTP/1.1 503 Service Unavailable');
		header('Status: 503 Service Unavailable');
		header('Retry-After: 300');
	} elseif ($status === 403) {
		status_header(403);
		nocache_headers();
	}
	header('Content-Type: text/html; charset=utf-8');
	header('Cache-Control: no-cache, no-store, must-revalidate');
	foreach ($extra_headers as $name => $value) {
		header((string) $name . ': ' . (string) $value, true);
	}
?>
<!DOCTYPE html>
<html lang="<?= esc_attr(get_bloginfo('language') ?: 'en') ?>">

<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?= esc_html($title) ?></title>
	<style>
		body {
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, sans-serif;
			margin: 0;
			padding: 0 16px;
			min-height: 100vh;
			display: flex;
			align-items: center;
			justify-content: center;
			background: #f0f0f1;
			color: #3c434a;
			-webkit-font-smoothing: antialiased;
		}

		.box {
			background: #fff;
			padding: 28px 24px 32px;
			max-width: 420px;
			width: 100%;
			box-shadow: 0 1px 3px rgba(0, 0, 0, .13);
			border: 1px solid #c3c4c7;
			border-radius: 4px;
			text-align: center;
			font-size: 16px;
			line-height: 1.4;
		}

		@media (max-width: 600px) {
			.box {
				font-size: 15px;
				padding: 20px 16px 24px;
			}
		}

		h1 {
			margin: 0 0 20px;
			font-size: 22px;
			line-height: 1.2;
			font-weight: 600;
			color: #1d2327;
			text-wrap: balance;
		}

		@media (max-width: 600px) {
			h1 {
				font-size: 20px;
				margin-bottom: 16px;
			}
		}

		.notice {
			margin: 0 0 24px;
			color: #50575e;
			text-wrap: balance;
		}

		.notice:last-child {
			margin-bottom: 0;
		}
<?php if ($extra_css !== '') { ?>

<?= $extra_css ?>
<?php } ?>
	</style>
</head>

<body>
	<div class="box">
		<h1><?= esc_html($title) ?></h1>
		<?= $body_html ?>
	</div>
</body>

</html>
<?php
	exit;
}
