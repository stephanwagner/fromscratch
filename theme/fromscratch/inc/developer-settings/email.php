<?php

defined('ABSPATH') || exit;

/**
 * Email addresses, mail delivery, and test mail — rendered on Developer › Settings.
 */
function fs_developer_render_email_settings_section(): void
{
	?>
	<form method="post" action="" class="fs-page-settings-form">
		<?php settings_fields(FS_THEME_OPTION_GROUP_DEVELOPER_GENERAL); ?>
		<h2 class="title" style="margin-top: 0;"><?= esc_html__('Email addresses', 'fromscratch') ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="admin_email"><?= esc_html__('Admin email', 'fromscratch') ?></label></th>
				<td>
					<input type="email" name="admin_email" id="admin_email" value="<?= esc_attr(get_option('admin_email')) ?>" class="regular-text" autocomplete="email">
					<p class="description"><?= esc_html__('WordPress default admin email used for WordPress core notifications.', 'fromscratch') ?></p>
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
		<div class="fs-submit-row">
			<button type="submit" class="button button-primary"><?= esc_html__('Save Changes') ?></button>
		</div>
	</form>

	<hr class="fs-page-settings-divider">

	<form method="post" action="" class="fs-page-settings-form fs-mail-delivery-form" id="fs-mail-delivery">
		<?php wp_nonce_field('fromscratch_system_mail_delivery'); ?>
		<input type="hidden" name="fromscratch_save_mail_delivery" value="1">
		<h2 class="title"><?= esc_html__('Mail delivery', 'fromscratch') ?></h2>
		<p class="description"><?= esc_html__('From address is used for all outgoing mail.', 'fromscratch') ?></p>

		<?php
		$current_mailer = get_option('fromscratch_mailer', 'php');
		$fs_mailer_options = [
			'php' => [
				'label' => __('WordPress default', 'fromscratch'),
				'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="#21759b"><path d="M2.597,7.81l4.91,13.454c-3.434-1.669-5.802-5.19-5.802-9.265,0-1.492.32-2.909.891-4.19ZM18.949,11.48c0-1.272-.457-2.153-.849-2.839-.522-.848-1.011-1.566-1.011-2.414,0-.946.718-1.827,1.729-1.827.046,0,.089.006.133.008-1.831-1.678-4.271-2.702-6.951-2.702-3.596,0-6.76,1.845-8.601,4.64.242.007.469.012.662.012,1.077,0,2.743-.131,2.743-.131.555-.033.62.782.066.848,0,0-.558.066-1.178.098l3.749,11.151,2.253-6.757-1.604-4.394c-.554-.033-1.079-.098-1.079-.098-.555-.033-.49-.881.065-.848,0,0,1.7.131,2.712.131,1.077,0,2.743-.131,2.743-.131.555-.033.621.782.066.848,0,0-.559.066-1.178.098l3.72,11.066,1.027-3.431c.445-1.424.784-2.447.784-3.328ZM12.18,12.9l-3.089,8.975c.922.271,1.897.419,2.908.419,1.199,0,2.348-.207,3.418-.584-.028-.044-.053-.091-.073-.142l-3.165-8.669ZM21.032,7.061c.044.328.069.68.069,1.059,0,1.045-.195,2.219-.783,3.687l-3.144,9.091c3.06-1.785,5.119-5.1,5.119-8.898,0-1.79-.457-3.473-1.261-4.939ZM24,12c0,6.617-5.384,12-12,12S0,18.617,0,12,5.383,0,12,0s12,5.383,12,12ZM23.449,12C23.449,5.686,18.313.55,12,.55S.55,5.686.55,12s5.136,11.45,11.449,11.45,11.449-5.137,11.449-11.45Z"/></svg>',
			],
			'smtp' => [
				'label' => 'SMTP',
				'icon' => '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor"><path d="M160-160q-33 0-56.5-23.5T80-240v-480q0-33 23.5-56.5T160-800h640q33 0 56.5 23.5T880-720v480q0 33-23.5 56.5T800-160H160Zm330.5-288.5Q496-450 501-453l283-177q8-5 12-12.5t4-16.5q0-20-17-30t-35 1L480-520 212-688q-18-11-35-.5T160-659q0 10 4 17.5t12 11.5l283 177q5 3 10.5 4.5T480-447q5 0 10.5-1.5Z"/></svg>',
			],
			'sendgrid' => [
				'label' => 'SendGrid',
				'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path d="M24,0v16h-8v8H0v-8s0,0,0,0V8h8V0h16Z" fill="#9dd6e3"/><polygon points="0 24 8 24 8 16 0 16 0 24" fill="#3f72ab"/><polygon points="16 16 24 16 24 8 16 8 16 16" fill="#00a9d1"/><polygon points="8 8 16 8 16 0 8 0 8 8" fill="#00a9d1"/><polygon points="8 16 16 16 16 8 8 8 8 16" fill="#2191c4"/><polygon points="16 8 24 8 24 0 16 0 16 8" fill="#3f72ab"/></svg>',
			],
		];
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="fromscratch_email_from"><?= esc_html__('From email', 'fromscratch') ?></label></th>
				<td>
					<input type="email" name="fromscratch_email_from" id="fromscratch_email_from" value="<?= esc_attr(get_option('fromscratch_email_from', '')) ?>" class="regular-text" autocomplete="email" placeholder="<?= esc_attr(get_option('admin_email', '')) ?>">
					<p class="description"><?= esc_html__('Leave empty to use the Admin email.', 'fromscratch') ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="fromscratch_email_from_name"><?= esc_html__('From name', 'fromscratch') ?></label></th>
				<td>
					<input type="text" name="fromscratch_email_from_name" id="fromscratch_email_from_name" value="<?= esc_attr(get_option('fromscratch_email_from_name', '')) ?>" class="regular-text" placeholder="<?= esc_attr(get_bloginfo('name', 'display')) ?>">
					<p class="description"><?= esc_html__('Leave empty to use the site name.', 'fromscratch') ?></p>
				</td>
			</tr>
		</table>

		<h3 class="title" style="margin-top: 20px;"><?= esc_html__('Method', 'fromscratch') ?></h3>
		<p class="description"><?= esc_html__('Choose how WordPress sends email.', 'fromscratch') ?></p>
		<input type="hidden" name="fromscratch_mailer" id="fromscratch_mailer_input" value="<?= esc_attr($current_mailer) ?>">
		<div class="fs-tabs -form-table" data-fs-tabs style="margin-top: 20px;">
			<nav class="fs-tabs-nav" data-fs-tabs-nav role="tablist">
				<?php foreach ($fs_mailer_options as $value => $opt) : ?>
					<button type="button" class="button fs-tabs-btn fs-button-can-toggle has-icon <?= $current_mailer === $value ? 'active' : '' ?>" role="tab" aria-selected="<?= $current_mailer === $value ? 'true' : 'false' ?>" aria-controls="fs-mailer-panel-<?= esc_attr($value) ?>" data-fs-tabs-btn data-tab="<?= esc_attr($value) ?>" data-mailer-value="<?= esc_attr($value) ?>">
						<span class="fs-tab-button-icon">
							<?= $opt['icon'] ?>
						</span>
						<?= esc_html($opt['label']) ?>
					</button>
				<?php endforeach; ?>
			</nav>
			<div class="fs-tabs-panels" data-fs-tabs-panels>
				<div id="fs-mailer-panel-php" class="fs-tabs-panel <?= $current_mailer === 'php' ? 'fs-tabs-panel--active' : '' ?>" data-fs-tabs-panel role="tabpanel" data-tab="php" <?= $current_mailer === 'php' ? 'data-fs-tabs-panel-active="1"' : '' ?>>
					<p class="description"><?= esc_html__('Uses the WordPress default mail function (PHP mail()).', 'fromscratch') ?></p>
					<p class="description"><?= esc_html__('No mail configuration is applied by the theme, so SMTP or mail plugins can override delivery.', 'fromscratch') ?></p>
				</div>
				<div id="fs-mailer-panel-smtp" class="fs-tabs-panel <?= $current_mailer === 'smtp' ? 'fs-tabs-panel--active' : '' ?>" data-fs-tabs-panel role="tabpanel" data-tab="smtp" <?= $current_mailer === 'smtp' ? 'data-fs-tabs-panel-active="1"' : '' ?>>
					<div class="fs-form-row">
						<label for="fromscratch_smtp_host" class="fs-input-label"><?= esc_html__('SMTP host', 'fromscratch') ?></label>
						<input type="text" name="fromscratch_smtp_host" id="fromscratch_smtp_host" value="<?= esc_attr(get_option('fromscratch_smtp_host', '')) ?>" class="regular-text" placeholder="smtp.example.com" style="width: 100%; max-width: 400px;">
					</div>
					<div class="fs-form-row">
						<label for="fromscratch_smtp_port" class="fs-input-label"><?= esc_html__('Port', 'fromscratch') ?></label>
						<input type="number" name="fromscratch_smtp_port" id="fromscratch_smtp_port" value="<?= esc_attr(get_option('fromscratch_smtp_port', '587')) ?>" min="1" max="65535" class="small-text">
						<p class="description" style="margin-top: 4px;"><?= esc_html__('Common: 587 (TLS), 465 (SSL), 25 (none).', 'fromscratch') ?></p>
					</div>
					<div class="fs-form-row">
						<label for="fromscratch_smtp_encryption" class="fs-input-label"><?= esc_html__('Encryption', 'fromscratch') ?></label>
						<select name="fromscratch_smtp_encryption" id="fromscratch_smtp_encryption" style="width: 100%; max-width: 400px;">
							<option value="none" <?= selected(get_option('fromscratch_smtp_encryption', 'tls'), 'none', false) ?>><?= esc_html__('None', 'fromscratch') ?></option>
							<option value="tls" <?= selected(get_option('fromscratch_smtp_encryption', 'tls'), 'tls', false) ?>><?= esc_html__('TLS', 'fromscratch') ?></option>
							<option value="ssl" <?= selected(get_option('fromscratch_smtp_encryption', 'tls'), 'ssl', false) ?>><?= esc_html__('SSL', 'fromscratch') ?></option>
						</select>
					</div>
					<div class="fs-form-row">
						<label for="fromscratch_smtp_user" class="fs-input-label"><?= esc_html__('Username', 'fromscratch') ?></label>
						<input type="text" name="fromscratch_smtp_user" id="fromscratch_smtp_user" value="<?= esc_attr(get_option('fromscratch_smtp_user', '')) ?>" class="regular-text" autocomplete="username" style="width: 100%; max-width: 400px;">
					</div>
					<div class="fs-form-row">
						<label for="fromscratch_smtp_pass" class="fs-input-label"><?= esc_html__('Password', 'fromscratch') ?></label>
						<input type="password" name="fromscratch_smtp_pass" id="fromscratch_smtp_pass" value="" class="regular-text" autocomplete="new-password" placeholder="" style="width: 100%; max-width: 400px;">
						<p class="description"><?= esc_html__('Leave blank to keep current.', 'fromscratch') ?></p>
					</div>
				</div>
				<div id="fs-mailer-panel-sendgrid" class="fs-tabs-panel <?= $current_mailer === 'sendgrid' ? 'fs-tabs-panel--active' : '' ?>" data-fs-tabs-panel role="tabpanel" data-tab="sendgrid" <?= $current_mailer === 'sendgrid' ? 'data-fs-tabs-panel-active="1"' : '' ?>>
					<div class="fs-form-row">
						<label for="fromscratch_sendgrid_api_key" class="fs-input-label"><?= esc_html__('API key', 'fromscratch') ?></label>
						<input type="password" name="fromscratch_sendgrid_api_key" id="fromscratch_sendgrid_api_key" value="<?= esc_attr(get_option('fromscratch_sendgrid_api_key', '')) ?>" class="regular-text" autocomplete="off" style="width: 100%; max-width: 400px;">
						<p class="description" style="margin-top: 4px;"><?= esc_html__('Create an API key in the SendGrid dashboard with send permissions.', 'fromscratch') ?></p>
					</div>
				</div>
			</div>
		</div>

		<script>
		(function() {
			var form = document.getElementById('fs-mail-delivery');
			if (!form) return;
			var input = document.getElementById('fromscratch_mailer_input');
			var buttons = form.querySelectorAll('[data-mailer-value]');
			buttons.forEach(function(btn) {
				btn.addEventListener('click', function() {
					input.value = btn.getAttribute('data-mailer-value');
				});
			});
		})();
		</script>

		<div class="fs-submit-row">
			<button type="submit" class="button button-primary"><?= esc_html__('Save Changes') ?></button>
		</div>
	</form>

	<hr class="fs-page-settings-divider">

	<form method="post" action="" class="fs-test-mail-form" id="fs-test-mail-form">
		<?php wp_nonce_field('fromscratch_system_test_mail'); ?>
		<input type="hidden" name="fromscratch_send_test_mail" value="1">
		<h3 class="title"><?= esc_html__('Test email', 'fromscratch') ?></h3>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="fromscratch_test_mail_to"><?= esc_html__('Send to', 'fromscratch') ?></label></th>
				<td>
					<input type="email" name="fromscratch_test_mail_to" id="fromscratch_test_mail_to" value="<?= esc_attr(function_exists('fs_developer_email') ? fs_developer_email() : get_option('fromscratch_developer_email', '')) ?>" placeholder="<?= esc_attr(get_option('fromscratch_developer_email', '')) ?>" class="regular-text" autocomplete="off" spellcheck="false">
					<p class="description"><?= esc_html__('Leave empty to use the developer email.', 'fromscratch') ?></p>
				</td>
			</tr>
		</table>
		<div class="fs-submit-row">
			<button type="submit" name="fromscratch_test_mail" class="button button-primary"><?= esc_html__('Send test email', 'fromscratch') ?></button>
		</div>
	</form>
	<?php
}
