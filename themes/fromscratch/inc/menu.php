<?php

defined('ABSPATH') || exit;

require_once get_template_directory() . '/config/nav-walker.php';

/**
 * Accessible submenu toggle behavior for FS_Walker_Nav_Menu.
 * Keeps markup framework-agnostic and progressive-enhancement friendly.
 */
add_action('wp_footer', function (): void {
	if (is_admin()) {
		return;
	}
	?>
	<script>
		(function () {
			var toggles = document.querySelectorAll('.sub-menu-toggle[aria-controls]');
			if (!toggles.length) return;

			function setExpanded(btn, expanded) {
				var submenuId = btn.getAttribute('aria-controls');
				if (!submenuId) return;
				var submenu = document.getElementById(submenuId);
				if (!submenu) return;

				btn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
				submenu.hidden = !expanded;
				var li = btn.closest('li');
				if (li) {
					li.classList.toggle('is-submenu-open', !!expanded);
				}
			}

			toggles.forEach(function (btn) {
				setExpanded(btn, false);
				btn.addEventListener('click', function () {
					var expanded = btn.getAttribute('aria-expanded') === 'true';
					setExpanded(btn, !expanded);
				});
			});

			document.addEventListener('keydown', function (e) {
				if (e.key !== 'Escape') return;
				toggles.forEach(function (btn) {
					if (btn.getAttribute('aria-expanded') === 'true') {
						setExpanded(btn, false);
					}
				});
			});
		})();
	</script>
	<?php
});