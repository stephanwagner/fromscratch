<footer class="footer__wrapper">
	<div class="footer__container container">
		<div class="footer__text">
			<?= fs_content_option('theme_content_general_company_name') ?><br>
			<?= fs_content_option('theme_content_general_company_name') ?><br>
			<?= nl2br(fs_content_option('theme_content_general_company_address')) ?><br>
			<a href="tel:<?= fs_content_option('theme_content_general_company_phone') ?>"><?= fs_content_option('theme_content_general_company_phone') ?></a><br>
			<a href="mailto:<?= fs_content_option('theme_content_general_company_email') ?>"><?= fs_content_option('theme_content_general_company_email') ?></a>
		</div>
		<div class="footer-menu__wrapper">
			<?php fs_nav_menu([
				'theme_location' => 'footer_menu',
				'menu_class' => 'footer-menu__container',
				'container' => 'nav'
			]); ?>
		</div>
	</div>
</footer>

</div>

<?php wp_footer(); ?>

</body>

</html>
