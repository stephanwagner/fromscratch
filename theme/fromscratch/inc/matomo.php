<?php

defined('ABSPATH') || exit;

/**
 * Whether to skip injecting the Matomo tracker (production/staging only; not typical dev URLs).
 *
 * Skips when: WP environment is local/development; Host ends with :8888 / :8890; SERVER_PORT those values;
 * hostname localhost / 127.0.0.1 / *.local; Host is a private/reserved IPv4 (e.g. 192.168.x).
 *
 * Filter {@see 'fs_matomo_skip_frontend_tracker'} receives the computed boolean — return true to force skip,
 * false to force load (e.g. test tracker on a dev box).
 */
function fs_matomo_skip_frontend_tracker(): bool
{
	$skip = false;

	if (function_exists('wp_get_environment_type')) {
		$env = wp_get_environment_type();
		if (in_array($env, ['local', 'development'], true)) {
			$skip = true;
		}
	}

	$host_full = isset($_SERVER['HTTP_HOST']) ? strtolower((string) $_SERVER['HTTP_HOST']) : '';
	if ($host_full !== '' && preg_match('/:(8888|8890)\s*$/', $host_full)) {
		$skip = true;
	}

	$server_port = isset($_SERVER['SERVER_PORT']) ? (int) $_SERVER['SERVER_PORT'] : 0;
	if (in_array($server_port, [8888, 8890], true)) {
		$skip = true;
	}

	$host_only = preg_replace('/:\d+$/', '', $host_full);
	if ($host_only === 'localhost' || $host_only === '127.0.0.1') {
		$skip = true;
	}
	if ($host_only !== '' && preg_match('/\.local$/', $host_only)) {
		$skip = true;
	}

	if ($host_only !== '' && filter_var($host_only, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
		if (!filter_var($host_only, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
			$skip = true;
		}
	}

	return (bool) apply_filters('fs_matomo_skip_frontend_tracker', $skip);
}

/**
 * Output Matomo tracking script on frontend when enabled.
 */
add_action('wp_head', function (): void {
	if (is_admin()) {
		return;
	}
	if (fs_matomo_skip_frontend_tracker()) {
		return;
	}
	if (!function_exists('fs_theme_feature_enabled') || !fs_theme_feature_enabled('matomo')) {
		return;
	}

	$matomo_url = trim((string) get_option('fromscratch_matomo_url', ''));
	$site_id = (int) get_option('fromscratch_matomo_site_id', 1);
	$token = trim((string) get_option('fromscratch_matomo_token_auth', ''));
	$custom_js = trim((string) get_option('fromscratch_matomo_custom_js', ''));

	if ($site_id <= 0) {
		$site_id = 1;
	}

	if ($matomo_url === '') {
		return;
	}

	$matomo_url = trailingslashit($matomo_url);
	$tracker_url = $matomo_url . 'matomo.php';
	if ($token !== '') {
		$tracker_url .= '?token_auth=' . rawurlencode($token);
	}
	?>
	<script>
		var _paq = window._paq = window._paq || [];
		<?php if ($custom_js !== '') : ?>
		<?php
		$lines = preg_split('/\r\n|\r|\n/', $custom_js);
		if (is_array($lines)) {
			foreach ($lines as $line) {
				$line = trim((string) $line);
				if ($line === '') {
					continue;
				}
				$line = rtrim($line, ';');
				echo $line . ";\n";
			}
		}
		?>
		<?php endif; ?>
		_paq.push(['setTrackerUrl', <?= wp_json_encode($tracker_url) ?>]);
		_paq.push(['setSiteId', <?= wp_json_encode((string) $site_id) ?>]);
		_paq.push(['trackPageView']);
		_paq.push(['enableLinkTracking']);
		(function() {
			var u = <?= wp_json_encode($matomo_url) ?>;
			var d = document, g = d.createElement('script'), s = d.getElementsByTagName('script')[0];
			g.async = true;
			g.src = u + 'matomo.js';
			s.parentNode.insertBefore(g, s);
		})();
	</script>
	<noscript><img src="<?= esc_url($tracker_url) ?>&amp;idsite=<?= esc_attr((string) $site_id) ?>&amp;rec=1" style="border:0;" alt=""></noscript>
	<?php
}, 20);
