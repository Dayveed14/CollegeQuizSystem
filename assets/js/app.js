/* College Quiz System — small, dependency-free enhancements */
(function () {
  'use strict';
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };

  /* ---------- Mobile navigation ---------- */
  var open = $('[data-open-nav]');
  if (open) {
    open.addEventListener('click', function () { document.body.classList.add('nav-open'); });
    $$('[data-close-nav]').forEach(function (el) {
      el.addEventListener('click', function () { document.body.classList.remove('nav-open'); });
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') document.body.classList.remove('nav-open');
    });
  }

  /* ---------- Auto-submit filters ---------- */
  $$('[data-autosubmit]').forEach(function (el) {
    el.addEventListener('change', function () { el.form.submit(); });
  });

  /* ---------- Confirm dialog (replaces window.confirm) ---------- */
  var dlg;
  function confirmBox(message, okLabel, onOk) {
    if (!window.HTMLDialogElement) { if (window.confirm(message)) onOk(); return; }
    if (!dlg) {
      dlg = document.createElement('dialog');
      dlg.className = 'dialog';
      dlg.innerHTML = '<p class="dialog-msg"></p><div class="dialog-actions">' +
        '<button type="button" class="btn btn-ghost" value="cancel">Cancel</button>' +
        '<button type="button" class="btn btn-primary" value="ok"></button></div>';
      document.body.appendChild(dlg);
      $$('button', dlg).forEach(function (b) {
        b.addEventListener('click', function () { dlg.close(b.value); });
      });
    }
    $('.dialog-msg', dlg).textContent = message;
    var ok = $('button[value=ok]', dlg);
    ok.textContent = okLabel;
    ok.classList.toggle('btn-danger', okLabel === 'Delete');
    dlg.onclose = function () { if (dlg.returnValue === 'ok') onOk(); };
    dlg.returnValue = '';
    dlg.showModal();
    $('button[value=cancel]', dlg).focus();
  }

  $$('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (e) {
      if (f.dataset.confirmed) return;
      e.preventDefault();
      confirmBox(f.dataset.confirm, 'Delete', function () { f.dataset.confirmed = '1'; f.submit(); });
    });
  });

  /* ---------- Quiz ---------- */
  var form = $('#quiz-form');
  if (!form) return;

  document.body.classList.add('js-quiz');
  var qs = $$('.question', form);
  var pals = $$('.pal', form);
  var prev = $('[data-prev]', form), next = $('[data-next]', form), submit = $('[data-submit]', form);
  var answeredEl = $('[data-answered]');
  var current = 0;

  // Start on the first unanswered question
  for (var i = 0; i < qs.length; i++) { if (!$('input:checked', qs[i])) { current = i; break; } }

  function show(n) {
    current = Math.max(0, Math.min(qs.length - 1, n));
    qs.forEach(function (q, i) { q.hidden = i !== current; });
    pals.forEach(function (p, i) { p.classList.toggle('is-current', i === current); });
    prev.disabled = current === 0;
    var last = current === qs.length - 1;
    next.hidden = last;
    submit.classList.toggle('is-last', last);
  }

  function countAnswered() {
    var n = 0;
    qs.forEach(function (q, i) {
      var a = !!$('input:checked', q);
      pals[i].classList.toggle('is-answered', a);
      if (a) n++;
    });
    answeredEl.textContent = n;
    return n;
  }

  prev.addEventListener('click', function () { show(current - 1); });
  next.addEventListener('click', function () { show(current + 1); });
  pals.forEach(function (p) {
    p.addEventListener('click', function () { show(+p.dataset.goto); });
  });

  // Keyboard: A–D choose, arrow keys move between questions
  document.addEventListener('keydown', function (e) {
    if (e.target.matches('input[type=text], textarea') || e.ctrlKey || e.metaKey || e.altKey) return;
    if (document.querySelector('dialog[open]')) return;
    var k = e.key.toUpperCase();
    if (['A', 'B', 'C', 'D'].indexOf(k) > -1) {
      var r = $('input[value=' + k + ']', qs[current]);
      if (r) { r.checked = true; r.dispatchEvent(new Event('change', { bubbles: true })); r.focus(); }
    } else if (e.key === 'ArrowRight' && !e.target.matches('input[type=radio]')) { show(current + 1); }
    else if (e.key === 'ArrowLeft' && !e.target.matches('input[type=radio]')) { show(current - 1); }
  });

  // Autosave
  var saveTimer;
  function autosave() {
    var fd = new FormData(form);
    fd.set('action', 'autosave');
    return fetch(location.href, { method: 'POST', body: fd, credentials: 'same-origin' }).catch(function () {});
  }
  form.addEventListener('change', function (e) {
    if (!e.target.matches('input[type=radio]')) return;
    countAnswered();
    clearTimeout(saveTimer);
    saveTimer = setTimeout(autosave, 400);
    // Move on shortly after answering, unless this is the last question
    var idx = qs.indexOf(e.target.closest('.question'));
    if (idx === current && current < qs.length - 1) {
      setTimeout(function () { if (idx === current) show(current + 1); }, 450);
    }
  });

  // Submit with a check for blanks
  var submitting = false;
  form.addEventListener('submit', function (e) {
    if (submitting) return;
    e.preventDefault();
    var blank = qs.length - countAnswered();
    var msg = blank
      ? 'You have ' + blank + ' unanswered question' + (blank === 1 ? '' : 's') + '. Blank answers are marked wrong. Submit anyway?'
      : 'Submit your answers? You can\'t change them afterwards.';
    confirmBox(msg, 'Submit quiz', function () { submitting = true; form.submit(); });
  });

  // Timer (uses the server clock so a wrong device clock can't add time)
  var timer = $('.timer');
  var deadline = +timer.dataset.deadline;
  var skew = +timer.dataset.now - Date.now();
  var out = $('b', timer);
  function tick() {
    var left = Math.max(0, Math.round((deadline - (Date.now() + skew)) / 1000));
    var m = Math.floor(left / 60), s = left % 60;
    out.textContent = m + ':' + (s < 10 ? '0' : '') + s;
    timer.classList.toggle('is-low', left <= 60);
    if (left === 0) {
      submitting = true;
      out.textContent = 'Time up';
      form.submit();
      return;
    }
    setTimeout(tick, 1000 - (Date.now() % 1000));
  }

  countAnswered();
  show(current);
  tick();
})();
