/**
 * Developer section on the FromScratch install page:
 * - When only the new user has developer rights: force "Log in as this user" checked (readonly, value still sent).
 * - When only the current user has developer rights: uncheck "Log in as this user".
 * Runs once on DOMContentLoaded.
 */

function initDeveloperInstaller() {
  const form = document.querySelector('form[data-fs-install-form]');

  if (!form) {
    return;
  }

  const currentDevCheckbox = form.querySelector(
    'input[type="checkbox"][name="developer[current_user][has_developer_rights]"]'
  );
  const newDevCheckbox = form.querySelector(
    'input[type="checkbox"][name="developer[new_user][has_developer_rights]"]'
  );
  const loginAfterSetupCheckbox = form.querySelector(
    'input[type="checkbox"][name="developer[new_user][login_after_setup]"]'
  );

  if (!newDevCheckbox || !loginAfterSetupCheckbox) {
    return;
  }

  function syncLoginAfterSetup() {
    const currentHasDev = currentDevCheckbox?.checked ?? false;
    const newHasDev = newDevCheckbox.checked;

    if (!currentHasDev && newHasDev) {
      // Only new user will be developer → force "Log in as this user" (readonly, value still sent).
      loginAfterSetupCheckbox.checked = true;
      loginAfterSetupCheckbox.setAttribute('data-fs-forced', '1');
    } else {
      loginAfterSetupCheckbox.removeAttribute('data-fs-forced');
      if (currentHasDev && !newHasDev) {
        // Only current user is developer → uncheck "Log in as this user".
        loginAfterSetupCheckbox.checked = false;
      }
    }
  }

  loginAfterSetupCheckbox.addEventListener('click', (e) => {
    if (loginAfterSetupCheckbox.getAttribute('data-fs-forced') === '1') {
      e.preventDefault();
      loginAfterSetupCheckbox.checked = true;
    }
  });

  currentDevCheckbox?.addEventListener('change', syncLoginAfterSetup);
  newDevCheckbox.addEventListener('change', syncLoginAfterSetup);

  syncLoginAfterSetup();
}

document.addEventListener('DOMContentLoaded', initDeveloperInstaller);
