/**
 * Theme settings → General: client logo and fallback OG image media pickers.
 * Requires wp.media (enqueue with dependency 'media-editor' or after wp_enqueue_media()).
 */
(function ($) {
	'use strict';

	function initPicker(selectId, inputId, previewId, removeId) {
		var $select = $(selectId);
		var $input = $(inputId);
		var $preview = $(previewId);
		var $remove = $(removeId);
		if (!$select.length || !$input.length || !$preview.length) {
			return;
		}
		var frame;
		$select.off('click.fsImagePicker').on('click.fsImagePicker', function (e) {
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
		$remove.off('click.fsImagePicker').on('click.fsImagePicker', function (e) {
			e.preventDefault();
			$input.val('0');
			$preview.empty();
			$(this).hide();
		});
	}

	function init() {
		if (typeof wp === 'undefined' || !wp.media) {
			return;
		}
		initPicker('#fs_client_logo_select', '#fromscratch_client_logo', '#fs_client_logo_preview', '#fs_client_logo_remove');
		initPicker('#fs_og_fallback_select', '#fromscratch_og_image_fallback', '#fs_og_fallback_preview', '#fs_og_fallback_remove');
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
