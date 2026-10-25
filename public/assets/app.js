(function () {
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
