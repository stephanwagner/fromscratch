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
