/**
 * Theme settings → General: fallback OG image media picker.
 * Requires wp.media (enqueue with dependency 'media-editor' or after wp_enqueue_media()).
 */
(function ($) {
	'use strict';

	function init() {
		if (typeof wp === 'undefined' || !wp.media) {
			return;
		}
		var frame;
		var $select = $('#fs_og_fallback_select');
		var $remove = $('#fs_og_fallback_remove');
		var $input = $('#fromscratch_og_image_fallback');
		var $preview = $('#fs_og_fallback_preview');
		if (!$select.length || !$input.length || !$preview.length) {
			return;
		}
		$select.off('click.fsOgFallback').on('click.fsOgFallback', function (e) {
			e.preventDefault();
			if (frame) {
				frame.open();
				return;
			}
			frame = wp.media({
				library: { type: 'image' },
				multiple: false
			});
			frame.on('select', function () {
				var attachment = frame.state().get('selection').first().toJSON();
				var url = (attachment.sizes && attachment.sizes.medium && attachment.sizes.medium.url)
					? attachment.sizes.medium.url
					: attachment.url;
				$input.val(attachment.id);
				$preview.empty().append(
					$('<img>').attr('src', url).css({ maxWidth: '300px', height: 'auto', display: 'block' })
				);
				$remove.show();
			});
			frame.open();
		});
		$remove.off('click.fsOgFallback').on('click.fsOgFallback', function (e) {
			e.preventDefault();
			$input.val('0');
			$preview.empty();
			$(this).hide();
		});
	}

	$(function () {
		if (typeof wp !== 'undefined' && wp.media) {
			init();
		} else {
			$(window).on('load', function () {
				setTimeout(init, 100);
			});
		}
	});
})(jQuery);
