<?php

defined('ABSPATH') || exit;

get_header();

$archive_heading = get_the_archive_title();
if (is_post_type_archive()) {
	$pto = get_queried_object();
	if ($pto instanceof \WP_Post_Type && isset($pto->labels->name)) {
		$archive_heading = $pto->labels->name;
	}
}

$is_event_archive = defined('FS_EVENT_POST_TYPE') && is_post_type_archive(FS_EVENT_POST_TYPE);
?>

<div class="content__wrapper">
	<div class="content__container container">

		<?= fs_breadcrumbs() ?>

		<div class="content__content">

			<h1><?php echo wp_kses_post($archive_heading); ?></h1>

			<?php if (have_posts()) : ?>
				<?php if ($is_event_archive) : ?>
					<div class="event-archive">
						<?php
						$month_marker = '';
						while (have_posts()) {
							the_post();
							$start_ts = (int) get_post_meta(get_the_ID(), FS_EVENT_META_START_TS, true);
							if ($start_ts > 0) {
								$month_label = wp_date('F Y', $start_ts);
							} else {
								$sd = get_post_meta(get_the_ID(), FS_EVENT_META_START_DATE, true);
								$month_label = is_string($sd) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $sd)
									? wp_date('F Y', strtotime($sd . ' 12:00:00'))
									: '';
							}
							if ($month_label !== '' && $month_label !== $month_marker) {
								$month_marker = $month_label;
								echo '<h2 class="event-archive__month">' . esc_html($month_label) . '</h2>';
							}
							fs_render_template('__content-event.php');
						}
						?>
					</div>
				<?php else : ?>
					<div class="archive__list">
						<?php
						while (have_posts()) {
							the_post();
							fs_render_template('post-preview.php');
						}
						?>
					</div>
				<?php endif; ?>

				<?php
				fs_render_template('pagination.php', [
					'aria_label' => __('Posts pagination', 'fromscratch'),
					'pagination_args' => [
						'mid_size'  => 2,
						'prev_text' => __('Previous', 'fromscratch'),
						'next_text' => __('Next', 'fromscratch'),
					],
				]);
				?>
			<?php else : ?>
				<p class="archive__empty"><?php echo $is_event_archive ? esc_html__('No events found.', 'fromscratch') : esc_html__('No posts found.', 'fromscratch'); ?></p>
			<?php endif; ?>
		</div>

	</div>
</div>

<?php get_footer(); ?>