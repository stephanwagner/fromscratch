// Menu toggler
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-toggle-menu]').forEach((el) => {
    el.addEventListener('click', toggleMenu);
  });
});

// Toggle menu
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

// Open menu
export function openMenu() {
  document.body.classList.add('-menu-open', '-menu-block-scroll');
}

// Close menu
export function closeMenu() {
  document.body.classList.remove('-menu-open', '-menu-block-scroll');
}

// Check if menu is open
export function menuIsOpen() {
  return document.body.classList.contains('-menu-open');
}

// Submenu togglers
function initSubmenuTogglers() {
  var toggles = document.querySelectorAll('.sub-menu-toggle[aria-controls]');

  if (!toggles.length) {
    return;
  }

  function setExpanded(btn, expanded) {
    var submenuId = btn.getAttribute('aria-controls');
    if (!submenuId) {
      return;
    }

    var submenu = document.getElementById(submenuId);
    if (!submenu) {
      return;
    }

    btn.setAttribute('aria-expanded', expanded ? 'true' : 'false');

    var li = btn.closest('li');
    if (li) {
      li.classList.toggle('is-submenu-open', !!expanded);
    }
  }

  toggles.forEach(function (btn) {
    setExpanded(btn, false);
    btn.addEventListener('click', function () {
      var expanded = btn.getAttribute('aria-expanded') === 'true';
      setExpanded(btn, !expanded);
    });
  });

  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;

    var active = document.activeElement;

    // If a toggle itself has focus, close that exact submenu first.
    var activeToggle = active ? active.closest('.sub-menu-toggle[aria-controls]') : null;
    if (activeToggle && activeToggle.getAttribute('aria-expanded') === 'true') {
      setExpanded(activeToggle, false);
      activeToggle.focus();
      return;
    }

    // If focus is inside an open menu-item-with-children, close that item's submenu.
    var activeOpenItem = active ? active.closest('li.menu-item-has-children.is-submenu-open') : null;
    if (activeOpenItem) {
      var ownedSubmenu = null;
      for (var i = 0; i < activeOpenItem.children.length; i += 1) {
        var child = activeOpenItem.children[i];
        if (child && child.classList && child.classList.contains('sub-menu') && child.id) {
          ownedSubmenu = child;
          break;
        }
      }
      if (ownedSubmenu) {
        var ownedToggle = activeOpenItem.querySelector('.menu-item__inner > .sub-menu-toggle[aria-controls]');
        if (ownedToggle && ownedToggle.getAttribute('aria-controls') === ownedSubmenu.id && ownedToggle.getAttribute('aria-expanded') === 'true') {
          setExpanded(ownedToggle, false);
          ownedToggle.focus();
          return;
        }
      }
    }

    // Otherwise close submenu nearest to current focus.
    var activeSubmenu = active ? active.closest('.sub-menu[id]') : null;
    if (activeSubmenu) {
      var ownerToggle = document.querySelector('.sub-menu-toggle[aria-controls="' + activeSubmenu.id + '"]');
      if (ownerToggle && ownerToggle.getAttribute('aria-expanded') === 'true') {
        setExpanded(ownerToggle, false);
        ownerToggle.focus();
        return;
      }
    }

    // No submenu focus context: close the main menu drawer if open.
    if (menuIsOpen()) {
      closeMenu();
    }
  });
}

initSubmenuTogglers();
