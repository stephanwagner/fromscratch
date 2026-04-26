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
		'update_count_callback' => 'fs_media_folders_update_term_count',
	]);
}, 10);

/**
 * Keep folder counts accurate for attachments (including inherited/unattached media).
 *
 * WordPress core generic callbacks can undercount attachment taxonomies depending on post status.
 * We count relationships directly against attachment posts and ignore only trashed attachments.
 *
 * @param array<int|string> $tt_ids
 */
function fs_media_folders_update_term_count(array $tt_ids, WP_Taxonomy $taxonomy): void
{
	global $wpdb;
	if (!$wpdb instanceof wpdb || empty($tt_ids)) {
		return;
	}

	$tt_ids = array_values(array_filter(array_map('intval', $tt_ids), static fn(int $id): bool => $id > 0));
	if (empty($tt_ids)) {
		return;
	}

	foreach ($tt_ids as $tt_id) {
		$count = (int) $wpdb->get_var($wpdb->prepare(
			"SELECT COUNT(DISTINCT tr.object_id)
			FROM {$wpdb->term_relationships} tr
			INNER JOIN {$wpdb->posts} p ON p.ID = tr.object_id
			WHERE tr.term_taxonomy_id = %d
			  AND p.post_type = 'attachment'
			  AND p.post_status <> 'trash'",
			$tt_id
		));

		$wpdb->update(
			$wpdb->term_taxonomy,
			['count' => $count],
			['term_taxonomy_id' => $tt_id],
			['%d'],
			['%d']
		);
	}

	clean_term_cache($tt_ids, $taxonomy->name, false);
}

/**
 * Recount all media-folder terms periodically so existing folders recover from stale counts.
 */
add_action('admin_init', function (): void {
	if (!is_admin() || !taxonomy_exists(FS_MEDIA_FOLDER_TAXONOMY) || get_transient('fs_media_folder_counts_recounted')) {
		return;
	}
	$tt_ids = get_terms([
		'taxonomy' => FS_MEDIA_FOLDER_TAXONOMY,
		'hide_empty' => false,
		'fields' => 'tt_ids',
	]);
	if (is_wp_error($tt_ids) || empty($tt_ids) || !is_array($tt_ids)) {
		set_transient('fs_media_folder_counts_recounted', '1', 10 * MINUTE_IN_SECONDS);
		return;
	}
	$tax = get_taxonomy(FS_MEDIA_FOLDER_TAXONOMY);
	if ($tax instanceof WP_Taxonomy) {
		fs_media_folders_update_term_count(array_map('intval', $tt_ids), $tax);
	}
	set_transient('fs_media_folder_counts_recounted', '1', 10 * MINUTE_IN_SECONDS);
});

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
	$before_ids = wp_get_object_terms($attachment_id, FS_MEDIA_FOLDER_TAXONOMY, ['fields' => 'ids']);
	$before_ids = is_wp_error($before_ids) ? [] : array_map('intval', $before_ids);

	if (is_array($raw_folder_value)) {
		$raw_folder_value = reset($raw_folder_value);
	}

	$folder_id = is_scalar($raw_folder_value) ? absint($raw_folder_value) : 0;


	if ($folder_id <= 0) {
		wp_set_object_terms($attachment_id, [], FS_MEDIA_FOLDER_TAXONOMY, false);
		if (!empty($before_ids)) {
			wp_update_term_count_now($before_ids, FS_MEDIA_FOLDER_TAXONOMY);
		}
		return;
	}

	wp_set_object_terms($attachment_id, [$folder_id], FS_MEDIA_FOLDER_TAXONOMY, false);
	$refresh_ids = array_values(array_unique(array_merge($before_ids, [$folder_id])));
	wp_update_term_count_now($refresh_ids, FS_MEDIA_FOLDER_TAXONOMY);
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
	$settings['library']['fs_media_folder_id'] = $folder_id;
	$settings['query']['fs_media_folder_id'] = $folder_id;

	return $settings;
});

/**
 * Shared Media Library / modal: folders panel visibility (one localStorage key for both UIs).
 */
add_action('admin_head', function (): void {
	if (!is_admin()) {
		return;
	}
?>
	<script>
		(function() {
			if (window.fsMediaFolderPanel) {
				return;
			}
			var key = 'fsMediaFoldersSidebarCollapsed';

			function read() {
				try {
					if (window.localStorage) {
						return window.localStorage.getItem(key) === '1';
					}
				} catch (err) {}
				return false;
			}

			function write(v) {
				try {
					if (window.localStorage) {
						window.localStorage.setItem(key, v ? '1' : '0');
					}
				} catch (err) {}
			}

			function applyToDom(collapsed) {
				var i;
				var layout = document.querySelector('.upload-php .fs-media-folders-layout');
				if (layout) {
					if (collapsed) {
						layout.classList.add('is-collapsed');
					} else {
						layout.classList.remove('is-collapsed');
					}
				}
				var toggles = document.querySelectorAll('.fs-media-folders-toggle');
				for (i = 0; i < toggles.length; i++) {
					var t = toggles[i];
					if (collapsed) {
						t.classList.remove('is-active');
						t.setAttribute('aria-pressed', 'false');
						t.setAttribute('title', '<?= esc_js(__('Show folders panel', 'fromscratch')) ?>');
					} else {
						t.classList.add('is-active');
						t.setAttribute('aria-pressed', 'true');
						t.setAttribute('title', '<?= esc_js(__('Hide folders panel', 'fromscratch')) ?>');
					}
				}
				var browsers = document.querySelectorAll('.media-modal .attachments-browser.fs-modal-sidebar-layout');
				for (i = 0; i < browsers.length; i++) {
					var b = browsers[i];
					if (collapsed) {
						b.classList.add('is-folders-panel-collapsed');
					} else {
						b.classList.remove('is-folders-panel-collapsed');
					}
				}
			}

			function setCollapsed(v) {
				if (read() === v) {
					applyToDom(v);
					return;
				}
				write(v);
				applyToDom(v);
			}

			function toggle() {
				setCollapsed(!read());
			}
			document.addEventListener('click', function(e) {
				var btn = e.target && e.target.closest && e.target.closest('.fs-media-folders-toggle');
				if (!btn) {
					return;
				}
				e.preventDefault();
				toggle();
			});
			document.addEventListener('storage', function(e) {
				if (e && e.key === key) {
					applyToDom(read());
				}
			});
			window.fsMediaFolderPanel = {
				key: key,
				isCollapsed: read,
				setCollapsed: setCollapsed,
				applyFromStorage: function() {
					applyToDom(read());
				}
			};
			if (document.readyState === 'loading') {
				document.addEventListener('DOMContentLoaded', function onReady() {
					document.removeEventListener('DOMContentLoaded', onReady);
					applyToDom(read());
				}, false);
			} else {
				applyToDom(read());
			}
		})();
	</script>
<?php
}, 1);

/**
 * Media modal: folder sidebar + query integration (admin-wide footer; only runs when modal opens).
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
	$fs_modal_folders_config = [
		'terms' => $flat,
		'i18n' => [
			'heading' => __('Folders', 'fromscratch'),
			'allFiles' => __('All files', 'fromscratch'),
		],
	];
?>
	<script>
		(function(cfg) {
			var folders = cfg.terms || [];
			var L = cfg.i18n || {};

			function fsEsc(s) {
				return String(s)
					.replace(/&/g, '&amp;')
					.replace(/</g, '&lt;')
					.replace(/>/g, '&gt;');
			}
			(function(wp) {
				if (!wp || !wp.media || !wp.media.model || !wp.media.model.Query) {
					return;
				}
				if (wp.media.model.Query.prototype.__fsFolderQueryPatched) {
					return;
				}
				var originalInit = wp.media.model.Query.prototype.initialize;
				wp.media.model.Query.prototype.initialize = function() {
					originalInit.apply(this, arguments);
					if (this.props && typeof this.props.set === 'function') {
						this.props.set({
							fs_media_folder_id: this.props.get('fs_media_folder_id') || 0
						});
					}
				};
				var originalSync = wp.media.model.Query.prototype.sync;
				wp.media.model.Query.prototype.sync = function(method, model, options) {
					options = options || {};
					options.data = options.data || {};
					options.data.query = options.data.query || {};
					var props = (this.props && typeof this.props.toJSON === 'function') ? this.props.toJSON() : {};
					if (props.fs_media_folder_id) {
						options.data.query.fs_media_folder_id = props.fs_media_folder_id;
						options.data.fs_media_folder_id = props.fs_media_folder_id;
					} else {
						delete options.data.query.fs_media_folder_id;
						delete options.data.fs_media_folder_id;
					}
					return originalSync.call(this, method, model, options);
				};
				wp.media.model.Query.prototype.__fsFolderQueryPatched = true;
			})(window.wp);

			function getActiveProps() {
				if (!window.wp || !wp.media || !wp.media.frame) {
					return null;
				}
				var frame = wp.media.frame;
				if (frame.content && typeof frame.content.get === 'function') {
					var content = frame.content.get();
					if (content && content.collection && content.collection.props) {
						return content.collection.props;
					}
				}
				if (typeof frame.state === 'function') {
					var state = frame.state();
					if (state && typeof state.get === 'function') {
						var lib = state.get('library');
						if (lib && lib.props) {
							return lib.props;
						}
					}
				}
				return null;
			}

			function getActiveState() {
				if (!window.wp || !wp.media || !wp.media.frame || typeof wp.media.frame.state !== 'function') {
					return null;
				}
				var state = wp.media.frame.state();
				if (!state || typeof state.get !== 'function' || typeof state.set !== 'function') {
					return null;
				}
				return state;
			}

			function selectedFolderId() {
				var props = getActiveProps();
				if (!props || typeof props.get !== 'function') {
					return 0;
				}
				var raw = props.get('fs_media_folder_id');
				var id = parseInt(raw, 10);
				return isNaN(id) || id < 1 ? 0 : id;
			}

			function applyFolder(id) {
				var state = getActiveState();
				if (!state || !window.wp || !wp.media || typeof wp.media.query !== 'function') {
					return;
				}
				var folderId = id > 0 ? id : 0;
				var library = wp.media.query({
					post_type: 'attachment',
					post_status: 'inherit',
					orderby: 'date',
					order: 'DESC',
					per_page: 40,
					paged: 1,
					fs_media_folder_id: folderId
				});
				state.set('library', library);
				if (window.wp.media.frame && wp.media.frame.content && typeof wp.media.frame.content.render === 'function') {
					wp.media.frame.content.render();
				}
			}

			function ensureModalFoldersToolbarToggle() {
				var i;
				var browsers = document.querySelectorAll('.media-modal .attachments-browser');
				for (i = 0; i < browsers.length; i++) {
					var browser = browsers[i];
					var bar = browser.querySelector('.media-toolbar');
					if (!bar) {
						continue;
					}
					if (bar.querySelector('.fs-media-folders-toggle[data-fs-toggle-context="modal"]')) {
						continue;
					}
					var secondary = bar.querySelector('.media-toolbar-secondary');
					var btn = document.createElement('button');
					btn.type = 'button';
					btn.className = 'button fs-media-folders-toggle';
					btn.setAttribute('data-fs-toggle-context', 'modal');
					btn.setAttribute('aria-pressed', 'true');
					btn.setAttribute('title', '<?= esc_js(__('Hide folders panel', 'fromscratch')) ?>');
					btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor"><path d="M160-160q-33 0-56.5-23.5T80-240v-480q0-33 23.5-56.5T160-800h207q16 0 30.5 6t25.5 17l57 57h320q33 0 56.5 23.5T880-640v400q0 33-23.5 56.5T800-160H160Z"/></svg>';
					if (secondary && secondary.parentNode === bar) {
						bar.insertBefore(btn, secondary);
					} else {
						bar.appendChild(btn);
					}
				}
				if (window.fsMediaFolderPanel && typeof window.fsMediaFolderPanel.applyFromStorage === 'function') {
					window.fsMediaFolderPanel.applyFromStorage();
				}
			}

			function injectModalFoldersPanel() {
				var browser = document.querySelector('.media-modal .attachments-browser');
				if (!browser) {
					return;
				}
				if (browser.querySelector('#fs-media-modal-folders')) {
					return;
				}
				browser.classList.add('fs-modal-sidebar-layout');
				var panel = document.createElement('div');
				panel.id = 'fs-media-modal-folders';
				panel.className = 'fs-media-modal-folders';
				var html = '<div class="fs-media-modal-folders__heading">' + fsEsc(L.heading || 'Folders') + '</div>';
				html += '<ul class="fs-media-modal-folders__list">';
				html += '<li class="fs-media-modal-folders__item"><button type="button" class="fs-media-modal-folder-btn" data-folder-id="0">' + fsEsc(L.allFiles || 'All files') + '</button></li>';
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
					html += '<li class="fs-media-modal-folders__item"><button type="button" class="fs-media-modal-folder-btn" data-folder-id="' + id + '" style="padding-left:' + pad + 'px">' + String(f.name).replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</button></li>';
				}
				html += '</ul>';
				panel.innerHTML = html;
				browser.appendChild(panel);

				function repaintActive() {
					var active = selectedFolderId();
					var buttons = panel.querySelectorAll('.fs-media-modal-folder-btn');
					for (var bi = 0; bi < buttons.length; bi++) {
						var b = buttons[bi];
						var bid = parseInt(b.getAttribute('data-folder-id') || '0', 10);
						if (bid === active) {
							b.classList.add('is-active');
						} else {
							b.classList.remove('is-active');
						}
					}
				}

				panel.addEventListener('click', function(e) {
					var btn = e.target.closest('.fs-media-modal-folder-btn');
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
			injectModalFoldersPanel();
			ensureModalFoldersToolbarToggle();
			var obs = new MutationObserver(function() {
				injectModalFoldersPanel();
				ensureModalFoldersToolbarToggle();
			});
			obs.observe(document.body, {
				childList: true,
				subtree: true
			});
		})(<?php echo wp_json_encode($fs_modal_folders_config); ?>);
	</script>
<?php
});

/**
 * Filter media attachments in list/grid mode by selected folder.
 */
add_filter('ajax_query_attachments_args', function (array $args): array {

	$folder_id = 0;
	$request = wp_unslash($_REQUEST);
	if (isset($request['query']) && is_array($request['query']) && isset($request['query']['fs_media_folder_id'])) {
		$folder_id = absint($request['query']['fs_media_folder_id']);
	}

	if ($folder_id <= 0 || !taxonomy_exists(FS_MEDIA_FOLDER_TAXONOMY)) {
		return $args;
	}
	$args['post_type'] = 'attachment';
	$args['post_status'] = 'inherit';

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
 * List view: add a "Folder" row action that opens a modal to assign the file to a folder.
 */
add_filter('media_row_actions', function (array $actions, WP_Post $post): array {
	if (!taxonomy_exists(FS_MEDIA_FOLDER_TAXONOMY) || $post->post_type !== 'attachment') {
		return $actions;
	}
	if (!current_user_can('edit_post', $post->ID)) {
		return $actions;
	}
	$terms = wp_get_object_terms($post->ID, FS_MEDIA_FOLDER_TAXONOMY, ['fields' => 'ids']);
	$current_id = !is_wp_error($terms) && !empty($terms) ? (int) $terms[0] : 0;
	$actions['fs_media_folder'] = sprintf(
		'<a href="#" class="fs-media-assign-folder-link" data-attachment-id="%d" data-current-folder="%d">%s</a>',
		$post->ID,
		$current_id,
		esc_html__('Folder', 'fromscratch')
	);
	return $actions;
}, 10, 2);

/**
 * AJAX: assign one attachment to a media folder (or clear folder when folder_id is 0).
 */
add_action('wp_ajax_fs_media_folder_assign', function (): void {
	if (!taxonomy_exists(FS_MEDIA_FOLDER_TAXONOMY) || !check_ajax_referer('fs_media_folder_assign', 'nonce', false)) {
		wp_send_json_error(['message' => __('Something went wrong.', 'fromscratch')], 403);
	}
	if (!current_user_can('upload_files')) {
		wp_send_json_error(['message' => __('You do not have permission to change folders.', 'fromscratch')], 403);
	}
	$attachment_id = isset($_POST['attachment_id']) ? absint($_POST['attachment_id']) : 0;
	$folder_id = isset($_POST['folder_id']) ? absint($_POST['folder_id']) : 0;
	if ($attachment_id <= 0 || get_post_type($attachment_id) !== 'attachment' || !current_user_can('edit_post', $attachment_id)) {
		wp_send_json_error(['message' => __('You cannot edit this item.', 'fromscratch')], 403);
	}
	if ($folder_id > 0) {
		$term = get_term($folder_id, FS_MEDIA_FOLDER_TAXONOMY);
		if (!$term instanceof WP_Term || is_wp_error($term)) {
			wp_send_json_error(['message' => __('Invalid folder.', 'fromscratch')], 400);
		}
	}
	fs_media_folders_set_attachment_folder($attachment_id, $folder_id);
	wp_send_json_success(['folder_id' => $folder_id]);
});

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
 * @param array<int,int> $display_counts
 */
function fs_media_folders_render_list(array $terms, array $display_counts, int $parent_id, int $depth, int $current_id, string $base_url, string $redirect_url): void
{
	foreach ($terms as $term) {
		if ((int) $term->parent !== $parent_id) {
			continue;
		}
		$term_id = (int) $term->term_id;
		$display_count = isset($display_counts[$term_id]) ? (int) $display_counts[$term_id] : (int) $term->count;
		$url = add_query_arg('fs_media_folder_id', (int) $term->term_id, $base_url);
		$item_classes = ['fs-media-folders-item', 'fs-media-folders-link'];
		if ($term_id === $current_id) {
			$item_classes[] = 'is-active';
		}
		$prefix = $depth > 0 ? str_repeat('– ', $depth) : '';
		$delete_url = add_query_arg([
			'action' => 'fs_media_folder_delete',
			'term_id' => $term_id,
			'redirect_to' => $redirect_url,
		], admin_url('admin-post.php'));
		$delete_url = wp_nonce_url($delete_url, 'fs_media_folder_delete_' . $term_id);
		echo '<li>';
		echo '<div class="' . esc_attr(implode(' ', $item_classes)) . '">';
		echo '<a class="fs-media-folders-link" href="' . esc_url($url) . '">';
		echo '<span class="name">' . esc_html($prefix . $term->name) . '</span>';
		echo ' <span class="count">(' . $display_count . ')</span>';
		echo '</a>';
		echo '<button type="button" class="fs-media-folder-delete-btn" aria-label="' . esc_attr__('Delete folder', 'fromscratch') . '" data-folder-name="' . esc_attr($term->name) . '" data-folder-count="' . $display_count . '" data-delete-url="' . esc_url($delete_url) . '"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor"><path d="M480-424 284-228q-11 11-28 11t-28-11q-11-11-11-28t11-28l196-196-196-196q-11-11-11-28t11-28q11-11 28-11t28 11l196 196 196-196q11-11 28-11t28 11q11 11 11 28t-11 28L536-480l196 196q11 11 11 28t-11 28q-11 11-28 11t-28-11L480-424Z"/></svg></button>';
		echo '</div>';
		echo '</li>';
		fs_media_folders_render_list($terms, $display_counts, $term_id, $depth + 1, $current_id, $base_url, $redirect_url);
	}
}

/**
 * Build aggregated folder counts so parents include all descendants.
 *
 * @param WP_Term[] $terms
 * @return array<int,int>
 */
function fs_media_folders_build_display_counts(array $terms): array
{
	$by_parent = [];
	$direct = [];
	foreach ($terms as $term) {
		if (!$term instanceof WP_Term) {
			continue;
		}
		$term_id = (int) $term->term_id;
		$parent_id = (int) $term->parent;
		$direct[$term_id] = (int) $term->count;
		if (!isset($by_parent[$parent_id])) {
			$by_parent[$parent_id] = [];
		}
		$by_parent[$parent_id][] = $term_id;
	}
	$totals = [];
	$walk = static function (int $term_id) use (&$walk, &$totals, $by_parent, $direct): int {
		if (isset($totals[$term_id])) {
			return $totals[$term_id];
		}
		$total = isset($direct[$term_id]) ? (int) $direct[$term_id] : 0;
		if (isset($by_parent[$term_id])) {
			foreach ($by_parent[$term_id] as $child_id) {
				$total += $walk((int) $child_id);
			}
		}
		$totals[$term_id] = $total;
		return $total;
	};
	foreach (array_keys($direct) as $term_id) {
		$walk((int) $term_id);
	}
	return $totals;
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
	$display_counts = fs_media_folders_build_display_counts($terms);

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
		<div class="fs-media-folders-header">
			<h2 class="fs-media-folders-title" id="fs-media-folders-heading"><?= esc_html__('Folders', 'fromscratch') ?></h2>
			<button type="button" class="components-button is-small is-tertiary fs-media-folders-add-btn" id="fs-media-folders-add-open" aria-expanded="false" aria-haspopup="dialog" aria-controls="fs-media-folder-create-modal">
				<div class="fs-media-folders-add-btn__icon">
					<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor">
						<path d="M440-440H240q-17 0-28.5-11.5T200-480q0-17 11.5-28.5T240-520h200v-200q0-17 11.5-28.5T480-760q17 0 28.5 11.5T520-720v200h200q17 0 28.5 11.5T760-480q0 17-11.5 28.5T720-440H520v200q0 17-11.5 28.5T480-200q-17 0-28.5-11.5T440-240v-200Z" />
					</svg>
				</div>
				<div class="fs-media-folders-add-btn__text"><?= esc_html__('Add', 'fromscratch') ?></div>
			</button>
		</div>
		<?php if ($message !== '') : ?>
			<div class="fs-media-folders-message <?= esc_attr($message_class) ?>"><?= esc_html($message) ?></div>
		<?php endif; ?>
		<ul class="fs-media-folders-list">
			<li>
				<?php
				$all_files_url = remove_query_arg(['fs_media_folder_id', 'fs_media_folder_error', 'fs_media_folder_success'], $base_url);
				$all_item_classes = ['fs-media-folders-item', 'fs-media-folders-item--all', 'fs-media-folders-link'];
				if ($folder_id <= 0) {
					$all_item_classes[] = 'is-active';
				}
				?>
				<div class="<?= esc_attr(implode(' ', $all_item_classes)) ?>">
					<button type="button" class="fs-media-folders-link fs-media-folders-link--all" data-fs-all-url="<?= esc_url($all_files_url) ?>">
						<?= esc_html__('All files', 'fromscratch') ?>
					</button>
				</div>
			</li>
			<?php
			$sidebar_redirect_url = remove_query_arg(['fs_media_folder_id', 'fs_media_folder_error', 'fs_media_folder_success'], $base_url);
			fs_media_folders_render_list($terms, $display_counts, 0, 0, $folder_id, $sidebar_redirect_url, $sidebar_redirect_url);
			?>
		</ul>
	</aside>
	<div id="fs-media-folder-delete-modal" class="fs-media-folder-delete-modal" aria-hidden="true">
		<div class="fs-media-folder-delete-backdrop" data-modal-close></div>
		<div class="fs-media-folder-delete-dialog" role="dialog" aria-modal="true" aria-labelledby="fs-media-folder-delete-title">
			<h2 id="fs-media-folder-delete-title"><?= esc_html__('Delete folder', 'fromscratch') ?></h2>
			<p id="fs-media-folder-delete-text"></p>
			<div class="fs-media-folder-delete-actions">
				<button type="button" class="button" data-modal-close><?= esc_html__('Cancel', 'fromscratch') ?></button>
				<a href="#" class="button button-primary button-link-delete" id="fs-media-folder-delete-confirm"><?= esc_html__('Delete folder', 'fromscratch') ?></a>
			</div>
		</div>
	</div>
	<div id="fs-media-folder-assign-modal" class="fs-media-folder-assign-modal" aria-hidden="true" data-fs-assign-nonce="<?= esc_attr(wp_create_nonce('fs_media_folder_assign')) ?>" data-fs-ajax-url="<?= esc_url(admin_url('admin-ajax.php')) ?>">
		<div class="fs-media-folder-assign-backdrop" data-modal-close></div>
		<div class="fs-media-folder-assign-dialog" role="dialog" aria-modal="true" aria-labelledby="fs-media-folder-assign-title" aria-describedby="fs-media-folder-assign-desc">
			<h2 id="fs-media-folder-assign-title"><?= esc_html__('Add to folder', 'fromscratch') ?></h2>
			<p id="fs-media-folder-assign-desc" class="description"><?= esc_html__('Choose a folder for this file. You can clear the folder by selecting “No folder”.', 'fromscratch') ?></p>
			<p>
				<label for="fs_media_assign_folder_id" class="screen-reader-text"><?= esc_html__('Folder', 'fromscratch') ?></label>
				<?php
				wp_dropdown_categories([
					'taxonomy' => FS_MEDIA_FOLDER_TAXONOMY,
					'name' => 'fs_media_assign_folder_id',
					'id' => 'fs_media_assign_folder_id',
					'orderby' => 'name',
					'hide_empty' => false,
					'hierarchical' => true,
					'show_option_none' => __('No folder', 'fromscratch'),
					'option_none_value' => '0',
					'value_field' => 'term_id',
				]);
				?>
			</p>
			<p class="fs-media-folder-assign-error" id="fs-media-folder-assign-error" role="alert" hidden></p>
			<div class="fs-media-folder-assign-actions">
				<button type="button" class="button" data-modal-close><?= esc_html__('Cancel', 'fromscratch') ?></button>
				<button type="button" class="button button-primary" id="fs-media-folder-assign-save"><?= esc_html__('Save', 'fromscratch') ?></button>
			</div>
		</div>
	</div>
	<div id="fs-media-folder-create-modal" class="fs-media-folder-create-modal" aria-hidden="true">
		<div class="fs-media-folder-create-backdrop" data-modal-close></div>
		<div class="fs-media-folder-create-dialog" role="dialog" aria-modal="true" aria-labelledby="fs-media-folder-create-title">
			<h2 id="fs-media-folder-create-title"><?= esc_html__('Add folder', 'fromscratch') ?></h2>
			<form method="post" action="<?= esc_url(admin_url('admin-post.php')) ?>" class="fs-media-folders-create" id="fs-media-folders-create-form">
				<input type="hidden" name="action" value="fs_media_folder_create">
				<input type="hidden" name="redirect_to" value="<?= esc_attr(remove_query_arg(['fs_media_folder_error', 'fs_media_folder_success'], $base_url)) ?>">
				<?php wp_nonce_field('fs_media_folder_create'); ?>
				<p>
					<label for="fs_media_folder_name" class="screen-reader-text"><?= esc_html__('Folder name', 'fromscratch') ?></label>
					<input type="text" name="fs_media_folder_name" id="fs_media_folder_name" class="regular-text" style="width:100%;" placeholder="<?= esc_attr__('New folder name', 'fromscratch') ?>" required autocomplete="off">
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
				<div class="fs-media-folder-create-actions">
					<button type="button" class="button" data-modal-close><?= esc_html__('Cancel', 'fromscratch') ?></button>
					<button type="submit" class="button button-primary"><?= esc_html__('Create folder', 'fromscratch') ?></button>
				</div>
			</form>
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

			var toggleButton = document.createElement('button');
			toggleButton.type = 'button';
			toggleButton.className = 'button fs-media-folders-toggle is-active';
			toggleButton.setAttribute('data-fs-toggle-context', 'upload');
			toggleButton.setAttribute('aria-pressed', 'true');
			toggleButton.setAttribute('title', '<?= esc_js(__('Hide folders panel', 'fromscratch')) ?>');
			toggleButton.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor"><path d="M160-160q-33 0-56.5-23.5T80-240v-480q0-33 23.5-56.5T160-800h207q16 0 30.5 6t25.5 17l57 57h320q33 0 56.5 23.5T880-640v400q0 33-23.5 56.5T800-160H160Z"/></svg>';

			/*
			 * List view outputs .view-switch in the initial HTML. Grid (#wp-media-grid) does not:
			 * media-grid.js injects it later, so we always need a fallback anchor.
			 */
			function placeUploadFoldersToggleNextToViewSwitch() {
				var vs = wrap.querySelector('.view-switch');
				if (!vs || !vs.parentNode) {
					return false;
				}
				if (toggleButton.nextSibling === vs) {
					return true;
				}
				vs.parentNode.insertBefore(toggleButton, vs);
				return true;
			}
			if (!placeUploadFoldersToggleNextToViewSwitch()) {
				if (headerEnd && headerEnd.parentNode) {
					headerEnd.parentNode.insertBefore(toggleButton, headerEnd);
				} else if (addButton && addButton.parentNode) {
					addButton.parentNode.insertBefore(toggleButton, addButton.nextSibling);
				} else {
					wrap.insertBefore(toggleButton, layout);
				}
			}
			function retryPlaceToggleNearViewSwitch() {
				placeUploadFoldersToggleNextToViewSwitch();
			}
			retryPlaceToggleNearViewSwitch();
			setTimeout(retryPlaceToggleNearViewSwitch, 0);
			setTimeout(retryPlaceToggleNearViewSwitch, 200);
			setTimeout(retryPlaceToggleNearViewSwitch, 800);
			if (window.jQuery) {
				window.jQuery(wrap).on('wp-media-grid-ready', retryPlaceToggleNearViewSwitch);
			}
			if (typeof MutationObserver !== 'undefined') {
				var togglePlaceObserver = new MutationObserver(function() {
					if (placeUploadFoldersToggleNextToViewSwitch()) {
						togglePlaceObserver.disconnect();
					}
				});
				togglePlaceObserver.observe(wrap, {
					childList: true,
					subtree: true
				});
				setTimeout(function() {
					togglePlaceObserver.disconnect();
				}, 15000);
			}
			if (window.fsMediaFolderPanel && typeof window.fsMediaFolderPanel.applyFromStorage === 'function') {
				window.fsMediaFolderPanel.applyFromStorage();
			}

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
			var assignModal = document.getElementById('fs-media-folder-assign-modal');
			var assignFolderSelect = document.getElementById('fs_media_assign_folder_id');
			var assignSaveBtn = document.getElementById('fs-media-folder-assign-save');
			var assignError = document.getElementById('fs-media-folder-assign-error');
			var assignAttachmentId = 0;
			var assignTriggerLink = null;
			var createModal = document.getElementById('fs-media-folder-create-modal');
			var createOpenBtn = document.getElementById('fs-media-folders-add-open');
			var folderNameInput = document.getElementById('fs_media_folder_name');

			function closeModal() {
				if (!modal || !modalConfirm) {
					return;
				}
				modal.classList.remove('is-open');
				modal.setAttribute('aria-hidden', 'true');
				modalConfirm.setAttribute('href', '#');
			}

			function openModal(name, count, deleteUrl) {
				if (!modal || !modalText || !modalConfirm) {
					return;
				}
				closeAssignModal();
				closeCreateModal();
				var countText = '';
				countText += <?= wp_json_encode(__('Delete folder "%s"?', 'fromscratch'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>.replace('%s', name);
				countText += ' ';
				countText += parseInt(count, 10) > 0 ?
					<?= wp_json_encode(__('This will also remove folder assignments from the contained files.', 'fromscratch'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?> :
					<?= wp_json_encode(__('The folder is empty.', 'fromscratch'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
				modalText.textContent = countText;
				modalConfirm.setAttribute('href', deleteUrl);
				modal.classList.add('is-open');
				modal.setAttribute('aria-hidden', 'false');
			}

			function closeCreateModal() {
				if (!createModal) {
					return;
				}
				var wasOpen = createModal.classList.contains('is-open');
				createModal.classList.remove('is-open');
				createModal.setAttribute('aria-hidden', 'true');
				if (createOpenBtn) {
					createOpenBtn.setAttribute('aria-expanded', 'false');
					if (wasOpen) {
						createOpenBtn.focus();
					}
				}
			}

			function openCreateModal() {
				if (!createModal || !createOpenBtn) {
					return;
				}
				closeModal();
				closeAssignModal();
				createModal.classList.add('is-open');
				createModal.setAttribute('aria-hidden', 'false');
				createOpenBtn.setAttribute('aria-expanded', 'true');
				if (folderNameInput) {
					folderNameInput.value = '';
					folderNameInput.focus();
				}
				var parentSel = document.getElementById('fs_media_folder_parent');
				if (parentSel) {
					parentSel.value = '0';
				}
			}

			function closeAssignModal() {
				if (!assignModal) {
					return;
				}
				var wasOpen = assignModal.classList.contains('is-open');
				assignModal.classList.remove('is-open');
				assignModal.setAttribute('aria-hidden', 'true');
				assignAttachmentId = 0;
				if (assignError) {
					assignError.textContent = '';
					assignError.hidden = true;
				}
				if (wasOpen && assignTriggerLink && typeof assignTriggerLink.focus === 'function') {
					assignTriggerLink.focus();
				}
				assignTriggerLink = null;
			}

			function openAssignModal(linkEl) {
				if (!assignModal || !assignFolderSelect || !linkEl) {
					return;
				}
				closeModal();
				closeCreateModal();
				assignTriggerLink = linkEl;
				assignAttachmentId = parseInt(linkEl.getAttribute('data-attachment-id') || '0', 10) || 0;
				var cur = parseInt(linkEl.getAttribute('data-current-folder') || '0', 10) || 0;
				assignFolderSelect.value = String(cur);
				if (assignError) {
					assignError.textContent = '';
					assignError.hidden = true;
				}
				assignModal.classList.add('is-open');
				assignModal.setAttribute('aria-hidden', 'false');
				assignFolderSelect.focus();
			}

			sidebar.addEventListener('click', function(e) {
				var allNav = e.target.closest('button.fs-media-folders-link--all[data-fs-all-url]');
				if (allNav) {
					e.preventDefault();
					var allUrl = allNav.getAttribute('data-fs-all-url');
					if (allUrl) {
						window.location.href = allUrl;
					}
					return;
				}
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
			if (modal) {
				modal.addEventListener('click', function(e) {
					if (e.target && e.target.hasAttribute('data-modal-close')) {
						closeModal();
					}
				});
			}
			if (createModal) {
				createModal.addEventListener('click', function(e) {
					if (e.target && e.target.hasAttribute('data-modal-close')) {
						closeCreateModal();
					}
				});
			}
			if (createOpenBtn) {
				createOpenBtn.addEventListener('click', function() {
					openCreateModal();
				});
			}
			document.addEventListener('click', function(e) {
				var folderLink = e.target && e.target.closest && e.target.closest('a.fs-media-assign-folder-link');
				if (!folderLink) {
					return;
				}
				e.preventDefault();
				openAssignModal(folderLink);
			});
			if (assignModal) {
				assignModal.addEventListener('click', function(e) {
					if (e.target && e.target.hasAttribute('data-modal-close')) {
						closeAssignModal();
					}
				});
			}
			if (assignSaveBtn && assignModal) {
				assignSaveBtn.addEventListener('click', function() {
					if (!assignFolderSelect || assignAttachmentId <= 0) {
						return;
					}
					var nonce = assignModal.getAttribute('data-fs-assign-nonce') || '';
					var ajaxUrl = assignModal.getAttribute('data-fs-ajax-url') || (typeof ajaxurl !== 'undefined' ? ajaxurl : '');
					if (!ajaxUrl || !nonce) {
						return;
					}
					var folderVal = assignFolderSelect.value || '0';
					if (assignError) {
						assignError.textContent = '';
						assignError.hidden = true;
					}
					assignSaveBtn.disabled = true;
					var params = new URLSearchParams();
					params.set('action', 'fs_media_folder_assign');
					params.set('nonce', nonce);
					params.set('attachment_id', String(assignAttachmentId));
					params.set('folder_id', folderVal);
					fetch(ajaxUrl, {
						method: 'POST',
						credentials: 'same-origin',
						headers: {
							'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
						},
						body: params.toString()
					}).then(function(r) {
						return r.json();
					}).then(function(payload) {
						assignSaveBtn.disabled = false;
						if (payload && payload.success) {
							var newId = payload.data && typeof payload.data.folder_id !== 'undefined' ? parseInt(payload.data.folder_id, 10) || 0 : parseInt(folderVal, 10) || 0;
							if (assignTriggerLink) {
								assignTriggerLink.setAttribute('data-current-folder', String(newId));
							}
							closeAssignModal();
						} else {
							var msg = <?= wp_json_encode(__('Could not update folder.', 'fromscratch'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
							if (payload && payload.data) {
								if (typeof payload.data.message === 'string' && payload.data.message) {
									msg = payload.data.message;
								} else if (payload.data[0] && typeof payload.data[0] === 'string') {
									msg = payload.data[0];
								}
							}
							if (assignError) {
								assignError.textContent = msg;
								assignError.hidden = false;
							}
						}
					}).catch(function() {
						assignSaveBtn.disabled = false;
						if (assignError) {
							assignError.textContent = <?= wp_json_encode(__('Could not update folder.', 'fromscratch'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
							assignError.hidden = false;
						}
					});
				});
			}
			document.addEventListener('keydown', function(e) {
				if (e.key !== 'Escape') {
					return;
				}
				if (assignModal && assignModal.classList.contains('is-open')) {
					closeAssignModal();
				} else if (createModal && createModal.classList.contains('is-open')) {
					closeCreateModal();
				} else if (modal && modal.classList.contains('is-open')) {
					closeModal();
				}
			});
		})();
	</script>
<?php
});
