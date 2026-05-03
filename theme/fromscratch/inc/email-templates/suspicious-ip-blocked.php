<?php
defined('ABSPATH') || exit;

$security_page = function_exists('fs_developer_settings_page_slug') ? fs_developer_settings_page_slug('security') : 'fs-developer-security';
$security_url = admin_url('options-general.php?page=' . $security_page . '#fs-security-failed-logins');
?>
<h1
	style="
		margin: 0 0 20px;
		font-size: 24px;
		line-height: 1.3;
		font-weight: bold;
		text-align: left;
		color: #1f2937;
	"><?= esc_html__('IP temporarily blocked', 'fromscratch') ?></h1>
<p style="margin: 0 0 16px; font-size: 16px; line-height: 1.5; color: #1f2937;">
	<?= esc_html(sprintf(__('The following IP address was temporarily blocked after failed logins exceeded the configured threshold on %s.', 'fromscratch'), $site_name)) ?>
</p>
<table role="presentation" cellpadding="0" cellspacing="0" style="margin: 0 0 16px; font-size: 16px; line-height: 1.5; color: #1f2937; border-collapse: collapse;">
	<tbody>
		<tr>
			<td style="padding: 4px 16px 4px 0; vertical-align: top; font-weight: 600;"><?= esc_html__('IP address', 'fromscratch') ?></td>
			<td style="padding: 4px 0; vertical-align: top;"><code style="font-size: 15px;"><?= esc_html($blocked_ip) ?></code></td>
		</tr>
		<tr>
			<td style="padding: 4px 16px 4px 0; vertical-align: top; font-weight: 600;"><?= esc_html__('Blocked for', 'fromscratch') ?></td>
			<td style="padding: 4px 0; vertical-align: top;"><?= esc_html($lockout_duration) ?></td>
		</tr>
		<tr>
			<td style="padding: 4px 16px 4px 0; vertical-align: top; font-weight: 600;"><?= esc_html__('Threshold', 'fromscratch') ?></td>
			<td style="padding: 4px 0; vertical-align: top;">
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: number of failed attempts, 2: observation window in minutes */
						_n('%1$d failed attempt within %2$d minutes', '%1$d failed attempts within %2$d minutes', (int) $attempts, 'fromscratch'),
						(int) $attempts,
						(int) $window_minutes
					)
				);
				?>
			</td>
		</tr>
	</tbody>
</table>

<p style="margin: 0 0 16px; font-size: 16px; line-height: 1.5; color: #1f2937;">
	<?php
	echo wp_kses(
		sprintf(
			/* translators: %s: URL to Developer › Security (failed logins / auto-blocked IPs). */
			__('This IP cannot use the site until the block expires. You can unblock the IP manually from the failed-login list in <a href="%s">Developer › Security</a>.', 'fromscratch'),
			esc_url($security_url)
		),
		[
			'a' => [
				'href' => true,
			],
		]
	);
	?>
</p>

<p class="fs-mail-weekly-report-has-link" style="margin: 0; font-size: 14px; line-height: 1.5; color: #64748b;">
	<?= esc_html__('Sent to:', 'fromscratch') ?> <?= esc_html($to_email) ?><br>
	<?= esc_html__('Sent at:', 'fromscratch') ?> <?= esc_html($sent_at) ?>
</p>
