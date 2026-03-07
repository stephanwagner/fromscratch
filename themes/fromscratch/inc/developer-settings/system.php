<?php

defined('ABSPATH') || exit;

$fs_developer_tab = 'system';
$fs_developer_page_slug = fs_developer_settings_page_slug($fs_developer_tab);

add_action('admin_menu', function () use ($fs_developer_tab, $fs_developer_page_slug) {
	if (!current_user_can('manage_options')) {
		return;
	}
	if (!function_exists('fs_is_developer_user') || !fs_is_developer_user((int) get_current_user_id())) {
		return;
	}
	$tabs = fs_developer_settings_available_tabs();
	if (!isset($tabs[$fs_developer_tab])) {
		return;
	}
	$label = $tabs[$fs_developer_tab]['label'];
	add_submenu_page(
		'options-general.php',
		__('Developer settings', 'fromscratch') . ' – ' . $label,
		sprintf(__('Developer › %s', 'fromscratch'), $label),
		'manage_options',
		$fs_developer_page_slug,
		'fs_render_developer_system',
		fs_developer_tab_position($fs_developer_tab)
	);
}, 20);

add_action('admin_init', function () use ($fs_developer_page_slug) {
	global $pagenow;
	if ($pagenow !== 'options-general.php' || $_SERVER['REQUEST_METHOD'] !== 'POST') {
		return;
	}
	if ((isset($_GET['page']) ? $_GET['page'] : '') !== $fs_developer_page_slug) {
		return;
	}
	if (!current_user_can('manage_options') || !function_exists('fs_is_developer_user') || !fs_is_developer_user((int) get_current_user_id())) {
		return;
	}
	$url = admin_url('options-general.php?page=fs-developer-system');

	// Search engine visibility (blog_public)
	if (!empty($_POST['fromscratch_save_search_visibility']) && !empty($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'fromscratch_system_search_visibility')) {
		$discourage = !empty($_POST['blog_public_discourage']);
		update_option('blog_public', $discourage ? '0' : '1');
		set_transient('fromscratch_system_saved', '1', 30);
		wp_safe_redirect($url . '#fs-search-visibility');
		exit;
	}

	// Email addresses (admin, report, developer)
	if (!empty($_POST['option_page']) && $_POST['option_page'] === FS_THEME_OPTION_GROUP_DEVELOPER_GENERAL && !empty($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], FS_THEME_OPTION_GROUP_DEVELOPER_GENERAL . '-options')) {
		$admin_email = isset($_POST['admin_email']) ? sanitize_email(wp_unslash($_POST['admin_email'])) : '';
		if (is_email($admin_email)) {
			update_option('admin_email', $admin_email);
		}
		$report_email = isset($_POST['fromscratch_report_email']) ? sanitize_email(wp_unslash($_POST['fromscratch_report_email'])) : '';
		update_option('fromscratch_report_email', is_email($report_email) ? $report_email : '');
		$developer_email = isset($_POST['fromscratch_developer_email']) ? sanitize_email(wp_unslash($_POST['fromscratch_developer_email'])) : '';
		update_option('fromscratch_developer_email', is_email($developer_email) ? $developer_email : '');
		set_transient('fromscratch_system_saved', '1', 30);
		wp_safe_redirect($url);
		exit;
	}

	// Mail delivery (From address + method + SMTP / SendGrid options)
	if (!empty($_POST['fromscratch_save_mail_delivery']) && !empty($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'fromscratch_system_mail_delivery')) {
		$from_email = isset($_POST['fromscratch_email_from']) ? sanitize_email(wp_unslash($_POST['fromscratch_email_from'])) : '';
		update_option('fromscratch_email_from', is_email($from_email) ? $from_email : '');
		update_option('fromscratch_email_from_name', sanitize_text_field(wp_unslash($_POST['fromscratch_email_from_name'] ?? '')));

		$mailer = isset($_POST['fromscratch_mailer']) && in_array($_POST['fromscratch_mailer'], ['php', 'smtp', 'sendgrid'], true) ? $_POST['fromscratch_mailer'] : 'php';
		update_option('fromscratch_mailer', $mailer);

		if ($mailer === 'smtp') {
			update_option('fromscratch_smtp_host', sanitize_text_field(wp_unslash($_POST['fromscratch_smtp_host'] ?? '')));
			$port = absint($_POST['fromscratch_smtp_port'] ?? 587);
			update_option('fromscratch_smtp_port', $port > 0 && $port <= 65535 ? $port : 587);
			$enc = isset($_POST['fromscratch_smtp_encryption']) && in_array($_POST['fromscratch_smtp_encryption'], ['none', 'tls', 'ssl'], true) ? $_POST['fromscratch_smtp_encryption'] : 'tls';
			update_option('fromscratch_smtp_encryption', $enc);
			update_option('fromscratch_smtp_user', sanitize_text_field(wp_unslash($_POST['fromscratch_smtp_user'] ?? '')));
			$smtp_pass = isset($_POST['fromscratch_smtp_pass']) ? wp_unslash($_POST['fromscratch_smtp_pass']) : '';
			if ($smtp_pass !== '') {
				update_option('fromscratch_smtp_pass', $smtp_pass);
			}
		}

		if ($mailer === 'sendgrid') {
			update_option('fromscratch_sendgrid_api_key', sanitize_text_field(wp_unslash($_POST['fromscratch_sendgrid_api_key'] ?? '')));
		}

		set_transient('fromscratch_system_saved', '1', 30);
		wp_safe_redirect($url . '#fs-mail-delivery');
		exit;
	}

	// Test mail: send to developer email using template from inc/email-templates
	if (!empty($_POST['fromscratch_send_test_mail']) && !empty($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'fromscratch_system_test_mail')) {
		$url_anchor = $url . '#fs-mail-delivery';
		if (!function_exists('fs_developer_email')) {
			set_transient('fromscratch_system_test_mail_error', __('Developer email is not configured.', 'fromscratch'), 30);
			wp_safe_redirect($url_anchor);
			exit;
		}
		$to = fs_developer_email();
		if ($to === '') {
			set_transient('fromscratch_system_test_mail_error', __('Please enter a developer email above before sending a test.', 'fromscratch'), 30);
			wp_safe_redirect($url_anchor);
			exit;
		}
		$site_name = get_bloginfo('name');
		$sent_at = wp_date(get_option('date_format') . ' ' . get_option('time_format'));
		$body = fs_get_email_template('test-mail', [
			'site_name' => $site_name,
			'to_email'  => $to,
			'sent_at'  => $sent_at,
		]);
		if ($body === '') {
			set_transient('fromscratch_system_test_mail_error', __('Test email template could not be loaded.', 'fromscratch'), 30);
			wp_safe_redirect($url_anchor);
			exit;
		}
		$subject = sprintf(/* translators: %s: site name */ __('Test email from %s', 'fromscratch'), $site_name);
		$headers = ['Content-Type: text/html; charset=UTF-8'];

		// Capture PHPMailer error when wp_mail fails (same request)
		$fs_test_mail_error_detail = null;
		$capture = function ($wp_error) use (&$fs_test_mail_error_detail) {
			if ($wp_error instanceof WP_Error) {
				$msgs = $wp_error->get_error_messages();
				$fs_test_mail_error_detail = implode(' ', $msgs);
			}
		};
		add_action('wp_mail_failed', $capture, 10, 1);

		$sent = wp_mail($to, $subject, $body, $headers);

		remove_action('wp_mail_failed', $capture, 10);

		if ($sent) {
			set_transient('fromscratch_system_test_mail_success', '1', 30);
		} else {
			$base = __('The test email could not be sent.', 'fromscratch');
			if ($fs_test_mail_error_detail !== null && $fs_test_mail_error_detail !== '') {
				$base .= ' ' . sprintf(/* translators: %s: error detail from mailer */ __('Reason: %s', 'fromscratch'), $fs_test_mail_error_detail);
			} else {
				$base .= ' ' . __('Check your mail delivery settings or try SMTP / SendGrid.', 'fromscratch');
			}
			set_transient('fromscratch_system_test_mail_error', $base, 30);
		}
		wp_safe_redirect($url_anchor);
		exit;
	}
}, 1);

function fs_render_developer_system(): void
{
	if (!current_user_can('manage_options')) {
		wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'fromscratch'));
	}

	$system_saved = get_transient('fromscratch_system_saved');
	if ($system_saved !== false) {
		delete_transient('fromscratch_system_saved');
	}
	$test_mail_success = get_transient('fromscratch_system_test_mail_success');
	if ($test_mail_success !== false) {
		delete_transient('fromscratch_system_test_mail_success');
	}
	$test_mail_error = get_transient('fromscratch_system_test_mail_error');
	if ($test_mail_error !== false) {
		delete_transient('fromscratch_system_test_mail_error');
	}
	?>
	<div class="wrap">
		<h1><?= esc_html(__('Developer settings', 'fromscratch')) ?></h1>
		<?php if ($system_saved !== false) : ?>
			<div class="notice notice-success is-dismissible">
				<p><strong><?= esc_html(__('Settings saved.', 'fromscratch')) ?></strong></p>
			</div>
		<?php endif; ?>
		<?php if ($test_mail_success !== false) : ?>
			<div class="notice notice-success is-dismissible">
				<p><strong><?= esc_html(__('Test email sent to the developer email address.', 'fromscratch')) ?></strong></p>
			</div>
		<?php endif; ?>
		<?php if ($test_mail_error !== false) : ?>
			<div class="notice notice-error is-dismissible">
				<p><strong><?= esc_html($test_mail_error) ?></strong></p>
			</div>
		<?php endif; ?>

		<?php fs_developer_settings_render_nav(); ?>

		<?php if ((int) get_option('blog_public', 1) === 0) : ?>
			<div class="notice notice-warning inline" style="margin: 16px 0 0;">
				<p><strong><?= esc_html__('Search engines are asked not to index this site.', 'fromscratch') ?></strong></p>
			</div>
		<?php endif; ?>

		<form method="post" action="" class="page-settings-form">
			<?php settings_fields(FS_THEME_OPTION_GROUP_DEVELOPER_GENERAL); ?>
			<h2 class="title"><?= esc_html__('Email addresses', 'fromscratch') ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="admin_email"><?= esc_html__('Admin email', 'fromscratch') ?></label></th>
					<td>
						<input type="email" name="admin_email" id="admin_email" value="<?= esc_attr(get_option('admin_email')) ?>" class="regular-text" autocomplete="email">
						<p class="description"><?= esc_html__('WordPress default admin email used for WordPress core notifications.', 'fromscratch') ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="fromscratch_report_email"><?= esc_html__('Report email', 'fromscratch') ?></label></th>
					<td>
						<input type="email" name="fromscratch_report_email" id="fromscratch_report_email" value="<?= esc_attr(get_option('fromscratch_report_email', '')) ?>" class="regular-text" autocomplete="email">
						<p class="description"><?= esc_html__('Used for automated reports such as weekly analytics summaries.', 'fromscratch') ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="fromscratch_developer_email"><?= esc_html__('Developer email', 'fromscratch') ?></label></th>
					<td>
						<input type="email" name="fromscratch_developer_email" id="fromscratch_developer_email" value="<?= esc_attr(get_option('fromscratch_developer_email', '')) ?>" class="regular-text" autocomplete="email">
						<p class="description"><?= esc_html__('Used for system alerts, error notifications and security warnings.', 'fromscratch') ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>

		<hr>

		<form method="post" action="" class="page-settings-form fs-mail-delivery-form" id="fs-mail-delivery">
			<?php wp_nonce_field('fromscratch_system_mail_delivery'); ?>
			<input type="hidden" name="fromscratch_save_mail_delivery" value="1">
			<h2 class="title"><?= esc_html__('Mail delivery', 'fromscratch') ?></h2>
			<p class="description"><?= esc_html__('From address is used for all outgoing mail. Choose how WordPress sends (PHP, SMTP, or SendGrid).', 'fromscratch') ?></p>

			<?php
			$current_mailer = get_option('fromscratch_mailer', 'php');
			$fs_mailer_options = [
				'php'      => ['label' => __('WordPress default', 'fromscratch'), 'logo' => 'php'],
				'smtp'     => ['label' => __('SMTP', 'fromscratch'), 'logo' => 'smtp'],
				'sendgrid' => ['label' => __('SendGrid', 'fromscratch'), 'logo' => 'sendgrid'],
			];
			?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="fromscratch_email_from"><?= esc_html__('From email', 'fromscratch') ?></label></th>
					<td>
						<input type="email" name="fromscratch_email_from" id="fromscratch_email_from" value="<?= esc_attr(get_option('fromscratch_email_from', '')) ?>" class="regular-text" autocomplete="email" placeholder="<?= esc_attr(get_option('admin_email', '')) ?>">
						<p class="description"><?= esc_html__('Used for all outgoing mail. Leave empty to use the Admin email above.', 'fromscratch') ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="fromscratch_email_from_name"><?= esc_html__('From name', 'fromscratch') ?></label></th>
					<td>
						<input type="text" name="fromscratch_email_from_name" id="fromscratch_email_from_name" value="<?= esc_attr(get_option('fromscratch_email_from_name', '')) ?>" class="regular-text" placeholder="<?= esc_attr(get_bloginfo('name', 'display')) ?>">
						<p class="description"><?= esc_html__('Leave empty to use the site name.', 'fromscratch') ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row" class="fs-mailer-th">
						<div class="fs-mailer-choices-vertical" role="group" aria-label="<?= esc_attr__('Mail delivery method', 'fromscratch') ?>">
							<?php foreach ($fs_mailer_options as $value => $opt) : ?>
								<label class="fs-mailer-choice <?= $current_mailer === $value ? 'is-selected' : '' ?>">
									<input type="radio" name="fromscratch_mailer" value="<?= esc_attr($value) ?>" <?= checked($current_mailer, $value, false) ?>>
									<span class="fs-mailer-choice-logo" aria-hidden="true">
										<span class="fs-mailer-logo-placeholder" data-provider="<?= esc_attr($value) ?>"></span>
									</span>
									<span class="fs-mailer-choice-label"><?= esc_html($opt['label']) ?></span>
								</label>
							<?php endforeach; ?>
						</div>
					</th>
					<td class="fs-mailer-td">
						<div class="fs-mailer-panel fs-mailer-panel-php" id="fs-mailer-panel-php" <?= $current_mailer !== 'php' ? ' hidden' : '' ?>>
							<p class="description"><?= esc_html__('Uses the WordPress default: PHP mail(). No configuration needed. Delivery depends on your server.', 'fromscratch') ?></p>
						</div>
						<div class="fs-mailer-panel fs-mailer-panel-smtp" id="fs-mailer-panel-smtp" <?= $current_mailer !== 'smtp' ? ' hidden' : '' ?>>
							<table class="form-table" role="presentation">
								<tr>
									<th scope="row"><label for="fromscratch_smtp_host"><?= esc_html__('SMTP host', 'fromscratch') ?></label></th>
									<td>
										<input type="text" name="fromscratch_smtp_host" id="fromscratch_smtp_host" value="<?= esc_attr(get_option('fromscratch_smtp_host', '')) ?>" class="regular-text" placeholder="smtp.example.com">
									</td>
								</tr>
								<tr>
									<th scope="row"><label for="fromscratch_smtp_port"><?= esc_html__('Port', 'fromscratch') ?></label></th>
									<td>
										<input type="number" name="fromscratch_smtp_port" id="fromscratch_smtp_port" value="<?= esc_attr(get_option('fromscratch_smtp_port', '587')) ?>" min="1" max="65535" class="small-text">
										<p class="description"><?= esc_html__('Common: 587 (TLS), 465 (SSL), 25 (none).', 'fromscratch') ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row"><label for="fromscratch_smtp_encryption"><?= esc_html__('Encryption', 'fromscratch') ?></label></th>
									<td>
										<select name="fromscratch_smtp_encryption" id="fromscratch_smtp_encryption">
											<option value="none" <?= selected(get_option('fromscratch_smtp_encryption', 'tls'), 'none', false) ?>><?= esc_html__('None', 'fromscratch') ?></option>
											<option value="tls" <?= selected(get_option('fromscratch_smtp_encryption', 'tls'), 'tls', false) ?>><?= esc_html__('TLS', 'fromscratch') ?></option>
											<option value="ssl" <?= selected(get_option('fromscratch_smtp_encryption', 'tls'), 'ssl', false) ?>><?= esc_html__('SSL', 'fromscratch') ?></option>
										</select>
									</td>
								</tr>
								<tr>
									<th scope="row"><label for="fromscratch_smtp_user"><?= esc_html__('Username', 'fromscratch') ?></label></th>
									<td>
										<input type="text" name="fromscratch_smtp_user" id="fromscratch_smtp_user" value="<?= esc_attr(get_option('fromscratch_smtp_user', '')) ?>" class="regular-text" autocomplete="username">
									</td>
								</tr>
								<tr>
									<th scope="row"><label for="fromscratch_smtp_pass"><?= esc_html__('Password', 'fromscratch') ?></label></th>
									<td>
										<input type="password" name="fromscratch_smtp_pass" id="fromscratch_smtp_pass" value="" class="regular-text" autocomplete="new-password" placeholder="<?= esc_attr__('Leave blank to keep current', 'fromscratch') ?>">
									</td>
								</tr>
							</table>
						</div>
						<div class="fs-mailer-panel fs-mailer-panel-sendgrid" id="fs-mailer-panel-sendgrid" <?= $current_mailer !== 'sendgrid' ? ' hidden' : '' ?>>
							<table class="form-table" role="presentation">
								<tr>
									<th scope="row"><label for="fromscratch_sendgrid_api_key"><?= esc_html__('API key', 'fromscratch') ?></label></th>
									<td>
										<input type="password" name="fromscratch_sendgrid_api_key" id="fromscratch_sendgrid_api_key" value="<?= esc_attr(get_option('fromscratch_sendgrid_api_key', '')) ?>" class="regular-text" autocomplete="off">
										<p class="description"><?= esc_html__('Create an API key in the SendGrid dashboard with Mail Send permission. From address above is used.', 'fromscratch') ?></p>
									</td>
								</tr>
							</table>
						</div>
					</td>
				</tr>
			</table>

			<script>
			(function() {
				var form = document.getElementById('fs-mail-delivery');
				if (!form) return;
				var radios = form.querySelectorAll('input[name="fromscratch_mailer"]');
				var panels = form.querySelectorAll('.fs-mailer-panel');
				var labels = form.querySelectorAll('.fs-mailer-choice');
				function update() {
					var value = form.querySelector('input[name="fromscratch_mailer"]:checked');
					value = value ? value.value : 'php';
					panels.forEach(function(p) {
						var show = p.id === 'fs-mailer-panel-' + value;
						p.hidden = !show;
					});
					labels.forEach(function(l) {
						var r = l.querySelector('input[type="radio"]');
						l.classList.toggle('is-selected', r && r.checked);
					});
				}
				radios.forEach(function(r) { r.addEventListener('change', update); });
				update();
			})();
			</script>

			<?php submit_button(); ?>
		</form>
		<form method="post" action="" class="fs-test-mail-form" id="fs-test-mail-form" style="margin-top: -16px;">
			<?php wp_nonce_field('fromscratch_system_test_mail'); ?>
			<input type="hidden" name="fromscratch_send_test_mail" value="1">
			<?php submit_button(__('Send test mail', 'fromscratch'), 'secondary', 'fromscratch_test_mail', false); ?>
		</form>

		<hr>

		<form method="post" action="" class="page-settings-form" id="fs-search-visibility">
			<?php wp_nonce_field('fromscratch_system_search_visibility'); ?>
			<input type="hidden" name="fromscratch_save_search_visibility" value="1">
			<h2 class="title"><?= esc_html__('Search engine visibility', 'fromscratch') ?></h2>
			<p class="description"><?= esc_html__('This setting matches Settings → Reading.', 'fromscratch') ?></p>
			<p class="description"><?= esc_html__('When enabled, search engines are asked not to index this site.', 'fromscratch') ?></p>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?= esc_html__('Visibility', 'fromscratch') ?></th>
					<td>
						<label>
							<input type="checkbox" name="blog_public_discourage" value="1" <?= checked((int) get_option('blog_public', 1), 0, false) ?>>
							<?= esc_html__('Discourage search engines from indexing this site', 'fromscratch') ?>
						</label>
						<p class="description fs-indent-checkbox"><?= esc_html__('It is up to search engines whether they follow this request.', 'fromscratch') ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}
