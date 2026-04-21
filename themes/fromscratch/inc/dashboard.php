<?php

defined('ABSPATH') || exit;

/** Transient: raw today/yesterday visit counts (locale applied when formatting for output). */
const FS_DASHBOARD_MATOMO_STATS_CACHE_KEY = 'fs_dashboard_matomo_stats_counts';

/**
 * @param array{today: int, yesterday: int} $counts
 * @return array{today: string, yesterday: string}
 */
function fs_dashboard_matomo_stats_format_lines(array $counts): array
{
	return [
		'today' => sprintf(
			__('%1$s visits', 'fromscratch'),
			number_format_i18n($counts['today'])
		),
		'yesterday' => sprintf(
			__('%1$s visits', 'fromscratch'),
			number_format_i18n($counts['yesterday'])
		),
	];
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
	$dash_placeholder = html_entity_decode('&#8211;', ENT_QUOTES | ENT_HTML5, 'UTF-8');

	$matomo_counts = fs_dashboard_matomo_stats_get_cached_counts();
	$matomo_stats_cached = is_array($matomo_counts);
	if ($matomo_stats_cached) {
		$matomo_lines = fs_dashboard_matomo_stats_format_lines($matomo_counts);
		$matomo_today_html = $matomo_lines['today'];
		$matomo_yesterday_html = $matomo_lines['yesterday'];
	} else {
		$matomo_today_html = $dash_placeholder;
		$matomo_yesterday_html = $dash_placeholder;
	}

	$scheduled = get_posts([
		'post_type'      => fs_theme_post_types(),
		'post_status'    => 'future',
		'posts_per_page' => 5,
		'orderby'        => 'date',
		'order'          => 'ASC',
	]);

	$expiring_published = [];
	if (
		function_exists('fs_theme_feature_enabled')
		&& fs_theme_feature_enabled('post_expirator')
		&& defined('FS_EXPIRATION_META_KEY')
		&& defined('FS_EXPIRATION_ENABLED_KEY')
	) {
		$expiring_published = get_posts([
			'post_type'      => fs_theme_post_types(),
			'post_status'    => 'publish',
			'posts_per_page' => 5,
			'orderby'        => 'meta_value',
			'meta_key'       => FS_EXPIRATION_META_KEY,
			'order'          => 'ASC',
			'meta_query'     => [
				'relation' => 'AND',
				[
					'key'   => FS_EXPIRATION_ENABLED_KEY,
					'value' => '1',
				],
				[
					'key'     => FS_EXPIRATION_META_KEY,
					'compare' => '!=',
					'value'   => '',
				],
			],
		]);
	}

	$pinned_pages = [];
	if (defined('FS_PIN_TO_DASHBOARD_META') && function_exists('fs_pin_to_dashboard_post_types')) {
		$pin_types = fs_pin_to_dashboard_post_types();
		if ($pin_types !== []) {
			$pinned_pages = get_posts([
				'post_type' => $pin_types,
				'post_status' => 'publish',
				'posts_per_page' => -1,
				'orderby' => 'title',
				'order' => 'ASC',
				'meta_query' => [
					[
						'key' => FS_PIN_TO_DASHBOARD_META,
						'value' => '1',
						'compare' => '=',
					],
				],
			]);
		}
	}
?>
	<div class="fs-dashboard__panel">

		<h2 class="fs-dashboard__title">FromScratch</h2>

		<p class="fs-dashboard__description">
			A developer-first foundation built for flexibility and control – crafted with care by <a href="https://stephanwagner.me" target="_blank" rel="noopener">Stephan Wagner</a> from <a href="https://bytesandstripes.com/en" target="_blank" rel="noopener">bytes and stripes</a>.
		</p>

		<div class="fs-dashboard__sections">

			<div class="fs-dashboard__section -links">
				<ul>
					<li><a href="<?= esc_url(admin_url('options-general.php?page=fs-theme-settings')) ?>"><?= esc_html__('Theme settings', 'fromscratch') ?></a></li>
					<?php if ($is_developer) : ?>
						<li><a href="<?= esc_url(admin_url('options-general.php?page=fs-developer-settings')) ?>"><?= esc_html__('Developer settings', 'fromscratch') ?></a></li>
					<?php endif; ?>
					<li><a href="<?= esc_url(admin_url('post-new.php?post_type=page')) ?>"><?= esc_html__('Create page', 'fromscratch') ?></a></li>
				</ul>
			</div>

			<div class="fs-dashboard__section -stats" data-fs-dashboard-stats data-fs-stats-cached="<?= $matomo_stats_cached ? '1' : '0' ?>">
				<strong><?= esc_html__('Today', 'fromscratch') ?>:</strong>
				<span data-fs-stat="today"><?= esc_html($matomo_today_html) ?></span><br>
				<strong><?= esc_html__('Yesterday', 'fromscratch') ?>:</strong>
				<span data-fs-stat="yesterday"><?= esc_html($matomo_yesterday_html) ?></span><br>
				<a href="<?= esc_url($stats_url) ?>"><?= esc_html__('Open analytics', 'fromscratch') ?></a>
			</div>

			<?php if (!empty($pinned_pages)) : ?>
				<div class="fs-dashboard__section -pinned">
					<strong><?= esc_html__('Pinned', 'fromscratch') ?></strong>
					<ul>
						<?php foreach ($pinned_pages as $pinned) : ?>
							<li>
								<a href="<?= esc_url(get_permalink($pinned)) ?>"><?= esc_html(get_the_title($pinned) ?: __('(no title)', 'fromscratch')) ?></a>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

		</div>

		<?php if (!empty($scheduled)) : ?>
			<div class="notice inline" style="margin: 0 0 1em 0;">
				<p><strong><?= esc_html__('Scheduled posts', 'fromscratch') ?></strong></p>
				<ul style="margin: 0 0 0 16px;">
					<?php foreach ($scheduled as $item) : ?>
						<li>
							<a href="<?= esc_url(get_edit_post_link((int) $item->ID)) ?>"><?= esc_html(get_the_title((int) $item->ID) ?: __('(no title)', 'fromscratch')) ?></a>
							<span style="color:#646970;"> – <?= esc_html(get_date_from_gmt((string) $item->post_date_gmt, get_option('date_format') . ' ' . get_option('time_format'))) ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

		<?php if (!empty($expiring_published)) : ?>
			<div class="notice inline" style="margin: 0 0 1em 0;">
				<p><strong><?= esc_html__('Published with expiration', 'fromscratch') ?></strong></p>
				<ul style="margin: 0 0 0 16px;">
					<?php
					foreach ($expiring_published as $item) :
						$exp_raw = get_post_meta((int) $item->ID, FS_EXPIRATION_META_KEY, true);
						$exp_label = is_string($exp_raw) ? $exp_raw : '';
						if ($exp_raw !== '' && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $exp_raw)) {
							$tz = function_exists('wp_timezone') ? wp_timezone() : new \DateTimeZone(wp_timezone_string() ?: 'UTC');
							$dt = \DateTimeImmutable::createFromFormat('Y-m-d H:i', $exp_raw, $tz);
							if ($dt instanceof \DateTimeImmutable) {
								$exp_label = wp_date(get_option('date_format') . ' ' . get_option('time_format'), $dt->getTimestamp());
							}
						}
						?>
						<li>
							<a href="<?= esc_url(get_edit_post_link((int) $item->ID)) ?>"><?= esc_html(get_the_title((int) $item->ID) ?: __('(no title)', 'fromscratch')) ?></a>
							<?php if ($exp_label !== '') : ?>
								<span style="color:#646970;"> – <?= esc_html(sprintf(__('expires %s', 'fromscratch'), $exp_label)) ?></span>
							<?php endif; ?>
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
							<code><?php echo esc_html($ip); ?></code> – <?php echo (int) $attempts; ?> <?php echo esc_html(_n('attempt', 'attempts', $attempts, 'fromscratch')); ?>
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

		<p class="fs-dashboard__description">

		</p>

	</div>
<?php
}
add_action('welcome_panel', 'fs_dashboard_panel');

/**
 * Load Matomo visit counts after dashboard paint (non-blocking).
 */
function fs_dashboard_enqueue_matomo_stats(string $hook_suffix): void
{
	if ($hook_suffix !== 'index.php') {
		return;
	}
	wp_register_script('fs-dashboard-matomo', false, [], null, true);
	wp_enqueue_script('fs-dashboard-matomo');
	wp_localize_script('fs-dashboard-matomo', 'fsDashboardMatomo', [
		'ajaxUrl'         => admin_url('admin-ajax.php'),
		'nonce'           => wp_create_nonce('fs_dashboard_matomo_stats'),
		'pollIntervalMs'  => HOUR_IN_SECONDS * 1000,
	]);
	$inline = <<<'JS'
(function () {
	document.addEventListener('DOMContentLoaded', function () {
		var cfg = typeof fsDashboardMatomo !== 'undefined' ? fsDashboardMatomo : null;
		var wrap = document.querySelector('[data-fs-dashboard-stats]');
		if (!wrap || !cfg) {
			return;
		}
		var pollMs = cfg.pollIntervalMs || 3600000;

		function applyStats(res) {
			if (!res || !res.success || !res.data) {
				return;
			}
			var t = wrap.querySelector('[data-fs-stat="today"]');
			var y = wrap.querySelector('[data-fs-stat="yesterday"]');
			if (t && res.data.today != null) {
				t.textContent = res.data.today;
			}
			if (y && res.data.yesterday != null) {
				y.textContent = res.data.yesterday;
			}
			wrap.setAttribute('data-fs-stats-cached', '1');
		}

		function fetchStats() {
			var params = new URLSearchParams();
			params.append('action', 'fs_dashboard_matomo_stats');
			params.append('nonce', cfg.nonce);
			return fetch(cfg.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
				body: params.toString()
			})
				.then(function (r) {
					return r.json();
				})
				.then(applyStats);
		}

		if (wrap.getAttribute('data-fs-stats-cached') !== '1') {
			fetchStats();
		}
		setInterval(fetchStats, pollMs);
	});
})();
JS;
	wp_add_inline_script('fs-dashboard-matomo', $inline);
}

add_action('admin_enqueue_scripts', 'fs_dashboard_enqueue_matomo_stats', 20);

/**
 * @return array{today: int, yesterday: int}|null Cached counts if transient is still valid.
 */
function fs_dashboard_matomo_stats_get_cached_counts(): ?array
{
	$c = get_transient(FS_DASHBOARD_MATOMO_STATS_CACHE_KEY);
	if (!is_array($c) || !array_key_exists('today', $c) || !array_key_exists('yesterday', $c)) {
		return null;
	}

	return [
		'today'     => (int) $c['today'],
		'yesterday' => (int) $c['yesterday'],
	];
}

/**
 * Load counts from Matomo and store for one hour (used by AJAX miss and hourly cron).
 *
 * @return array{today: int, yesterday: int}
 */
function fs_dashboard_matomo_stats_refresh_counts(): array
{
	$zero = ['today' => 0, 'yesterday' => 0];
	if (!function_exists('fs_dashboard_get_matomo_daily_visits')) {
		set_transient(FS_DASHBOARD_MATOMO_STATS_CACHE_KEY, $zero, HOUR_IN_SECONDS);
		return $zero;
	}

	$today_matomo = fs_dashboard_get_matomo_daily_visits(1);
	$yesterday_matomo = fs_dashboard_get_matomo_daily_visits(2);
	$today_visits = !empty($today_matomo) ? (int) ($today_matomo[0]['visits'] ?? 0) : 0;
	$yesterday_visits = !empty($yesterday_matomo) ? (int) ($yesterday_matomo[0]['visits'] ?? 0) : 0;

	$out = [
		'today'     => $today_visits,
		'yesterday' => $yesterday_visits,
	];
	set_transient(FS_DASHBOARD_MATOMO_STATS_CACHE_KEY, $out, HOUR_IN_SECONDS);

	return $out;
}

/** Hourly: refresh dashboard Matomo stats cache in the background. */
add_action('fs_dashboard_matomo_stats_hourly', 'fs_dashboard_matomo_stats_refresh_counts');

add_action('init', function (): void {
	if (wp_installing()) {
		return;
	}
	if (wp_next_scheduled('fs_dashboard_matomo_stats_hourly')) {
		return;
	}
	wp_schedule_event(time(), 'hourly', 'fs_dashboard_matomo_stats_hourly');
}, 30);

/**
 * AJAX: formatted visit lines for dashboard (Matomo). Reads 1h transient when possible.
 */
function fs_dashboard_ajax_matomo_stats(): void
{
	check_ajax_referer('fs_dashboard_matomo_stats', 'nonce');
	if (!current_user_can('read')) {
		wp_send_json_error(null, 403);
	}

	$counts = fs_dashboard_matomo_stats_get_cached_counts();
	if ($counts === null) {
		$counts = fs_dashboard_matomo_stats_refresh_counts();
	}

	wp_send_json_success(fs_dashboard_matomo_stats_format_lines($counts));
}

add_action('wp_ajax_fs_dashboard_matomo_stats', 'fs_dashboard_ajax_matomo_stats');
