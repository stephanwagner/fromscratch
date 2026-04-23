<?php

defined('ABSPATH') || exit;

/**
 * Output Matomo tracking script on frontend when enabled.
 */
add_action('wp_head', function (): void {
	if (is_admin()) {
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
