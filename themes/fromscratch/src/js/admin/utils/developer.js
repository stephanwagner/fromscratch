/**
 * Developer section logic for the FromScratch installer.
 * If the current user does NOT have developer rights and the new user DOES,
 * auto-check the "log in as this user after setup" checkbox.
 *
 * @param {HTMLElement} [root=document]
 */
function initDeveloperInstaller(root = document) {
  const scope = root || document;

  const currentDevCheckbox = scope.querySelector(
    'input[type="checkbox"][name="developer[current_user][has_developer_rights]"]'
  );
  const newDevCheckbox = scope.querySelector(
    'input[type="checkbox"][name="developer[new_user][has_developer_rights]"]'
  );
  const loginAfterSetupCheckbox = scope.querySelector(
    'input[type="checkbox"][name="developer[new_user][login_after_setup]"]'
  );

  if (!newDevCheckbox || !loginAfterSetupCheckbox) {
    return;
  }

  function updateLoginAfterSetup() {
    const currentHasDev = currentDevCheckbox ? currentDevCheckbox.checked : false;
    const newHasDev = newDevCheckbox.checked;

    // If only the new user will be a developer, force login as that user and make it readonly.
    if (!currentHasDev && newHasDev) {
      loginAfterSetupCheckbox.checked = true;
      loginAfterSetupCheckbox.disabled = true;
    } else {
      // If only the current user is developer (right is not), uncheck "log in as this user".
      if (currentHasDev && !newHasDev) {
        loginAfterSetupCheckbox.checked = false;
      }
      loginAfterSetupCheckbox.disabled = false;
    }
  }

  if (currentDevCheckbox) {
    currentDevCheckbox.addEventListener('change', updateLoginAfterSetup);
  }
  newDevCheckbox.addEventListener('change', updateLoginAfterSetup);

  // Initialize once on load to reflect the default checkbox state.
  updateLoginAfterSetup();
}

window.fromscratchInitDeveloperInstaller = initDeveloperInstaller;

document.addEventListener('DOMContentLoaded', () => {
  initDeveloperInstaller();
});

