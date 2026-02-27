<?php

defined('ABSPATH') || exit;

require_once __DIR__ . '/install-system.php';

/**
 * Redirect to install page only when setup is not completed and user tries to access Theme settings, Tools, Users, or their subpages.
 * On all other admin pages we show the notice only (no redirect, no skip).
 */
add_action('admin_init', function () {
  if (fs_setup_completed()) {
    return;
  }
  global $pagenow;
  global $parent_file;
  $on_settings = ($pagenow === 'options-general.php') || ($parent_file === 'options-general.php');
  $on_tools = ($pagenow === 'tools.php') || ($parent_file === 'tools.php');
  $on_users = ($pagenow === 'users.php') || ($parent_file === 'users.php') || ($pagenow === 'user-new.php') || ($pagenow === 'user-edit.php') || ($pagenow === 'profile.php');
  $on_others = ($pagenow === 'theme-editor.php') || ($pagenow === 'nav-menus.php') || ($pagenow === 'customize.php') || ($pagenow === 'site-editor.php');
  if (!$on_settings && !$on_tools && !$on_users && !$on_others) {
    return;
  }
  if (defined('DOING_AJAX') && DOING_AJAX) {
    return;
  }
  wp_safe_redirect(admin_url('themes.php?page=fromscratch-install'));
  exit;
}, 5);

/**
 * After switching to this theme, redirect to install page if setup not completed.
 */
add_action('after_switch_theme', function () {
  if (fs_setup_completed()) {
    return;
  }
  if (get_stylesheet() !== 'fromscratch') {
    return;
  }
  wp_safe_redirect(admin_url('themes.php?page=fromscratch-install'));
  exit;
});

/**
 * After install: if user chose "Log in as developer user", switch to the new dev user and redirect to dashboard.
 */
add_action('admin_init', function () {
  $user_id = get_transient('fromscratch_login_as_dev');
  if (!$user_id || !isset($_GET['page']) || $_GET['page'] !== 'fromscratch-install' || !isset($_GET['fromscratch_success'])) {
    return;
  }
  $user = get_userdata($user_id);
  if (!$user) {
    delete_transient('fromscratch_login_as_dev');
    return;
  }
  delete_transient('fromscratch_login_as_dev');
  wp_logout();
  wp_clear_auth_cookie();
  wp_set_current_user($user_id);
  wp_set_auth_cookie($user_id, true);
  wp_safe_redirect(admin_url());
  exit;
}, 1);

/**
 * Add FromScratch installer to admin menu (when setup not completed).
 */
add_action('admin_menu', function () {
  if (fs_setup_completed() && !isset($_GET['fromscratch_success'])) {
    return;
  }
  add_theme_page(
    __('Install theme', 'fromscratch'),
    __('Install theme', 'fromscratch'),
    'manage_options',
    'fromscratch-install',
    'fs_render_installer'
  );
});

/**
 * Show FromScratch installer notice (when not on the install page; redirect usually sends users there).
 */
add_action('admin_notices', function () {
  if (fs_setup_completed()) {
    return;
  }

  $screen = get_current_screen();
  if ($screen && $screen->id === 'appearance_page_fromscratch-install') {
    return;
  }

  echo '<div class="notice notice-warning">';
  echo '<p><strong>' . esc_html__('FromScratch isn\'t set up yet.', 'fromscratch') . '</strong></p>';
  echo '<p>' . esc_html__('A one-time initialization is required to configure core options and activate essential system features.', 'fromscratch') . '</p>';
  echo '<p>';
  echo '<a href="' . esc_url(admin_url('themes.php?page=fromscratch-install')) . '" class="button button-primary">' . esc_html__('Go to installer', 'fromscratch') . '</a>';
  echo '</p>';
  echo '</div>';
});


/**
 * Render the FromScratch installer page (theme setup wizard).
 *
 * @return void
 */
function fs_render_installer(): void
{
  if (!current_user_can('manage_options')) {
    return;
  }

?>
  <div class="wrap">
    <h1><?= esc_html__('Install FromScratch', 'fromscratch') ?></h1>

    <?php if (get_option('fromscratch_install_success')) { ?>

      <div class="notice notice-success">
        <p><?= esc_html__('FromScratch is installed.', 'fromscratch') ?></p>
      </div>

      <p>
        <a
          href="<?php echo esc_url(admin_url()); ?>"
          class="button button-primary"><?= esc_html__('Go to dashboard', 'fromscratch') ?></a>
      </p>

    <?php } else { ?>
      <?php if (get_transient('fromscratch_install_error') === 'developer_required') {
        delete_transient('fromscratch_install_error');
        echo '<div class="notice notice-error"><p>' . esc_html__('At least one user must have developer rights. Either check "Has developer rights" for the current user or add another user and check it there.', 'fromscratch') . '</p></div>';
      } ?>
      <p>
        <?= esc_html__('Before getting started, a one-time initialization is required to configure essential system components and ensure a stable, high-performance environment.', 'fromscratch') ?>
      </p>

      <form method="post" autocomplete="off">
        <?php wp_nonce_field('fromscratch_install'); ?>

        <h2><?= esc_html__('Theme', 'fromscratch') ?></h2>

        <table class="form-table" role="presentation">
          <tr>
            <th scope="row">
              <label>
                <?= esc_html__('Theme name', 'fromscratch') ?>
              </label>
            </th>
            <td>
              <input type="text" name="theme[name]" value="<?= esc_attr(get_bloginfo('name')) ?>" class="regular-text">
            </td>
          </tr>
          <tr>
            <th scope="row">
              <label>
                <?= esc_html__('Theme folder', 'fromscratch') ?>
              </label>
            </th>
            <td>
              <input type="text" name="theme[slug]" value="<?= esc_attr(sanitize_title(get_bloginfo('name'))) ?>" class="regular-text">
              <p class="description"><?= esc_html__('Use only lowercase letters, numbers and hyphens.', 'fromscratch') ?></p>
            </td>
          </tr>
          <tr>
            <th scope="row">
              <label>
                <?= esc_html__('Theme description', 'fromscratch') ?>
              </label>
            </th>
            <td>
              <input type="text" name="theme[description]" value="<?= esc_attr(sprintf(__('Theme of the webpage %s.', 'fromscratch'), get_bloginfo('name'))) ?>" class="regular-text">
            </td>
          </tr>
          <tr>
            <th scope="row">
              <label for="theme_author"><?= esc_html__('Theme Author', 'fromscratch') ?></label>
            </th>
            <td>
              <input type="text" name="theme[author]" id="theme_author" value="" class="regular-text">
            </td>
          </tr>
          <tr>
            <th scope="row">
              <label for="theme_author_uri"><?= esc_html__('Theme Author URI', 'fromscratch') ?></label>
            </th>
            <td>
              <input type="url" name="theme[author_uri]" id="theme_author_uri" value="" class="regular-text">
            </td>
          </tr>
        </table>

        <hr>

        <h2><?= esc_html__('Media', 'fromscratch') ?></h2>

        <table class="form-table" role="presentation">
          <tr>
            <th scope="row"><?= esc_html__('Media sizes', 'fromscratch') ?></th>
            <td>
              <p style="margin-top: 0;">
                <label>
                  <input type="checkbox" name="install[media]" checked data-fs-checkbox-toggle="media">
                  <?= esc_html__('Set media sizes', 'fromscratch') ?>
                </label>
              </p>
              <p class="description"><?= esc_html__('Stores the values in WordPress media settings.', 'fromscratch') ?></p>
              <div data-fs-checkbox-toggle-content="media">
                <?php
                $install_media_sizes = [
                  'thumbnail' => ['name' => __('Thumbnail'), 'width' => 300, 'height' => 300],
                  'small' => ['name' => _x('Small', 'Image size', 'fromscratch'), 'width' => 600, 'height' => 600],
                  'medium' => ['name' => __('Medium'), 'width' => 1200, 'height' => 1200],
                  'large' => ['name' => __('Large'), 'width' => 2400, 'height' => 2400],
                ];
                foreach ($install_media_sizes as $slug => $size) {
                  $w = (int) $size['width'];
                  $h = (int) $size['height'];
                ?>
                  <p style="margin-bottom: 8px;">
                    <label>
                      <span style="display: inline-block; min-width: 120px;"><?= esc_html($size['name']) ?></span>
                      <input type="number" name="media[<?= esc_attr($slug) ?>][width]" value="<?= $w ?>" class="small-text" min="1" style="width: 72px;"> ×
                      <input type="number" name="media[<?= esc_attr($slug) ?>][height]" value="<?= $h ?>" class="small-text" min="0" style="width: 72px;"> px
                    </label>
                    <?php if ($slug === 'thumbnail') { ?>
                      <label style="margin-left: 12px;">
                        <input type="checkbox" name="media[thumbnail][crop]" value="1">
                        <?= esc_html__('Crop thumbnail to exact dimensions', 'fromscratch') ?>
                      </label>
                    <?php } ?>
                  </p>
                <?php
                }
                ?>
              </div>
            </td>
          </tr>
        </table>

        <hr>

        <h2><?= esc_html__('System', 'fromscratch') ?></h2>

        <table class="form-table" role="presentation">
          <tr>
            <th scope="row"><?= esc_html__('Permalinks', 'fromscratch') ?></th>
            <td>
              <p style="margin-top: 0;">
                <label>
                  <input type="checkbox" name="install[permalinks]" checked>
                  <?= esc_html__('Enable pretty permalinks', 'fromscratch') ?>
                </label>
              </p>
              <p class="description"><?= esc_html__('Sets the permalink structure to /%postname%/ so URLs look like /about/ instead of ?p=123.', 'fromscratch') ?></p>
            </td>
          </tr>

          <!-- Apache .htaccess -->
          <tr>
            <th scope="row"><?= esc_html__('Apache (.htaccess)', 'fromscratch') ?></th>
            <td>
              <p style="margin-top: 0;">
                <label>
                  <input type="checkbox" name="install[htaccess]" checked>
                  <?= esc_html__('Apply recommended rules to .htaccess', 'fromscratch') ?>
                </label>
              </p>
              <p class="description"><?= esc_html__('Writes Expires headers, removes Set-Cookie on static assets, and enables gzip/deflate in the WordPress root .htaccess.', 'fromscratch') ?></p>
              <?php
              $htaccess_config = fs_get_htaccess_config();
              if ($htaccess_config !== '') {
              ?>
                <details style="margin-top: 8px;">
                  <summary style="cursor: pointer;"><?= esc_html__('Show config', 'fromscratch') ?></summary>
                  <div style="margin-top: 8px;">
                    <textarea id="fs-htaccess-config" class="large-text code" rows="16" style="width: 100%; max-width: 640px; font-size: 12px; font-family: monospace;"><?= esc_textarea($htaccess_config) ?></textarea>
                  </div>
                </details>
              <?php
              }
              ?>
            </td>
          </tr>
          <!-- Nginx (copy snippet) -->
          <tr>
            <th scope="row"><?= esc_html__('Nginx', 'fromscratch') ?></th>
            <td>
              <p class="description"><?= esc_html__('Recommended snippet for Nginx: add to your server block for gzip, long cache on static assets, and Vary Accept-Encoding. Copy and paste into your Nginx config.', 'fromscratch') ?></p>
              <?php
              $nginx_config = fs_get_nginx_config();
              if ($nginx_config !== '') {
              ?>
                <details style="margin-top: 8px;">
                  <summary style="cursor: pointer;"><?= esc_html__('Show config', 'fromscratch') ?></summary>
                  <div style="margin-top: 8px; position: relative;">
                    <pre id="fs-nginx-config" style="margin: 0; padding: 12px; background: #f6f7f7; border: 1px solid #c3c4c7; border-radius: 4px; overflow: auto; max-height: 280px; font-size: 12px;"><?= esc_html($nginx_config) ?></pre>
                    <button type="button" class="button button-small" style="margin-top: 8px;" data-fs-copy-from-source="fs-nginx-config" data-fs-copy-feedback-text="<?= esc_attr__('Copied', 'fromscratch') ?>"><?= esc_html__('Copy', 'fromscratch') ?></button>
                  </div>
                </details>
              <?php
              }
              ?>
            </td>
          </tr>
        </table>

        <hr>

        <h2><?= esc_html__('Content', 'fromscratch') ?></h2>

        <table class="form-table" role="presentation">
          <tr>
            <th scope="row"><?= esc_html__('Pages', 'fromscratch') ?></th>
            <td>
              <p style="margin-top: 0;">
                <label>
                  <input type="checkbox" name="install[pages]" checked data-fs-checkbox-toggle="pages">
                  <?= esc_html__('Create pages', 'fromscratch') ?>
                </label>
              </p>
              <p class="description"><?= esc_html__('Pages will only be created if they don\'t exist yet.', 'fromscratch') ?></p>
              <div data-fs-checkbox-toggle-content="pages">
                <table class="widefat striped" style="max-width: 600px; margin-top: 8px;">
                  <thead>
                    <tr>
                      <th style="padding: 8px 10px; line-height: 1.4em; width: 32px;"></th>
                      <th style="padding: 8px 10px; line-height: 1.4em"><?= esc_html__('Page', 'fromscratch') ?></th>
                      <th style="padding: 8px 10px; line-height: 1.4em"><?= esc_html__('Title', 'fromscratch') ?></th>
                      <th style="padding: 8px 10px; line-height: 1.4em"><?= esc_html__('Slug', 'fromscratch') ?></th>
                    </tr>
                  </thead>
                  <tbody>

                    <tr>
                      <td style="vertical-align: middle;">
                        <input type="hidden" name="pages[homepage][add]" value="1">
                        <input type="checkbox" checked disabled aria-label="<?= esc_attr__('Add page', 'fromscratch') ?>">
                      </td>
                      <td><strong><?= esc_html__('Homepage', 'fromscratch') ?></strong></td>
                      <td>
                        <input
                          type="text"
                          name="pages[homepage][title]"
                          value="<?= esc_attr__('Homepage', 'fromscratch') ?>"
                          class="regular-text" style="width: 180px">
                      </td>
                      <td>
                        <input
                          type="text"
                          name="pages[homepage][slug]"
                          value="<?= esc_attr__('homepage', 'fromscratch') ?>"
                          class="regular-text" style="width: 180px">
                      </td>
                    </tr>

                    <tr>
                      <td style="vertical-align: middle;">
                        <input type="hidden" name="pages[contact][add]" value="1">
                        <input type="checkbox" checked disabled aria-label="<?= esc_attr__('Add page', 'fromscratch') ?>">
                      </td>
                      <td><strong><?= esc_html__('Contact', 'fromscratch') ?></strong></td>
                      <td>
                        <input
                          type="text"
                          name="pages[contact][title]"
                          value="<?= esc_attr__('Contact', 'fromscratch') ?>"
                          class="regular-text" style="width: 180px">
                      </td>
                      <td>
                        <input
                          type="text"
                          name="pages[contact][slug]"
                          value="<?= esc_attr__('contact', 'fromscratch') ?>"
                          class="regular-text" style="width: 180px">
                      </td>
                    </tr>

                    <tr>
                      <td style="vertical-align: middle;">
                        <label class="screen-reader-text"><?= esc_html(sprintf(__('Add %s page', 'fromscratch'), __('Imprint', 'fromscratch'))) ?></label>
                        <input type="checkbox" name="pages[imprint][add]" value="1" checked>
                      </td>
                      <td><strong><?= esc_html__('Imprint', 'fromscratch') ?></strong></td>
                      <td>
                        <input
                          type="text"
                          name="pages[imprint][title]"
                          value="<?= esc_attr__('Imprint', 'fromscratch') ?>"
                          class="regular-text" style="width: 180px">
                      </td>
                      <td>
                        <input
                          type="text"
                          name="pages[imprint][slug]"
                          value="<?= esc_attr__('imprint', 'fromscratch') ?>"
                          class="regular-text" style="width: 180px">
                      </td>
                    </tr>

                    <tr>
                      <td style="vertical-align: middle;">
                        <input type="hidden" name="pages[privacy][add]" value="1">
                        <input type="checkbox" checked disabled aria-label="<?= esc_attr__('Add page', 'fromscratch') ?>">
                      </td>
                      <td><strong><?= esc_html__('Privacy', 'fromscratch') ?></strong></td>
                      <td>
                        <input
                          type="text"
                          name="pages[privacy][title]"
                          value="<?= esc_attr__('Privacy', 'fromscratch') ?>"
                          class="regular-text" style="width: 180px">
                      </td>
                      <td>
                        <input
                          type="text"
                          name="pages[privacy][slug]"
                          value="<?= esc_attr__('privacy', 'fromscratch') ?>"
                          class="regular-text" style="width: 180px">
                      </td>
                    </tr>

                  </tbody>
                </table>
              </div>
            </td>
          </tr>

          <tr>
            <th scope="row"><?= esc_html__('Menus', 'fromscratch') ?></th>
            <td>
              <p style="margin-top: 0;">
                <label>
                  <input type="checkbox" name="install[menus]" checked>
                  <?= esc_html__('Assign created pages to menus', 'fromscratch') ?>
                </label>
              </p>
              <p class="description"><?= esc_html__('Adds the pages to the configured menu locations.', 'fromscratch') ?></p>
            </td>
          </tr>

          <tr>
            <th scope="row"><?= esc_html__('Blogs', 'fromscratch') ?></th>
            <td>
              <p style="margin-top: 0;">
                <label>
                  <input type="checkbox" name="install[blogs]" value="1" checked>
                  <?= esc_html__('Enable blogs', 'fromscratch') ?>
                </label>
              </p>
              <p class="description"><?= esc_html__('Shows the Posts menu in the admin and allows creating and editing blog posts. You can change this later in Theme settings.', 'fromscratch') ?></p>
            </td>
          </tr>
        </table>

        <hr>

        <h2><?= esc_html__('Users', 'fromscratch') ?></h2>

        <p class="description"><?= esc_html__('At least one user must have developer rights.', 'fromscratch') ?></p>

        <?php
        $current_user = wp_get_current_user();
        ?>
        <table class="form-table" role="presentation" style="margin-top: 16px;">
          <tr>
            <td colspan="2" style="padding: 0; border: none; vertical-align: top;">
              <div style="display: flex; flex-wrap: wrap; gap: 24px;">
                <!-- Current user -->
                <div style="flex: 1; min-width: 280px; padding: 16px; background: #f6f7f7; border: 1px solid #c3c4c7; border-radius: 4px;">
                  <h3 style="margin: 0 0 12px 0; font-size: 14px;"><?= esc_html__('Current user', 'fromscratch') ?></h3>
                  <p style="margin-bottom: 12px;">
                    <label for="developer_current_username"><?= esc_html__('Username', 'fromscratch') ?></label><br>
                    <input type="text" id="developer_current_username" value="<?= esc_attr($current_user->user_login) ?>" class="regular-text" style="width: 100%;" readonly>
                  </p>
                  <p style="margin-bottom: 12px;">
                    <label for="developer_current_email"><?= esc_html__('Email', 'fromscratch') ?></label><br>
                    <input type="email" name="developer[current_user][email]" id="developer_current_email" value="<?= esc_attr($current_user->user_email) ?>" class="regular-text" style="width: 100%;" autocomplete="email">
                  </p>
                  <p style="margin-bottom: 12px;">
                    <label for="developer_current_password"><?= esc_html__('Password', 'fromscratch') ?></label><br>
                    <input type="password" name="developer[current_user][password]" id="developer_current_password" value="" class="regular-text" style="width: 100%;" autocomplete="off">
                    <span class="description"><?= esc_html__('Leave empty to keep current password.', 'fromscratch') ?></span>
                  </p>
                  <p style="margin-bottom: 0;">
                    <label>
                      <input type="checkbox" name="developer[current_user][has_developer_rights]" value="1" checked>
                      <?= esc_html__('Has developer rights', 'fromscratch') ?>
                    </label>
                  </p>
                </div>
                <!-- Optional additional user -->
                <div style="flex: 1; min-width: 280px; padding: 16px; background: #f6f7f7; border: 1px solid #c3c4c7; border-radius: 4px;">
                  <h3 style="margin: 0 0 12px 0; font-size: 14px;"><?= esc_html__('Add another admin user', 'fromscratch') ?></h3>
                  <p style="margin-bottom: 12px;">
                    <label for="developer_new_username"><?= esc_html__('Username', 'fromscratch') ?></label><br>
                    <input type="text" name="developer[new_user][username]" id="developer_new_username" value="" class="regular-text" style="width: 100%;" autocomplete="off">
                  </p>
                  <p style="margin-bottom: 12px;">
                    <label for="developer_new_email"><?= esc_html__('Email', 'fromscratch') ?></label><br>
                    <input type="email" name="developer[new_user][email]" id="developer_new_email" value="" class="regular-text" style="width: 100%;" autocomplete="off">
                  </p>
                  <p style="margin-bottom: 12px;">
                    <label for="developer_new_password"><?= esc_html__('Password', 'fromscratch') ?></label><br>
                    <input type="password" name="developer[new_user][password]" id="developer_new_password" value="" class="regular-text" style="width: 100%;" autocomplete="new-password">
                    <a class="fs-description-link -gray -has-icon" href="https://passwordcopy.app" target="_blank" rel="noopener" style="margin-left: 4px;">
                      <span class="fs-description-link-icon"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor">
                          <path d="M200-120q-33 0-56.5-23.5T120-200v-560q0-33 23.5-56.5T200-840h240q17 0 28.5 11.5T480-800q0 17-11.5 28.5T440-760H200v560h560v-240q0-17 11.5-28.5T800-480q17 0 28.5 11.5T840-440v240q0 33-23.5 56.5T760-120H200Zm560-584L416-360q-11 11-28 11t-28-11q-11-11-11-28t11-28l344-344H600q-17 0-28.5-11.5T560-800q0-17 11.5-28.5T600-840h200q17 0 28.5 11.5T840-800v200q0 17-11.5 28.5T800-560q-17 0-28.5-11.5T760-600v-104Z" />
                        </svg></span>
                      <span>passwordcopy.app</span>
                    </a>
                  </p>
                  <p>
                    <label>
                      <input type="checkbox" name="developer[new_user][has_developer_rights]" value="1">
                      <?= esc_html__('Has developer rights', 'fromscratch') ?>
                    </label>
                  </p>
                  <p style="margin-bottom: 0;">
                    <label>
                      <input type="checkbox" name="developer[new_user][login_after_setup]" value="1">
                      <?= esc_html__('Log in as this user after setup', 'fromscratch') ?>
                    </label>
                  </p>
                </div>
              </div>
            </td>
          </tr>
        </table>

        <p>
          <button class="button button-primary" name="fromscratch_run_install">
            <?= esc_html__('Run setup', 'fromscratch') ?>
          </button>
        </p>
      </form>

    <?php } ?>
  </div>
<?php
}

/**
 * Run FromScratch installation
 */
if (isset($_POST['fromscratch_run_install'])) {
  check_admin_referer('fromscratch_install');

  fromscratch_run_install();

  echo '<div class="notice notice-success"><p>FromScratch installation completed.</p></div>';
}

/**
 * Run the FromScratch installation: theme info, pages, menus, options.
 *
 * @return void
 */
function fromscratch_run_install(): void
{
  if (get_option('fromscratch_installed')) {
    wp_die('FromScratch installation is already complete.');
    return;
  }

  $current_has_dev = !empty($_POST['developer']['current_user']['has_developer_rights']);
  $new_username = !empty(trim($_POST['developer']['new_user']['username'] ?? ''));
  $new_email = !empty(trim($_POST['developer']['new_user']['email'] ?? ''));
  $new_pass = !empty($_POST['developer']['new_user']['password'] ?? '');
  $new_has_dev = !empty($_POST['developer']['new_user']['has_developer_rights']);
  $create_new = $new_username && $new_email && $new_pass;

  $has_at_least_one_developer = $current_has_dev || ($create_new && $new_has_dev);
  if (!$has_at_least_one_developer) {
    set_transient('fromscratch_install_error', 'developer_required', 30);
    wp_safe_redirect(admin_url('themes.php?page=fromscratch-install'));
    exit;
  }

  $theme_name = sanitize_text_field($_POST['theme']['name'] ?? '');
  $theme_desc = sanitize_text_field($_POST['theme']['description'] ?? '');
  $theme_author = sanitize_text_field($_POST['theme']['author'] ?? '');
  $theme_author_uri = esc_url_raw($_POST['theme']['author_uri'] ?? '');

  /**
   * Theme infos (style.css header)
   */
  $style_css = '/*
Theme Name: ' . $theme_name . '
Author: ' . $theme_author . '
Author URI: ' . $theme_author_uri . '
Description: ' . $theme_desc . '
Version: 1.0.0
License: Private
License URI: 
Text Domain: 
Tags: 
*/
';

  $style_file = get_stylesheet_directory() . '/style.css';
  file_put_contents($style_file, $style_css);

  /**
   * Media sizes (built-in only; set during install). Extra sizes are edited on Settings → Media.
   */
  $installMedia = isset($_POST['install']['media']) && $_POST['install']['media'] === 'on';

  if ($installMedia) {
    $install_media_defaults = [
      'thumbnail' => ['width' => 300, 'height' => 300],
      'small' => ['width' => 600, 'height' => 600],
      'medium' => ['width' => 1200, 'height' => 0],
      'large' => ['width' => 2400, 'height' => 0],
    ];
    $large_width = 0;
    foreach ($install_media_defaults as $slug => $defaults) {
      $posted_w = isset($_POST['media'][$slug]['width']) ? (int) $_POST['media'][$slug]['width'] : $defaults['width'];
      $posted_h = isset($_POST['media'][$slug]['height']) ? (int) $_POST['media'][$slug]['height'] : $defaults['height'];
      if ($slug === 'thumbnail') {
        update_option('thumbnail_size_w', $posted_w);
        update_option('thumbnail_size_h', $posted_h);
        $thumbnail_crop = !empty($_POST['media']['thumbnail']['crop']) ? 1 : 0;
        update_option('thumbnail_crop', $thumbnail_crop);
      } elseif ($slug === 'small') {
        update_option('small_size_w', $posted_w);
        update_option('small_size_h', $posted_h);
      } elseif ($slug === 'medium') {
        update_option('medium_size_w', $posted_w);
        update_option('medium_size_h', $posted_h);
      } elseif ($slug === 'large') {
        update_option('large_size_w', $posted_w);
        update_option('large_size_h', $posted_h);
        $large_width = $posted_w;
      }
    }
    if ($large_width > 0) {
      update_option('big_image_size_threshold', $large_width);
    }
  }

  /**
   * Permalinks
   */
  $installPermalinks = isset($_POST['install']['permalinks']) && $_POST['install']['permalinks'] === 'on';

  if ($installPermalinks) {
    global $wp_rewrite;

    if ($wp_rewrite->permalink_structure !== '/%postname%/') {
      $wp_rewrite->set_permalink_structure('/%postname%/');
      flush_rewrite_rules();
    }
  }

  /**
   * .htaccess (Apache only)
   */
  $installHtaccess = isset($_POST['install']['htaccess']) && $_POST['install']['htaccess'] === 'on';
  if ($installHtaccess) {
    fs_write_htaccess();
  }

  /**
   * Required pages
   */
  $installPages = isset($_POST['install']['pages']) && $_POST['install']['pages'] === 'on';

  if ($installPages) {

    // Delete "Sample Page"
    $sample_page = get_page_by_path('sample-page', OBJECT, 'page');
    if ($sample_page) {
      wp_delete_post($sample_page->ID, true);
    }

    // Delete "Hello World!" post
    $hello_post = get_page_by_path('hello-world', OBJECT, 'post');
    if ($hello_post) {
      wp_delete_post($hello_post->ID, true);
    }

    // Delete Privacy Policy page (ONLY if WP created/assigned it)
    $privacy_id = (int) get_option('wp_page_for_privacy_policy');
    if ($privacy_id && $privacy_id < 3) { // TODO check post id
      wp_delete_post($privacy_id, true);
      update_option('wp_page_for_privacy_policy', 0);
    }

    // Delete comments
    $comments = get_comments([
      'number' => -1,
    ]);

    foreach ($comments as $comment) {
      wp_delete_comment($comment->comment_ID, true);
    }

    // Add pages (imprint only if checkbox checked; others always added via hidden input)
    $pages = $_POST['pages'] ?? [];

    foreach ($pages as $page_id => $page) {
      $add_page = !empty($page['add']);
      if (!$add_page) {
        continue;
      }

      $page_obj = get_page_by_path($page['slug']);

      if (!$page_obj) {
        $page_content = <<<HTML
        <!-- wp:heading {\"level\":1} -->
        <h1>{$page['title']}</h1>
        <!-- /wp:heading -->

        <!-- wp:paragraph -->
        <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.</p>
        <!-- /wp:paragraph -->
        HTML;

        if ($page_id === 'homepage') {
          $page_content = <<<HTML
          <!-- wp:heading {\"level\":1} -->
          <h1>Welcome to FromScratch</h1>
          <!-- /wp:heading -->

          <!-- wp:paragraph -->
          <p>This page was created by the FromScratch installer and contains example blocks.</p>
          <!-- /wp:paragraph -->
          HTML;
        }

        $page_post_id = wp_insert_post([
          'post_type'   => 'page',
          'post_status' => 'publish',
          'post_title'  => $page['title'],
          'post_name'   => $page['slug'],
          'post_content' => $page_content,
        ]);

        if ($page_id === 'homepage') {
          update_option('show_on_front', 'page');
          update_option('page_on_front', (int) $page_post_id);
          update_option('page_for_posts', 0);
        }

        if ($page_id === 'privacy') {
          update_option('wp_page_for_privacy_policy', (int) $page_post_id);
        }
      }
    }
  }

  /**
   * Menus
   */
  $installMenus = isset($_POST['install']['menus']) && $_POST['install']['menus'] === 'on';

  if ($installMenus) {
    $menuItems = [
      'slider' => [
        'title' => __('Slider', 'fromscratch'),
        'menu' => 'main_menu',
        'link' => '/#slider'
      ],
      'contact' => [
        'title' => __('Contact', 'fromscratch'),
        'menu' => 'main_menu',
        'is-button' => true
      ],
    ];
    if (!empty($_POST['pages']['imprint']['add'])) {
      $menuItems['imprint'] = [
        'title' => __('Imprint', 'fromscratch'),
        'menu' => 'footer_menu'
      ];
    }
    $menuItems['privacy'] = [
      'title' => __('Privacy', 'fromscratch'),
      'menu' => 'footer_menu'
    ];


    foreach ($menuItems as $slug => $config) {

      $menu_id = fs_get_or_create_menu_id($config['menu']);
      if (!$menu_id) {
        continue;
      }

      // Custom link
      if (!empty($config['link'])) {

        $item_id = wp_update_nav_menu_item($menu_id, 0, [
          'menu-item-title'  => $config['title'],
          'menu-item-url'    => $config['link'],
          'menu-item-status' => 'publish',
          'menu-item-type'   => 'custom',
        ]);

        // Page link
      } else {

        $page_id = fs_get_page_id_by_slug($slug);
        if (!$page_id) {
          continue;
        }

        $item_id = wp_update_nav_menu_item($menu_id, 0, [
          'menu-item-object-id' => $page_id,
          'menu-item-object'    => 'page',
          'menu-item-type'      => 'post_type',
          'menu-item-status'    => 'publish',
        ]);
      }

      // Link is a button
      if (!empty($config['is-button']) && $item_id) {
        update_post_meta($item_id, '_menu_item_is_button', '1');
      }
    }
  }

  /**
   * Blogs: save enable_blogs from install form (default on)
   */
  $features = get_option('fromscratch_features', []);
  if (!is_array($features)) {
    $features = [];
  }
  $features['enable_blogs'] = !empty($_POST['install']['blogs']) ? 1 : 0;
  update_option('fromscratch_features', $features);

  /**
   * Developer: update current user (name, email, password, developer flag) and optionally create new user.
   */
  $current_id = get_current_user_id();
  if ($current_id) {
    $cur_email = isset($_POST['developer']['current_user']['email']) ? sanitize_email(wp_unslash($_POST['developer']['current_user']['email'])) : '';
    $cur_password = isset($_POST['developer']['current_user']['password']) ? $_POST['developer']['current_user']['password'] : '';
    $cur_has_dev = !empty($_POST['developer']['current_user']['has_developer_rights']);

    $user_data = [
      'ID' => $current_id,
      'user_email' => $cur_email ?: get_userdata($current_id)->user_email,
    ];
    if ($cur_password !== '' && strlen($cur_password) >= 8) {
      $user_data['user_pass'] = $cur_password;
    }
    wp_update_user($user_data);
    if ($cur_has_dev) {
      update_user_meta($current_id, 'fromscratch_developer', '1');
    } else {
      delete_user_meta($current_id, 'fromscratch_developer');
    }
  }

  $new_developer_user_id = 0;
  $dev_username = isset($_POST['developer']['new_user']['username']) ? sanitize_user(wp_unslash($_POST['developer']['new_user']['username']), true) : '';
  $dev_email = isset($_POST['developer']['new_user']['email']) ? sanitize_email(wp_unslash($_POST['developer']['new_user']['email'])) : '';
  $dev_password = isset($_POST['developer']['new_user']['password']) ? $_POST['developer']['new_user']['password'] : '';
  $new_has_dev = !empty($_POST['developer']['new_user']['has_developer_rights']);
  if ($dev_username && $dev_email && strlen($dev_password) >= 8 && !username_exists($dev_username) && !email_exists($dev_email)) {
    $user_id = wp_insert_user([
      'user_login' => $dev_username,
      'user_email' => $dev_email,
      'user_pass' => $dev_password,
      'role' => 'administrator',
    ]);
    if (!is_wp_error($user_id)) {
      if ($new_has_dev) {
        update_user_meta($user_id, 'fromscratch_developer', '1');
      }
      $new_developer_user_id = (int) $user_id;
    }
  }

  $login_after_setup = !empty($_POST['developer']['new_user']['login_after_setup']) && $new_developer_user_id > 0;
  if ($login_after_setup) {
    set_transient('fromscratch_login_as_dev', $new_developer_user_id, 60);
  }

  /**
   * Rename theme
   */
  $themes_dir = WP_CONTENT_DIR . '/themes';

  $old_slug = 'fromscratch';
  $new_slug = sanitize_title($_POST['theme']['slug']);

  $old_dir = $themes_dir . '/' . $old_slug;
  $new_dir = $themes_dir . '/' . $new_slug;

  // Rename at the VERY END
  if (is_dir($old_dir) && is_dir($new_dir) && $old_dir !== $new_dir) {
    rename($old_dir, $new_dir);
    switch_theme($new_slug);
  }

  /**
   * Save install complete
   */
  update_option('fromscratch_install_success', true);

  /**
   * Redirect
   */
  wp_safe_redirect(
    admin_url('themes.php?page=fromscratch-install&fromscratch_success=1')
  );
  exit;
}

/**
 * Get nav menu term_id by config slug; create menu and assign to location if missing.
 *
 * @param string $menu_slug Key from config menus (e.g. "main_menu", "footer_menu").
 * @return int Nav menu term ID.
 * @throws RuntimeException If menu config is missing for the slug.
 */
function fs_get_or_create_menu_id(string $menu_slug): int
{
  $menu_name = fs_config('menus.' . $menu_slug);
  if ($menu_name === null) {
    throw new RuntimeException("Menu config missing for slug: {$menu_slug}");
  }

  $menu = wp_get_nav_menu_object($menu_name);

  if ($menu) {
    return (int) $menu->term_id;
  }

  // Create menu with name
  $menu_id = wp_create_nav_menu($menu_name);
  fs_assign_menu_to_location($menu_slug, $menu_id);

  return (int) $menu_id;
}

/**
 * Get page ID by post slug (path).
 *
 * @param string $slug Page slug (path).
 * @return int|null Page ID or null if not found.
 */
function fs_get_page_id_by_slug(string $slug): ?int
{
  $page = get_page_by_path($slug);
  return $page ? (int) $page->ID : null;
}

/**
 * Assign a nav menu to a theme location (e.g. main_menu, footer_menu).
 *
 * @param string $location Theme location key from config.
 * @param int    $menu_id  Nav menu term ID.
 * @return void
 */
function fs_assign_menu_to_location(string $location, int $menu_id): void
{
  $locations = get_theme_mod('nav_menu_locations', []);

  // Only update if not already assigned
  if (!isset($locations[$location]) || (int) $locations[$location] !== $menu_id) {
    $locations[$location] = $menu_id;
    set_theme_mod('nav_menu_locations', $locations);
  }
}
