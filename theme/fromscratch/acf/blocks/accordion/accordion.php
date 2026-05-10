<?php
$title = get_field('title');
$id = get_field('id');
$closeNeighbouringAccordions = get_field('close_neighbouring_accordions');
$scrollToAccordionTop = get_field('scroll_to_accordion_top');
$isOpen = get_field('accordion_is_open');

global $globalAccordionId;

if (empty($globalAccordionId)) {
	$globalAccordionId = 0;
}
$globalAccordionId += 1;

$accordionId = $id ? $id : 'accordion-' . $globalAccordionId;
?>

<div
    class="accordion__wrapper<?= $isOpen ? ' accordion-open' : '' ?>"
	data-accordion-id="<?= $accordionId ?>"
    data-close-neighbouring-accordions="<?= $closeNeighbouringAccordions ? 'true' : 'false' ?>"
    data-scroll-to-accordion-top="<?= $scrollToAccordionTop ? 'true' : 'false' ?>"
    data-accordion-is-open="<?= $isOpen ? 'true' : 'false' ?>"
>
	<div class="accordion__container">
		<div
			class="accordion__header noselect"
			id="accordion-header-<?= $accordionId ?>"
			role="button"
			tabindex="0"
			aria-expanded="<?= $isOpen ? 'true' : 'false' ?>"
			aria-controls="accordion-content-<?= $accordionId ?>"
		>
			<div
				class="accordion__title"
			>
				<?= $title ?>
			</div>
			<div class="accordion__icon">
				<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor">
					<path d="M466.54-375.23q-6.23-2.31-11.85-7.92L274.92-562.92q-8.3-8.31-8.5-20.89-.19-12.57 8.5-21.27 8.7-8.69 21.08-8.69 12.38 0 21.08 8.69L480-442.15l162.92-162.93q8.31-8.3 20.89-8.5 12.57-.19 21.27 8.5 8.69 8.7 8.69 21.08 0 12.38-8.69 21.08L505.31-383.15q-5.62 5.61-11.85 7.92-6.23 2.31-13.46 2.31t-13.46-2.31Z"/>
				</svg>
			</div>
		</div>
		<div
			class="accordion__content"
			id="accordion-content-<?= $accordionId ?>"
			aria-labelledby="accordion-header-<?= $accordionId ?>"
		>
            <div class="accordion__content-inner">
				<InnerBlocks />
            </div>
		</div>
	</div>
</div>
