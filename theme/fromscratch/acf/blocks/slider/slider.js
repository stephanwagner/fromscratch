import $ from 'jquery';
import 'slick-carousel';

$(function () {
  const imageSliderEl = $('.image-slider__slides');

  // Init callback
  imageSliderEl.on('init', function (ev, imageSlider) {
    imageSliderEl.addClass('-init').on('click', function () {
      imageSlider.pause();
    });
  });

  imageSliderEl.on('swipe', function (ev, imageSlider) {
    imageSlider.pause();
  });

  const config = {
    dots: true,
    arrows: true,
    infinite: true,
    speed: 600,
    slidesToShow: 1,
    slidesToScroll: 1,
    autoplay: true,
    autoplaySpeed: 6000
  };

  const amount = parseInt(imageSliderEl.attr('data-amount')) || 1;

  if (amount == 2) {
    config.slidesToShow = 2;
    config.responsive = [
      {
        breakpoint: 450,
        settings: {
          slidesToShow: 1,
          slidesToScroll: 1,
          arrows: false,
        }
      }
    ]
  }

  if (amount == 3) {
    config.slidesToShow = 3;
    config.responsive = [
      {
        breakpoint: 600,
        settings: {
          slidesToShow: 2,
          slidesToScroll: 2,
          arrows: false,
        }
      },
      {
        breakpoint: 450,
        settings: {
          slidesToShow: 1,
          slidesToScroll: 1,
          arrows: false,
        }
      }
    ];
  }

  imageSliderEl.slick(config);
});
