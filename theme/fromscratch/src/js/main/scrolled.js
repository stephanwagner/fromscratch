import config from '../config';

document.addEventListener('DOMContentLoaded', () => {
  window.addEventListener('scroll', checkScroll);
  window.addEventListener('resize', checkScroll);
  checkScroll();
});

let isScrolled = false;

function checkScroll() {
  const shouldBeScrolled = window.scrollY >= config.startScrolled;

  if (shouldBeScrolled !== isScrolled) {
    document.body.classList.toggle('-scrolled', shouldBeScrolled);
    isScrolled = shouldBeScrolled;
  }
}