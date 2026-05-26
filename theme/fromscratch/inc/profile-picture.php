<?php

defined('ABSPATH') || exit;

const FS_OPTION_PROFILE_PICTURE_MODE = 'fromscratch_profile_picture_mode';
const FS_PROFILE_PICTURE_MODE_DEFAULT = 'upload';
const FS_USER_META_PROFILE_PICTURE = 'fromscratch_profile_picture';
const FS_USER_META_PROFILE_PICTURE_URL = 'fromscratch_profile_picture_url';
const FS_ATTACHMENT_META_PROFILE_PICTURE = '_fs_profile_picture';

function fs_profile_picture_mode(): string
{
	$mode = get_option(FS_OPTION_PROFILE_PICTURE_MODE, FS_PROFILE_PICTURE_MODE_DEFAULT);

	return $mode === 'gravatar' ? 'gravatar' : FS_PROFILE_PICTURE_MODE_DEFAULT;
}

/**
 * Persist theme default on first run (upload, not Gravatar).
 */
function fs_profile_picture_register_default_option(): void
{
	if (get_option(FS_OPTION_PROFILE_PICTURE_MODE, false) !== false) {
		return;
	}

	add_option(FS_OPTION_PROFILE_PICTURE_MODE, FS_PROFILE_PICTURE_MODE_DEFAULT, '', false);
}

add_action('init', 'fs_profile_picture_register_default_option', 1);

function fs_profile_picture_uses_upload(): bool
{
	return fs_profile_picture_mode() === 'upload';
}

function fs_profile_picture_is_profile_admin_screen(): bool
{
	if (!is_admin()) {
		return false;
	}

	if (function_exists('get_current_screen')) {
		$screen = get_current_screen();
		if ($screen && in_array($screen->id, ['profile', 'user-edit'], true)) {
			return true;
		}
	}

	global $pagenow;

	return in_array($pagenow ?? '', ['profile.php', 'user-edit.php'], true);
}

function fs_profile_picture_gravatar_description(): string
{
	return sprintf(
		/* translators: %s: Gravatar URL. */
		__('You can change your profile picture on <a href="%s">Gravatar</a>.', 'fromscratch'),
		esc_url(__('https://gravatar.com/', 'fromscratch'))
	);
}

function fs_profile_picture_placeholder_url(): string
{
	return fs_asset_url('/admin/placeholder-profile.webp');
}

function fs_profile_picture_is_placeholder_url(string $url): bool
{
	return str_contains($url, 'placeholder-profile.webp')
		|| str_contains($url, 'placeholder.webp');
}

function fs_profile_picture_url(int $user_id): string
{
	if ($user_id <= 0) {
		return '';
	}

	$url = get_user_meta($user_id, FS_USER_META_PROFILE_PICTURE_URL, true);
	if (!is_string($url) || $url === '' || fs_profile_picture_is_placeholder_url($url)) {
		return '';
	}

	return $url;
}

/**
 * @param int|string|object $id_or_email User ID, email, login, or comment object.
 */
function fs_profile_picture_resolve_user($id_or_email): ?WP_User
{
	if ($id_or_email instanceof WP_User) {
		return $id_or_email;
	}

	if (is_numeric($id_or_email)) {
		$user = get_user_by('id', (int) $id_or_email);

		return $user instanceof WP_User ? $user : null;
	}

	if (is_string($id_or_email)) {
		if (is_email($id_or_email)) {
			$user = get_user_by('email', $id_or_email);

			return $user instanceof WP_User ? $user : null;
		}

		$user = get_user_by('login', $id_or_email);

		return $user instanceof WP_User ? $user : null;
	}

	if (is_object($id_or_email) && isset($id_or_email->user_id)) {
		$user = get_user_by('id', (int) $id_or_email->user_id);

		return $user instanceof WP_User ? $user : null;
	}

	return null;
}

/**
 * Upload mode: custom URL or theme placeholder; never Gravatar.
 *
 * @param array<string, mixed> $args
 * @return array<string, mixed>
 */
function fs_profile_picture_filter_avatar_data(array $args, $id_or_email): array
{
	if (!fs_profile_picture_uses_upload()) {
		return $args;
	}

	$user = fs_profile_picture_resolve_user($id_or_email);
	$url = $user instanceof WP_User ? fs_profile_picture_url((int) $user->ID) : '';
	if ($url === '') {
		$url = fs_profile_picture_placeholder_url();
	}

	$args['url'] = $url;
	$args['found_avatar'] = true;

	return $args;
}

add_filter('pre_get_avatar_data', 'fs_profile_picture_filter_avatar_data', 10, 2);

function fs_profile_picture_profile_section(WP_User $user): void
{
	if (!fs_profile_picture_uses_upload()) {
		return;
	}

	$can_edit = get_current_user_id() === (int) $user->ID
		? current_user_can('upload_files')
		: current_user_can('edit_users');

	if (!$can_edit) {
		return;
	}

	$url = fs_profile_picture_url((int) $user->ID);
	?>
	<h2><?= esc_html__('Profile picture', 'fromscratch') ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?= esc_html__('Current picture', 'fromscratch') ?></th>
			<td>
				<img src="<?= esc_attr($url !== '' ? $url : fs_profile_picture_placeholder_url()) ?>" alt="" width="96" height="96" class="avatar avatar-96 photo" style="border-radius:8px;object-fit:cover;display:block;border:1px solid #ddd;" decoding="async">
				<?php if ($url === '') { ?>
					<p class="description" style="margin:8px 0 0;"><?= esc_html__('No picture uploaded yet.', 'fromscratch') ?></p>
				<?php } ?>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="fs_profile_picture_file"><?= esc_html__('Upload new', 'fromscratch') ?></label></th>
			<td>
				<input type="file" name="fs_profile_picture_file" id="fs_profile_picture_file" accept="image/jpeg,image/png,image/gif,image/webp">
				<p class="description"><?= esc_html__('Choose a file, then click “Update Profile” at the bottom of this page.', 'fromscratch') ?></p>
				<?php if ($url !== '') { ?>
					<p style="margin-top:12px;">
						<label>
							<input type="checkbox" name="fs_profile_picture_remove" value="1">
							<?= esc_html__('Remove picture', 'fromscratch') ?>
						</label>
					</p>
				<?php } ?>
			</td>
		</tr>
	</table>
	<?php
}

/**
 * Save profile picture on profile update (runs once per request).
 */
function fs_profile_picture_save(int $user_id): void
{
	static $done = [];

	if (isset($done[$user_id]) || !fs_profile_picture_uses_upload() || $user_id <= 0) {
		return;
	}
	$done[$user_id] = true;

	$can_edit = get_current_user_id() === $user_id
		? current_user_can('upload_files')
		: current_user_can('edit_users');

	if (!$can_edit) {
		return;
	}

	if (!empty($_POST['fs_profile_picture_remove'])) {
		fs_profile_picture_delete($user_id);
		fs_profile_picture_flash_notice('removed');

		return;
	}

	if (
		empty($_FILES['fs_profile_picture_file']['name'])
		|| !is_array($_FILES['fs_profile_picture_file'])
		|| (int) ($_FILES['fs_profile_picture_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
	) {
		return;
	}

	if (!function_exists('wp_handle_upload')) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}
	if (!function_exists('wp_generate_attachment_metadata')) {
		require_once ABSPATH . 'wp-admin/includes/image.php';
	}

	$file = $_FILES['fs_profile_picture_file'];
	$upload = wp_handle_upload($file, [
		'test_form' => false,
		'mimes'     => [
			'jpg|jpeg|jpe' => 'image/jpeg',
			'gif'          => 'image/gif',
			'png'          => 'image/png',
			'webp'         => 'image/webp',
		],
	]);

	// Prevent WordPress or other code from re-testing this moved upload.
	unset($_FILES['fs_profile_picture_file']);

	if (!empty($upload['error']) || empty($upload['url']) || empty($upload['file'])) {
		fs_profile_picture_flash_notice('error', (string) ($upload['error'] ?? __('Upload failed.', 'fromscratch')));

		return;
	}

	$attachment_id = wp_insert_attachment([
		'post_mime_type' => $upload['type'],
		'post_title'     => sanitize_file_name(pathinfo($upload['file'], PATHINFO_FILENAME)),
		'post_status'    => 'inherit',
		'post_author'    => $user_id,
		'guid'           => $upload['url'],
	], $upload['file']);

	if (is_wp_error($attachment_id) || $attachment_id <= 0) {
		fs_profile_picture_flash_notice('error', __('Could not save the image.', 'fromscratch'));

		return;
	}

	update_post_meta($attachment_id, FS_ATTACHMENT_META_PROFILE_PICTURE, '1');
	$relative = _wp_relative_upload_path($upload['file']);
	if (is_string($relative) && $relative !== '') {
		update_post_meta($attachment_id, '_wp_attached_file', $relative);
	}
	wp_update_attachment_metadata($attachment_id, wp_generate_attachment_metadata($attachment_id, $upload['file']));

	$old_id = (int) get_user_meta($user_id, FS_USER_META_PROFILE_PICTURE, true);
	if ($old_id > 0 && $old_id !== $attachment_id) {
		wp_delete_attachment($old_id, true);
	}

	update_user_meta($user_id, FS_USER_META_PROFILE_PICTURE, (int) $attachment_id);
	update_user_meta($user_id, FS_USER_META_PROFILE_PICTURE_URL, (string) $upload['url']);

	fs_profile_picture_flash_notice('saved');
}

function fs_profile_picture_delete(int $user_id): void
{
	$old_id = (int) get_user_meta($user_id, FS_USER_META_PROFILE_PICTURE, true);
	if ($old_id > 0) {
		wp_delete_attachment($old_id, true);
	}
	delete_user_meta($user_id, FS_USER_META_PROFILE_PICTURE);
	delete_user_meta($user_id, FS_USER_META_PROFILE_PICTURE_URL);
}

/**
 * @param 'saved'|'removed'|'error' $type
 */
function fs_profile_picture_flash_notice(string $type, string $message = ''): void
{
	$user_id = get_current_user_id();
	if ($user_id <= 0) {
		return;
	}
	set_transient('fs_profile_picture_notice_' . $user_id, ['type' => $type, 'message' => $message], 60);
}

add_filter('pre_option_show_avatars', function ($value) {
	if (fs_profile_picture_uses_upload() || !fs_profile_picture_is_profile_admin_screen()) {
		return $value;
	}

	return '1';
});

add_filter('user_profile_picture_description', function (string $description, WP_User $profile_user): string {
	if (fs_profile_picture_uses_upload()) {
		return __('You can upload a custom picture in the Profile picture section below. Gravatar is disabled for this site.', 'fromscratch');
	}

	if (IS_PROFILE_PAGE) {
		return fs_profile_picture_gravatar_description();
	}

	if ($description !== '') {
		return $description;
	}

	return __('This user’s profile picture is loaded from Gravatar using their account email.', 'fromscratch');
}, 10, 2);

add_action('user_edit_form_tag', function (): void {
	if (fs_profile_picture_uses_upload()) {
		echo ' enctype="multipart/form-data"';
	}
});

add_action('personal_options_update', 'fs_profile_picture_save', 10);
add_action('edit_user_profile_update', 'fs_profile_picture_save', 10);

add_action('show_user_profile', 'fs_profile_picture_profile_section', 11);
add_action('edit_user_profile', 'fs_profile_picture_profile_section', 11);

add_action('admin_head-profile.php', 'fs_profile_picture_hide_core_row');
add_action('admin_head-user-edit.php', 'fs_profile_picture_hide_core_row');

function fs_profile_picture_hide_core_row(): void
{
	if (!fs_profile_picture_uses_upload()) {
		return;
	}
	echo '<style>.user-profile-picture{display:none!important;}</style>';
}

add_action('admin_notices', function (): void {
	$screen = function_exists('get_current_screen') ? get_current_screen() : null;
	if (!$screen || !in_array($screen->id, ['profile', 'user-edit'], true)) {
		return;
	}

	$user_id = get_current_user_id();
	$notice = get_transient('fs_profile_picture_notice_' . $user_id);
	if (!is_array($notice) || empty($notice['type'])) {
		return;
	}
	delete_transient('fs_profile_picture_notice_' . $user_id);

	$messages = [
		'saved'   => __('Profile picture saved.', 'fromscratch'),
		'removed' => __('Profile picture removed.', 'fromscratch'),
	];
	if ($notice['type'] === 'error') {
		$text = $notice['message'] !== '' ? $notice['message'] : __('Upload failed.', 'fromscratch');
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($text) . '</p></div>';

		return;
	}
	if (!isset($messages[$notice['type']])) {
		return;
	}
	echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($messages[$notice['type']]) . '</p></div>';
}, 5);

add_filter('ajax_query_attachments_args', function (array $args): array {
	$meta_query = is_array($args['meta_query'] ?? null) ? $args['meta_query'] : [];
	if ($meta_query !== [] && !isset($meta_query['relation'])) {
		$meta_query['relation'] = 'AND';
	}
	$meta_query[] = ['key' => FS_ATTACHMENT_META_PROFILE_PICTURE, 'compare' => 'NOT EXISTS'];
	$args['meta_query'] = $meta_query;

	return $args;
}, 20);

add_action('pre_get_posts', function (WP_Query $query): void {
	if (!is_admin() || !$query->is_main_query()) {
		return;
	}
	global $pagenow;
	if ($pagenow !== 'upload.php') {
		return;
	}
	$meta_query = $query->get('meta_query');
	$meta_query = is_array($meta_query) ? $meta_query : [];
	if ($meta_query !== [] && !isset($meta_query['relation'])) {
		$meta_query['relation'] = 'AND';
	}
	$meta_query[] = ['key' => FS_ATTACHMENT_META_PROFILE_PICTURE, 'compare' => 'NOT EXISTS'];
	$query->set('meta_query', $meta_query);
}, 20);
