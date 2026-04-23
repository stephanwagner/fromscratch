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
 * @return array{went_live_last_week: array<int,array{title:string,url:string,date:string}>, scheduled_upcoming: array<int,array{title:string,url:string,date:string}>, expired_last_week: array<int,array{title:string,url:string,date:string}>, expiring_upcoming: array<int,array{title:string,url:string,date:string}>}
 */
function fs_weekly_report_build_insights(\DateTimeImmutable $last_monday, \DateTimeImmutable $this_monday): array
{
	$insight_date_format = 'd.m.Y H:i';

	$out = [
		'went_live_last_week' => [],
		'scheduled_upcoming' => [],
		'expired_last_week' => [],
		'expiring_upcoming' => [],
	];
	if (!function_exists('fs_theme_post_types')) {
		return $out;
	}
	$post_types = fs_theme_post_types();
	$last_week_start = $last_monday->format('Y-m-d H:i:s');
	$last_week_end = $this_monday->modify('-1 second')->format('Y-m-d H:i:s');

	$scheduled = get_posts([
		'post_type' => $post_types,
		'post_status' => 'future',
		'posts_per_page' => 10,
		'orderby' => 'date',
		'order' => 'ASC',
	]);
	foreach ($scheduled as $p) {
		$out['scheduled_upcoming'][] = [
			'title' => (string) (get_the_title((int) $p->ID) ?: __('(no title)', 'fromscratch')),
			'url' => (string) get_permalink((int) $p->ID),
			'date' => (string) get_date_from_gmt((string) $p->post_date_gmt, $insight_date_format),
		];
	}

	$went_live = get_posts([
		'post_type' => $post_types,
		'post_status' => 'publish',
		'posts_per_page' => 10,
		'orderby' => 'date',
		'order' => 'DESC',
		'date_query' => [
			[
				'after' => $last_week_start,
				'before' => $last_week_end,
				'inclusive' => true,
				'column' => 'post_date',
			],
		],
	]);
	foreach ($went_live as $p) {
		$out['went_live_last_week'][] = [
			'title' => (string) (get_the_title((int) $p->ID) ?: __('(no title)', 'fromscratch')),
			'url' => (string) get_permalink((int) $p->ID),
			'date' => (string) get_the_date($insight_date_format, (int) $p->ID),
		];
	}

	if (
		function_exists('fs_theme_feature_enabled')
		&& fs_theme_feature_enabled('post_expirator')
		&& defined('FS_EXPIRATION_META_KEY')
		&& defined('FS_EXPIRATION_ENABLED_KEY')
	) {
		$expiring = get_posts([
			'post_type' => $post_types,
			'post_status' => ['publish', 'future', 'draft', 'private', 'pending'],
			'posts_per_page' => 200,
			'orderby' => 'meta_value',
			'meta_key' => FS_EXPIRATION_META_KEY,
			'order' => 'ASC',
			'meta_query' => [
				'relation' => 'AND',
				[
					'key' => FS_EXPIRATION_ENABLED_KEY,
					'value' => '1',
				],
				[
					'key' => FS_EXPIRATION_META_KEY,
					'compare' => '!=',
					'value' => '',
				],
			],
		]);
		$last_week_start_ts = $last_monday->getTimestamp();
		$this_monday_ts = $this_monday->getTimestamp();
		foreach ($expiring as $p) {
			$raw = (string) get_post_meta((int) $p->ID, FS_EXPIRATION_META_KEY, true);
			$ts = fs_weekly_report_parse_expiration_timestamp($raw);
			if ($ts === null) {
				continue;
			}
			$row = [
				'title' => (string) (get_the_title((int) $p->ID) ?: __('(no title)', 'fromscratch')),
				'url' => (string) get_permalink((int) $p->ID),
				'date' => (string) wp_date($insight_date_format, $ts),
			];
			if ($ts >= $this_monday_ts) {
				if (count($out['expiring_upcoming']) < 10) {
					$out['expiring_upcoming'][] = $row;
				}
				continue;
			}
			if ($ts >= $last_week_start_ts && $ts < $this_monday_ts) {
				if (count($out['expired_last_week']) < 10) {
					$out['expired_last_week'][] = $row;
				}
			}
		}
	}

	return $out;
}

/**
 * Parse post-expirator `Y-m-d H:i` into timestamp (site timezone).
 */
function fs_weekly_report_parse_expiration_timestamp(string $raw): ?int
{
	if ($raw === '') {
		return null;
	}
	$tz = function_exists('wp_timezone') ? wp_timezone() : new \DateTimeZone(wp_timezone_string() ?: 'UTC');
	$dt = \DateTimeImmutable::createFromFormat('Y-m-d H:i', $raw, $tz);
	if (!$dt instanceof \DateTimeImmutable) {
		return null;
	}

	return $dt->getTimestamp();
}

/**
 * Build the HTML body for weekly report.
 */
function fs_weekly_report_build_html(): string
{
	$site_name = get_bloginfo('name');
	$site_url = home_url();
	$admin_url = admin_url();
	$stats_url = function_exists('fs_dashboard_statistics_url') ? fs_dashboard_statistics_url() : admin_url();
	$theme_settings_url = admin_url('options-general.php?page=fs-theme-settings');
	$developer_settings_url = admin_url('options-general.php?page=fs-developer-settings');
	$developer_email = function_exists('fs_developer_email') ? fs_developer_email() : '';
	$admin_email = get_option('admin_email', '');
	$developer_email_link = (is_string($developer_email) && is_email($developer_email)) ? ('mailto:' . $developer_email) : '';
	$admin_email_link = (is_string($admin_email) && is_email($admin_email)) ? ('mailto:' . $admin_email) : '';
	$date_now = wp_date(get_option('date_format') . ' ' . get_option('time_format'));
	$matomo_on = function_exists('fs_theme_feature_enabled') && fs_theme_feature_enabled('matomo');
	$tz = function_exists('wp_timezone') ? wp_timezone() : new \DateTimeZone(wp_timezone_string() ?: 'UTC');
	$today = new \DateTimeImmutable('now', $tz);
	$this_monday = $today->modify('monday this week')->setTime(0, 0, 0);
	$last_monday = $this_monday->modify('-7 days');
	$last_sunday = $this_monday->modify('-1 day');
	$insights = fs_weekly_report_build_insights($last_monday, $this_monday);

	$daily = [];
	$weekly = [];
	$daily_chart_url = '';
	$weekly_chart_url = '';

	if ($matomo_on && function_exists('fs_matomo_get_statistics')) {
		// Fetch a little extra; then filter to full periods only.
		$series = fs_matomo_get_statistics();
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
		$daily_chart_url = fs_weekly_report_build_chart_url(
			array_map(static function ($row) use ($tz): array {
				$date = isset($row['date']) ? (string) $row['date'] : '';
				if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
					return ['', ''];
				}
				$dt = new \DateTimeImmutable($date . ' 12:00:00', $tz);
				$ts = $dt->getTimestamp();
				$week_monday = $dt->modify('monday this week');
				$week_no = (int) $week_monday->format('W');

				return [
					wp_date('l', $ts),
					wp_date('d.m.Y', $ts),
				];
			}, $daily),
			[
				[
					'label' => __('Unique visitors', 'fromscratch'),
					'data' => array_map(static fn($r) => (int) ($r['unique'] ?? 0), $daily),
					'color' => '#2284e5',
					'transparent' => '#2284e535',
				],
				[
					'label' => __('Visits', 'fromscratch'),
					'data' => array_map(static fn($r) => (int) ($r['visits'] ?? 0), $daily),
					'color' => '#8f70cc',
					'transparent' => '#8f70cc35',
				],
				[
					'label' => __('Page views', 'fromscratch'),
					'data' => array_map(static fn($r) => (int) ($r['pageviews'] ?? 0), $daily),
					'color' => '#ff6673',
					'transparent' => '#ff667340',
				],
			],
			'line'
		);
		$weekly_chart_url = fs_weekly_report_build_chart_url(
			array_map(static function ($row) use ($tz): array {
				$d = isset($row['date']) ? (string) $row['date'] : '';
				if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
					return ['', ''];
				}
				$monday = (new \DateTimeImmutable($d . ' 12:00:00', $tz))->modify('monday this week');
				$m_ts = $monday->getTimestamp();
				$week_no = (int) $monday->format('W');

				return [
					sprintf(__('Week %d', 'fromscratch'), $week_no),
					wp_date('d.m.Y', $m_ts),
				];
			}, $weekly),
			[
				[
					'label' => __('Unique visitors', 'fromscratch'),
					'data' => array_map(static fn($r) => (int) ($r['unique'] ?? 0), $weekly),
					'color' => '#2284e5',
					'transparent' => '#2284e535',
				],
				[
					'label' => __('Visits', 'fromscratch'),
					'data' => array_map(static fn($r) => (int) ($r['visits'] ?? 0), $weekly),
					'color' => '#8f70cc',
					'transparent' => '#8f70cc35',
				],
				[
					'label' => __('Page views', 'fromscratch'),
					'data' => array_map(static fn($r) => (int) ($r['pageviews'] ?? 0), $weekly),
					'color' => '#ff6673',
					'transparent' => '#ff667340',
				],
			],
			'line'
		);
	}
	$template_html = fs_get_email_template('weekly-report', [
		'site_name' => $site_name,
		'date_now' => $date_now,
		'site_url' => $site_url,
		'admin_url' => $admin_url,
		'stats_url' => $stats_url,
		'insights' => $insights,
		'daily' => $daily,
		'weekly' => $weekly,
		'daily_chart_url' => $daily_chart_url,
		'weekly_chart_url' => $weekly_chart_url,
		'matomo_enabled' => $matomo_on,
		'theme_settings_url' => $theme_settings_url,
		'developer_settings_url' => $developer_settings_url,
		'developer_email_link' => $developer_email_link,
		'admin_email_link' => $admin_email_link,
	]);
	if ($template_html !== '') {
		return $template_html;
	}

	return '<h2>' . esc_html($site_name) . ' - ' . esc_html__('Weekly report', 'fromscratch') . '</h2>';
}

/**
 * Build chart image URL via QuickChart (Chart.js v4 config).
 *
 * @param array<int, string|array<int,string>> $labels
 * @param array<int, array{label:string,data:array<int,int>,color:string,transparent:string}> $series
 */
function fs_weekly_report_build_chart_url(array $labels, array $series, string $type = 'line'): string
{
	if ($labels === [] || $series === []) {
		return '';
	}
	$datasets = [];
	foreach ($series as $s) {
		$datasets[] = [
			'label' => $s['label'],
			'data' => $s['data'],
			'borderColor' => $s['color'],
			'backgroundColor' => $s['transparent'],
			'fill' => true,
			'tension' => 0.3,
			'pointRadius' => 3,
			'pointHoverRadius' => 4,
			'pointBackgroundColor' => $s['color'],
			'borderWidth' => 2,
		];
	}
	$config = [
		'type' => $type,
		'data' => [
			'labels' => $labels,
			'datasets' => $datasets,
		],
		'options' => [
			'plugins' => [
				'legend' => ['display' => false],
			],
			'scales' => [
				'x' => ['ticks' => ['color' => '#888'], 'grid' => ['color' => '#8888884d']],
				'y' => ['beginAtZero' => true, 'ticks' => ['color' => '#888'], 'grid' => ['color' => '#8888884d']],
			],
		],
	];

	return 'https://quickchart.io/chart?version=4&width=600&height=300&devicePixelRatio=2&c=' . rawurlencode(wp_json_encode($config));
}

/**
 * Send Weekly website report to one or many recipients.
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
		__('Weekly website report – %s', 'fromscratch'),
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
