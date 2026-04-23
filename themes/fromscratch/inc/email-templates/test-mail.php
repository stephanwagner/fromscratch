<?php
defined('ABSPATH') || exit;
?>
<h1
	style="
		margin: 0 0 20px;
		font-size: 24px;
		line-height: 1.3;
		font-weight: bold;
		text-align: left;
		color: #1f2937;
	"><?= esc_html__('Test email', 'fromscratch') ?></h1>
<p style="margin: 0 0 16px; font-size: 16px; line-height: 1.5; color: #1f2937;">
	<?= esc_html(sprintf(__('This is a test email from %s.', 'fromscratch'), $site_name)) ?>
</p>
<p style="margin: 0 0 16px; font-size: 16px; line-height: 1.5; color: #1f2937;">
	<?= esc_html__('If you received this, your mail delivery settings are working.', 'fromscratch') ?>
</p>
<p class="fs-mail-weekly-report-has-link" style="margin: 0; font-size: 14px; line-height: 1.5; color: #64748b;">
	<?= esc_html__('Sent to:', 'fromscratch') ?> <?= esc_html($to_email) ?><br>
	<?= esc_html__('Sent at:', 'fromscratch') ?> <?= esc_html($sent_at) ?>
</p>