<footer class="footer__wrapper">
	<div class="footer__container container">
		<div class="footer__text">
			<b><?= fs_content('theme_content_general_company_name') ?></b><br>
			<?= nl2br(fs_content('theme_content_general_company_address')) ?><br>
			<a href="tel:<?= fs_content('theme_content_general_company_phone') ?>"><?= fs_content('theme_content_general_company_phone') ?></a><br>
			<a href="mailto:<?= fs_content('theme_content_general_company_email') ?>"><?= fs_content('theme_content_general_company_email') ?></a>
		</div>
		<div class="footer-menu__wrapper">
			<?php fs_nav_menu([
				'theme_location' => 'footer_menu',
				'menu_class' => 'footer-menu__container',
				'container' => 'nav',
				'aria_label' => esc_attr__('Footer navigation', 'fromscratch'),
			]); ?>
		</div>
	</div>
</footer>

</div>

<?php wp_footer(); ?>

</body>

</html>
