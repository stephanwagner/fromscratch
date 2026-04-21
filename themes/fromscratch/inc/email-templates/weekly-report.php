<?php

defined('ABSPATH') || exit;
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?= esc_html__('Weekly report', 'fromscratch') ?></title>
</head>
<body style="margin:0;padding:0;background:#f1f5f9;color:#1f2937;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
	<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f1f5f9;padding:24px;">
		<tr>
			<td align="center">
				<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:760px;background:#fff;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;">
					<tr>
						<td style="padding:24px 24px 12px;">
							<h1 style="margin:0 0 8px;font-size:24px;line-height:1.3;"><?= esc_html__('Weekly report', 'fromscratch') ?></h1>
							<p style="margin:0;color:#64748b;font-size:14px;">
								<strong><?= esc_html($site_name ?? '') ?></strong> -
								<?= esc_html__('Generated', 'fromscratch') ?>: <?= esc_html($date_now ?? '') ?>
							</p>
						</td>
					</tr>
					<tr>
						<td style="padding:0 24px 16px;">
							<h2 style="margin:0 0 10px;font-size:18px;"><?= esc_html__('Insights', 'fromscratch') ?></h2>
							<table role="presentation" width="100%" cellpadding="6" cellspacing="0" border="0" style="border-collapse:collapse;font-size:13px;">
								<tr style="vertical-align:top;">
									<td width="50%" style="padding:0 10px 10px 0;">
										<strong><?= esc_html__('Scheduled posts', 'fromscratch') ?></strong>
										<div style="margin-top:6px;color:#475569;"><?= esc_html__('Went live last week', 'fromscratch') ?></div>
										<ul style="margin:6px 0 0 18px;padding:0;">
											<?php foreach ((($insights['went_live_last_week'] ?? [])) as $row) : ?>
												<li><a href="<?= esc_url((string) ($row['url'] ?? '')) ?>"><?= esc_html((string) ($row['title'] ?? '')) ?></a> <span style="color:#64748b;">(<?= esc_html((string) ($row['date'] ?? '')) ?>)</span></li>
											<?php endforeach; ?>
											<?php if (empty($insights['went_live_last_week'])) : ?><li><?= esc_html__('None', 'fromscratch') ?></li><?php endif; ?>
										</ul>
										<div style="margin-top:8px;color:#475569;"><?= esc_html__('Upcoming scheduled', 'fromscratch') ?></div>
										<ul style="margin:6px 0 0 18px;padding:0;">
											<?php foreach ((($insights['scheduled_upcoming'] ?? [])) as $row) : ?>
												<li><a href="<?= esc_url((string) ($row['url'] ?? '')) ?>"><?= esc_html((string) ($row['title'] ?? '')) ?></a> <span style="color:#64748b;">(<?= esc_html((string) ($row['date'] ?? '')) ?>)</span></li>
											<?php endforeach; ?>
											<?php if (empty($insights['scheduled_upcoming'])) : ?><li><?= esc_html__('None', 'fromscratch') ?></li><?php endif; ?>
										</ul>
									</td>
									<td width="50%" style="padding:0 0 10px 10px;">
										<strong><?= esc_html__('Expiring posts', 'fromscratch') ?></strong>
										<div style="margin-top:6px;color:#475569;"><?= esc_html__('Expired last week', 'fromscratch') ?></div>
										<ul style="margin:6px 0 0 18px;padding:0;">
											<?php foreach ((($insights['expired_last_week'] ?? [])) as $row) : ?>
												<li><a href="<?= esc_url((string) ($row['url'] ?? '')) ?>"><?= esc_html((string) ($row['title'] ?? '')) ?></a> <span style="color:#64748b;">(<?= esc_html((string) ($row['date'] ?? '')) ?>)</span></li>
											<?php endforeach; ?>
											<?php if (empty($insights['expired_last_week'])) : ?><li><?= esc_html__('None', 'fromscratch') ?></li><?php endif; ?>
										</ul>
										<div style="margin-top:8px;color:#475569;"><?= esc_html__('Upcoming expirations', 'fromscratch') ?></div>
										<ul style="margin:6px 0 0 18px;padding:0;">
											<?php foreach ((($insights['expiring_upcoming'] ?? [])) as $row) : ?>
												<li><a href="<?= esc_url((string) ($row['url'] ?? '')) ?>"><?= esc_html((string) ($row['title'] ?? '')) ?></a> <span style="color:#64748b;">(<?= esc_html((string) ($row['date'] ?? '')) ?>)</span></li>
											<?php endforeach; ?>
											<?php if (empty($insights['expiring_upcoming'])) : ?><li><?= esc_html__('None', 'fromscratch') ?></li><?php endif; ?>
										</ul>
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<?php if (!empty($matomo_enabled)) : ?>
						<tr>
							<td style="padding:0 24px 16px;">
								<h2 style="margin:0 0 10px;font-size:18px;"><?= esc_html__('Daily statistics (last week, 7 days)', 'fromscratch') ?></h2>
								<?php if (!empty($daily_chart_url)) : ?>
									<img src="<?= esc_url($daily_chart_url) ?>" alt="<?= esc_attr__('Daily chart', 'fromscratch') ?>" style="display:block;width:100%;max-width:100%;height:auto;border-radius:8px;border:1px solid #e2e8f0;">
								<?php endif; ?>
								<table role="presentation" width="100%" cellpadding="6" cellspacing="0" border="0" style="margin-top:10px;border-collapse:collapse;font-size:13px;">
									<tr style="background:#f8fafc;">
										<th align="left" style="border-bottom:1px solid #e2e8f0;"><?= esc_html__('Date', 'fromscratch') ?></th>
										<th align="right" style="border-bottom:1px solid #e2e8f0;"><?= esc_html__('Unique visitors', 'fromscratch') ?></th>
										<th align="right" style="border-bottom:1px solid #e2e8f0;"><?= esc_html__('Visits', 'fromscratch') ?></th>
										<th align="right" style="border-bottom:1px solid #e2e8f0;"><?= esc_html__('Page views', 'fromscratch') ?></th>
									</tr>
									<?php foreach (($daily ?? []) as $row) : ?>
										<tr>
											<td style="border-bottom:1px solid #eef2f7;"><?= esc_html(isset($row['date']) ? wp_date(get_option('date_format'), strtotime((string) $row['date'])) : '') ?></td>
											<td align="right" style="border-bottom:1px solid #eef2f7;"><?= esc_html(number_format_i18n((int) ($row['unique'] ?? 0))) ?></td>
											<td align="right" style="border-bottom:1px solid #eef2f7;"><?= esc_html(number_format_i18n((int) ($row['visits'] ?? 0))) ?></td>
											<td align="right" style="border-bottom:1px solid #eef2f7;"><?= esc_html(number_format_i18n((int) ($row['pageviews'] ?? 0))) ?></td>
										</tr>
									<?php endforeach; ?>
								</table>
							</td>
						</tr>
						<tr>
							<td style="padding:0 24px 16px;">
								<h2 style="margin:0 0 10px;font-size:18px;"><?= esc_html__('Weekly statistics (last 8 completed weeks)', 'fromscratch') ?></h2>
								<?php if (!empty($weekly_chart_url)) : ?>
									<img src="<?= esc_url($weekly_chart_url) ?>" alt="<?= esc_attr__('Weekly chart', 'fromscratch') ?>" style="display:block;width:100%;max-width:100%;height:auto;border-radius:8px;border:1px solid #e2e8f0;">
								<?php endif; ?>
								<table role="presentation" width="100%" cellpadding="6" cellspacing="0" border="0" style="margin-top:10px;border-collapse:collapse;font-size:13px;">
									<tr style="background:#f8fafc;">
										<th align="left" style="border-bottom:1px solid #e2e8f0;"><?= esc_html__('Week', 'fromscratch') ?></th>
										<th align="right" style="border-bottom:1px solid #e2e8f0;"><?= esc_html__('Unique visitors', 'fromscratch') ?></th>
										<th align="right" style="border-bottom:1px solid #e2e8f0;"><?= esc_html__('Visits', 'fromscratch') ?></th>
										<th align="right" style="border-bottom:1px solid #e2e8f0;"><?= esc_html__('Page views', 'fromscratch') ?></th>
									</tr>
									<?php foreach (($weekly ?? []) as $row) : ?>
										<tr>
											<td style="border-bottom:1px solid #eef2f7;"><?= esc_html((string) ($row['date'] ?? '')) ?></td>
											<td align="right" style="border-bottom:1px solid #eef2f7;"><?= esc_html(number_format_i18n((int) ($row['unique'] ?? 0))) ?></td>
											<td align="right" style="border-bottom:1px solid #eef2f7;"><?= esc_html(number_format_i18n((int) ($row['visits'] ?? 0))) ?></td>
											<td align="right" style="border-bottom:1px solid #eef2f7;"><?= esc_html(number_format_i18n((int) ($row['pageviews'] ?? 0))) ?></td>
										</tr>
									<?php endforeach; ?>
								</table>
							</td>
						</tr>
					<?php endif; ?>
					<tr>
						<td style="padding:8px 24px 24px;">
							<p style="margin:0 0 8px;">
								<a href="<?= esc_url($stats_url ?? '') ?>" style="display:inline-block;padding:10px 16px;border-radius:22px;background:#e2e8f0;color:#1f2937;text-decoration:none;"><?= esc_html__('Open analytics', 'fromscratch') ?></a>
							</p>
							<p style="margin:0;color:#94a3b8;font-size:12px;">
								<a href="<?= esc_url($site_url ?? '') ?>" style="color:#94a3b8;"><?= esc_html($site_url ?? '') ?></a>
							</p>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</body>
</html>
