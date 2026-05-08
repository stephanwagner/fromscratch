import $ from 'jquery';
import { scrollToElement } from '../../../src/js/utils/scroll-to-element';

$(function () {
  $('.accordion__header').on('click', function () {
    var wrapper = $(this).parents('.accordion__wrapper');
    var accordionIsOpen = wrapper.hasClass('accordion-open');
    if (accordionIsOpen) {
      closeAccordion(wrapper);
      return;
    }
    closeAccordion($('.accordion__wrapper.accordion-open'));
    openAccordion(wrapper);
  });

  if (window.location.hash) {
    $('.accordion__wrapper[id]').each(function (index, item) {
      var id = $(item).attr('id');
      var hash = window.location.hash;
      if ('#' + id == hash) {
        openAccordion($(item));
      }
    });
  }
});

function openAccordion(wrapper) {
  wrapper.addClass('accordion-open');
  wrapper.find('.accordion__content').slideDown({
    duration: 320,
    queue: false,
    complete: function () {
      scrollToElement($(wrapper), -8);
    }
  });
}

function closeAccordion(wrapper) {
  wrapper.removeClass('accordion-open');
  wrapper.find('.accordion__content').slideUp({
    duration: 320,
    queue: false,
  });
}
