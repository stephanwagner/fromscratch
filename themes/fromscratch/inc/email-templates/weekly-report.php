<?php
$has_heading = isset($email_heading) && is_string($email_heading) && trim($email_heading) !== '';
?>
<?php if ($has_heading) : ?>
<h1
	style="
								margin: 0;
								font-size: 24px;
								line-height: 1.3;
								font-weight: bold;
								text-wrap: balance;
								text-align: center;
								">
	<?= esc_html(trim($email_heading)) ?>
</h1>
<?php endif; ?>

<?php
$insights = is_array($insights ?? null) ? $insights : [];

$has_insights = !empty($insights['went_live_last_week'])
	|| !empty($insights['scheduled_upcoming'])
	|| !empty($insights['expired_last_week'])
	|| !empty($insights['expiring_upcoming']);

$has_matomo = !empty($matomo_enabled);

if (!$has_insights && !$has_matomo) {
?>
	<tr>
		<td>
			<div
				style="
									margin: 32px auto 0;
									font-size: 16px;
									line-height: 1.5;
									color: #64748b;
									text-align: center;
									text-wrap: balance;
									max-width: 400px;
								">
				<?= esc_html__('Everything stayed unchanged last week, no content updates to show.', 'fromscratch') ?>
			</div>
		</td>
	</tr>
	<?php
} else {
	foreach (
		[
			'went_live_last_week' => __('Pages or posts published last week', 'fromscratch'),
			'scheduled_upcoming' => __('Upcoming scheduled pages or posts', 'fromscratch'),
			'expired_last_week' => __('Expired pages or posts last week', 'fromscratch'),
			'expiring_upcoming' => __('Upcoming expirations', 'fromscratch'),
		] as $key => $label
	) {
		if (!empty($insights[$key])) {
	?>
			<tr>
				<td>
					<div
						style="
										margin: 24px auto 6px;
										font-size: 16px;
										color: #64748b;
										text-wrap: balance;
									">
						<?= esc_html($label) ?>
					</div>
					<table
						role="presentation"
						width="100%"
						cellpadding="0"
						cellspacing="0"
						style="
											border: 0;
											border-collapse: collapse;
											font-size: 13px;
										">
						<?php foreach ($insights[$key] as $row) { ?>
							<tr>
								<td style="padding: 2px 6px 2px 0; white-space: nowrap; color: #72839b;"><?= esc_html((string) ($row['date'] ?? '')) ?></td>
								<td style="padding: 2px 0 2px 6px;" class="fs-mail-weekly-report-has-link" width="100%"><a href="<?= esc_url((string) ($row['url'] ?? '')) ?>"><?= esc_html((string) ($row['title'] ?? '')) ?></a></td>
							</tr>
						<?php } ?>
					</table>
				</td>
			</tr>
<?php
		}
	}
}
?>
<?php if (!empty($matomo_enabled)) : ?>
	<tr>
		<td>
			<div
				style="
										margin: 32px auto 16px;
										font-size: 16px;
										color: #64748b;
										text-align: center;
										text-wrap: balance;
										max-width: 320px;
									">
				<?= wp_kses(__('Visitors and page views <div class="fs-mail__small-mobile-inline">of the last week</div>', 'fromscratch'), ['br' => [], 'div' => ['class' => []]]) ?>
			</div>
			<?php if (!empty($daily_chart_url)) : ?>
				<img
					src="<?= esc_url($daily_chart_url) ?>"
					alt=""
					style="
											display: block;
											width: 100%;
											max-width: 100%;
											height: auto;
											">
			<?php endif; ?>
			<table
				role="presentation"
				width="100%"
				cellpadding="0"
				cellspacing="0"
				style="
										border: 0;
										margin-top: 24px;
										border-collapse: collapse;
										font-size: 13px;
										line-height: 1.4;
										">
				<tr>
					<th style="border-bottom:2px solid #e2e8f0;"></th>
					<th style="border-bottom:2px solid #e2e8f0; padding: 0 4px 6px; text-align: center; font-weight: bold; color: #2284e5;"><?= wp_kses(__('Unique<br>visitors', 'fromscratch'), ['br' => []]) ?></th>
					<th style="border-bottom:2px solid #e2e8f0; padding: 0 4px 6px; text-align: center; font-weight: bold; color: #8f70cc;"><?= wp_kses(__('Visits<br>total', 'fromscratch'), ['br' => []]) ?></th>
					<th style="border-bottom:2px solid #e2e8f0; padding: 0 4px 6px; text-align: center; font-weight: bold; color: #ff6673;"><?= wp_kses(__('Page<br>views', 'fromscratch'), ['br' => []]) ?></th>
				</tr>
				<?php foreach (($daily ?? []) as $row) : ?>
					<?php
					$date_str = isset($row['date']) ? (string) $row['date'] : '';
					$daily_weekday = '';
					$daily_written = '';
					if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_str)) {
						$dd = \DateTimeImmutable::createFromFormat('Y-m-d', $date_str, wp_timezone());
						if ($dd instanceof \DateTimeImmutable) {
							$daily_weekday = wp_date('l', $dd->getTimestamp());
							$daily_written = wp_date('j. M Y', $dd->getTimestamp());
						}
					}
					?>
					<tr>
						<td style="border-bottom:2px solid #e2e8f0;line-height:1.4; padding: 6px 0"><b style="font-weight: bold;"><?php if ($daily_weekday !== '') : ?><?= esc_html($daily_weekday) ?></b><br><span style="color: #72839b;"><?= esc_html($daily_written) ?></span><?php endif; ?></td>
						<td style="border-bottom:2px solid #e2e8f0; padding: 6px; text-align: center; font-weight: bold;"><?= esc_html(number_format_i18n((int) ($row['unique'] ?? 0))) ?></td>
						<td style="border-bottom:2px solid #e2e8f0; padding: 6px; text-align: center;"><?= esc_html(number_format_i18n((int) ($row['visits'] ?? 0))) ?></td>
						<td style="border-bottom:2px solid #e2e8f0; padding: 6px; text-align: center;"><?= esc_html(number_format_i18n((int) ($row['pageviews'] ?? 0))) ?></td>
					</tr>
				<?php endforeach; ?>
			</table>
		</td>
	</tr>
	<tr>
		<td>
			<div
				style="
							margin: 32px auto 16px;
							font-size: 16px;
							color: #64748b;
							text-align: center;
							text-wrap: balance;
						">
				<?= wp_kses(__('Visitors and page views <div class="fs-mail__small-mobile-inline">of the last 8 weeks</div>', 'fromscratch'), ['br' => [], 'div' => ['class' => []]]) ?>
			</div>
			<?php if (!empty($weekly_chart_url)) : ?>
				<img
					src="<?= esc_url($weekly_chart_url) ?>"
					alt=""
					style="
								display: block;
								width: 100%;
								max-width: 100%;
								height: auto;
							">
			<?php endif; ?>
			<table
				role="presentation"
				width="100%"
				cellpadding="0"
				cellspacing="0"
				border="0"
				style="
							border: 0;
							margin-top: 24px;
							border-collapse: collapse;
							font-size: 13px;
							line-height: 1.4;
						">
				<tr>
					<th style="border-bottom:2px solid #e2e8f0;"></th>
					<th style="border-bottom:2px solid #e2e8f0; padding: 0 4px 6px; text-align: center; font-weight: bold; color: #2284e5;"><?= wp_kses(__('Unique<br>visitors', 'fromscratch'), ['br' => []]) ?></th>
					<th style="border-bottom:2px solid #e2e8f0; padding: 0 4px 6px; text-align: center; font-weight: bold; color: #8f70cc;"><?= wp_kses(__('Visits<br>total', 'fromscratch'), ['br' => []]) ?></th>
					<th style="border-bottom:2px solid #e2e8f0; padding: 0 4px 6px; text-align: center; font-weight: bold; color: #ff6673;"><?= wp_kses(__('Page<br>views', 'fromscratch'), ['br' => []]) ?></th>
				</tr>
				<?php foreach (($weekly ?? []) as $row) : ?>
					<?php
					$w_date = isset($row['date']) ? (string) $row['date'] : '';
					$week_line = '';
					$monday_written = '';
					if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $w_date)) {
						$wd = \DateTimeImmutable::createFromFormat('Y-m-d', $w_date, wp_timezone());
						if ($wd instanceof \DateTimeImmutable) {
							$monday = $wd->modify('monday this week');
							$week_no = (int) $monday->format('W');
							$week_line = sprintf(__('Week %d', 'fromscratch'), $week_no);
							$monday_written = wp_date('j. M Y', $monday->getTimestamp());
						}
					}
					?>
					<tr>
						<td style="border-bottom:2px solid #e2e8f0;line-height:1.4; padding: 6px 0"><b style="font-weight: bold;"><?php if ($week_line !== '') : ?><?= esc_html($week_line) ?></b><br><span style="color: #72839b;"><?= esc_html($monday_written) ?></span><?php endif; ?></td>
						<td style="border-bottom:2px solid #e2e8f0; padding: 6px; text-align: center; font-weight: bold;"><?= esc_html(number_format_i18n((int) ($row['unique'] ?? 0))) ?></td>
						<td style="border-bottom:2px solid #e2e8f0; padding: 6px; text-align: center;"><?= esc_html(number_format_i18n((int) ($row['visits'] ?? 0))) ?></td>
						<td style="border-bottom:2px solid #e2e8f0; padding: 6px; text-align: center;"><?= esc_html(number_format_i18n((int) ($row['pageviews'] ?? 0))) ?></td>
					</tr>
				<?php endforeach; ?>
			</table>
		</td>
	</tr>
<?php else : ?>
	<tr>
		<td>
			<div
				class="fs-mail-weekly-report-has-link"
				style="
									margin: 32px auto 0;
									font-size: 16px;
									line-height: 1.5;
									color: #64748b;
									text-align: center;
									text-wrap: balance;
								">
				<?php
				$matomo_info = __(
					'Visitor statistics for this report are not enabled yet. A <a href="%s">developer</a> can turn on analytics in <a href="%s">WordPress</a> to show visiter charts and key numbers in future emails.',
					'fromscratch'
				);
				echo wp_kses(
					sprintf($matomo_info, esc_url($developer_email_link ?? ''), esc_url($developer_settings_url ?? '')),
					[
						'a' => [
							'href' => [],
						],
					]
				);
				?>
			</div>
		</td>
	</tr>
<?php endif; ?>
<tr>
	<td>
		<div
			style="
                      padding-top: 32px;
					  text-align: center;
                    ">
			<?php if ($has_matomo && !empty($stats_url)) : ?>
				<div
					style="
								font-size: 17px;
								line-height: 1.4;
								text-align: center;
							">
					<a
						href="<?= esc_url($stats_url) ?>"
						class="fs-mail__button"
						style="
									color: #1f2937;
									text-decoration: none;
									padding: 8px 24px;
									border-radius: 32px;
									background: #e2e8f0;
									display: inline-block;
									transition: background-color 280ms, color 280ms;
								">
						<?= esc_html__('Open analytics', 'fromscratch') ?>
					</a>
				</div>
			<?php endif; ?>

			<div
				style="
									<?php if ($has_matomo && !empty($stats_url)) { ?>
										margin-top: 16px;
										<?php } ?>
										font-size: 17px;
										line-height: 1.4;
										text-align: center;
									">
				<a
					href="<?= esc_url($admin_url) ?>"
					class="fs-mail__button"
					style="
								color: #1f2937;
								text-decoration: none;
								padding: 8px 24px;
								border-radius: 32px;
								background: #e2e8f0;
								display: inline-block;
								transition: background-color 280ms, color 280ms;
							">
					<?= esc_html__('Open admin dashboard', 'fromscratch') ?>
				</a>
			</div>
		</div>