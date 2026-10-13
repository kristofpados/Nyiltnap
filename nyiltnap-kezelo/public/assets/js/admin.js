/*
 * Admin enhancements. Every action also works without JavaScript:
 * the delete link leads to a confirmation page, filters are a plain GET form.
 */
(function () {
  'use strict';

  /* ---- Accessible delete dialog ---- */
  var dialog = document.getElementById('delete-dialog');
  if (dialog && typeof dialog.showModal === 'function') {
    var form = dialog.querySelector('[data-delete-form]');
    var cancel = dialog.querySelector('[data-dialog-close]');
    var confirmButton = form.querySelector('[type="submit"]');
    var lastTrigger = null;

    var fill = function (selector, value) {
      var el = dialog.querySelector(selector);
      if (el) el.textContent = value || '–';
    };

    document.addEventListener('click', function (event) {
      var trigger = event.target.closest('[data-delete-trigger]');
      if (!trigger) return;
      event.preventDefault();
      lastTrigger = trigger;

      form.action = trigger.getAttribute('data-action');
      form.querySelector('[data-delete-reference]').value = trigger.getAttribute('data-reference');
      fill('[data-delete-name]', trigger.getAttribute('data-name'));
      fill('[data-delete-school]', trigger.getAttribute('data-school'));
      fill('[data-delete-reference-label]', trigger.getAttribute('data-reference-label'));
      fill('[data-delete-date]', trigger.getAttribute('data-date'));
      form.dataset.submitting = '';
      confirmButton.removeAttribute('aria-disabled');

      dialog.showModal();
      // The safe choice gets focus first.
      cancel.focus();
    });

    cancel.addEventListener('click', function () { dialog.close(); });

    // Clicking the dimmed backdrop closes the dialog (the dialog box itself fills the form).
    dialog.addEventListener('click', function (event) {
      if (event.target === dialog) dialog.close();
    });

    dialog.addEventListener('close', function () {
      if (lastTrigger) lastTrigger.focus();
    });

    form.addEventListener('submit', function (event) {
      if (form.dataset.submitting === 'true') {
        event.preventDefault();
        return;
      }
      form.dataset.submitting = 'true';
      confirmButton.setAttribute('aria-disabled', 'true');
    });
  }

  /* ---- Filter form: omit empty parameters for clean, shareable URLs ---- */
  var filterForm = document.querySelector('[data-filter-form]');
  if (filterForm) {
    filterForm.addEventListener('submit', function () {
      Array.prototype.forEach.call(filterForm.elements, function (el) {
        if (!el.name || el.disabled) return;
        var emptyRadio = el.type === 'radio' && el.checked && el.value === '';
        var emptyField = (el.tagName === 'SELECT' || el.type === 'search') && el.value === '';
        if (emptyRadio || emptyField) el.disabled = true;
      });
    });
    window.addEventListener('pageshow', function () {
      Array.prototype.forEach.call(filterForm.elements, function (el) { el.disabled = false; });
    });
  }

  /* ---- Print report (button only appears when it can work) ---- */
  Array.prototype.forEach.call(document.querySelectorAll('[data-print]'), function (button) {
    button.hidden = false;
    button.addEventListener('click', function () { window.print(); });
  });
  window.addEventListener('beforeprint', function () {
    Array.prototype.forEach.call(document.querySelectorAll('details'), function (d) {
      d.dataset.wasOpen = d.open ? '1' : '';
      d.open = true;
    });
  });
  window.addEventListener('afterprint', function () {
    Array.prototype.forEach.call(document.querySelectorAll('details'), function (d) {
      d.open = d.dataset.wasOpen === '1';
    });
  });

  /* ---- Show / hide password on the login page ---- */
  var toggle = document.querySelector('[data-password-toggle]');
  if (toggle) {
    var input = document.getElementById(toggle.getAttribute('aria-controls'));
    toggle.hidden = false;
    toggle.setAttribute('aria-label', 'Jelszó megjelenítése');
    toggle.removeAttribute('aria-pressed');
    toggle.addEventListener('click', function () {
      var show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      toggle.textContent = show ? 'Elrejtés' : 'Mutasd';
      toggle.setAttribute('aria-label', show ? 'Jelszó elrejtése' : 'Jelszó megjelenítése');
      input.focus();
    });
  }

  /* ---- Login: prevent double submission ---- */
  var loginForm = document.querySelector('[data-login-form]');
  if (loginForm) {
    loginForm.addEventListener('submit', function (event) {
      if (loginForm.dataset.submitting === 'true') {
        event.preventDefault();
        return;
      }
      loginForm.dataset.submitting = 'true';
    });
    window.addEventListener('pageshow', function () { loginForm.dataset.submitting = ''; });
  }
})();
