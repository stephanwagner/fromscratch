<?php

/**
 * Debug
 */
add_action('admin_init', function () {
  // delete_option('fromscratch_install_skipped');
  // delete_option('fromscratch_install_success');
});

/**
 * Should show FromScratch installer
 */
function fs_should_show_installer(): bool
{
  if (get_option('fromscratch_install_success')) {
    return false;
  }

  if (get_option('fromscratch_install_skipped')) {
    return false;
  }

  return true;
}

/** 
 * Add FromScratch installer to admin menu
 */
add_action('admin_menu', function () {
  if (!fs_should_show_installer() && !isset($_GET['fromscratch_success'])) {
    return;
  }

  add_theme_page(
    fs_t('INSTALL_MENU_TITLE'),
    fs_t('INSTALL_MENU_TITLE'),
    'manage_options',
    'fromscratch-install',
    'fs_render_installer'
  );
});

/**
 * Show FromScratch installer notice
 */
add_action('admin_notices', function () {
  if (!fs_should_show_installer()) {
    return;
  }

  $screen = get_current_screen();
  if ($screen && $screen->id === 'appearance_page_fromscratch-install') {
    return;
  }

  echo '<div class="notice notice-warning">';
  echo '<p><strong>' . fs_t('INSTALL_NOTICE_TITLE') . '</strong></p>';
  echo '<p>' . fs_t('INSTALL_NOTICE_DESCRIPTION') . '</p>';
  echo '<p>';
  echo '<a href="' . esc_url(admin_url('themes.php?page=fromscratch-install')) . '" class="button button-primary">' . fs_t('INSTALL_NOTICE_BUTTON_GO_TO_INSTALLER') . '</a> ';
  echo '<a href="' . esc_url(wp_nonce_url(
    admin_url('themes.php?page=fromscratch-install&fromscratch_skip=1'),
    'fromscratch_skip'
  )) . '" class="button">' . fs_t('INSTALL_NOTICE_BUTTON_SKIP_SETUP') . '</a>';
  echo '</p>';
  echo '</div>';
});


/**
 * Render FromScratch installer
 */
function fs_render_installer()
{
  if (!current_user_can('manage_options')) {
    return;
  }

?>
  <div class="wrap">
    <h1><?= fs_t('INSTALL_TITLE') ?></h1>

    <?php if (get_option('fromscratch_install_success')) { ?>

      <div class="notice notice-success">
        <p><?= fs_t('INSTALL_COMPLETE_MESSAGE') ?></p>
      </div>

      <p>
        <a
          href="<?php echo esc_url(admin_url()); ?>"
          class="button button-primary"><?= fs_t('INSTALL_GO_TO_DASHBOARD_BUTTON') ?></a>
      </p>

    <?php } else { ?>
      <p>
        <?= fs_t('INSTALL_DESCRIPTION') ?>
      </p>

      <form method="post">
        <?php wp_nonce_field('fromscratch_install'); ?>

        <table class="form-table" role="presentation">

          <!-- Theme name and description -->

          <tr>
            <th scope="row">
              <label>
                <?= fs_t('INSTALL_THEME_NAME_TITLE') ?>
              </label>
            </th>
            <td>
              <input type="text" name="theme[name]" value="<?= get_bloginfo('name') ?>" class="regular-text">
            </td>
          </tr>
          <th scope="row">
            <label>
              <?= fs_t('INSTALL_THEME_SLUG_TITLE') ?>
            </label>
          </th>
          <td>
            <input type="text" name="theme[slug]" value="<?= sanitize_title(get_bloginfo('name')); ?>" class="regular-text">
          </td>
          </tr>
          <tr>
            <th scope="row">
              <label>
                <?= fs_t('INSTALL_THEME_DESCRIPTION_TITLE') ?>
              </label>
            </th>
            <td>
              <input type="text" name="theme[description]" value="<?= fs_t('INSTALL_THEME_DESCRIPTION_FORM_DESCRIPTION', ['NAME' => get_bloginfo('name')]) ?>" class="regular-text">
            </td>
          </tr>

          <!-- Media sizes -->
          <tr>
            <th scope="row">
              <label>
                <input type="checkbox" name="install[media]" checked>
                <?= fs_t('INSTALL_MEDIA_SIZES_TITLE') ?>
              </label>
            </th>
            <td>
              <input type="number" name="media[thumbnail]" value="600" class="small-text"> px
              <input type="number" name="media[medium]" value="1200" class="small-text"> px
              <input type="number" name="media[large]" value="2400" class="small-text"> px
              <p class="description">
                <?= fs_t('INSTALL_MEDIA_SIZES_DESCRIPTION') ?>
              </p>
            </td>
          </tr>

          <!-- Permalinks -->
          <tr>
            <th scope="row">
              <label>
                <input type="checkbox" name="install[permalinks]" checked>
                <?= fs_t('INSTALL_PERMALINKS_TITLE') ?>
              </label>
            </th>
            <td>
              <p class="description">
                <?= fs_t('INSTALL_PERMALINKS_DESCRIPTION') ?>
              </p>
            </td>
          </tr>

          <!-- Pages -->
          <tr>
            <th scope="row">
              <label>
                <input type="checkbox" name="install[pages]" checked>
                <?= fs_t('INSTALL_PAGES_TITLE') ?>
              </label>
            </th>
            <td>

              <table class="widefat striped" style="max-width: 600px">
                <thead>
                  <tr>
                    <th style="padding: 8px 10px; line-height: 1.4em"><?= fs_t('INSTALL_PAGES_TABLE_HEADING_PAGE') ?></th>
                    <th style="padding: 8px 10px; line-height: 1.4em"><?= fs_t('INSTALL_PAGES_TABLE_HEADING_TITLE') ?></th>
                    <th style="padding: 8px 10px; line-height: 1.4em"><?= fs_t('INSTALL_PAGES_TABLE_HEADING_SLUG') ?></th>
                  </tr>
                </thead>
                <tbody>

                  <tr>
                    <td><strong><?= fs_t('INSTALL_PAGES_HOMEPAGE_TITLE') ?></strong></td>
                    <td>
                      <input
                        type="text"
                        name="pages[homepage][title]"
                        value="<?= fs_t('INSTALL_PAGES_HOMEPAGE_FORM_TITLE') ?>"
                        class="regular-text" style="width: 180px">
                    </td>
                    <td>
                      <input
                        type="text"
                        name="pages[homepage][slug]"
                        value="<?= fs_t('INSTALL_PAGES_HOMEPAGE_FORM_SLUG') ?>"
                        class="regular-text" style="width: 180px">
                    </td>
                  </tr>

                  <tr>
                    <td><strong><?= fs_t('INSTALL_PAGES_CONTACT_TITLE') ?></strong></td>
                    <td>
                      <input
                        type="text"
                        name="pages[contact][title]"
                        value="<?= fs_t('INSTALL_PAGES_CONTACT_FORM_TITLE') ?>"
                        class="regular-text" style="width: 180px">
                    </td>
                    <td>
                      <input
                        type="text"
                        name="pages[contact][slug]"
                        value="<?= fs_t('INSTALL_PAGES_CONTACT_FORM_SLUG') ?>"
                        class="regular-text" style="width: 180px">
                    </td>
                  </tr>

                  <tr>
                    <td><strong><?= fs_t('INSTALL_PAGES_IMPRINT_TITLE') ?></strong></td>
                    <td>
                      <input
                        type="text"
                        name="pages[imprint][title]"
                        value="<?= fs_t('INSTALL_PAGES_IMPRINT_FORM_TITLE') ?>"
                        class="regular-text" style="width: 180px">
                    </td>
                    <td>
                      <input
                        type="text"
                        name="pages[imprint][slug]"
                        value="<?= fs_t('INSTALL_PAGES_IMPRINT_FORM_SLUG') ?>"
                        class="regular-text" style="width: 180px">
                    </td>
                  </tr>

                  <tr>
                    <td><strong><?= fs_t('INSTALL_PAGES_PRIVACY_TITLE') ?></strong></td>
                    <td>
                      <input
                        type="text"
                        name="pages[privacy][title]"
                        value="<?= fs_t('INSTALL_PAGES_PRIVACY_FORM_TITLE') ?>"
                        class="regular-text" style="width: 180px">
                    </td>
                    <td>
                      <input
                        type="text"
                        name="pages[privacy][slug]"
                        value="<?= fs_t('INSTALL_PAGES_PRIVACY_FORM_SLUG') ?>"
                        class="regular-text" style="width: 180px">
                    </td>
                  </tr>

                </tbody>
              </table>

              <p class="description">
                <?= fs_t('INSTALL_PAGES_DESCRIPTION') ?>
              </p>

            </td>
          </tr>

          <!-- Menus -->
          <tr>
            <th scope="row">
              <label>
                <input type="checkbox" name="install[menus]" checked>
                <?= fs_t('INSTALL_MENUS_TITLE') ?>
              </label>
            </th>
            <td>
              <?= fs_t('INSTALL_MENUS_DESCRIPTION') ?>
            </td>
          </tr>

        </table>

        <p>
          <button class="button button-primary" name="fromscratch_run_install">
            <?= fs_t('INSTALL_RUN_SETUP_BUTTON') ?>
          </button>

          <a
            href="<?php echo esc_url(
                    wp_nonce_url(
                      admin_url('themes.php?page=fromscratch-install&fromscratch_skip=1'),
                      'fromscratch_skip'
                    )
                  ); ?>"
            class="button">
            <?= fs_t('INSTALL_SKIP_SETUP_BUTTON') ?>
          </a>
        </p>
      </form>

    <?php } ?>
  </div>
<?php
}

/**
 * Skip FromScratch installation
 */
add_action('admin_init', function () {
  if (
    isset($_GET['fromscratch_skip']) &&
    $_GET['fromscratch_skip'] === '1' &&
    check_admin_referer('fromscratch_skip')
  ) {
    update_option('fromscratch_install_skipped', true);

    wp_safe_redirect(wp_get_referer() ?: admin_url());
    exit;
  }
});

/**
 * Run FromScratch installation
 */
if (isset($_POST['fromscratch_run_install'])) {
  check_admin_referer('fromscratch_install');

  fromscratch_run_install();

  echo '<div class="notice notice-success"><p>FromScratch installation completed.</p></div>';
}

function fromscratch_run_install()
{
  if (get_option('fromscratch_installed')) {
    wp_die('FromScratch installation is already complete.');
    return;
  }

  $theme_name = sanitize_text_field($_POST['theme']['name'] ?? '');
  $theme_desc = sanitize_text_field($_POST['theme']['description'] ?? '');

  /**
   * Theme infos
   */
  $style_css = '/*
Theme Name: ' . $theme_name . '
Author: Stephan Wagner
Author URI: https://stephanwagner.me
Description: ' . $theme_desc . '
Version: 1.0.0
License: Proprietary
License URI: 
Text Domain: 
Tags: 
*/
';

  $style_file = get_stylesheet_directory() . '/style.css';
  file_put_contents($style_file, $style_css);

  /**
   * Media sizes
   */
  $installMedia = isset($_POST['install']['media']) && $_POST['install']['media'] === 'on';

  if ($installMedia) {
    // Thumbnail
    $thumbnail = $_POST['media']['thumbnail'];
    update_option('thumbnail_size_w', $thumbnail);
    update_option('thumbnail_size_h', $thumbnail);
    update_option('thumbnail_crop', 0);

    // Medium
    $medium = $_POST['media']['medium'];
    update_option('medium_size_w', $medium);
    update_option('medium_size_h', $medium);

    // Medium Large (often forgotten!)
    update_option('medium_large_size_w', $medium);
    update_option('medium_large_size_h', $medium);

    // Large
    $large = $_POST['media']['large'];
    update_option('large_size_w', $large);
    update_option('large_size_h', $large);

    // Big image threshold (WP auto downscaling)
    update_option('big_image_size_threshold', $large);
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

    // Add pages
    $pages = $_POST['pages'];

    foreach ($pages as $page_id => $page) {

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
      // Main menu
      'slider' => [
        'title' => fs_t('INSTALL_MENU_LINK_SLIDER_TITLE'),
        'menu' => 'main_menu',
        'link' => '/#slider'
      ],
      'contact' => [
        'title' => fs_t('INSTALL_PAGES_CONTACT_FORM_TITLE'),
        'menu' => 'main_menu',
        'is-button' => true
      ],

      // Footer menu
      'imprint' => [
        'title' => fs_t('INSTALL_PAGES_IMPRINT_FORM_TITLE'),
        'menu' => 'footer_menu'
      ],
      'privacy' => [
        'title' => fs_t('INSTALL_PAGES_PRIVACY_FORM_TITLE'),
        'menu' => 'footer_menu'
      ],
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
  delete_option('fromscratch_install_skipped');

  /**
   * Redirect
   */
  wp_safe_redirect(
    admin_url('themes.php?page=fromscratch-install&fromscratch_success=1')
  );
  exit;
}

/**
 * Get menu ID by slug
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
 * Get page ID by slug
 */
function fs_get_page_id_by_slug(string $slug): ?int
{
  $page = get_page_by_path($slug);
  return $page ? (int) $page->ID : null;
}

/**
 * Assign menu to location
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