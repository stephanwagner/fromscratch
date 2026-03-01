<?php

defined('ABSPATH') || exit;

/** User meta key for the developer flag */
const FS_USER_META_DEVELOPER = 'fromscratch_developer';

/**
 * Whether a user has the Administrator role.
 *
 * @param int $user_id User ID.
 * @return bool
 */
function fs_user_is_administrator(int $user_id): bool
{
  $user = get_userdata($user_id);
  if (!$user || !isset($user->roles) || !is_array($user->roles)) {
    return false;
  }
  return in_array('administrator', $user->roles, true);
}

/**
 * Whether a user has developer rights (and is an administrator; only admins can be developers).
 *
 * @param int $user_id User ID.
 * @return bool
 */
function fs_is_developer_user(int $user_id): bool
{
  if (!fs_user_is_administrator($user_id)) {
    return false;
  }
  return (string) get_user_meta($user_id, FS_USER_META_DEVELOPER, true) === '1';
}

/**
 * User IDs of all users who have developer rights (only administrators, only existing users).
 *
 * @return int[]
 */
function fs_get_developer_user_ids(): array
{
  global $wpdb;
  $ids = $wpdb->get_col($wpdb->prepare(
    "SELECT um.user_id FROM {$wpdb->usermeta} um
    INNER JOIN {$wpdb->users} u ON u.ID = um.user_id
    WHERE um.meta_key = %s AND um.meta_value = %s",
    FS_USER_META_DEVELOPER,
    '1'
  ));
  $ids = array_map('intval', (array) $ids);
  return array_values(array_filter($ids, 'fs_user_is_administrator'));
}

/**
 * Whether removing developer rights from this user would leave zero developers.
 *
 * @param int $user_id User ID that would lose developer rights.
 * @return bool
 */
function fs_is_last_developer(int $user_id): bool
{
  $developer_ids = fs_get_developer_user_ids();
  return count($developer_ids) === 1 && in_array($user_id, $developer_ids, true);
}

/**
 * Show Developer section only when the current user is a developer (so only they can set developer rights).
 */
add_action('show_user_profile', 'fs_developer_user_profile_section');
add_action('edit_user_profile', 'fs_developer_user_profile_section');

function fs_developer_user_profile_section(WP_User $user): void
{
  if (!current_user_can('edit_users')) {
    return;
  }
  if (!fs_is_developer_user((int) get_current_user_id())) {
    return;
  }
  if (!fs_user_is_administrator((int) $user->ID)) {
    return;
  }
  $edited_id = (int) $user->ID;
  $checked = fs_is_developer_user($edited_id) ? ' checked' : '';
  $is_last = fs_is_last_developer($edited_id);
  $disabled = $is_last ? ' disabled' : '';
  ?>
  <h2><?= esc_html__('Developer', 'fromscratch') ?></h2>
  <table class="form-table" role="presentation">
    <tr>
      <th scope="row"><?= esc_html__('Developer rights', 'fromscratch') ?></th>
      <td>
        <label>
          <input type="checkbox" name="fromscratch_developer" value="1"<?= $checked ?><?= $disabled ?>>
          <?= esc_html__('This user has developer rights', 'fromscratch') ?>
        </label>
        <?php if ($is_last) { ?>
          <input type="hidden" name="fromscratch_developer" value="1">
          <p class="description"><?= esc_html__('At least one user must have developer rights. Add another developer before removing yours.', 'fromscratch') ?></p>
        <?php } ?>
      </td>
    </tr>
  </table>
  <?php
}

/**
 * In the Users list, show "Developer" instead of the role name (e.g. Administrator) when the user has developer rights.
 */
add_filter('get_role_list', function (array $role_list, WP_User $user): array {
  if (!fs_user_is_administrator((int) $user->ID)) {
    return $role_list;
  }
  if ((string) get_user_meta($user->ID, FS_USER_META_DEVELOPER, true) !== '1') {
    return $role_list;
  }
  $developer_label = __('Developer', 'fromscratch');
  return array_combine(array_keys($role_list), array_fill(0, count($role_list), $developer_label));
}, 10, 2);

/**
 * Save Developer checkbox. Only developers can change it; never allow removing the last developer.
 */
add_action('personal_options_update', 'fs_developer_user_profile_update');
add_action('edit_user_profile_update', 'fs_developer_user_profile_update');

function fs_developer_user_profile_update(int $user_id): void
{
  if (!current_user_can('edit_users')) {
    return;
  }
  if (!fs_is_developer_user((int) get_current_user_id())) {
    return;
  }
  $user = get_userdata($user_id);
  if (!$user) {
    return;
  }
  if (!fs_user_is_administrator($user_id)) {
    delete_user_meta($user_id, FS_USER_META_DEVELOPER);
    return;
  }
  if (fs_is_last_developer($user_id)) {
    return;
  }
  if (isset($_POST['fromscratch_developer']) && $_POST['fromscratch_developer'] === '1') {
    update_user_meta($user_id, FS_USER_META_DEVELOPER, '1');
  } else {
    delete_user_meta($user_id, FS_USER_META_DEVELOPER);
  }
}

/**
 * Default admin access: who can see which admin pages. Keys match Settings → Theme → Developer → User rights.
 *
 * @return array<string, array{admin: int, developer: int}>
 */
function fs_admin_access_defaults(): array
{
  return [
    'plugins' => ['admin' => 0, 'developer' => 1],
    'options_general' => ['admin' => 1, 'developer' => 1],
    'options_writing' => ['admin' => 0, 'developer' => 1],
    'options_reading' => ['admin' => 0, 'developer' => 1],
    'options_media' => ['admin' => 0, 'developer' => 1],
    'options_permalink' => ['admin' => 0, 'developer' => 1],
    'options_privacy' => ['admin' => 1, 'developer' => 1],
    'tools' => ['admin' => 0, 'developer' => 1],
    'themes' => ['admin' => 0, 'developer' => 1],
    'theme_settings_general' => ['admin' => 1, 'developer' => 1],
    'theme_settings_security' => ['admin' => 1, 'developer' => 1],
    'theme_settings_texts' => ['admin' => 1, 'developer' => 1],
    'theme_settings_design' => ['admin' => 1, 'developer' => 1],
    'theme_settings_css' => ['admin' => 1, 'developer' => 1],
    'theme_settings_redirects' => ['admin' => 1, 'developer' => 1],
  ];
}

/**
 * Whether the current user (admin or developer) is allowed to access the given item.
 *
 * @param string $item Key from fs_admin_access_defaults(), e.g. 'plugins', 'options_reading'.
 * @return bool
 */
function fs_admin_can_access(string $item): bool
{
  if (!function_exists('fs_setup_completed') || !fs_setup_completed()) {
    return true;
  }
  $access = get_option('fromscratch_admin_access', fs_admin_access_defaults());
  if (!is_array($access) || !isset($access[$item]) || !is_array($access[$item])) {
    $defaults = fs_admin_access_defaults();
    $access = isset($defaults[$item]) ? $defaults[$item] : ['admin' => 0, 'developer' => 1];
  } else {
    $access = $access[$item];
  }
  $is_dev = fs_is_developer_user((int) get_current_user_id());
  $key = $is_dev ? 'developer' : 'admin';
  return !empty($access[$key]);
}

/**
 * Restrict admin by developer-toggled access. Block direct load when access is disabled.
 */
add_action('admin_init', function () {
  if (!is_user_logged_in() || !function_exists('fs_setup_completed') || !fs_setup_completed()) {
    return;
  }
  if (defined('DOING_AJAX') && DOING_AJAX) {
    return;
  }
  global $pagenow;
  $item = null;
  if ($pagenow === 'plugins.php') {
    $item = 'plugins';
  } elseif ($pagenow === 'tools.php') {
    $item = 'tools';
  } elseif (in_array($pagenow, ['themes.php', 'site-editor.php', 'theme-editor.php'], true)) {
    $item = 'themes';
  } elseif ($pagenow === 'options-general.php') {
    $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
    $map = [
      '' => 'options_general',
      'options-general.php' => 'options_general',
      'options-writing.php' => 'options_writing',
      'options-reading.php' => 'options_reading',
      'options-media.php' => 'options_media',
      'options-permalink.php' => 'options_permalink',
      'options-privacy.php' => 'options_privacy',
    ];
    $item = isset($map[$page]) ? $map[$page] : 'options_general';
  }
  if ($item !== null && !fs_admin_can_access($item)) {
    wp_safe_redirect(admin_url());
    exit;
  }
}, 20);

add_action('admin_menu', function () {
  if (!function_exists('fs_setup_completed') || !fs_setup_completed()) {
    return;
  }

  // Move Menus from Appearance to Settings for everyone (direct link to nav-menus.php), after Theme settings.
  global $submenu;
  remove_submenu_page('themes.php', 'nav-menus.php');
  $menus_item = [
    __('Menus'),
    'edit_theme_options',
    admin_url('nav-menus.php'),
  ];
  $settings = &$submenu['options-general.php'];
  $insert_at = null;
  foreach ($settings as $i => $item) {
    if (isset($item[2]) && $item[2] === 'fs-theme-settings') {
      $insert_at = $i + 1;
      break;
    }
  }
  if ($insert_at !== null) {
    array_splice($settings, $insert_at, 0, [$menus_item]);
  } else {
    $settings[] = $menus_item;
  }

  if (!is_user_logged_in()) {
    return;
  }
  if (!fs_admin_can_access('tools')) {
    remove_menu_page('tools.php');
  }
  if (!fs_admin_can_access('plugins')) {
    remove_menu_page('plugins.php');
  }
  if (!fs_admin_can_access('themes')) {
    remove_menu_page('themes.php');
  }
  if (!fs_admin_can_access('options_general')) {
    remove_submenu_page('options-general.php', 'options-general.php');
  }
  if (!fs_admin_can_access('options_reading')) {
    remove_submenu_page('options-general.php', 'options-reading.php');
  }
  if (!fs_admin_can_access('options_writing')) {
    remove_submenu_page('options-general.php', 'options-writing.php');
  }
  if (!fs_admin_can_access('options_media')) {
    remove_submenu_page('options-general.php', 'options-media.php');
  }
  if (!fs_admin_can_access('options_permalink')) {
    remove_submenu_page('options-general.php', 'options-permalink.php');
  }
  if (!fs_admin_can_access('options_privacy')) {
    remove_submenu_page('options-general.php', 'options-privacy.php');
  }
}, 999);

add_action('load-nav-menus.php', function () {
  global $parent_file, $submenu_file;
  $parent_file = 'options-general.php';
  $submenu_file = admin_url('nav-menus.php');
});

/**
 * On Settings → General, hide specific rows for non-developers (e.g. WordPress Address URL).
 * Also prevent saving those options so they cannot be changed via tampered requests.
 */
add_action('load-options-general.php', function () {
  if (is_multisite() || fs_is_developer_user((int) get_current_user_id())) {
    return;
  }
  $hide_field_ids = ['siteurl', 'home', 'users_can_register', 'default_role'];
  add_action('admin_head', function () use ($hide_field_ids) {
    $selectors = array_map(function ($id) {
      return '.form-table tr:has(#' . esc_attr($id) . ')';
    }, $hide_field_ids);
    echo '<style>', implode(', ', $selectors), ' { display: none !important; }</style>';
  }, 1);
});

add_action('admin_init', function () {
  if (fs_is_developer_user((int) get_current_user_id())) {
    return;
  }
  $protected_options = [
    'siteurl',
    'home',
    'users_can_register',
    'default_role',
    'show_on_front',
    'page_on_front',
    'page_for_posts',
  ];
  foreach ($protected_options as $option) {
    add_filter('pre_update_option_' . $option, function ($value, $old_value) {
      return $old_value;
    }, 10, 2);
  }
}, 1);
