<?php

defined('ABSPATH') || exit;

/**
 * Test mail template. Variables available: $site_name, $to_email, $sent_at.
 *
 * @var string $site_name
 * @var string $to_email
 * @var string $sent_at
 */
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?= esc_html__('Test email', 'fromscratch') ?></title>
</head>
<body style="margin: 0; padding: 24px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen-Sans, Ubuntu, sans-serif; font-size: 16px; line-height: 1.5; color: #1e1e1e;">
	<div style="max-width: 560px; margin: 0 auto;">
		<h1 style="margin: 0 0 16px; font-size: 22px;"><?= esc_html__('Test email', 'fromscratch') ?></h1>
		<p style="margin: 0 0 16px;"><?= esc_html(sprintf(__('This is a test email from %s.', 'fromscratch'), $site_name)) ?></p>
		<p style="margin: 0 0 16px;"><?= esc_html__('If you received this, your mail delivery settings are working.', 'fromscratch') ?></p>
		<p style="margin: 0; font-size: 14px; color: #646970;">
			<?= esc_html__('Sent to:', 'fromscratch') ?> <?= esc_html($to_email) ?><br>
			<?= esc_html__('Time:', 'fromscratch') ?> <?= esc_html($sent_at) ?>
		</p>
	</div>
</body>
</html>
