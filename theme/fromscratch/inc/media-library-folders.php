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
 * TEMP DEBUG: make media modal visually obvious to confirm hook execution.
 * Remove this block after verification.
 */
add_action('admin_head', function (): void {
	if (!is_admin()) {
		return;
	}
	?>
	<style id="fs-media-modal-debug-proof">
		.media-modal {
			outline: 8px solid #e30000 !important;
			box-shadow: 0 0 0 6px #ffcc00 !important;
		}

		.media-modal .media-frame-title h1::before {
			content: "FS HOOKED - ";
			color: #e30000;
			font-weight: 800;
			margin-right: 4px;
		}

		.media-modal .media-frame-router,
		.media-modal .media-toolbar {
			background: #fff3f3 !important;
		}

		.media-modal .attachments-browser.fs-modal-sidebar-layout #fs-media-modal-proof {
			position: absolute;
			left: 0;
			top: 50px;
			bottom: 0;
			width: 220px;
			box-sizing: border-box;
			overflow: auto;
			padding: 12px;
			background: #ff00c8;
			color: #fff;
			border-right: 4px solid #000;
			box-shadow: inset -2px 0 0 #fff;
			z-index: 3;
		}

		.media-modal .attachments-browser.fs-modal-sidebar-layout .attachments-wrapper,
		.media-modal .attachments-browser.fs-modal-sidebar-layout .uploader-inline {
			margin-left: 220px;
		}
	</style>
	<?php
});

/**
 * TEMP DEBUG: inject a visible node into the media modal DOM.
 */
add_action('admin_footer', function (): void {
	if (!is_admin()) {
		return;
	}
	$terms = get_terms([
		'taxonomy' => FS_MEDIA_FOLDER_TAXONOMY,
		'hide_empty' => false,
		'orderby' => 'name',
		'order' => 'ASC',
	]);
	if (is_wp_error($terms)) {
		$terms = [];
	}
	$by_parent = [];
	foreach ($terms as $term) {
		if (!$term instanceof WP_Term) {
			continue;
		}
		$pid = (int) $term->parent;
		if (!isset($by_parent[$pid])) {
			$by_parent[$pid] = [];
		}
		$by_parent[$pid][] = $term;
	}
	$flat = [];
	$walk = static function (int $parent_id, int $depth) use (&$walk, &$by_parent, &$flat): void {
		if (empty($by_parent[$parent_id])) {
			return;
		}
		foreach ($by_parent[$parent_id] as $t) {
			$flat[] = [
				'id' => (int) $t->term_id,
				'name' => (string) $t->name,
				'depth' => $depth,
			];
			$walk((int) $t->term_id, $depth + 1);
		}
	};
	$walk(0, 0);
	?>
	<script>
	(function (folders) {
		function getActiveProps() {
			if (!window.wp || !wp.media || !wp.media.frame || typeof wp.media.frame.state !== 'function') {
				return null;
			}
			var state = wp.media.frame.state();
			if (!state || typeof state.get !== 'function') {
				return null;
			}
			var lib = state.get('library');
			return lib && lib.props ? lib.props : null;
		}

		function selectedFolderId() {
			var props = getActiveProps();
			if (!props || typeof props.get !== 'function') {
				return 0;
			}
			var raw = props.get('fs_media_folder_id');
			if (raw === undefined || raw === null || raw === '') {
				raw = props.get('fs_media_folder');
			}
			var id = parseInt(raw, 10);
			return isNaN(id) || id < 1 ? 0 : id;
		}

		function applyFolder(id) {
			var props = getActiveProps();
			if (!props || typeof props.set !== 'function') {
				return;
			}
			var v = id > 0 ? id : '';
			props.set({
				fs_media_folder_id: v,
				fs_media_folder: v,
				paged: 1
			});
			if (typeof props.trigger === 'function') {
				props.trigger('change');
			}
		}

		function injectProofNode() {
			var browser = document.querySelector('.media-modal .attachments-browser');
			if (!browser) {
				return;
			}
			if (browser.querySelector('#fs-media-modal-proof')) {
				return;
			}
			browser.classList.add('fs-modal-sidebar-layout');
			var proof = document.createElement('div');
			proof.id = 'fs-media-modal-proof';
			var html = '<div style="font-size:13px;font-weight:900;letter-spacing:.4px;margin-bottom:8px;">Folders</div>';
			html += '<ul style="list-style:none;margin:0;padding:0;">';
			html += '<li style="margin:0 0 2px;"><button type="button" class="fs-modal-folder-btn" data-folder-id="0" style="width:100%;text-align:left;background:rgba(255,255,255,.2);border:0;color:#fff;padding:6px 8px;border-radius:4px;font-size:12px;cursor:pointer;">All files</button></li>';
			for (var i = 0; i < folders.length; i++) {
				var f = folders[i];
				if (!f || typeof f.name !== 'string') {
					continue;
				}
				var id = parseInt(f.id, 10);
				if (isNaN(id) || id < 1) {
					continue;
				}
				var depth = parseInt(f.depth, 10);
				if (isNaN(depth) || depth < 0) {
					depth = 0;
				}
				var pad = 8 + (depth * 14);
				html += '<li style="margin:0 0 2px;"><button type="button" class="fs-modal-folder-btn" data-folder-id="' + id + '" style="width:100%;text-align:left;background:transparent;border:0;color:#fff;padding:6px 8px 6px ' + pad + 'px;border-radius:4px;font-size:12px;cursor:pointer;">' + String(f.name).replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</button></li>';
			}
			html += '</ul>';
			proof.innerHTML = html;
			browser.appendChild(proof);

			function repaintActive() {
				var active = selectedFolderId();
				var buttons = proof.querySelectorAll('.fs-modal-folder-btn');
				for (var bi = 0; bi < buttons.length; bi++) {
					var b = buttons[bi];
					var id = parseInt(b.getAttribute('data-folder-id') || '0', 10);
					if (id === active) {
						b.style.background = 'rgba(255,255,255,.35)';
						b.style.fontWeight = '700';
					} else {
						b.style.background = 'transparent';
						b.style.fontWeight = '400';
					}
				}
			}

			proof.addEventListener('click', function (e) {
				var btn = e.target.closest('.fs-modal-folder-btn');
				if (!btn) {
					return;
				}
				e.preventDefault();
				var id = parseInt(btn.getAttribute('data-folder-id') || '0', 10);
				if (isNaN(id) || id < 0) {
					id = 0;
				}
				applyFolder(id);
				repaintActive();
			});
			repaintActive();
		}
		injectProofNode();
		var obs = new MutationObserver(injectProofNode);
		obs.observe(document.body, { childList: true, subtree: true });
	})(<?php echo wp_json_encode($flat); ?>);
	</script>
	<?php
});

/**
 * Debug probe: inject a tiny marker into media modal toolbar.
 * Remove after modal integration is confirmed.
 */
add_action('print_media_templates', function (): void {
	if (!is_admin()) {
		return;
	}
	?>
	<script>
	(function(wp) {
		if (!wp || !wp.media || !wp.media.view || !wp.media.view.AttachmentsBrowser) {
			return;
		}
		if (wp.media.view.AttachmentsBrowser.prototype.__fsModalProbePatched) {
			return;
		}
		var originalCreateToolbar = wp.media.view.AttachmentsBrowser.prototype.createToolbar;
		wp.media.view.AttachmentsBrowser.prototype.createToolbar = function() {
			originalCreateToolbar.apply(this, arguments);
			if (!this.toolbar || this.toolbar.get('fsModalProbe')) {
				return;
			}
			var probeView = new wp.media.View({
				tagName: 'span',
				className: 'button button-small',
				attributes: {
					style: 'margin-left:8px;pointer-events:none;opacity:.85;'
				}
			});
			probeView.$el.text('FS modal test');
			this.toolbar.set('fsModalProbe', probeView, { priority: 200 });
		};
		wp.media.view.AttachmentsBrowser.prototype.__fsModalProbePatched = true;
	})(window.wp);
	</script>
	<?php
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
function fs_media_folders_render_list(array $terms, int $parent_id, int $depth, int $current_id, string $base_url, string $redirect_url): void
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
		$delete_url = add_query_arg([
			'action' => 'fs_media_folder_delete',
			'term_id' => (int) $term->term_id,
			'redirect_to' => $redirect_url,
		], admin_url('admin-post.php'));
		$delete_url = wp_nonce_url($delete_url, 'fs_media_folder_delete_' . (int) $term->term_id);
		echo '<li>';
		echo '<div class="fs-media-folders-row">';
		echo '<a class="' . esc_attr(implode(' ', $classes)) . '" href="' . esc_url($url) . '">';
		echo '<span class="name">' . esc_html($prefix . $term->name) . '</span>';
		echo ' <span class="count">(' . (int) $term->count . ')</span>';
		echo '</a>';
		echo '<button type="button" class="fs-media-folder-delete-btn" aria-label="' . esc_attr__('Delete folder', 'fromscratch') . '" data-folder-name="' . esc_attr($term->name) . '" data-folder-count="' . (int) $term->count . '" data-delete-url="' . esc_url($delete_url) . '">×</button>';
		echo '</div>';
		echo '</li>';
		fs_media_folders_render_list($terms, (int) $term->term_id, $depth + 1, $current_id, $base_url, $redirect_url);
	}
}

/**
 * Handle sidebar "delete folder" action.
 */
add_action('admin_post_fs_media_folder_delete', function (): void {
	if (!taxonomy_exists(FS_MEDIA_FOLDER_TAXONOMY)) {
		wp_die(esc_html__('Media folder taxonomy not available.', 'fromscratch'));
	}
	$term_id = isset($_GET['term_id']) ? absint($_GET['term_id']) : 0;
	$redirect_to = isset($_GET['redirect_to']) ? (string) wp_unslash($_GET['redirect_to']) : '';
	$redirect_to = wp_validate_redirect($redirect_to, admin_url('upload.php'));
	if ($redirect_to === '') {
		$redirect_to = admin_url('upload.php');
	}
	$redirect_to = remove_query_arg(['fs_media_folder_id', 'fs_media_folder', 'fs_media_folder_error', 'fs_media_folder_success'], $redirect_to);

	$tax = get_taxonomy(FS_MEDIA_FOLDER_TAXONOMY);
	$delete_cap = ($tax && isset($tax->cap->delete_terms)) ? $tax->cap->delete_terms : 'manage_categories';
	if (!current_user_can($delete_cap)) {
		wp_safe_redirect(add_query_arg('fs_media_folder_error', 'delete_cap', $redirect_to));
		exit;
	}
	if ($term_id <= 0) {
		wp_safe_redirect(add_query_arg('fs_media_folder_error', 'delete_invalid', $redirect_to));
		exit;
	}

	check_admin_referer('fs_media_folder_delete_' . $term_id);

	$term = get_term($term_id, FS_MEDIA_FOLDER_TAXONOMY);
	if (!$term || is_wp_error($term)) {
		wp_safe_redirect(add_query_arg('fs_media_folder_error', 'delete_missing', $redirect_to));
		exit;
	}

	$deleted = wp_delete_term($term_id, FS_MEDIA_FOLDER_TAXONOMY);
	if (is_wp_error($deleted) || !$deleted) {
		wp_safe_redirect(add_query_arg('fs_media_folder_error', 'delete_failed', $redirect_to));
		exit;
	}

	wp_safe_redirect(add_query_arg('fs_media_folder_success', 'deleted', $redirect_to));
	exit;
});

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
			flex-shrink: 0;
			background: #fff;
			border: 1px solid #dcdcde;
			border-radius: 6px;
			padding: 14px;
			position: sticky;
			top: 46px;
			z-index: 20;
			align-self: flex-start;
			max-height: calc(100vh - 46px);
			overflow-y: auto;
			-webkit-overflow-scrolling: touch;
		}

		.upload-php .fs-media-folders-content {
			min-width: 0;
			flex: 1 1 auto;
			position: relative;
		}

		/*
		 * Grid: core positions .media-frame absolute within the content column.
		 * min-height gives the percentage heights inside the frame a basis.
		 */
		.upload-php #wp-media-grid .fs-media-folders-content {
			min-height: 400px;
		}

		.upload-php .fs-media-folders-content .media-frame {
			max-width: 100%;
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

		.upload-php .fs-media-folders-row {
			display: flex;
			align-items: center;
			gap: 6px;
		}

		.upload-php .fs-media-folders-link {
			display: block;
			flex: 1 1 auto;
			min-width: 0;
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

		.upload-php .fs-media-folder-delete-btn {
			border: 0;
			background: transparent;
			color: #b32d2e;
			cursor: pointer;
			font-size: 16px;
			line-height: 1;
			padding: 4px 6px;
			border-radius: 4px;
		}

		.upload-php .fs-media-folder-delete-btn:hover {
			background: #fcf0f1;
		}

		.upload-php .fs-media-folder-delete-btn:focus {
			outline: 2px solid #2271b1;
			outline-offset: 1px;
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

		.upload-php .fs-media-folder-delete-modal {
			position: fixed;
			inset: 0;
			z-index: 100000;
			display: none;
		}

		.upload-php .fs-media-folder-delete-modal.is-open {
			display: block;
		}

		.upload-php .fs-media-folder-delete-backdrop {
			position: absolute;
			inset: 0;
			background: rgba(0, 0, 0, 0.45);
		}

		.upload-php .fs-media-folder-delete-dialog {
			position: relative;
			background: #fff;
			width: min(520px, calc(100vw - 32px));
			margin: 10vh auto 0;
			padding: 16px;
			border-radius: 6px;
			box-shadow: 0 20px 60px rgba(0, 0, 0, 0.25);
		}

		.upload-php .fs-media-folder-delete-actions {
			display: flex;
			justify-content: flex-end;
			gap: 8px;
			margin-top: 16px;
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
		$error = sanitize_key((string) $_GET['fs_media_folder_error']);
		if ($error === 'empty') {
			$message = __('Please enter a folder name.', 'fromscratch');
		} elseif (strpos($error, 'delete_') === 0) {
			$message = __('Could not delete this folder.', 'fromscratch');
		} else {
			$message = __('Could not create this folder.', 'fromscratch');
		}
	} elseif (isset($_GET['fs_media_folder_success'])) {
		$message_class = 'is-success';
		$success = sanitize_key((string) $_GET['fs_media_folder_success']);
		$message = $success === 'deleted'
			? __('Folder deleted.', 'fromscratch')
			: __('Folder created.', 'fromscratch');
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
			<?php
			$sidebar_redirect_url = remove_query_arg(['fs_media_folder_id', 'fs_media_folder_error', 'fs_media_folder_success'], $base_url);
			fs_media_folders_render_list($terms, 0, 0, $folder_id, $sidebar_redirect_url, $sidebar_redirect_url);
			?>
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
	<div id="fs-media-folder-delete-modal" class="fs-media-folder-delete-modal" aria-hidden="true">
		<div class="fs-media-folder-delete-backdrop" data-modal-close></div>
		<div class="fs-media-folder-delete-dialog" role="dialog" aria-modal="true" aria-labelledby="fs-media-folder-delete-title">
			<h2 id="fs-media-folder-delete-title"><?= esc_html__('Delete folder?', 'fromscratch') ?></h2>
			<p id="fs-media-folder-delete-text"></p>
			<div class="fs-media-folder-delete-actions">
				<button type="button" class="button" data-modal-close><?= esc_html__('Cancel', 'fromscratch') ?></button>
				<a href="#" class="button button-primary button-link-delete" id="fs-media-folder-delete-confirm"><?= esc_html__('Delete folder', 'fromscratch') ?></a>
			</div>
		</div>
	</div>
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

			/*
			 * Grid view: media-grid.js appends .media-frame as a direct child of #wp-media-grid.
			 * After we insert the flex layout, that frame would sit below the row and cover full width.
			 * Move it into .fs-media-folders-content so folders stay beside the library when selecting items.
			 */
			function fsMoveMediaFrameIntoFolderContent() {
				var gridRoot = document.getElementById('wp-media-grid');
				if (!gridRoot) {
					return;
				}
				var contentCol = gridRoot.querySelector('.fs-media-folders-content');
				var frame = null;
				for (var fi = 0; fi < gridRoot.children.length; fi++) {
					var ch = gridRoot.children[fi];
					if (ch.nodeType === 1 && ch.classList && ch.classList.contains('media-frame')) {
						frame = ch;
						break;
					}
				}
				if (!contentCol || !frame || frame.parentNode === contentCol) {
					return;
				}
				contentCol.insertBefore(frame, contentCol.firstChild);
			}
			if (wrap.id === 'wp-media-grid') {
				fsMoveMediaFrameIntoFolderContent();
				if (window.jQuery) {
					var $wrap = window.jQuery(wrap);
					$wrap.on('wp-media-grid-ready', fsMoveMediaFrameIntoFolderContent);
					window.jQuery(fsMoveMediaFrameIntoFolderContent);
				}
				var gridObserver = new MutationObserver(fsMoveMediaFrameIntoFolderContent);
				gridObserver.observe(wrap, {
					childList: true
				});
				setTimeout(fsMoveMediaFrameIntoFolderContent, 0);
				setTimeout(fsMoveMediaFrameIntoFolderContent, 200);
			}

			var modal = document.getElementById('fs-media-folder-delete-modal');
			var modalText = document.getElementById('fs-media-folder-delete-text');
			var modalConfirm = document.getElementById('fs-media-folder-delete-confirm');
			if (!modal || !modalText || !modalConfirm) {
				return;
			}

			function closeModal() {
				modal.classList.remove('is-open');
				modal.setAttribute('aria-hidden', 'true');
				modalConfirm.setAttribute('href', '#');
			}

			function openModal(name, count, deleteUrl) {
				var countText = parseInt(count, 10) > 0 ?
					'<?= esc_js(__('This will also remove folder assignments from the contained files.', 'fromscratch')) ?>' :
					'<?= esc_js(__('The folder is empty.', 'fromscratch')) ?>';
				modalText.textContent = '<?= esc_js(__('Delete folder', 'fromscratch')) ?> "' + name + '"? ' + countText;
				modalConfirm.setAttribute('href', deleteUrl);
				modal.classList.add('is-open');
				modal.setAttribute('aria-hidden', 'false');
			}
			sidebar.addEventListener('click', function(e) {
				var btn = e.target.closest('.fs-media-folder-delete-btn');
				if (!btn) {
					return;
				}
				e.preventDefault();
				openModal(
					btn.getAttribute('data-folder-name') || '',
					btn.getAttribute('data-folder-count') || '0',
					btn.getAttribute('data-delete-url') || '#'
				);
			});
			modal.addEventListener('click', function(e) {
				if (e.target && e.target.hasAttribute('data-modal-close')) {
					closeModal();
				}
			});
			document.addEventListener('keydown', function(e) {
				if (e.key === 'Escape' && modal.classList.contains('is-open')) {
					closeModal();
				}
			});
		})();
	</script>
<?php
});
