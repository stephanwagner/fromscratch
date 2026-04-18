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

		<header class="archive__header">
			<h1 class="archive__heading"><?php echo wp_kses_post($archive_heading); ?></h1>
		</header>

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
						get_template_part('template-parts/content', 'event');
					}
					?>
				</div>
			<?php else : ?>
				<div class="archive__list">
					<?php
					while (have_posts()) {
						the_post();
						get_template_part('template-parts/content', 'archive');
					}
					?>
				</div>
			<?php endif; ?>

			<nav class="archive__pagination" aria-label="<?php esc_attr_e('Posts pagination', 'fromscratch'); ?>">
				<?php
				the_posts_pagination([
					'mid_size'  => 2,
					'prev_text' => __('Previous', 'fromscratch'),
					'next_text' => __('Next', 'fromscratch'),
				]);
				?>
			</nav>
		<?php else : ?>
			<p class="archive__empty"><?php echo $is_event_archive ? esc_html__('No events found.', 'fromscratch') : esc_html__('No posts found.', 'fromscratch'); ?></p>
		<?php endif; ?>

	</div>
</div>

<?php get_footer(); ?>
