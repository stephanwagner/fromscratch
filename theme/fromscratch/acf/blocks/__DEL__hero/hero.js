import $ from 'jquery';
import 'slick-carousel';

$(function () {
  const heroSliderEl = $('.hero__slides');

  // Init callback
  heroSliderEl.on('init', function (ev, heroSlider) {
    heroSliderEl.addClass('-init').on('click', function () {
      heroSlider.pause();
    });
  });

  heroSliderEl.on('swipe', function (ev, heroSlider) {
    heroSlider.pause();
  });

  // Hero slider
  heroSliderEl.slick({
    dots: true,
    arrows: true,
    infinite: true,
    speed: 600,
    slidesToShow: 1,
    autoplay: true,
    autoplaySpeed: 6000
  });
});
