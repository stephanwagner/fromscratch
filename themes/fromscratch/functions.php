<?php

/**
 * Bootstrap
 */
require_once 'inc/bootstrap.php';

/**
 * Languages
 */
require_once 'inc/lang.php';

/**
 * Install
 */
if (is_admin()) {
    require_once 'inc/install.php';
}

/**
 * Clean up
 */
require_once 'inc/clean-up.php';

/**
 * Head
 */
require_once 'inc/head.php';

/**
 * Theme setup
 */
require_once 'inc/theme-setup.php';

/**
 * Support SVG
 */
require_once 'inc/svg-support.php';

/**
 * Menu
 */
require_once 'inc/menu.php';

/**
 * Assets
 */
require_once 'inc/assets.php';

/**
 * Variables
 */
require_once 'inc/variables.php';
