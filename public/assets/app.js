(function () {
  // Tabellen (behalve presentielijst) krijgen op mobiel een kaartweergave: kolomtitel als label per cel
  document.querySelectorAll('table.table:not(.attendance)').forEach(function (t) {
    var heads = Array.prototype.map.call(t.querySelectorAll('thead th'), function (th) { return th.textContent.trim(); });
    if (!heads.length) return;
    t.classList.add('stack');
    t.querySelectorAll('tbody tr').forEach(function (tr) {
      Array.prototype.forEach.call(tr.children, function (td, i) { td.setAttribute('data-label', heads[i] || ''); });
    });
  });

  // Inschrijfformulier: velden tonen naargelang "mijn kind" of "mezelf"
  var typeRadios = document.querySelectorAll('input[name=applicant_type]');
  if (typeRadios.length) {
    var syncType = function () {
      var sel = document.querySelector('input[name=applicant_type]:checked');
      var v = sel ? sel.value : 'PARENT';
      document.querySelectorAll('[data-show-for]').forEach(function (el) { el.hidden = el.getAttribute('data-show-for') !== v; });
      var nm = document.querySelector('input[name=contact_name]');
      if (nm) nm.required = v === 'PARENT';
    };
    typeRadios.forEach(function (r) { r.addEventListener('change', syncType); });
    syncType();
  }

  // Actieve menu-link markeren en in beeld scrollen
  var path = location.search;
  document.querySelectorAll('.nav a').forEach(function (a) {
    var r = (a.getAttribute('href').match(/r=([a-z_]+)/) || [])[1];
    var cur = (path.match(/r=([a-z_]+)/) || [])[1] || 'dashboard';
    var map = { member: 'members', member_form: 'members', group: 'groups', group_form: 'groups', activity: 'calendar', activity_form: 'calendar', user_form: 'users' };
    if (r && (r === cur || r === map[cur])) { a.setAttribute('aria-current', 'page'); a.scrollIntoView({ block: 'nearest', inline: 'center' }); }
  });

  // Bevestiging voor formulieren met data-confirm
  document.addEventListener('submit', function (ev) {
    var msg = ev.target.getAttribute && ev.target.getAttribute('data-confirm');
    if (msg && !window.confirm(msg)) {
      ev.preventDefault();
    }
  });

  // "Iedereen aanwezig" op de presentielijst
  document.addEventListener('click', function (ev) {
    var btn = ev.target.closest && ev.target.closest('[data-mark-all]');
    if (!btn) return;
    var value = btn.getAttribute('data-mark-all');
    btn.form.querySelectorAll('input[type=radio][value="' + value + '"]').forEach(function (r) {
      r.checked = true;
    });
  });

  // Lid-keuze enkel tonen als de rol Zwemmer is aangevinkt (gebruikersformulier)
  var swimmerBox = document.querySelector('input[name="roles[]"][value="SWIMMER"]');
  var swimmerMember = document.getElementById('swimmer-member');
  if (swimmerBox && swimmerMember) {
    var syncSwimmer = function () { swimmerMember.hidden = !swimmerBox.checked; };
    swimmerBox.addEventListener('change', syncSwimmer);
    syncSwimmer();
  }

  // Inschrijfformulier: standaard 1 kind, extra kinderen via knop (zonder JS zijn alle velden zichtbaar)
  var addChild = document.getElementById('add-child');
  if (addChild) {
    var extras = Array.prototype.slice.call(document.querySelectorAll('fieldset.child-extra'));
    var refresh = function () {
      var hiddenOnes = extras.filter(function (f) { return f.classList.contains('is-collapsed'); });
      addChild.hidden = hiddenOnes.length === 0;
    };
    extras.forEach(function (f) {
      var filled = Array.prototype.some.call(f.querySelectorAll('input, select'), function (el) { return el.value !== ''; });
      if (!filled) { f.classList.add('is-collapsed'); }
    });
    extras.forEach(function (f) {
      var rm = f.querySelector('.remove-child');
      if (!rm) { return; }
      rm.hidden = false;
      rm.addEventListener('click', function () {
        f.querySelectorAll('input').forEach(function (el) { el.value = ''; });
        f.querySelectorAll('select').forEach(function (el) { el.selectedIndex = 0; });
        f.classList.add('is-collapsed');
        refresh();
        addChild.focus();
      });
    });
    addChild.addEventListener('click', function () {
      var next = extras.filter(function (f) { return f.classList.contains('is-collapsed'); })[0];
      if (next) {
        next.classList.remove('is-collapsed');
        var first = next.querySelector('input');
        if (first) { first.focus(); }
      }
      refresh();
    });
    refresh();
  }

  // Relatie bewerken: eerst tekst met knop "Bewerken"; het formulier klapt open na een klik
  document.querySelectorAll('.rel-edit').forEach(function (box) {
    var text = box.querySelector('.rel-text'), form = box.querySelector('.rel-form');
    var edit = box.querySelector('.rel-edit-btn'), cancel = box.querySelector('.rel-cancel');
    var show = function (editing) {
      text.hidden = editing; edit.hidden = editing; form.hidden = !editing; cancel.hidden = !editing;
      if (editing) { form.querySelector('input[name=relationship]').focus(); }
    };
    edit.addEventListener('click', function () { show(true); });
    cancel.addEventListener('click', function () { form.reset(); show(false); });
    show(false);
  });

  // Groepenkeuze enkel tonen bij doelgroep "Specifieke groepen"
  var picks = document.getElementById('group-picks');
  if (picks) {
    var radios = document.querySelectorAll('input[name=audience]');
    var sync = function () {
      var sel = document.querySelector('input[name=audience]:checked');
      picks.hidden = !(sel && sel.value === 'GROUPS');
    };
    radios.forEach(function (r) { r.addEventListener('change', sync); });
    sync();
  }
})();
