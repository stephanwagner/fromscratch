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
						<?= fs_svg_code('/img/fromscratch-logo.svg', ['class' => 'logo__image', 'aria-hidden' => 'true']); ?>
					</a>
				</div>

				<button class="main-menu__toggler" data-toggle-menu>
					<div class="main-menu__toggler-icon">
						<span class="main-menu__toggler-icon-line1"></span>
						<span class="main-menu__toggler-icon-line2"></span>
						<span class="main-menu__toggler-icon-line3"></span>
					</div>
				</button>

				<?= function_exists('fs_language_switcher_html') ? fs_language_switcher_html() : '' ?>

				<div class="main-menu__wrapper">
					<?php fs_nav_menu([
						'theme_location' => 'main_menu',
						'menu_class' => 'main-menu__container',
						'container' => 'nav',
						'aria_label' => esc_attr__('Main navigation', 'fromscratch'),
					]); ?>
				</div>

			</div>
		</header>

		<div class="header__placeholder"></div>
		<div class="header__menu-overlay"></div>