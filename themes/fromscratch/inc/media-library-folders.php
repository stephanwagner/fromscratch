<?php

defined('ABSPATH') || exit;

const FS_MEDIA_FOLDER_TAXONOMY = 'fs_media_folder';

/**
 * Register the attachment folder taxonomy used by Media Library.
 */
add_action('init', function () {
	register_taxonomy(FS_MEDIA_FOLDER_TAXONOMY, ['attachment'], [
		'hierarchical' => true,
		'labels' => [
			'name' => __('Media folders', 'fromscratch'),
			'singular_name' => __('Media folder', 'fromscratch'),
			'search_items' => __('Search media folders', 'fromscratch'),
			'all_items' => __('All media folders', 'fromscratch'),
			'parent_item' => __('Parent media folder', 'fromscratch'),
			'parent_item_colon' => __('Parent media folder:', 'fromscratch'),
			'edit_item' => __('Edit media folder', 'fromscratch'),
			'update_item' => __('Update media folder', 'fromscratch'),
			'add_new_item' => __('Add new media folder', 'fromscratch'),
			'new_item_name' => __('New media folder name', 'fromscratch'),
			'menu_name' => __('Media folders', 'fromscratch'),
		],
		'public' => false,
		'show_ui' => false,
		'show_admin_column' => false,
		'show_in_quick_edit' => false,
		'show_in_rest' => true,
		'rewrite' => false,
	]);
}, 10);

/**
 * Get active media folder filter from request.
 */
function fs_media_folders_current_id(): int
{
	return isset($_GET['fs_media_folder_id']) ? absint($_GET['fs_media_folder_id']) : 0;
}

/**
 * Assign an attachment to a folder term (or clear it).
 *
 * @param mixed $raw_folder_value
 */
function fs_media_folders_set_attachment_folder(int $attachment_id, $raw_folder_value): void
{
	if (!taxonomy_exists(FS_MEDIA_FOLDER_TAXONOMY) || $attachment_id <= 0) {
		return;
	}

	if (is_array($raw_folder_value)) {
		$raw_folder_value = reset($raw_folder_value);
	}

	$folder_id = is_scalar($raw_folder_value) ? absint($raw_folder_value) : 0;


	if ($folder_id <= 0) {
		wp_set_object_terms($attachment_id, [], FS_MEDIA_FOLDER_TAXONOMY, false);
		return;
	}

	wp_set_object_terms($attachment_id, [$folder_id], FS_MEDIA_FOLDER_TAXONOMY, false);
}

/**
 * Extract folder value from media AJAX request payload.
 */
function fs_media_folders_get_ajax_folder_value(int $attachment_id)
{
	$request = wp_unslash($_REQUEST);

	$attachments = isset($request['attachments']) && is_array($request['attachments']) ? $request['attachments'] : [];
	if (isset($attachments[$attachment_id]) && is_array($attachments[$attachment_id])) {
		$row = $attachments[$attachment_id];
		if (array_key_exists('fs_media_folder_id', $row)) {
			return $row['fs_media_folder_id'];
		}
		if (isset($row['compat']) && is_array($row['compat']) && array_key_exists('fs_media_folder_id', $row['compat'])) {
			return $row['compat']['fs_media_folder_id'];
		}
	}
	if (isset($attachments[(string) $attachment_id]) && is_array($attachments[(string) $attachment_id])) {
		$row = $attachments[(string) $attachment_id];
		if (array_key_exists('fs_media_folder_id', $row)) {
			return $row['fs_media_folder_id'];
		}
		if (isset($row['compat']) && is_array($row['compat']) && array_key_exists('fs_media_folder_id', $row['compat'])) {
			return $row['compat']['fs_media_folder_id'];
		}
	}

	if (isset($request['changes']) && is_array($request['changes']) && array_key_exists('fs_media_folder_id', $request['changes'])) {
		return $request['changes']['fs_media_folder_id'];
	}
	if (isset($request['attachment']) && is_array($request['attachment'])) {
		$attachment = $request['attachment'];
		if (array_key_exists('fs_media_folder_id', $attachment)) {
			return $attachment['fs_media_folder_id'];
		}
		if (isset($attachment['compat']) && is_array($attachment['compat']) && array_key_exists('fs_media_folder_id', $attachment['compat'])) {
			return $attachment['compat']['fs_media_folder_id'];
		}
	}

	return null;
}

/**
 * Keep folder filter in the media grid AJAX queries.
 */
add_filter('media_view_settings', function (array $settings): array {
	if (!is_admin()) {
		return $settings;
	}
	$screen = function_exists('get_current_screen') ? get_current_screen() : null;
	if (!$screen || $screen->id !== 'upload') {
		return $settings;
	}

	$folder_id = fs_media_folders_current_id();
	if ($folder_id <= 0) {
		return $settings;
	}

	if (!isset($settings['library']) || !is_array($settings['library'])) {
		$settings['library'] = [];
	}
	if (!isset($settings['query']) || !is_array($settings['query'])) {
		$settings['query'] = [];
	}
	// Keep both keys for media-grid compatibility across request shapes.
	$settings['library']['fs_media_folder'] = $folder_id;
	$settings['library']['fs_media_folder_id'] = $folder_id;
	$settings['query']['fs_media_folder'] = $folder_id;
	$settings['query']['fs_media_folder_id'] = $folder_id;

	return $settings;
});

/**
 * Filter media attachments in list/grid mode by selected folder.
 */
add_filter('ajax_query_attachments_args', function (array $args): array {

	$folder_id = 0;
	$request = wp_unslash($_REQUEST);
	if (isset($request['query']) && is_array($request['query']) && !empty($request['query']['fs_media_folder_id'])) {
		$folder_id = absint($request['query']['fs_media_folder_id']);
	} elseif (isset($request['query']) && is_array($request['query']) && !empty($request['query']['fs_media_folder'])) {
		$folder_id = absint($request['query']['fs_media_folder']);
	} elseif (!empty($request['fs_media_folder_id'])) {
		$folder_id = absint($request['fs_media_folder_id']);
	} elseif (!empty($request['fs_media_folder'])) {
		$folder_id = absint($request['fs_media_folder']);
	} elseif (!empty($_GET['fs_media_folder_id'])) {
		$folder_id = absint($_GET['fs_media_folder_id']);
	} elseif (!empty($_GET['fs_media_folder'])) {
		$folder_id = absint($_GET['fs_media_folder']);
	}

	if ($folder_id <= 0 || !taxonomy_exists(FS_MEDIA_FOLDER_TAXONOMY)) {
		return $args;
	}

	$tax_query = isset($args['tax_query']) ? (array)$args['tax_query'] : [];

	$tax_query[] = [
		'taxonomy' => FS_MEDIA_FOLDER_TAXONOMY,
		'field' => 'term_id',
		'terms' => [$folder_id],
		'include_children' => true,
	];

	$args['tax_query'] = $tax_query;

	return $args;

}, 10);

/**
 * Filter Media > Library list table by selected folder.
 */
add_action('pre_get_posts', function (WP_Query $query): void {
	if (!is_admin() || !$query->is_main_query()) {
		return;
	}
	global $pagenow;
	if ($pagenow !== 'upload.php') {
		return;
	}

	$folder_id = fs_media_folders_current_id();
	if ($folder_id <= 0 || !taxonomy_exists(FS_MEDIA_FOLDER_TAXONOMY)) {
		return;
	}

	$tax_query = (array) $query->get('tax_query');
	$tax_query[] = [
		'taxonomy' => FS_MEDIA_FOLDER_TAXONOMY,
		'field' => 'term_id',
		'terms' => [$folder_id],
		'include_children' => true,
	];
	$query->set('tax_query', $tax_query);
}, 10);

/**
 * Add a folder selector in attachment edit details.
 */
add_filter('attachment_fields_to_edit', function (array $form_fields, WP_Post $post): array {
	if (!taxonomy_exists(FS_MEDIA_FOLDER_TAXONOMY)) {
		return $form_fields;
	}
	$current_terms = wp_get_object_terms($post->ID, FS_MEDIA_FOLDER_TAXONOMY, ['fields' => 'ids']);
	$current_id = !is_wp_error($current_terms) && !empty($current_terms) ? (int) $current_terms[0] : 0;

	ob_start();
	wp_dropdown_categories([
		'taxonomy' => FS_MEDIA_FOLDER_TAXONOMY,
		'name' => 'attachments[' . (int) $post->ID . '][fs_media_folder_id]',
		'orderby' => 'name',
		'hide_empty' => false,
		'hierarchical' => true,
		'show_option_none' => __('No folder', 'fromscratch'),
		'option_none_value' => '0',
		'selected' => $current_id,
		'value_field' => 'term_id',
	]);
	$field_html = (string) ob_get_clean();

	$form_fields['fs_media_folder_id'] = [
		'label' => __('Folder', 'fromscratch'),
		'input' => 'html',
		'html' => $field_html,
		'helps' => __('Assign this file to a media folder.', 'fromscratch'),
	];
	return $form_fields;
}, 10, 2);

/**
 * Save folder assignment from attachment edit details.
 */
add_filter('attachment_fields_to_save', function (array $post, array $attachment): array {
	if (!taxonomy_exists(FS_MEDIA_FOLDER_TAXONOMY) || empty($post['ID'])) {
		return $post;
	}
	$attachment_id = (int) $post['ID'];
	$raw_folder = null;
	if (array_key_exists('fs_media_folder_id', $attachment)) {
		$raw_folder = $attachment['fs_media_folder_id'];
	} elseif (isset($attachment['compat']) && is_array($attachment['compat']) && array_key_exists('fs_media_folder_id', $attachment['compat'])) {
		$raw_folder = $attachment['compat']['fs_media_folder_id'];
	}
	if ($raw_folder === null) {
		return $post;
	}
	fs_media_folders_set_attachment_folder($attachment_id, $raw_folder);
	return $post;
}, 10, 2);

/**
 * Fallback for media grid/modal save flow where custom compat fields are posted via AJAX.
 */
add_action('wp_ajax_save_attachment_compat', function (): void {
	if (!current_user_can('upload_files') || !taxonomy_exists(FS_MEDIA_FOLDER_TAXONOMY)) {
		return;
	}
	$attachment_id = isset($_REQUEST['id']) ? absint($_REQUEST['id']) : 0;
	if ($attachment_id <= 0) {
		return;
	}
	$folder_value = fs_media_folders_get_ajax_folder_value($attachment_id);
	if ($folder_value === null) {
		return;
	}
	fs_media_folders_set_attachment_folder($attachment_id, $folder_value);
}, 1);

/**
 * Handle sidebar "create folder" form submission.
 */
add_action('admin_post_fs_media_folder_create', function (): void {
	if (!current_user_can('upload_files')) {
		wp_die(esc_html__('You do not have permission to create media folders.', 'fromscratch'));
	}
	check_admin_referer('fs_media_folder_create');

	$redirect_to = isset($_POST['redirect_to']) ? (string) wp_unslash($_POST['redirect_to']) : '';
	$redirect_to = wp_validate_redirect($redirect_to, admin_url('upload.php'));
	if ($redirect_to === '') {
		$redirect_to = admin_url('upload.php');
	}

	$name = isset($_POST['fs_media_folder_name']) ? sanitize_text_field((string) wp_unslash($_POST['fs_media_folder_name'])) : '';
	$parent = isset($_POST['fs_media_folder_parent']) ? absint($_POST['fs_media_folder_parent']) : 0;
	if ($name === '') {
		wp_safe_redirect(add_query_arg('fs_media_folder_error', 'empty', $redirect_to));
		exit;
	}

	$insert = wp_insert_term($name, FS_MEDIA_FOLDER_TAXONOMY, ['parent' => $parent]);
	if (is_wp_error($insert)) {
		$term_exists = $insert->get_error_data('term_exists');
		$term_id = is_numeric($term_exists) ? (int) $term_exists : 0;
		if ($term_id <= 0) {
			wp_safe_redirect(add_query_arg('fs_media_folder_error', 'insert', $redirect_to));
			exit;
		}
	} else {
		$term_id = isset($insert['term_id']) ? (int) $insert['term_id'] : 0;
	}

	$url = add_query_arg('fs_media_folder_id', $term_id, $redirect_to);
	$url = add_query_arg('fs_media_folder_success', '1', $url);
	wp_safe_redirect($url);
	exit;
});

/**
 * Build tree list markup for folder sidebar.
 *
 * @param WP_Term[] $terms
 */
function fs_media_folders_render_list(array $terms, int $parent_id, int $depth, int $current_id, string $base_url): void
{
	foreach ($terms as $term) {
		if ((int) $term->parent !== $parent_id) {
			continue;
		}
		$url = add_query_arg('fs_media_folder_id', (int) $term->term_id, $base_url);
		$classes = ['fs-media-folders-link'];
		if ((int) $term->term_id === $current_id) {
			$classes[] = 'is-active';
		}
		$prefix = $depth > 0 ? str_repeat('- ', $depth) : '';
		echo '<li>';
		echo '<a class="' . esc_attr(implode(' ', $classes)) . '" href="' . esc_url($url) . '">';
		echo esc_html($prefix . $term->name);
		echo ' <span class="count">(' . (int) $term->count . ')</span>';
		echo '</a>';
		echo '</li>';
		fs_media_folders_render_list($terms, (int) $term->term_id, $depth + 1, $current_id, $base_url);
	}
}

/**
 * Sidebar styles for Media > Library.
 */
add_action('admin_head-upload.php', function (): void {
?>
	<style>
		.upload-php .fs-media-folders-layout {
			display: flex;
			gap: 20px;
			align-items: flex-start;
			margin-top: 12px;
		}

		.upload-php .fs-media-folders-sidebar {
			width: 260px;
			flex: 0 0 260px;
			background: #fff;
			border: 1px solid #dcdcde;
			border-radius: 6px;
			padding: 14px;
			position: sticky;
			top: 46px;
		}

		.upload-php .fs-media-folders-content {
			min-width: 0;
			flex: 1 1 auto;
		}

		.upload-php .fs-media-folders-sidebar h2 {
			margin: 0 0 10px;
			font-size: 14px;
			line-height: 1.4;
		}

		.upload-php .fs-media-folders-list {
			margin: 0 0 14px;
			padding: 0;
			list-style: none;
		}

		.upload-php .fs-media-folders-list li {
			margin: 0;
		}

		.upload-php .fs-media-folders-link {
			display: block;
			padding: 5px 8px;
			border-radius: 4px;
			color: #1d2327;
			text-decoration: none;
		}

		.upload-php .fs-media-folders-link:hover {
			background: #f6f7f7;
		}

		.upload-php .fs-media-folders-link.is-active {
			background: #2271b1;
			color: #fff;
		}

		.upload-php .fs-media-folders-link .count {
			opacity: 0.75;
		}

		.upload-php .fs-media-folders-create p {
			margin: 0 0 8px;
		}

		.upload-php .fs-media-folders-create .button {
			width: 100%;
		}

		.upload-php .fs-media-folders-message {
			margin: 0 0 10px;
			padding: 8px 10px;
			border-radius: 4px;
			font-size: 12px;
			line-height: 1.35;
		}

		.upload-php .fs-media-folders-message.is-error {
			background: #fcf0f1;
			color: #8a2424;
		}

		.upload-php .fs-media-folders-message.is-success {
			background: #edfaef;
			color: #1a5f29;
		}
	</style>
<?php
});

/**
 * Render the sidebar and attach it left of the media list.
 */
add_action('admin_footer-upload.php', function (): void {
	if (!current_user_can('upload_files') || !taxonomy_exists(FS_MEDIA_FOLDER_TAXONOMY)) {
		return;
	}
	$folder_id = fs_media_folders_current_id();
	$terms = get_terms([
		'taxonomy' => FS_MEDIA_FOLDER_TAXONOMY,
		'hide_empty' => false,
		'orderby' => 'name',
		'order' => 'ASC',
	]);
	if (is_wp_error($terms)) {
		$terms = [];
	}

	$base_args = ['mode', 'post_mime_type', 'detached', 'm', 's'];
	$base_url = admin_url('upload.php');
	foreach ($base_args as $arg) {
		if (!isset($_GET[$arg])) {
			continue;
		}
		$base_url = add_query_arg($arg, sanitize_text_field((string) wp_unslash($_GET[$arg])), $base_url);
	}

	$message = '';
	$message_class = '';
	if (isset($_GET['fs_media_folder_error'])) {
		$message_class = 'is-error';
		$message = $_GET['fs_media_folder_error'] === 'empty'
			? __('Please enter a folder name.', 'fromscratch')
			: __('Could not create this folder.', 'fromscratch');
	} elseif (isset($_GET['fs_media_folder_success'])) {
		$message_class = 'is-success';
		$message = __('Folder created.', 'fromscratch');
	}
?>
	<aside id="fs-media-folders-sidebar" class="fs-media-folders-sidebar" style="display:none;">
		<h2><?= esc_html__('Folders', 'fromscratch') ?></h2>
		<?php if ($message !== '') : ?>
			<div class="fs-media-folders-message <?= esc_attr($message_class) ?>"><?= esc_html($message) ?></div>
		<?php endif; ?>
		<ul class="fs-media-folders-list">
			<li>
				<a class="fs-media-folders-link <?= $folder_id <= 0 ? 'is-active' : '' ?>" href="<?= esc_url(remove_query_arg(['fs_media_folder_id', 'fs_media_folder_error', 'fs_media_folder_success'], $base_url)) ?>">
					<?= esc_html__('All files', 'fromscratch') ?>
				</a>
			</li>
			<?php fs_media_folders_render_list($terms, 0, 0, $folder_id, remove_query_arg(['fs_media_folder_id', 'fs_media_folder_error', 'fs_media_folder_success'], $base_url)); ?>
		</ul>
		<form method="post" action="<?= esc_url(admin_url('admin-post.php')) ?>" class="fs-media-folders-create">
			<input type="hidden" name="action" value="fs_media_folder_create">
			<input type="hidden" name="redirect_to" value="<?= esc_attr(remove_query_arg(['fs_media_folder_error', 'fs_media_folder_success'], $base_url)) ?>">
			<?php wp_nonce_field('fs_media_folder_create'); ?>
			<p>
				<label for="fs_media_folder_name" class="screen-reader-text"><?= esc_html__('Folder name', 'fromscratch') ?></label>
				<input type="text" name="fs_media_folder_name" id="fs_media_folder_name" class="regular-text" style="width:100%;" placeholder="<?= esc_attr__('New folder name', 'fromscratch') ?>" required>
			</p>
			<p>
				<label for="fs_media_folder_parent" class="screen-reader-text"><?= esc_html__('Parent folder', 'fromscratch') ?></label>
				<?php
				wp_dropdown_categories([
					'taxonomy' => FS_MEDIA_FOLDER_TAXONOMY,
					'name' => 'fs_media_folder_parent',
					'id' => 'fs_media_folder_parent',
					'orderby' => 'name',
					'hide_empty' => false,
					'hierarchical' => true,
					'show_option_none' => __('No parent', 'fromscratch'),
					'option_none_value' => '0',
				]);
				?>
			</p>
			<p><button type="submit" class="button button-primary"><?= esc_html__('Create folder', 'fromscratch') ?></button></p>
		</form>
	</aside>
	<script>
		(function() {
			var sidebar = document.getElementById('fs-media-folders-sidebar');
			var wrap = document.querySelector('#wpbody-content .wrap');
			if (!sidebar || !wrap || wrap.dataset.fsMediaFoldersReady === '1') {
				return;
			}
			var heading = wrap.querySelector('h1.wp-heading-inline');
			var addButton = wrap.querySelector('.page-title-action');
			var headerEnd = wrap.querySelector('hr.wp-header-end');

			var layout = document.createElement('div');
			layout.className = 'fs-media-folders-layout';
			var content = document.createElement('div');
			content.className = 'fs-media-folders-content';

			layout.appendChild(sidebar);
			layout.appendChild(content);
			if (headerEnd && headerEnd.nextSibling) {
				wrap.insertBefore(layout, headerEnd.nextSibling);
			} else {
				wrap.appendChild(layout);
			}

			Array.prototype.slice.call(wrap.children).forEach(function(node) {
				if (node === heading || node === addButton || node === headerEnd || node === layout) {
					return;
				}
				content.appendChild(node);
			});

			sidebar.style.display = '';
			wrap.dataset.fsMediaFoldersReady = '1';
		})();
	</script>
<?php
});
