<!DOCTYPE html>
<html <?php language_attributes(); ?>>

<head>
	<?php wp_head(); ?>
	<style>
		:root {
			--default-text-color: red;
		}
	</style>
</head>

<body <?php body_class(); ?>>

	<div class="page__wrapper">

		<header class="header__wrapper">
			<div class="header__container container">

				<div class="logo__container">
					<a href="/">
						<img class="logo__image" src="<?= get_template_directory_uri() ?>/img/logo.png" alt="">
					</a>
				</div>

				<div class="header-menu__wrapper">
					<?php wp_nav_menu([
						'theme_location' => 'main_menu',
						'menu_class' => 'header-menu__container',
						'container' => 'nav'
					]); ?>
				</div>

				<div class="header-menu__toggler-container" data-toggle-menu>
					<div class="main-menu__toggler-icon-container">
						<div class="main-menu__toggler-icon">
							<span class="main-menu__toggler-icon-line1"></span>
							<span class="main-menu__toggler-icon-line2"></span>
							<span class="main-menu__toggler-icon-line3"></span>
							<span class="main-menu__toggler-icon-line-close1"></span>
							<span class="main-menu__toggler-icon-line-close2"></span>
						</div>
					</div>
				</div>

			</div>
		</header>

		<div class="header__placeholder"></div>
		<div class="header__menu-overlay"></div>

