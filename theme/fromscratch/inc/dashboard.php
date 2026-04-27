<?php

defined('ABSPATH') || exit;

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
	wp_add_dashboard_widget(
		'fs_dashboard_panel_widget',
		__('FromScratch', 'fromscratch'),
		'fs_dashboard_panel_widget_render',
		null,
		null,
		'normal',
		'high'
	);
}, 20);

/**
 * Keep custom dashboard panel in first position by default.
 */
add_filter('get_user_option_meta-box-order_dashboard', function ($value, $user_id) {
	if (!is_user_logged_in()) {
		return $value;
	}

	$widget_id = 'fs_dashboard_panel_widget';
	$default_order = [
		'normal' => $widget_id,
		'side'   => '',
	];

	if (is_array($value) && $value !== []) {
		return $value;
	}

	if (is_string($value) && $value !== '') {
		return $value;
	}

	if ($value === false || $value === null || $value === '' || $value === []) {
		return $default_order;
	}

	// Respect each user's saved drag/drop layout once it exists.
	return $value;
}, 10, 2);

/**
 * Add a custom welcome panel
 */
function fs_dashboard_panel()
{
	$is_developer = function_exists('fs_is_developer_user') && fs_is_developer_user((int) get_current_user_id());
	$is_admin = current_user_can('manage_options');
	$can_view_widget_notices = $is_admin || $is_developer;
	$can_view_theme_settings = $is_admin || $is_developer;
	$can_view_stats = function_exists('fs_dashboard_can_access_statistics') && fs_dashboard_can_access_statistics();
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

		<p class="fs-dashboard__description">
			A developer-first foundation built for flexibility and control.<br>
			Crafted with care by <a href="https://stephanwagner.me" target="_blank" rel="noopener">Stephan Wagner</a> from <a href="https://bytesandstripes.com/en" target="_blank" rel="noopener">bytes and stripes</a>.
		</p>

		<?php
		if ($can_view_widget_notices && fs_theme_feature_enabled('blocked_ips')) {
			$suspicious_ips = function_exists('fs_blocked_ips_suspicious_list') ? fs_blocked_ips_suspicious_list() : [];
			if (!empty($suspicious_ips)) :
		?>
				<div class="notice notice-warning inline" style="margin: 16px 0;">
					<p style="margin-bottom: 6px"><strong><?php esc_html_e('Suspicious login attempts', 'fromscratch'); ?></strong></p>
					<p style="margin-top: 0"><?php esc_html_e('The following IPs exceeded the configured threshold.', 'fromscratch'); ?></p>
					<ul style="list-style: none; padding-left: 0; margin: 8px 0;">
						<?php foreach ($suspicious_ips as $ip => $row) :
							$attempts = (int) ($row['attempts'] ?? 0);
						?>
							<li style="margin-bottom: 8px">
								<code><?php echo esc_html($ip); ?></code> – <?php echo (int) $attempts; ?> <?php echo esc_html(_n('attempt', 'attempts', $attempts, 'fromscratch')); ?>
							</li>
						<?php endforeach; ?>
					</ul>
					<p style="margin-top: 8px;"><a href="<?php echo esc_url($security_url); ?>#fs-security-blocked-ips"><?php esc_html_e('Manage in Developer → Security', 'fromscratch'); ?></a></p>
				</div>
		<?php
			endif;
		}
		?>

		<?php if ($can_view_widget_notices && (int) get_option('blog_public', 1) === 0) : ?>
			<div class="notice notice-warning inline" style="margin: 16px 0;">
				<p><strong><?php esc_html_e('Search engines are asked not to index this site.', 'fromscratch'); ?></strong></p>
				<p style="margin-top: -4px;"><a href="<?php echo esc_url($system_url); ?>"><?php esc_html_e('Enable search engine indexing in Developer → System', 'fromscratch'); ?></a></p>
			</div>
		<?php endif; ?>

		<?php if ($can_view_widget_notices && get_option('fromscratch_site_password_protection') === '1') : ?>
			<div class="notice notice-info inline" style="margin: 16px 0;">
				<p><strong><?php esc_html_e('Password protection is active.', 'fromscratch'); ?></strong></p>
				<p style="margin-top: -4px;"><a href="<?php echo esc_url($security_url); ?>"><?php esc_html_e('Manage in Developer → Security', 'fromscratch'); ?></a></p>
			</div>
		<?php endif; ?>

		<?php if ($can_view_widget_notices && get_option('fromscratch_maintenance_mode') === '1') : ?>
			<div class="notice notice-info inline" style="margin: 16px 0;">
				<p><strong><?php esc_html_e('Maintenance mode is active.', 'fromscratch'); ?></strong></p>
				<p style="margin-top: -4px;"><a href="<?php echo esc_url($security_url); ?>"><?php esc_html_e('Manage in Developer → Security', 'fromscratch'); ?></a></p>
			</div>
		<?php endif; ?>

		<div class="fs-dashboard__sections -flex">

			<div class="fs-dashboard__section -links">
				<div class="fs-dashboard__section-title"><?= esc_html__('Quick links', 'fromscratch') ?></div>
				<ul class="fs-dashboard__section-list -limit">
					<?php if ($can_view_theme_settings) : ?>
						<li><a href="<?= esc_url(admin_url('options-general.php?page=fs-theme-settings')) ?>"><?= esc_html__('Theme settings', 'fromscratch') ?></a></li>
					<?php endif; ?>
					<?php if ($is_developer) : ?>
						<li><a href="<?= esc_url(admin_url('options-general.php?page=fs-developer-settings')) ?>"><?= esc_html__('Developer settings', 'fromscratch') ?></a></li>
					<?php endif; ?>
					<?php if (fs_theme_feature_enabled('blogs') && current_user_can('edit_posts')) : ?>
						<li><a href="<?= esc_url(admin_url('post-new.php?post_type=post')) ?>"><?= esc_html__('Create post', 'fromscratch') ?></a></li>
					<?php endif; ?>
					<?php if (current_user_can('edit_pages')) : ?>
						<li><a href="<?= esc_url(admin_url('post-new.php?post_type=page')) ?>"><?= esc_html__('Create page', 'fromscratch') ?></a></li>
					<?php endif; ?>
				</ul>
			</div>

			<?php if (fs_theme_feature_enabled('matomo') && $can_view_stats) : ?>
				<div class="fs-dashboard__section -stats" data-fs-dashboard-stats data-fs-stats-cached="<?= $matomo_stats_cached ? '1' : '0' ?>">
					<div class="fs-dashboard__section-title"><?= esc_html__('Analytics', 'fromscratch') ?></div>
					<ul class="fs-dashboard__section-list -limit">
						<li>
							<strong><?= esc_html__('Today', 'fromscratch') ?>:</strong>
							<span data-fs-stat="today"><?= esc_html($matomo_today_html) ?></span>
						</li>
						<li>
							<strong><?= esc_html__('Yesterday', 'fromscratch') ?>:</strong>
							<span data-fs-stat="yesterday"><?= esc_html($matomo_yesterday_html) ?></span>
						</li>
						<li>
							<a href="<?= esc_url($stats_url) ?>"><?= esc_html__('Open analytics', 'fromscratch') ?></a>
						</li>
					</ul>
				</div>
			<?php endif; ?>
		</div>

		<?php if (!empty($pinned_pages)) : ?>
			<div class="fs-dashboard__section -pinned -margin">
				<div class="fs-dashboard__section-title"><?= esc_html__('Pinned posts', 'fromscratch') ?></div>
				<table class="fs-dashboard__section-table">
					<?php foreach ($pinned_pages as $pinned) : ?>
						<?php
						$pinned_post_type = get_post_type((int) $pinned->ID);
						$pinned_post_type_object = is_string($pinned_post_type) ? get_post_type_object($pinned_post_type) : null;
						if ($pinned_post_type === 'post') {
							$pinned_type_label = __('Post', 'fromscratch');
						} elseif ($pinned_post_type === 'page') {
							$pinned_type_label = __('Page', 'fromscratch');
						} else {
							$pinned_type_label = $pinned_post_type_object instanceof WP_Post_Type
								? (string) __($pinned_post_type_object->labels->singular_name ?: $pinned_post_type_object->label, 'fromscratch')
								: __('Content', 'fromscratch');
						}
						?>
						<tr>
							<td class="fs-dashboard__section-cell -type">
								<?= esc_html($pinned_type_label) ?>
							</td>
							<td class="fs-dashboard__section-cell -preview">
								<a href="<?= esc_url(get_permalink($pinned->ID)) ?>" target="_blank"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor"><path d="M200-120q-33 0-56.5-23.5T120-200v-560q0-33 23.5-56.5T200-840h240q17 0 28.5 11.5T480-800q0 17-11.5 28.5T440-760H200v560h560v-240q0-17 11.5-28.5T800-480q17 0 28.5 11.5T840-440v240q0 33-23.5 56.5T760-120H200Zm560-584L416-360q-11 11-28 11t-28-11q-11-11-11-28t11-28l344-344H600q-17 0-28.5-11.5T560-800q0-17 11.5-28.5T600-840h200q17 0 28.5 11.5T840-800v200q0 17-11.5 28.5T800-560q-17 0-28.5-11.5T760-600v-104Z"/></svg></a>
							</td>
							<td class="fs-dashboard__section-cell -title">
								<a href="<?= esc_url(get_edit_post_link($pinned->ID)) ?>"><?= esc_html(get_the_title($pinned->ID) ?: __('(no title)', 'fromscratch')) ?></a><br>
							</td>
						</tr>
					<?php endforeach; ?>
				</table>
			</div>
		<?php endif; ?>

		<?php if (!empty($scheduled)) : ?>
			<div class="fs-dashboard__section -margin">
				<div class="fs-dashboard__section-title"><?= esc_html__('Scheduled posts', 'fromscratch') ?></div>
				<table class="fs-dashboard__section-table">
					<?php foreach ($scheduled as $item) : ?>
						<tr>
							<td class="fs-dashboard__section-cell -date">
								<?= esc_html(get_date_from_gmt((string) $item->post_date_gmt, get_option('date_format') . ' ' . get_option('time_format'))) ?>
							</td>
							<td class="fs-dashboard__section-cell -preview">
								<a href="<?= esc_url(get_permalink($pinned->ID)) ?>" target="_blank"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor"><path d="M200-120q-33 0-56.5-23.5T120-200v-560q0-33 23.5-56.5T200-840h240q17 0 28.5 11.5T480-800q0 17-11.5 28.5T440-760H200v560h560v-240q0-17 11.5-28.5T800-480q17 0 28.5 11.5T840-440v240q0 33-23.5 56.5T760-120H200Zm560-584L416-360q-11 11-28 11t-28-11q-11-11-11-28t11-28l344-344H600q-17 0-28.5-11.5T560-800q0-17 11.5-28.5T600-840h200q17 0 28.5 11.5T840-800v200q0 17-11.5 28.5T800-560q-17 0-28.5-11.5T760-600v-104Z"/></svg></a>
							</td>
							<td class="fs-dashboard__section-cell -title">
								<a href="<?= esc_url(get_edit_post_link((int) $item->ID)) ?>"><?= esc_html(get_the_title((int) $item->ID) ?: __('(no title)', 'fromscratch')) ?></a>
							</td>
						</tr>
					<?php endforeach; ?>
				</table>
			</div>
		<?php endif; ?>

		<?php if (!empty($expiring_published)) : ?>
			<div class="fs-dashboard__section -margin">
				<div class="fs-dashboard__section-title"><?= esc_html__('Expiring posts', 'fromscratch') ?></div>
				<table class="fs-dashboard__section-table">
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
						<tr>
							<td class="fs-dashboard__section-cell -date">
								<?= $exp_label ?>
							</td>
							<td class="fs-dashboard__section-cell -preview">
								<a href="<?= esc_url(get_permalink($pinned->ID)) ?>" target="_blank"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor"><path d="M200-120q-33 0-56.5-23.5T120-200v-560q0-33 23.5-56.5T200-840h240q17 0 28.5 11.5T480-800q0 17-11.5 28.5T440-760H200v560h560v-240q0-17 11.5-28.5T800-480q17 0 28.5 11.5T840-440v240q0 33-23.5 56.5T760-120H200Zm560-584L416-360q-11 11-28 11t-28-11q-11-11-11-28t11-28l344-344H600q-17 0-28.5-11.5T560-800q0-17 11.5-28.5T600-840h200q17 0 28.5 11.5T840-800v200q0 17-11.5 28.5T800-560q-17 0-28.5-11.5T760-600v-104Z"/></svg></a>
							</td>
							<td class="fs-dashboard__section-cell -title">
								<a href="<?= esc_url(get_edit_post_link((int) $item->ID)) ?>"><?= esc_html(get_the_title((int) $item->ID) ?: __('(no title)', 'fromscratch')) ?></a>
							</td>
						</tr>
					<?php endforeach; ?>
				</table>
			</div>
		<?php endif; ?>

	</div>
<?php
}
/**
 * Dashboard widget renderer.
 */
function fs_dashboard_panel_widget_render(): void
{
	echo '<div class="fs-dashboard__widget">';
	fs_dashboard_panel();
	echo '</div>';
}

/**
 * Load Matomo visit counts after dashboard paint (non-blocking).
 */
function fs_dashboard_enqueue_matomo_stats(string $hook_suffix): void
{
	if ($hook_suffix !== 'index.php') {
		return;
	}
	if (!(function_exists('fs_dashboard_can_access_statistics') && fs_dashboard_can_access_statistics())) {
		return;
	}
	wp_register_script('fs-dashboard-matomo', false, [], null, true);
	wp_enqueue_script('fs-dashboard-matomo');
	wp_localize_script('fs-dashboard-matomo', 'fsDashboardMatomo', [
		'ajaxUrl'            => admin_url('admin-ajax.php'),
		'nonce'              => wp_create_nonce('fs_dashboard_matomo_stats'),
		'pollIntervalMs'     => HOUR_IN_SECONDS * 1000,
		'pollPendingMs'      => 2500,
		'pollPendingMaxPolls' => 48,
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
		var pendMs = cfg.pollPendingMs || 2500;
		var pendMax = cfg.pollPendingMaxPolls || 48;
		var pendTimer = null;
		var pendPolls = 0;

		function fetchStatsBody() {
			var params = new URLSearchParams();
			params.append('action', 'fs_dashboard_matomo_stats');
			params.append('nonce', cfg.nonce);
			return fetch(cfg.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
				body: params.toString()
			}).then(function (r) {
				return r.json();
			});
		}

		function applyStats(res) {
			if (!res || !res.success || !res.data) {
				return false;
			}
			var d = res.data;
			var t = wrap.querySelector('[data-fs-stat="today"]');
			var y = wrap.querySelector('[data-fs-stat="yesterday"]');
			if (d.pending) {
				if (t && d.today != null) {
					t.textContent = d.today;
				}
				if (y && d.yesterday != null) {
					y.textContent = d.yesterday;
				}
				return false;
			}
			if (t && d.today != null) {
				t.textContent = d.today;
			}
			if (y && d.yesterday != null) {
				y.textContent = d.yesterday;
			}
			wrap.setAttribute('data-fs-stats-cached', '1');
			return true;
		}

		function stopPendingPoll() {
			if (pendTimer) {
				clearInterval(pendTimer);
				pendTimer = null;
			}
			pendPolls = 0;
		}

		function startPendingPoll() {
			if (pendTimer) {
				return;
			}
			pendPolls = 0;
			pendTimer = setInterval(function () {
				pendPolls += 1;
				if (pendPolls > pendMax) {
					stopPendingPoll();
					return;
				}
				fetchStatsBody().then(function (res) {
					if (applyStats(res)) {
						stopPendingPoll();
					}
				});
			}, pendMs);
		}

		function fetchStats() {
			fetchStatsBody().then(function (res) {
				var ok = applyStats(res);
				if (!ok && res && res.success && res.data && res.data.pending) {
					startPendingPoll();
				}
			});
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
	$c = get_transient(FS_MATOMO_DASHBOARD_VISITS_TRANSIENT);
	if (!is_array($c) || !array_key_exists('today', $c) || !array_key_exists('yesterday', $c)) {
		return null;
	}

	return [
		'today'     => (int) $c['today'],
		'yesterday' => (int) $c['yesterday'],
	];
}

/**
 * Queue a wp-cron run to fetch all Matomo statistics (does not block the current HTTP response).
 */
function fs_dashboard_matomo_stats_schedule_background_refresh(): void
{
	if (!function_exists('fs_matomo_statistics_refresh_full') || !defined('FS_MATOMO_BACKGROUND_REFRESH_LOCK')) {
		return;
	}
	if (get_transient(FS_MATOMO_BACKGROUND_REFRESH_LOCK)) {
		if (function_exists('spawn_cron')) {
			spawn_cron();
		}

		return;
	}
	set_transient(FS_MATOMO_BACKGROUND_REFRESH_LOCK, '1', 90);
	wp_schedule_single_event(time(), 'fs_matomo_background_statistics_refresh');
	if (function_exists('spawn_cron')) {
		spawn_cron();
	}
}

/** Stop legacy hourly Matomo cron (replaced by on-demand full refresh). */
add_action('init', function (): void {
	if (wp_installing()) {
		return;
	}
	while (($t = wp_next_scheduled('fs_dashboard_matomo_stats_hourly')) !== false) {
		wp_unschedule_event((int) $t, 'fs_dashboard_matomo_stats_hourly');
	}
}, 25);

/**
 * AJAX: formatted visit lines for dashboard (Matomo). Cached 1 hour; cache miss queues a cron refresh (non-blocking).
 */
function fs_dashboard_ajax_matomo_stats(): void
{
	check_ajax_referer('fs_dashboard_matomo_stats', 'nonce');
	if (!(function_exists('fs_dashboard_can_access_statistics') && fs_dashboard_can_access_statistics())) {
		wp_send_json_error(null, 403);
	}

	$counts = fs_dashboard_matomo_stats_get_cached_counts();
	if ($counts !== null) {
		wp_send_json_success(fs_dashboard_matomo_stats_format_lines($counts));

		return;
	}

	fs_dashboard_matomo_stats_schedule_background_refresh();
	$dash = html_entity_decode('&#8211;', ENT_QUOTES | ENT_HTML5, 'UTF-8');
	wp_send_json_success([
		'pending' => true,
		'today' => $dash,
		'yesterday' => $dash,
	]);
}

add_action('wp_ajax_fs_dashboard_matomo_stats', 'fs_dashboard_ajax_matomo_stats');
