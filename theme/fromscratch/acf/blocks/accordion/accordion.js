import $ from 'jquery';
import {
  scrollToElement,
  getOffset
} from '../../../src/js/utils/scroll-to-element';
import config from '../../../src/js/config';

$(function () {
  $('.accordion__header').on('click keydown', function (e) {
    if (e.type === 'keydown' && e.key !== 'Enter') {
      return;
    }
    var wrapper = $(this).parents('.accordion__wrapper');
    var accordionIsOpen = wrapper.hasClass('accordion-open');
    if (accordionIsOpen) {
      closeAccordion(wrapper);
      return;
    }
    if (wrapper.attr('data-close-neighbouring-accordions') === 'true') {
      let wrapperSiblings = $();
      wrapperSiblings = wrapperSiblings.add(
        wrapper.prevUntil(':not(.accordion__wrapper)')
      );
      wrapperSiblings = wrapperSiblings.add(
        wrapper.nextUntil(':not(.accordion__wrapper)')
      );
      wrapperSiblings
        .filter('.accordion__wrapper.accordion-open')
        .each(function (index, item) {
          closeAccordion($(item));
        });
    }
    openAccordion(wrapper);
  });

  const windowHash = window.location.hash;
  if (windowHash) {
    const hashId = windowHash.replace('#', '');
    const accordionWrapper = $(
      '.accordion__wrapper[data-accordion-id="' + hashId + '"]'
    ).first();

    if (accordionWrapper.length) {
      scrollToElement($(accordionWrapper)[0], getOffset(), function () {
        openAccordion($(accordionWrapper));
      });
    }
  }
});

function openAccordion(wrapper) {
  wrapper.addClass('accordion-open');
  wrapper.attr('aria-expanded', 'true');
  wrapper.find('.accordion__content').slideDown({
    duration: config.transitionSpeed,
    queue: false,
    complete: function () {
      if (wrapper.attr('data-scroll-to-accordion-top') === 'true') {
        scrollToElement($(wrapper)[0], getOffset());
      }
    }
  });
}

function closeAccordion(wrapper) {
  wrapper.removeClass('accordion-open');
  wrapper.attr('aria-expanded', 'false');
  wrapper.find('.accordion__content').slideUp({
    duration: config.transitionSpeed,
    queue: false
  });
}
