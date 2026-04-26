<?php

/**
 * Themed one-shot toast (user meta, no query args). Not a core .notice, so admin common.js
 * does not relocate it. Rendered in admin_footer and wp_footer (logged-in front + admin bar purge).
 *
 * Same UI can be shown immediately via window.fsAdminNoticeShow(type, message) after admin assets print.
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
 * Queue a toast for the current user (next full page load).
 */
function fs_admin_notice_current_user(string $type, string $message): void
{
	$uid = (int) get_current_user_id();
	if ($uid < 1) {
		return;
	}
	fs_admin_notice($uid, $type, $message);
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
 * CSS variables for toast position (admin vs front).
 *
 * @return array{top: string, top_mobile: string}
 */
function fs_admin_notice_position_vars(): array
{
	if (is_admin()) {
		return ['top' => '40px', 'top_mobile' => '46px'];
	}
	return [
		'top'        => is_admin_bar_showing() ? '52px' : '1.25rem',
		'top_mobile' => is_admin_bar_showing() ? '58px' : '1.25rem',
	];
}

/**
 * Print shared toast styles + window.fsAdminNoticeShow once per request (logged-in).
 */
function fs_admin_notice_print_client_assets(): void
{
	static $printed = false;
	if ($printed || !is_user_logged_in()) {
		return;
	}
	$printed = true;
	$pos = fs_admin_notice_position_vars();
	$dismiss = esc_attr__('Dismiss notice', 'fromscratch');
	?>
	<style id="fs-admin-notice--base">
	#fs-admin-notice-root.fs-admin-notice { position: fixed; z-index: 100050; left: 20px; right: 20px; top: <?= esc_attr($pos['top']) ?>; max-width: 42rem; margin: 0 auto; box-shadow: 0 1px 1px rgba(0,0,0,.04), 0 1px 3px rgba(0,0,0,.1); border-radius: 1px; border-left: 4px solid var(--fs-an-border, #50575e); background: #fff; color: #1d2327; font-size: 13px; line-height: 1.5; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; transform: translateY(calc(-100% - 1rem)); opacity: 0; pointer-events: none; transition: transform 0.38s cubic-bezier(0.2, 0.8, 0.2, 1), opacity 0.32s ease; will-change: transform, opacity; }
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
	@media (max-width: 782px) { #fs-admin-notice-root.fs-admin-notice { top: <?= esc_attr($pos['top_mobile']) ?>; } }
	</style>
	<script>
	(function () {
		var dismissLabel = <?= wp_json_encode($dismiss, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
		window.fsAdminNoticeShow = window.fsAdminNoticeShow || function (type, message) {
			var ok = { success: 1, error: 1, warning: 1, info: 1 };
			if (!ok[type]) {
				type = 'info';
			}
			message = String(message || '').replace(/^\s+|\s+$/g, '');
			if (!message) {
				return;
			}
			var existing = document.getElementById('fs-admin-notice-root');
			if (existing && existing.parentNode) {
				existing.parentNode.removeChild(existing);
			}
			var root = document.createElement('div');
			root.id = 'fs-admin-notice-root';
			root.className = 'fs-admin-notice fs-admin-notice--' + type;
			root.setAttribute('role', type === 'error' ? 'alert' : 'status');
			root.setAttribute('aria-live', type === 'error' ? 'assertive' : 'polite');
			var inner = document.createElement('div');
			inner.className = 'fs-admin-notice__inner';
			var p = document.createElement('p');
			p.className = 'fs-admin-notice__message';
			p.textContent = message;
			var btn = document.createElement('button');
			btn.type = 'button';
			btn.className = 'fs-admin-notice__dismiss';
			btn.setAttribute('aria-label', dismissLabel);
			btn.appendChild(document.createTextNode('\u00d7'));
			inner.appendChild(p);
			inner.appendChild(btn);
			root.appendChild(inner);
			document.body.appendChild(root);
			var autoTimer = null;
			var done = false;
			function removeNode() {
				if (root && root.parentNode) {
					root.parentNode.removeChild(root);
				}
			}
			function close() {
				if (done) {
					return;
				}
				done = true;
				if (autoTimer !== null) {
					clearTimeout(autoTimer);
					autoTimer = null;
				}
				root.classList.remove('is-open');
				root.classList.add('is-closed');
				setTimeout(removeNode, 320);
			}
			function show() {
				requestAnimationFrame(function () {
					requestAnimationFrame(function () {
						root.classList.add('is-open');
						autoTimer = setTimeout(close, 6000);
					});
				});
			}
			show();
			btn.addEventListener('click', close);
		};
	})();
	</script>
	<?php
}

/**
 * Print the toast if user meta is set; clears meta before output.
 */
function fs_admin_notice_maybe_output(): void
{
	if (!is_user_logged_in()) {
		return;
	}
	fs_admin_notice_print_client_assets();
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
	?>
	<script>
	(function () {
		if (typeof window.fsAdminNoticeShow === 'function') {
			window.fsAdminNoticeShow(<?= wp_json_encode($type, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>, <?= wp_json_encode($message, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>);
		}
	})();
	</script>
	<?php
}

add_action('admin_footer', 'fs_admin_notice_print_client_assets', 0);
add_action('wp_footer', 'fs_admin_notice_print_client_assets', 0);
add_action('admin_footer', 'fs_admin_notice_maybe_output', 1);
add_action('wp_footer', 'fs_admin_notice_maybe_output', 1);
