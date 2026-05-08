<?php
$title = get_field('title');
$content = get_field('content');
$closeNeighbouringAccordions = get_field('close_neighbouring_accordions');
$scrollToAccordionTop = get_field('scroll_to_accordion_top');

global $accordionId;

if (empty($accordionId)) {
	$accordionId = 0;
}
$accordionId += 1;
?>

<div
    class="accordion__wrapper"
	data-accordion-id="<?= $accordionId ?>"
    data-close-neighbouring-accordions="<?= $closeNeighbouringAccordions ? 'true' : 'false' ?>"
    data-scroll-to-accordion-top="<?= $scrollToAccordionTop ? 'true' : 'false' ?>"
>
	<div class="accordion__container">
		<div class="accordion__header noselect">
			<div class="accordion__title">
				<?= $title ?>
			</div>
			<div class="accordion__icon">
				<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor">
					<path d="M465-363.5q-7-2.5-13-8.5L268-556q-11-11-11-28t11-28q11-11 28-11t28 11l156 156 156-156q11-11 28-11t28 11q11 11 11 28t-11 28L508-372q-6 6-13 8.5t-15 2.5q-8 0-15-2.5Z"/>
				</svg>
			</div>
		</div>
		<div class="accordion__content">
            <div class="accordion__content-inner">
                <?= $content ?>
            </div>
		</div>
	</div>
</div>
