<?php

defined('ABSPATH') || exit;

/**
 * Dashboard stats storage option.
 */
const FS_DASHBOARD_STATS_OPTION = 'fromscratch_dashboard_daily_stats';

/**
 * Get Matomo tracking settings when available.
 *
 * @return array{url:string,site_id:int,token:string}|null
 */
function fs_dashboard_matomo_settings(): ?array
{
	if (!function_exists('fs_theme_feature_enabled') || !fs_theme_feature_enabled('matomo')) {
		return null;
	}
	$url = trim((string) get_option('fromscratch_matomo_url', ''));
	$site_id = (int) get_option('fromscratch_matomo_site_id', 1);
	$token = trim((string) get_option('fromscratch_matomo_token_auth', ''));
	if ($url === '' || $site_id <= 0 || $token === '') {
		return null;
	}
	return [
		'url' => trailingslashit($url),
		'site_id' => $site_id,
		'token' => $token,
	];
}

/**
 * Fetch daily Matomo visits for last N days.
 *
 * @return array<int, array{date:string,visits:int,pageviews:int}>
 */
function fs_dashboard_get_matomo_daily_visits(int $days = 30): array
{
	$days = max(1, min(365, $days));
	$settings = fs_dashboard_matomo_settings();
	if ($settings === null || !function_exists('wp_remote_get')) {
		return [];
	}

	$series = [];
	for ($i = $days - 1; $i >= 0; $i--) {
		$key = gmdate('Y-m-d', time() - ($i * DAY_IN_SECONDS));
		$series[$key] = ['visits' => 0, 'pageviews' => 0];
	}

	$api_url = add_query_arg([
		'module' => 'API',
		'method' => 'VisitsSummary.get',
		'idSite' => (string) $settings['site_id'],
		'period' => 'day',
		'date' => 'last' . $days,
		'format' => 'JSON',
		'token_auth' => $settings['token'],
	], $settings['url'] . 'index.php');

	$response = wp_remote_get($api_url, [
		'timeout' => 10,
		'headers' => ['Accept' => 'application/json'],
	]);
	if (is_wp_error($response)) {
		return [];
	}
	$body = wp_remote_retrieve_body($response);
	$data = json_decode($body, true);
	if (!is_array($data)) {
		return [];
	}

	foreach ($data as $date => $value) {
		if (!is_string($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
			continue;
		}
		$visits = 0;
		$pageviews = 0;
		if (is_array($value)) {
			$visits = (int) ($value['nb_visits'] ?? 0);
			$pageviews = (int) ($value['nb_actions'] ?? 0);
		} elseif (is_numeric($value)) {
			$visits = (int) $value;
		}
		if (array_key_exists($date, $series)) {
			$series[$date]['visits'] = max(0, $visits);
			$series[$date]['pageviews'] = max(0, $pageviews);
		}
	}

	$out = [];
	foreach ($series as $date => $row) {
		$out[] = [
			'date' => $date,
			'visits' => (int) ($row['visits'] ?? 0),
			'pageviews' => (int) ($row['pageviews'] ?? 0),
		];
	}
	return $out;
}

/**
 * Reusable Chart.js line chart config.
 *
 * @param string[] $labels
 * @param array<int, array<string, mixed>> $datasets
 * @return array<string, mixed>
 */
function fs_dashboard_line_chart_config(array $labels, array $datasets): array
{
	return [
		'type' => 'line',
		'data' => [
			'labels' => $labels,
			'datasets' => $datasets,
		],
		'options' => [
			'plugins' => [
				'legend' => [
					'display' => false,
				],
			],
			'scales' => [
				'x' => [
					'ticks' => [
						'color' => '#888',
					],
					'grid' => [
						'color' => '#8888884d',
					],
				],
				'y' => [
					'beginAtZero' => true,
					'ticks' => [
						'color' => '#888',
					],
					'grid' => [
						'color' => '#8888884d',
					],
				],
			],
		],
	];
}

/**
 * Basic front-end daily stats tracking (page views + unique visitors).
 * Unique visitors are tracked via a first-party cookie.
 */
function fs_dashboard_track_visit(): void
{
	if (is_admin() || wp_doing_ajax() || wp_doing_cron() || is_feed() || is_trackback() || is_preview()) {
		return;
	}

	$date = gmdate('Y-m-d');
	$cookie_name = 'fs_dashboard_visitor_id';
	$visitor_id = isset($_COOKIE[$cookie_name]) ? sanitize_text_field((string) wp_unslash($_COOKIE[$cookie_name])) : '';
	if ($visitor_id === '' || strlen($visitor_id) < 12) {
		try {
			$visitor_id = bin2hex(random_bytes(16));
		} catch (\Throwable $e) {
			$visitor_id = wp_generate_uuid4();
		}
		$secure = is_ssl();
		$expires = time() + (365 * DAY_IN_SECONDS);
		setcookie($cookie_name, $visitor_id, $expires, COOKIEPATH, COOKIE_DOMAIN, $secure, true);
		if (COOKIEPATH !== '/') {
			setcookie($cookie_name, $visitor_id, $expires, '/', COOKIE_DOMAIN, $secure, true);
		}
	}

	$stats = get_option(FS_DASHBOARD_STATS_OPTION, []);
	if (!is_array($stats)) {
		$stats = [];
	}
	if (!isset($stats[$date]) || !is_array($stats[$date])) {
		$stats[$date] = ['views' => 0, 'visitors' => 0];
	}
	$stats[$date]['views'] = (int) ($stats[$date]['views'] ?? 0) + 1;

	$unique_key = 'fs_dashboard_uv_' . $date . '_' . md5($visitor_id);
	if (!get_transient($unique_key)) {
		$stats[$date]['visitors'] = (int) ($stats[$date]['visitors'] ?? 0) + 1;
		set_transient($unique_key, '1', 2 * DAY_IN_SECONDS);
	}

	ksort($stats);
	if (count($stats) > 120) {
		$stats = array_slice($stats, -120, null, true);
	}

	update_option(FS_DASHBOARD_STATS_OPTION, $stats, false);
}
add_action('template_redirect', 'fs_dashboard_track_visit', 5);

/**
 * Get dashboard stats for the last N days.
 *
 * @return array<int, array{date:string,views:int,visitors:int}>
 */
function fs_dashboard_get_daily_stats(int $days = 30): array
{
	$days = max(1, min(365, $days));
	$stats = get_option(FS_DASHBOARD_STATS_OPTION, []);
	if (!is_array($stats)) {
		$stats = [];
	}
	$out = [];
	for ($i = $days - 1; $i >= 0; $i--) {
		$key = gmdate('Y-m-d', time() - ($i * DAY_IN_SECONDS));
		$row = isset($stats[$key]) && is_array($stats[$key]) ? $stats[$key] : [];
		$out[] = [
			'date' => $key,
			'views' => (int) ($row['views'] ?? 0),
			'visitors' => (int) ($row['visitors'] ?? 0),
		];
	}
	return $out;
}

function fs_dashboard_stats_page_slug(): string
{
	return 'fromscratch-analytics';
}

function fs_dashboard_statistics_url(): string
{
	return admin_url('index.php?page=' . fs_dashboard_stats_page_slug());
}

/**
 * Dashboard > Analytics page.
 */
add_action('admin_menu', function (): void {
	if (!current_user_can('edit_posts')) {
		return;
	}
	add_submenu_page(
		'index.php',
		__('Analytics', 'fromscratch'),
		__('Analytics', 'fromscratch'),
		'edit_posts',
		fs_dashboard_stats_page_slug(),
		'fs_render_dashboard_statistics_page'
	);
});

function fs_render_dashboard_statistics_page(): void
{
	if (!current_user_can('edit_posts')) {
		wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'fromscratch'));
	}
	$matomo_rows = fs_dashboard_get_matomo_daily_visits(7);
	$use_matomo = !empty($matomo_rows);
	$rows = $use_matomo ? $matomo_rows : fs_dashboard_get_daily_stats(7);
	$labels = array_map(static fn($r) => $r['date'], $rows);
	$visits = $use_matomo
		? array_map(static fn($r) => (int) ($r['visits'] ?? 0), $rows)
		: array_map(static fn($r) => (int) ($r['visitors'] ?? 0), $rows);
	$pageviews = $use_matomo
		? array_map(static fn($r) => (int) ($r['pageviews'] ?? 0), $rows)
		: array_map(static fn($r) => (int) ($r['views'] ?? 0), $rows);
	$datasets = [[
		'label' => __('Daily visits', 'fromscratch'),
		'data' => $visits,
		'borderWidth' => 2,
		'tension' => 0.2,
	], [
		'label' => __('Page views', 'fromscratch'),
		'data' => $pageviews,
		'borderColor' => '#d63638',
		'backgroundColor' => '#d63638',
		'borderWidth' => 2,
		'tension' => 0.2,
	]];
	$line_chart_config = fs_dashboard_line_chart_config($labels, $datasets);
	?>
	<div class="wrap">
		<h1><?= esc_html__('Analytics', 'fromscratch') ?></h1>
		<p class="description"><?= esc_html__('Daily visits and page views for the last 7 days.', 'fromscratch') ?></p>
		<div style="max-width: 980px; background: #fff; border: 1px solid #dcdcde; border-radius: 6px; padding: 14px;">
			<canvas
				id="fs-stats-chart"
				height="110"
				data-chart="line"
				data-chart-config="<?= esc_attr(wp_json_encode($line_chart_config)) ?>"></canvas>
		</div>
	</div>
	<?php
}

/**
 * Remove WordPress Events and News panel
 */
add_action('wp_dashboard_setup', function () {
	remove_meta_box('dashboard_primary', 'dashboard', 'side');
}, 20);

/**
 * Force welcome panel to be shown
 */
add_filter('get_user_metadata', function ($value, $user_id, $meta_key) {
	if ($meta_key === 'show_welcome_panel') {
		return 1;
	}
	return $value;
}, 10, 3);

/**
 * Remove Welcome panel
 */
add_action('admin_init', function () {
	remove_action('welcome_panel', 'wp_welcome_panel');
});

/**
 * Hide Welcome panel checkbox
 */
add_action('admin_head', function () {

	$screen = get_current_screen();

	if ($screen->id !== 'dashboard') {
		return;
	}

	echo '<style>
        #screen-options-wrap label[for="wp_welcome_panel-hide"] {
            display:none;
        }
    </style>';
});

/**
 * Add a custom welcome panel
 */
function fs_dashboard_panel()
{
	$is_developer = function_exists('fs_is_developer_user') && fs_is_developer_user((int) get_current_user_id());
	$system_url = admin_url('options-general.php?page=' . fs_developer_settings_page_slug('system') . '#fs-search-visibility');
	$security_url = admin_url('options-general.php?page=fs-developer-security');
	$stats_url = fs_dashboard_statistics_url();
	$today_matomo = fs_dashboard_get_matomo_daily_visits(1);
	$today_local = fs_dashboard_get_daily_stats(1);
	$today_visits = !empty($today_matomo)
		? (int) ($today_matomo[0]['visits'] ?? 0)
		: (int) ($today_local[0]['visitors'] ?? 0);

	$scheduled = get_posts([
		'post_type'      => fs_theme_post_types(),
		'post_status'    => 'future',
		'posts_per_page' => 5,
		'orderby'        => 'date',
		'order'          => 'ASC',
	]);
?>
	<div class="fs-dashboard-panel">
		<div class="welcome-panel-content">

			<h2><?php esc_html_e('FromScratch', 'fromscratch'); ?></h2>

			<p class="about-description">
				<?php esc_html_e('Your development environment is ready.', 'fromscratch'); ?>
			</p>

			<div class="notice inline" style="margin: 0 0 1em 0; padding: 10px 12px;">
				<p style="margin: 0;">
					<strong><?= esc_html__('Today', 'fromscratch') ?>:</strong>
					<?= esc_html(sprintf(__('%1$s visits', 'fromscratch'), number_format_i18n($today_visits))) ?>
					· <a href="<?= esc_url($stats_url) ?>"><?= esc_html__('More analytics', 'fromscratch') ?></a>
				</p>
			</div>

			<?php if (!empty($scheduled)) : ?>
				<div class="notice inline" style="margin: 0 0 1em 0;">
					<p><strong><?= esc_html__('Scheduled posts', 'fromscratch') ?></strong></p>
					<ul style="margin: 0 0 0 16px;">
						<?php foreach ($scheduled as $item) : ?>
							<li>
								<a href="<?= esc_url(get_edit_post_link((int) $item->ID)) ?>"><?= esc_html(get_the_title((int) $item->ID) ?: __('(no title)', 'fromscratch')) ?></a>
								<span style="color:#646970;"> — <?= esc_html(get_date_from_gmt((string) $item->post_date_gmt, get_option('date_format') . ' ' . get_option('time_format'))) ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<?php
			$suspicious_ips = function_exists('fs_blocked_ips_suspicious_list') ? fs_blocked_ips_suspicious_list() : [];
			if (!empty($suspicious_ips)) :
			?>
				<div class="notice notice-warning inline" style="margin: 0 0 1em 0;">
					<p><strong><?php esc_html_e('Suspicious login attempts', 'fromscratch'); ?></strong></p>
					<p><?php esc_html_e('The following IPs exceeded the configured threshold and can be blocked.', 'fromscratch'); ?></p>
					<ul style="list-style: none; padding-left: 0;">
						<?php foreach ($suspicious_ips as $ip => $row) :
							$attempts = (int) ($row['attempts'] ?? 0);
						?>
							<li style="margin-bottom: 0.5em;">
								<code><?php echo esc_html($ip); ?></code> — <?php echo (int) $attempts; ?> <?php echo esc_html(_n('attempt', 'attempts', $attempts, 'fromscratch')); ?>
								<form method="post" action="<?php echo esc_url($security_url); ?>" style="display: inline; margin-left: 8px;">
									<?php wp_nonce_field('fromscratch_block_ip'); ?>
									<input type="hidden" name="fromscratch_block_ip" value="<?php echo esc_attr($ip); ?>">
									<button type="submit" name="fromscratch_do_block_ip" value="1" class="button button-small"><?php esc_html_e('Block IP', 'fromscratch'); ?></button>
								</form>
							</li>
						<?php endforeach; ?>
					</ul>
					<p><a href="<?php echo esc_url($security_url); ?>"><?php esc_html_e('Manage on Security page', 'fromscratch'); ?></a></p>
				</div>
			<?php endif; ?>

			<?php if ($is_developer && (int) get_option('blog_public', 1) === 0) : ?>
				<div class="notice notice-warning inline" style="margin: 0 0 1em 0;">
					<p><strong><?php esc_html_e('Search engines are asked not to index this site.', 'fromscratch'); ?></strong></p>
					<p><a href="<?php echo esc_url($system_url); ?>"><?php esc_html_e('Enable search engine indexing in Developer → System', 'fromscratch'); ?></a></p>
				</div>
			<?php endif; ?>

			<?php if ($is_developer && get_option('fromscratch_site_password_protection') === '1') : ?>
				<div class="notice notice-info inline" style="margin: 0 0 1em 0;">
					<p><strong><?php esc_html_e('Password protection is active.', 'fromscratch'); ?></strong></p>
					<p><a href="<?php echo esc_url($security_url); ?>"><?php esc_html_e('Manage in Developer → Security', 'fromscratch'); ?></a></p>
				</div>
			<?php endif; ?>

			<?php if ($is_developer && get_option('fromscratch_maintenance_mode') === '1') : ?>
				<div class="notice notice-info inline" style="margin: 0 0 1em 0;">
					<p><strong><?php esc_html_e('Maintenance mode is active.', 'fromscratch'); ?></strong></p>
					<p><a href="<?php echo esc_url($security_url); ?>"><?php esc_html_e('Manage in Developer → Security', 'fromscratch'); ?></a></p>
				</div>
			<?php endif; ?>

			<div class="welcome-panel-column-container">
				<div class="welcome-panel-column">
					<h3><?= esc_html__('Quick links', 'fromscratch') ?></h3>
					<ul>
						<li><a href="<?= esc_url(admin_url('options-general.php?page=fs-theme-settings')) ?>"><?= esc_html__('Theme settings', 'fromscratch') ?></a></li>
						<li><a href="<?= esc_url(admin_url('post-new.php?post_type=page')) ?>"><?= esc_html__('Create page', 'fromscratch') ?></a></li>
						<li><a href="<?= esc_url($stats_url) ?>"><?= esc_html__('Open analytics', 'fromscratch') ?></a></li>
					</ul>
				</div>

			</div>

		</div>
	</div>
<?php
}
add_action('welcome_panel', 'fs_dashboard_panel');
