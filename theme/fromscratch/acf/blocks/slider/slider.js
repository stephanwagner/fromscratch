import $ from 'jquery';
import Swiper from 'swiper';
import { Autoplay, EffectFade, Pagination, Navigation } from 'swiper/modules';

const sliders = $('.slider__wrapper');

$.each(sliders, function (index, slider) {
  // Wrapper
  const sliderWrapper = $(slider);

  // Id
  const id = sliderWrapper.attr('data-slider-id');

  // Modules
  const modules = [Autoplay, Pagination, Navigation];

  // Construct config object
  const sliderConfig = {
    wrapperClass: 'acf-innerblocks-container',
    effect: sliderWrapper.attr('data-slider-animation') || 'slide',
    speed: 800
  };

  // Animation effect
  switch (sliderWrapper.attr('data-slider-animation')) {
    case 'fade':
      modules.push(EffectFade);
      sliderConfig.fadeEffect = {
        crossFade: true
      };
      break;
  }

  // Loop
  sliderConfig.loop = sliderWrapper.attr('data-slider-loop') === 'true';

  // Space between
  sliderConfig.spaceBetween =
    parseInt(sliderWrapper.attr('data-slider-space-between')) || 16;

  // Slides per view
  sliderConfig.slidesPerView =
    parseInt(sliderWrapper.attr('data-slider-slides-per-view')) || 1;

  // Slides per group
  sliderConfig.slidesPerGroup =
    parseInt(sliderWrapper.attr('data-slider-slides-per-group')) || 1;

  if (sliderConfig.slidesPerView == 2) {
    sliderConfig.breakpoints = {
      600: {
        slidesPerView: 2
      },
      0: {
        slidesPerView: 1
      }
    };
  }
  if (sliderConfig.slidesPerView == 3) {
    sliderConfig.breakpoints = {
      900: {
        slidesPerView: 3
      },
      600: {
        slidesPerView: 2
      },
      0: {
        slidesPerView: 1
      }
    };
  }
  if (sliderConfig.slidesPerView == 4) {
    sliderConfig.breakpoints = {
      1200: {
        slidesPerView: 4
      },
      900: {
        slidesPerView: 3
      },
      600: {
        slidesPerView: 2
      },
      0: {
        slidesPerView: 1
      }
    };
  }
  if (sliderConfig.slidesPerView >= 5) {
    sliderConfig.breakpoints = {
      1200: {
        slidesPerView: sliderConfig.slidesPerView
      },
      900: {
        slidesPerView: 4
      },
      600: {
        slidesPerView: 2
      },
      0: {
        slidesPerView: 1
      }
    };
  }

  // Pagination
  if (sliderWrapper.attr('data-slider-pagination') === 'true') {
    sliderConfig.pagination = {
      el: '.slider__pagination',
      clickable: true,
      dynamicBullets: true,
      dynamicMainBullets: 3
    };
  }

  // Navigation
  if (sliderWrapper.attr('data-slider-navigation') === 'true') {
    sliderConfig.navigation = {
      nextEl: '.slider__navigation .slider__button-next',
      prevEl: '.slider__navigation .slider__button-prev'
    };
  }

  // Autoplay
  if (sliderWrapper.attr('data-slider-autoplay') === 'true') {
    let autoplayDelay = parseFloat(
      sliderWrapper.attr('data-slider-autoplay-delay')
    );
    if (!autoplayDelay) {
      autoplayDelay = 6000;
    } else {
      autoplayDelay *= 1000;
    }

    sliderConfig.autoplay = {
      delay: autoplayDelay,
      disableOnInteraction: true,
      pauseOnMouseEnter: false
    };
  }

  // Modules
  sliderConfig.modules = modules;

  // Slider selector
  const sliderSelector =
    '.slider__wrapper[data-slider-id="' + id + '"] .swiper';

  // Init slider
  const swiper = new Swiper(sliderSelector, sliderConfig);

  // Stop autoplay then clicking on or in slide
  sliderWrapper.find('.slider-slide__wrapper').on('click', function () {
    swiper.autoplay.stop();
  });

  // Stop autoplay when starting to play video
  sliderWrapper.find('video').on('play', function () {
    swiper.autoplay.stop();
  });
});
