import $ from 'jquery';

import config from '../../../src/js/config';
import { scrollToElement } from '../../../src/js/utils/scroll-to-element';
import { closeMenu } from '../../../src/js/main/menu';

function getOffset() {
  let offset = config.scrollOffset * -1;
  offset -= config.headerHeightScrolled;
  const adminBar = document.getElementById('wpadminbar');
  if (adminBar) {
    offset -= adminBar.offsetHeight;
  }
  return offset;
}

$(function () {
  if ($('[data-anchor-id]').length) {
    $('a[href*="#"]').each(function (index, item) {
      const link = $(item);
      const href = link.attr('href');
      const hrefSplit = href.split('#');
      let targetEl = $(
        '[data-anchor-id="' + hrefSplit[hrefSplit.length - 1] + '"]'
      );
      if (targetEl.length) {
        if (targetEl.next().length) {
          targetEl = targetEl.next();
        }
        link.on('click', function () {
          closeMenu();
          const offset = getOffset(targetEl);
          scrollToElement(targetEl, offset, true);
        });
      }
    });
  }

  var checkActiveNav = function () {
    $($('[data-anchor-id]').get().reverse()).each(function (index, item) {
      var id = $(item).attr('data-anchor-id');
      var windowTop = $(document).scrollTop();
      let itemTop = $(item).offset().top;
      if ($(item).next().length) {
        itemTop = $(item).next().offset().top;
      }
      $('header .menu-item').removeClass('-current-active');

      let offset = getOffset() * -1 + 4;

      if (windowTop >= 16 && windowTop > itemTop - offset) {
        $('header .menu-item').each(function (index, item) {
          const link = $(item).find('> a[href*="#' + id + '"]');
          if (link.length) {
            $(item).addClass('-current-active');
          }
        });
        return false;
      }
    });
  };

  $(window).on('scroll resize', checkActiveNav);
  checkActiveNav();
});

// Add automatic scroll

$(window).on('load', function () {
  if (window.location.hash) {
    const hashid = window.location.hash.replace('#', '');
    const targetEl = $('[data-anchor-id="' + hashid + '"]');
    if (targetEl.length) {
      const offset = getOffset(targetEl);
      scrollToElement(targetEl, offset, true);
    }
  }
});
