<?php

defined('ABSPATH') || exit;
?>
<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?= esc_html__('Weekly website report', 'fromscratch') ?></title>

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
							<h1
								style="
								margin: 0;
								font-size: 24px;
								line-height: 1.3;
								font-weight: bold;
								text-wrap: balance;
								text-align: center;
								">
								<?= esc_html__('Your weekly website report', 'fromscratch') ?>
							</h1>
						</td>
					</tr>
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
						</td>
					</tr>
				</table>
			</td>
		</tr>
		<tr>
			<td
				class="fs-mail-weekly-report-footer-text"
				style="
              padding: 16px 0 0;
              color: #94a3b8;
              font-size: 13px;
			  text-wrap: balance;
			  text-align: center;
            ">
				<div style="
					max-width: 540px;
					padding: 0 24px;
					margin: 0 auto;
				">
					<?php
					$footer_message = __(
						'If you no longer want to receive these reports, <a href="%1$s">log in to WordPress</a> and disable weekly reports, or contact the <a href="%2$s">developer</a> or <a href="%3$s">admin</a>.',
						'fromscratch'
					);

					echo wp_kses(
						sprintf(
							$footer_message,
							esc_url($theme_settings_url ?? ''),
							esc_url($developer_email_link ?? ''),
							esc_url($admin_email_link ?? '')
						),
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
	</table>
</body>

</html>