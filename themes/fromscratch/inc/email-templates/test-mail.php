<?php

defined('ABSPATH') || exit;

/**
 * Test mail body (used inside email-header / email-footer layout).
 *
 * @var string $site_name
 * @var string $to_email
 * @var string $sent_at
 * @var string $email_heading Optional; non-empty string shows the main &lt;h1&gt;.
 */
$has_heading = isset($email_heading) && is_string($email_heading) && trim($email_heading) !== '';
?>
<div style="padding: 32px 32px 40px;">
	<?php if ($has_heading) : ?>
		<h1
			style="
				margin: 0 0 20px;
				font-size: 24px;
				line-height: 1.3;
				font-weight: bold;
				text-align: center;
				color: #1f2937;
			"><?= esc_html(trim($email_heading)) ?></h1>
	<?php endif; ?>
	<p style="margin: 0 0 16px; font-size: 16px; line-height: 1.5; color: #1f2937;">
		<?= esc_html(sprintf(__('This is a test email from %s.', 'fromscratch'), $site_name)) ?>
	</p>
	<p style="margin: 0 0 16px; font-size: 16px; line-height: 1.5; color: #1f2937;">
		<?= esc_html__('If you received this, your mail delivery settings are working.', 'fromscratch') ?>
	</p>
	<p style="margin: 0; font-size: 14px; line-height: 1.5; color: #64748b;">
		<?= esc_html__('Sent to:', 'fromscratch') ?> <?= esc_html($to_email) ?><br>
		<?= esc_html__('Time:', 'fromscratch') ?> <?= esc_html($sent_at) ?>
	</p>
</div>
