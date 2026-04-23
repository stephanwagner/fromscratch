document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-toggle-menu]').forEach(el => {
      el.addEventListener('click', toggleMenu);
    });
  });
  
  export function toggleMenu() {
    if (menuIsOpen()) {
      closeMenu();
    } else {
      openMenu();
    }
  
    const overlay = document.querySelector('.header__menu-overlay');
    if (overlay) {
      overlay.addEventListener('click', closeMenu, { once: true });
    }
  }
  
  export function openMenu() {
    document.body.classList.add('-menu-open', '-menu-block-scroll');
  }
  
  export function closeMenu() {
     document.body.classList.remove('-menu-open', '-menu-block-scroll');
  }
  
  export function menuIsOpen() {
    return document.body.classList.contains('-menu-open');
  }
  