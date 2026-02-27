<?php

defined('ABSPATH') || exit;

/** User meta key for the developer flag */
const FS_USER_META_DEVELOPER = 'fromscratch_developer';

/**
 * Whether a user has developer rights.
 *
 * @param int $user_id User ID.
 * @return bool
 */
function fs_is_developer_user(int $user_id): bool
{
  return (string) get_user_meta($user_id, FS_USER_META_DEVELOPER, true) === '1';
}

/**
 * Show Developer section on Edit User / Profile only when the user is a developer.
 */
add_action('show_user_profile', 'fs_developer_user_profile_section');
add_action('edit_user_profile', 'fs_developer_user_profile_section');

function fs_developer_user_profile_section(WP_User $user): void
{
  if (!fs_is_developer_user((int) $user->ID)) {
    return;
  }
  if (!current_user_can('edit_users')) {
    return;
  }
  $checked = fs_is_developer_user((int) $user->ID) ? ' checked' : '';
  ?>
  <h2><?= esc_html(fs_t('DEVELOPER_SECTION_TITLE')) ?></h2>
  <table class="form-table" role="presentation">
    <tr>
      <th scope="row"><?= esc_html(fs_t('DEVELOPER_RIGHTS_LABEL')) ?></th>
      <td>
        <label>
          <input type="checkbox" name="fromscratch_developer" value="1"<?= $checked ?>>
          <?= esc_html(fs_t('DEVELOPER_RIGHTS_CHECKBOX')) ?>
        </label>
      </td>
    </tr>
  </table>
  <?php
}

/**
 * Save Developer checkbox on profile update.
 */
add_action('personal_options_update', 'fs_developer_user_profile_update');
add_action('edit_user_profile_update', 'fs_developer_user_profile_update');

function fs_developer_user_profile_update(int $user_id): void
{
  if (!current_user_can('edit_users')) {
    return;
  }
  $user = get_userdata($user_id);
  if (!$user || !fs_is_developer_user($user_id)) {
    return;
  }
  if (isset($_POST['fromscratch_developer']) && $_POST['fromscratch_developer'] === '1') {
    update_user_meta($user_id, FS_USER_META_DEVELOPER, '1');
  } else {
    delete_user_meta($user_id, FS_USER_META_DEVELOPER);
  }
}
