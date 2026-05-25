<!DOCTYPE html>
<html <?php language_attributes(); ?>>

<head>
	<?php wp_head(); ?>
	<?php function_exists('fs_output_custom_css') && fs_output_custom_css(); ?>
</head>

<body <?php body_class(); ?>>

	<div class="page__wrapper">

		<header class="header__wrapper">
			<div class="header__container container">

				<div class="logo__container">
					<a href="/">
						<img src="<?= fs_asset_url('/img/logo.svg'); ?>" alt="" class="logo__image" aria-hidden="true">
					</a>
				</div>

				<?= function_exists('fs_language_switcher_html') ? fs_language_switcher_html() : '' ?>

				<button class="main-menu__toggler" data-toggle-menu aria-expanded="false" aria-controls="main-navigation">
					<div class="main-menu__toggler-icon">
						<span class="main-menu__toggler-icon-line1"></span>
						<span class="main-menu__toggler-icon-line2"></span>
						<span class="main-menu__toggler-icon-line3"></span>
					</div>
				</button>

				<div class="main-menu__wrapper">
					<?php fs_nav_menu([
						'theme_location' => 'main_menu',
						'menu_class' => 'main-menu__container',
						'container' => 'nav',
						'container_id' => 'main-navigation',
						'container_aria_label' => esc_attr__('Main navigation', 'fromscratch'),
					]); ?>
				</div>

				<button
					type="button"
					class="header-search__toggle"
					data-modal="search"
					aria-label="<?= esc_attr__('Open search', 'fromscratch') ?>">
					<span class="header-search__toggle-icon" aria-hidden="true">
						<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor">
							<path d="M380-320q-109 0-184.5-75.5T120-580q0-109 75.5-184.5T380-840q109 0 184.5 75.5T640-580q0 44-14 83t-38 69l224 224q11 11 11 28t-11 28q-11 11-28 11t-28-11L532-372q-30 24-69 38t-83 14Zm0-80q75 0 127.5-52.5T560-580q0-75-52.5-127.5T380-760q-75 0-127.5 52.5T200-580q0 75 52.5 127.5T380-400Z" />
						</svg>
					</span>
				</button>

			</div>
		</header>

		<div class="header__placeholder"></div>
		<div class="header__menu-overlay"></div>