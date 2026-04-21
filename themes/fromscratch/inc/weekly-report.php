<?php

defined('ABSPATH') || exit;

add_filter('cron_schedules', function (array $schedules): array {
	if (!isset($schedules['weekly'])) {
		$schedules['weekly'] = [
			'interval' => 7 * DAY_IN_SECONDS,
			'display' => __('Once Weekly', 'fromscratch'),
		];
	}

	return $schedules;
});

/**
 * Next Monday 08:00 in site timezone.
 */
function fs_weekly_report_next_monday_timestamp(): int
{
	$tz = function_exists('wp_timezone') ? wp_timezone() : new \DateTimeZone(wp_timezone_string() ?: 'UTC');
	$now = new \DateTimeImmutable('now', $tz);
	$monday = $now->setTime(8, 0, 0);
	if ((int) $monday->format('N') !== 1) {
		$monday = $monday->modify('next monday');
	}
	if ($monday <= $now) {
		$monday = $monday->modify('+7 days');
	}

	return $monday->getTimestamp();
}

/**
 * Build the HTML body for weekly report.
 */
function fs_weekly_report_build_html(): string
{
	$site_name = get_bloginfo('name');
	$site_url = home_url('/');
	$stats_url = function_exists('fs_dashboard_statistics_url') ? fs_dashboard_statistics_url() : admin_url();
	$date_now = wp_date(get_option('date_format') . ' ' . get_option('time_format'));
	$matomo_on = function_exists('fs_theme_feature_enabled') && fs_theme_feature_enabled('matomo');
	$tz = function_exists('wp_timezone') ? wp_timezone() : new \DateTimeZone(wp_timezone_string() ?: 'UTC');
	$today = new \DateTimeImmutable('now', $tz);
	$this_monday = $today->modify('monday this week')->setTime(0, 0, 0);
	$last_monday = $this_monday->modify('-7 days');
	$last_sunday = $this_monday->modify('-1 day');

	$html = '';
	$html .= '<h2>' . esc_html($site_name) . ' - ' . esc_html__('Weekly report', 'fromscratch') . '</h2>';
	$html .= '<p><strong>' . esc_html__('Generated', 'fromscratch') . ':</strong> ' . esc_html($date_now) . '</p>';
	$html .= '<p><a href="' . esc_url($site_url) . '">' . esc_html($site_url) . '</a></p>';

	if ($matomo_on && function_exists('fs_dashboard_get_matomo_daily_and_weekly')) {
		// Fetch a little extra; then filter to full periods only.
		$series = fs_dashboard_get_matomo_daily_and_weekly(14, 9);
		$daily_src = isset($series['daily']) && is_array($series['daily']) ? $series['daily'] : [];
		$weekly_src = isset($series['weekly']) && is_array($series['weekly']) ? $series['weekly'] : [];
		$daily = [];
		foreach ($daily_src as $row) {
			$date = isset($row['date']) ? (string) $row['date'] : '';
			if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
				continue;
			}
			$dt = new \DateTimeImmutable($date . ' 00:00:00', $tz);
			if ($dt < $last_monday || $dt > $last_sunday) {
				continue;
			}
			$daily[] = $row;
		}
		$weekly = [];
		foreach ($weekly_src as $row) {
			$date = isset($row['date']) ? (string) $row['date'] : '';
			if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
				continue;
			}
			$week_start = new \DateTimeImmutable($date . ' 00:00:00', $tz);
			$week_start = $week_start->modify('monday this week');
			// Exclude the current week; include only full, completed weeks.
			if ($week_start >= $this_monday) {
				continue;
			}
			$weekly[] = $row;
		}
		if (count($weekly) > 8) {
			$weekly = array_slice($weekly, -8);
		}

		$html .= '<h3>' . esc_html__('Daily statistics (last week, 7 days)', 'fromscratch') . '</h3>';
		$html .= '<table cellpadding="6" cellspacing="0" border="1" style="border-collapse:collapse;">';
		$html .= '<tr><th align="left">' . esc_html__('Date', 'fromscratch') . '</th><th align="right">' . esc_html__('Unique visitors', 'fromscratch') . '</th><th align="right">' . esc_html__('Visits', 'fromscratch') . '</th><th align="right">' . esc_html__('Page views', 'fromscratch') . '</th></tr>';
		foreach ($daily as $row) {
			$label = isset($row['date']) ? wp_date(get_option('date_format'), strtotime((string) $row['date'])) : '';
			$html .= '<tr>';
			$html .= '<td>' . esc_html($label) . '</td>';
			$html .= '<td align="right">' . esc_html(number_format_i18n((int) ($row['unique'] ?? 0))) . '</td>';
			$html .= '<td align="right">' . esc_html(number_format_i18n((int) ($row['visits'] ?? 0))) . '</td>';
			$html .= '<td align="right">' . esc_html(number_format_i18n((int) ($row['pageviews'] ?? 0))) . '</td>';
			$html .= '</tr>';
		}
		$html .= '</table>';

		$html .= '<h3 style="margin-top:16px;">' . esc_html__('Weekly statistics (last 8 completed weeks)', 'fromscratch') . '</h3>';
		$html .= '<table cellpadding="6" cellspacing="0" border="1" style="border-collapse:collapse;">';
		$html .= '<tr><th align="left">' . esc_html__('Week', 'fromscratch') . '</th><th align="right">' . esc_html__('Unique visitors', 'fromscratch') . '</th><th align="right">' . esc_html__('Visits', 'fromscratch') . '</th><th align="right">' . esc_html__('Page views', 'fromscratch') . '</th></tr>';
		foreach ($weekly as $row) {
			$label = isset($row['date']) ? (string) $row['date'] : '';
			$html .= '<tr>';
			$html .= '<td>' . esc_html($label) . '</td>';
			$html .= '<td align="right">' . esc_html(number_format_i18n((int) ($row['unique'] ?? 0))) . '</td>';
			$html .= '<td align="right">' . esc_html(number_format_i18n((int) ($row['visits'] ?? 0))) . '</td>';
			$html .= '<td align="right">' . esc_html(number_format_i18n((int) ($row['pageviews'] ?? 0))) . '</td>';
			$html .= '</tr>';
		}
		$html .= '</table>';
	}

	$html .= '<p style="margin-top:16px;"><a href="' . esc_url($stats_url) . '">' . esc_html__('Open analytics', 'fromscratch') . '</a></p>';

	return $html;
}

/**
 * Send weekly report email to one or many recipients.
 *
 * @param array<int, string> $emails Recipient list.
 */
function fs_weekly_report_send(array $emails): bool
{
	$emails = array_values(array_filter(array_unique(array_map(static function ($email): string {
		return is_string($email) ? sanitize_email($email) : '';
	}, $emails))));
	if ($emails === []) {
		return false;
	}
	$subject = sprintf(
		/* translators: %s: site name */
		__('Weekly report - %s', 'fromscratch'),
		get_bloginfo('name')
	);
	$body = fs_weekly_report_build_html();
	$headers = ['Content-Type: text/html; charset=UTF-8'];

	return (bool) wp_mail($emails, $subject, $body, $headers);
}

/**
 * Weekly sender callback.
 */
function fs_weekly_report_monday_send(): void
{
	if (get_option('fromscratch_weekly_report_enabled', '0') !== '1') {
		return;
	}
	if (!function_exists('fs_report_emails')) {
		return;
	}
	$emails = fs_report_emails();
	if ($emails === []) {
		return;
	}
	$monday_key = wp_date('o-\WW');
	$last_sent = (string) get_option('fromscratch_weekly_report_last_sent_week', '');
	if ($last_sent === $monday_key) {
		return;
	}

	if (fs_weekly_report_send($emails)) {
		update_option('fromscratch_weekly_report_last_sent_week', $monday_key, false);
	}
}
add_action('fs_weekly_report_weekly', 'fs_weekly_report_monday_send');

/**
 * Ensure weekly cron exists (Monday, 08:00 site timezone).
 */
add_action('init', function (): void {
	if (wp_installing()) {
		return;
	}

	// Migrate from previous daily hook setup.
	$old_daily = wp_next_scheduled('fs_weekly_report_daily');
	if ($old_daily) {
		wp_unschedule_event($old_daily, 'fs_weekly_report_daily');
	}

	$next = wp_next_scheduled('fs_weekly_report_weekly');
	if ($next) {
		return;
	}
	wp_schedule_event(fs_weekly_report_next_monday_timestamp(), 'weekly', 'fs_weekly_report_weekly');
}, 35);

