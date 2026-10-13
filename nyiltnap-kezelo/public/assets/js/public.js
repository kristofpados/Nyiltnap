/*
 * Public registration form — progressive enhancement only.
 * Without JavaScript the browser's native validation and the server-side
 * validation still apply; this script adds Hungarian inline messages that
 * mirror the server rules, a live selection counter and a double-submit guard.
 */
(function () {
  'use strict';

  var form = document.querySelector('[data-registration-form]');
  if (!form) {
    return;
  }

  var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var summary = form.querySelector('[data-error-summary]');
  var summaryList = form.querySelector('[data-error-summary-list]');
  var submit = form.querySelector('[data-submit]');
  var submitLabel = form.querySelector('[data-submit-label]');
  var counter = form.querySelector('[data-selection-count]');
  var programInputs = Array.prototype.slice.call(form.querySelectorAll('input[name="programs[]"]'));

  var NAME_PATTERN = /^[\p{L}\p{M}][\p{L}\p{M} .'’‐-]*$/u;
  // JS \s already covers every Unicode space; add the zero-width space like the server does.
  var WHITESPACE = new RegExp('[\\s' + String.fromCharCode(0x200B) + ']+', 'g');

  function clean(value) {
    return value.replace(WHITESPACE, ' ').trim();
  }
  function length(value) {
    return Array.from(value).length;
  }
  function letters(value) {
    return (value.match(/\p{L}/gu) || []).length;
  }
  function checked(name) {
    return form.querySelector('input[name="' + name + '"]:checked') !== null;
  }

  // Same rules and wording as app/Forms/RegistrationForm.php.
  var validators = {
    full_name: function () {
      var v = clean(form.elements.full_name.value);
      if (v === '') return 'Add meg a teljes nevedet.';
      if (length(v) < 2) return 'A név legalább 2 karakter legyen.';
      if (length(v) > 120) return 'A név legfeljebb 120 karakter lehet.';
      if (!NAME_PATTERN.test(v)) return 'A név csak betűket, szóközt, kötőjelet, pontot és aposztrófot tartalmazhat.';
      if (letters(v) < 2) return 'A név legalább két betűt tartalmazzon.';
      return null;
    },
    current_school: function () {
      var v = clean(form.elements.current_school.value);
      if (v === '') return 'Add meg, melyik iskolába jársz jelenleg.';
      if (length(v) < 2) return 'Az iskola neve legalább 2 karakter legyen.';
      if (length(v) > 180) return 'Az iskola neve legfeljebb 180 karakter lehet.';
      if (letters(v) < 1) return 'Az iskola neve betűket is tartalmazzon.';
      return null;
    },
    programs: function () {
      if (programInputs.length === 0) return 'Jelenleg nincs választható szakma. Kérjük, jelezd az iskolának.';
      return programInputs.some(function (i) { return i.checked; }) ? null : 'Válassz legalább egy szakmát, amely érdekel.';
    },
    residency: function () {
      return checked('residency') ? null : 'Válaszd ki, hogy kollégista vagy bejárós lennél.';
    },
    graduating: function () {
      return checked('graduating') ? null : 'Jelöld meg, hogy végzős vagy-e.';
    }
  };

  var targets = {
    full_name: 'full_name',
    current_school: 'current_school',
    programs: programInputs.length ? programInputs[0].id : 'programs',
    residency: 'residency-boarding',
    graduating: 'graduating-yes'
  };

  function toggleDescribedBy(element, id, on) {
    var ids = (element.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean);
    ids = ids.filter(function (x) { return x !== id; });
    if (on) ids.push(id);
    if (ids.length) {
      element.setAttribute('aria-describedby', ids.join(' '));
    } else {
      element.removeAttribute('aria-describedby');
    }
  }

  function setError(field, message) {
    var box = form.querySelector('[data-error-for="' + field + '"]');
    if (box) {
      box.hidden = !message;
      box.querySelector('span').textContent = message || '';
    }
    var input = form.elements[field];
    if (field === 'full_name' || field === 'current_school') {
      if (message) {
        input.setAttribute('aria-invalid', 'true');
      } else {
        input.removeAttribute('aria-invalid');
      }
      toggleDescribedBy(input, field + '-error', !!message);
    } else {
      var group = form.querySelector('[data-field="' + field + '"]');
      if (group) {
        group.classList.toggle('has-error', !!message);
        toggleDescribedBy(group, field + '-error', !!message);
      }
    }
  }

  function collectErrors() {
    return Object.keys(validators)
      .map(function (field) { return [field, validators[field]()]; })
      .filter(function (pair) { return pair[1] !== null; });
  }

  function renderSummary(errors) {
    if (!summary || !summaryList) return;
    while (summaryList.firstChild) summaryList.removeChild(summaryList.firstChild);
    errors.forEach(function (pair) {
      var li = document.createElement('li');
      var a = document.createElement('a');
      a.href = '#' + targets[pair[0]];
      a.textContent = pair[1];
      li.appendChild(a);
      summaryList.appendChild(li);
    });
    summary.hidden = errors.length === 0;
  }

  function validateField(field) {
    var message = validators[field]();
    setError(field, message);
    // Keep an already visible summary in sync without stealing focus.
    if (summary && !summary.hidden) renderSummary(collectErrors());
    return message;
  }

  function updateCounter() {
    if (!counter) return;
    var n = programInputs.filter(function (i) { return i.checked; }).length;
    counter.textContent = n === 0 ? '' : '· ' + n + ' kiválasztva';
  }

  // Our own messages replace the browser's native bubbles.
  form.noValidate = true;

  ['full_name', 'current_school'].forEach(function (name) {
    var input = form.elements[name];
    input.addEventListener('blur', function () {
      if (input.value.trim() !== '' || input.hasAttribute('aria-invalid')) validateField(name);
    });
    input.addEventListener('input', function () {
      if (input.hasAttribute('aria-invalid')) validateField(name);
    });
  });

  programInputs.forEach(function (input) {
    input.addEventListener('change', function () {
      updateCounter();
      var group = form.querySelector('[data-field="programs"]');
      if (group && group.classList.contains('has-error')) validateField('programs');
    });
  });

  ['residency', 'graduating'].forEach(function (name) {
    Array.prototype.forEach.call(form.querySelectorAll('input[name="' + name + '"]'), function (input) {
      input.addEventListener('change', function () { validateField(name); });
    });
  });

  if (summary) {
    summary.addEventListener('click', function (event) {
      var link = event.target.closest('a');
      if (!link) return;
      var target = document.getElementById(link.hash.slice(1));
      if (!target) return;
      event.preventDefault();
      target.focus({ preventScroll: true });
      target.scrollIntoView({ block: 'center', behavior: reduceMotion ? 'auto' : 'smooth' });
    });
  }

  function setBusy(busy) {
    form.dataset.submitting = busy ? 'true' : '';
    if (!submit) return;
    submit.classList.toggle('is-busy', busy);
    if (busy) {
      submit.setAttribute('aria-disabled', 'true');
    } else {
      submit.removeAttribute('aria-disabled');
    }
    if (submitLabel) submitLabel.textContent = busy ? 'Küldés folyamatban…' : 'Jelentkezés elküldése';
  }

  form.addEventListener('submit', function (event) {
    if (form.dataset.submitting === 'true') {
      event.preventDefault();
      return;
    }
    var errors = [];
    Object.keys(validators).forEach(function (field) {
      var message = validators[field]();
      setError(field, message);
      if (message) errors.push([field, message]);
    });
    if (errors.length) {
      event.preventDefault();
      renderSummary(errors);
      summary.focus({ preventScroll: true });
      summary.scrollIntoView({ block: 'start', behavior: reduceMotion ? 'auto' : 'smooth' });
      return;
    }
    setBusy(true);
  });

  // Coming back via the back/forward cache must not leave the button stuck.
  window.addEventListener('pageshow', function (event) {
    if (event.persisted) setBusy(false);
  });

  updateCounter();

  // Server-side errors: move focus to the summary so it is announced.
  if (summary && !summary.hidden) {
    summary.focus({ preventScroll: true });
    summary.scrollIntoView({ block: 'start' });
  }
})();
