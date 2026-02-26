<footer class="footer__wrapper">
	<div class="footer__container container">
		<div class="footer__credits">
			<?php
			if (get_option('theme_variables_footer_text')) {
				echo get_option('theme_variables_footer_text');
			} else {
				echo 'Go to <a href="/wp-admin/options-general.php?page=fs-theme-settings">Settings › Theme settings</a> to edit this text';
			}
			?>
		</div>
		<div class="footer-menu__wrapper">
			<?php wp_nav_menu([
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
