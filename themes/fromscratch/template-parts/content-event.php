<?php

defined('ABSPATH') || exit;

$range = function_exists('fs_event_format_range_text') ? fs_event_format_range_text(get_the_ID()) : '';
?>

<article id="post-<?php the_ID(); ?>" <?php post_class('archive__item event-archive__item'); ?>>
	<?php if (has_post_thumbnail()) : ?>
		<a class="archive__thumb-link" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
			<?php the_post_thumbnail('small', ['class' => 'archive__thumb', 'loading' => 'lazy']); ?>
		</a>
	<?php endif; ?>
	<div class="archive__body">
		<?php if ($range !== '') : ?>
			<p class="event-archive__range"><?php echo esc_html($range); ?></p>
		<?php endif; ?>
		<h2 class="archive__title">
			<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
		</h2>
		<div class="archive__excerpt"><?php the_excerpt(); ?></div>
		<p class="archive__more">
			<a class="archive__readmore" href="<?php the_permalink(); ?>"><?php esc_html_e('Read more', 'fromscratch'); ?></a>
		</p>
	</div>
</article>
