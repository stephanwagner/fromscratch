import $ from 'jquery';
import { CountUp } from 'countup.js';
import { onEnterViewport } from '../../../src/js/utils/viewport.js';

const containerSelector = '.number-ticker__wrapper';

$(function () {
  const tickerContainer = $(containerSelector);
  if (tickerContainer.length) {
    onEnterViewport(containerSelector, function () {
      $.each($(containerSelector + ' [data-countup]'), function (index, el) {
        const startNumber = $(el).html();
        const targetNumber = $(el).attr('data-countup');
        const numAnim = new CountUp(el, targetNumber, {
          startVal: startNumber,
          separator: '',
          decimalPlaces: 0,
          duration: 3
        });
        numAnim.start();
      });
    });
  }
});
