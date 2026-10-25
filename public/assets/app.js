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
