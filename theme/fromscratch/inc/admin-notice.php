<?php

/**
 * Themed one-shot toast (user meta, no query args). Not a core .notice, so admin common.js
 * does not relocate it. Rendered in admin_footer and wp_footer (logged-in front + admin bar purge).
 */

defined('ABSPATH') || exit;

const FS_ADMIN_NOTICE_META = 'fs_admin_notice';

/**
 * Queue a one-shot message for a user, shown on the next full page load (wp-admin or front when logged in).
 *
 * @param int    $user_id WordPress user ID.
 * @param string $type    success|error|warning|info
 * @param string $message Plain text (stored stripped; use translated strings for i18n).
 */
function fs_admin_notice(int $user_id, string $type, string $message): void
{
	if ($user_id < 1) {
		return;
	}
	$message = wp_strip_all_tags($message, true);
	$message = trim($message);
	if ($message === '') {
		return;
	}
	$type = in_array($type, ['success', 'error', 'warning', 'info'], true) ? $type : 'info';
	update_user_meta($user_id, FS_ADMIN_NOTICE_META, [
		'type'    => $type,
		'message' => $message,
	]);
}

/**
 * @param array{message?:string,type?:string} $data
 * @return array{message: string, type: string}|null
 */
function fs_admin_notice_normalize(array $data): ?array
{
	$type = (string) ($data['type'] ?? 'info');
	$type = in_array($type, ['success', 'error', 'warning', 'info'], true) ? $type : 'info';
	$message = isset($data['message']) ? trim(wp_strip_all_tags((string) $data['message'], true)) : '';
	if ($message === '') {
		return null;
	}

	return ['type' => $type, 'message' => $message];
}

/**
 * Print the toast if user meta is set; clears meta before output.
 */
function fs_admin_notice_maybe_output(): void
{
	if (!is_user_logged_in()) {
		return;
	}
	$user_id = (int) get_current_user_id();
	$raw     = get_user_meta($user_id, FS_ADMIN_NOTICE_META, true);
	if (!is_array($raw)) {
		if ($raw !== false && $raw !== '') {
			delete_user_meta($user_id, FS_ADMIN_NOTICE_META);
		}

		return;
	}
	$data = fs_admin_notice_normalize($raw);
	delete_user_meta($user_id, FS_ADMIN_NOTICE_META);
	if ($data === null) {
		return;
	}
	$type    = $data['type'];
	$message = $data['message'];
	$label = esc_attr__('Dismiss notice', 'fromscratch');
	$top   = is_admin() ? '40px' : (is_admin_bar_showing() ? '52px' : '1.25rem');
	$topMobile = is_admin() ? '46px' : (is_admin_bar_showing() ? '58px' : '1.25rem');
	?>
	<style>
	#fs-admin-notice-root.fs-admin-notice { position: fixed; z-index: 100050; left: 20px; right: 20px; top: <?= esc_attr($top) ?>; max-width: 42rem; margin: 0 auto; box-shadow: 0 1px 1px rgba(0,0,0,.04), 0 1px 3px rgba(0,0,0,.1); border-radius: 1px; border-left: 4px solid var(--fs-an-border, #50575e); background: #fff; color: #1d2327; font-size: 13px; line-height: 1.5; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; transform: translateY(calc(-100% - 1rem)); opacity: 0; pointer-events: none; transition: transform 0.38s cubic-bezier(0.2, 0.8, 0.2, 1), opacity 0.32s ease; will-change: transform, opacity; }
	#fs-admin-notice-root.fs-admin-notice.is-open { transform: translateY(0); opacity: 1; pointer-events: auto; }
	#fs-admin-notice-root.fs-admin-notice.is-closed { transform: translateY(calc(-100% - 1rem)) !important; opacity: 0 !important; pointer-events: none; transition-duration: 0.28s; }
	#fs-admin-notice-root.fs-admin-notice--success { --fs-an-border: #00a32a; background: #edfaef; }
	#fs-admin-notice-root.fs-admin-notice--error { --fs-an-border: #d63638; background: #fcf0f1; }
	#fs-admin-notice-root.fs-admin-notice--warning { --fs-an-border: #dba617; background: #fcf9e8; }
	#fs-admin-notice-root.fs-admin-notice--info { --fs-an-border: #72aee6; background: #f0f6fc; }
	#fs-admin-notice-root .fs-admin-notice__inner { display: flex; align-items: flex-start; gap: 0.5rem; padding: 0.5rem 0.75rem 0.5rem 0.5rem; }
	#fs-admin-notice-root .fs-admin-notice__message { flex: 1; margin: 0; padding: 0.1rem 0; }
	#fs-admin-notice-root .fs-admin-notice__dismiss { flex-shrink: 0; min-width: 1.5rem; height: 1.5rem; margin: 0.1rem 0 0; padding: 0; border: 0; background: transparent; color: #646970; cursor: pointer; font-size: 1.1rem; line-height: 1; border-radius: 2px; }
	#fs-admin-notice-root .fs-admin-notice__dismiss:hover, #fs-admin-notice-root .fs-admin-notice__dismiss:focus { color: #1d2327; outline: 1px solid currentColor; outline-offset: 0; }
	@media (max-width: 782px) { #fs-admin-notice-root.fs-admin-notice { top: <?= esc_attr($topMobile) ?>; } }
	</style>
	<div id="fs-admin-notice-root" class="fs-admin-notice fs-admin-notice--<?= esc_attr($type) ?>" role="status"<?= 'error' === $type ? ' aria-live="assertive"' : ' aria-live="polite"'; ?>>
		<div class="fs-admin-notice__inner">
			<p class="fs-admin-notice__message"><?= esc_html($message) ?></p>
			<button type="button" class="fs-admin-notice__dismiss" aria-label="<?= $label ?>">&times;</button>
		</div>
	</div>
	<script>
	(function () {
		var n = document.getElementById('fs-admin-notice-root');
		if (!n) { return; }
		var btn = n.querySelector('.fs-admin-notice__dismiss');
		var autoTimer = null;
		var done = false;
		function removeNode() {
			if (n && n.parentNode) { n.parentNode.removeChild(n); }
		}
		function close() {
			if (done) { return; }
			done = true;
			if (autoTimer !== null) { clearTimeout(autoTimer); autoTimer = null; }
			n.classList.remove('is-open');
			n.classList.add('is-closed');
			setTimeout(removeNode, 320);
		}
		function show() {
			requestAnimationFrame(function () {
				requestAnimationFrame(function () {
					n.classList.add('is-open');
					autoTimer = setTimeout(close, 6000);
				});
			});
		}
		if (document.readyState === 'complete' || document.readyState === 'interactive') { show(); }
		else { document.addEventListener('DOMContentLoaded', show, { once: true }); }
		if (btn) { btn.addEventListener('click', close); }
	})();
	</script>
	<?php
}

add_action('admin_footer', 'fs_admin_notice_maybe_output', 1);
add_action('wp_footer', 'fs_admin_notice_maybe_output', 1);
