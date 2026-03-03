<footer class="footer__wrapper">
	<div class="footer__container container">
		<div class="footer__credits">
			<?php
			if (get_option(FS_THEME_CONTENT_OPTION_PREFIX . 'footer_footer4_text')) {
				echo get_option(FS_THEME_CONTENT_OPTION_PREFIX . 'footer_footer4_text');
			} else {
				$menu_label = esc_html__('Theme', 'fromscratch');
				if (current_user_can('manage_options')) {
					echo 'Go to <a href="' . esc_url(admin_url('options-general.php?page=fs-theme-settings&tab=texte')) . '">Settings › ' . $menu_label . '</a> to edit this text';
				} else {
					echo 'Go to Settings › ' . $menu_label . ' to edit this text';
				}
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
