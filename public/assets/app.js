(function () {
  // Tabellen (behalve presentielijst) krijgen op mobiel een kaartweergave: kolomtitel als label per cel
  document.querySelectorAll('table.table:not(.attendance):not(.member-table)').forEach(function (t) {
    var heads = Array.prototype.map.call(t.querySelectorAll('thead th'), function (th) { return th.textContent.trim(); });
    if (!heads.length) return;
    t.classList.add('stack');
    t.querySelectorAll('tbody tr').forEach(function (tr) {
      Array.prototype.forEach.call(tr.children, function (td, i) { td.setAttribute('data-label', heads[i] || ''); });
    });
  });

  // Beheer-app: menu als uitschuifbaar paneel op smalle schermen
  var appBtns = document.querySelectorAll('.app-menu-btn'), appBtn = appBtns[0], appOverlay = document.querySelector('.app-overlay');
  if (appBtn) {
    var setNav = function (open) {
      document.body.classList.toggle('nav-open', open);
      appBtns.forEach(function (b) { b.setAttribute('aria-expanded', open ? 'true' : 'false'); });
      if (appOverlay) appOverlay.hidden = !open;
      if (open) { var first = document.querySelector('#app-nav .side-link'); if (first) first.focus(); } else if (document.activeElement && document.activeElement.closest && document.activeElement.closest('#sidebar')) appBtn.focus();
    };
    appBtns.forEach(function (b) { b.addEventListener('click', function () { setNav(!document.body.classList.contains('nav-open')); }); });
    if (appOverlay) appOverlay.addEventListener('click', function () { setNav(false); });
    document.addEventListener('keydown', function (ev) { if (ev.key === 'Escape' && document.body.classList.contains('nav-open')) setNav(false); });
    document.querySelectorAll('#app-nav a').forEach(function (a) { a.addEventListener('click', function () { setNav(false); }); });
    if (window.matchMedia) window.matchMedia('(min-width: 960px)').addEventListener('change', function (e) { if (e.matches) setNav(false); });
  }

  // Trainersplanning "Meerdere tegelijk": teller op de knop, alles aan/uit
  var bulk = document.getElementById('cover-bulk');
  if (bulk) {
    var coverPicks = function () { return bulk.querySelectorAll('input.cover-pick'); };
    var coverCount = bulk.querySelector('[data-bulk-count]');
    var coverUpdate = function () {
      var n = 0;
      coverPicks().forEach(function (c) { if (c.checked) n++; });
      if (coverCount) coverCount.textContent = String(n);
      bulk.querySelectorAll('button[type=submit]').forEach(function (b) { b.disabled = n === 0; });
    };
    bulk.addEventListener('change', function (ev) {
      if (ev.target.id === 'cover-all') coverPicks().forEach(function (c) { c.checked = ev.target.checked; });
      coverUpdate();
    });
    coverUpdate();
  }

  // Bladeren in de kalender (‹ ›, Naar nu, Week/Lijst): op dezelfde hoogte blijven in plaats van naar boven te springen
  (function () {
    var KEY = 'hzs-keep-scroll';
    try {
      var saved = JSON.parse(sessionStorage.getItem(KEY) || 'null');
      sessionStorage.removeItem(KEY);
      if (saved && saved.path === location.pathname && !location.hash) {
        if ('scrollRestoration' in history) { history.scrollRestoration = 'manual'; }
        var go = function () { window.scrollTo(0, saved.y); };
        go();
        requestAnimationFrame(go);
        window.addEventListener('load', go, { once: true });
      }
    } catch (e) { /* geen sessionStorage: gewoon bovenaan beginnen */ }
    document.addEventListener('click', function (ev) {
      var a = ev.target.closest('a[data-keep-scroll]');
      if (!a || ev.metaKey || ev.ctrlKey || ev.shiftKey || ev.button !== 0) { return; }
      try {
        var url = new URL(a.href, location.href);
        sessionStorage.setItem(KEY, JSON.stringify({ path: url.pathname, y: window.scrollY }));
      } catch (e) { /* niets */ }
    });
  })();

  // Filters: meteen toepassen bij elke wijziging (de knop "Filteren" verdwijnt dan)
  document.querySelectorAll('form.filters[data-auto]').forEach(function (f) {
    f.classList.add('is-auto');
    f.addEventListener('change', function (ev) { if (ev.target.matches('select, input[type=checkbox], input[type=date]')) f.submit(); });
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

  // Publiek menu: op smalle schermen inklapbaar achter een knop
  var menuBtn = document.querySelector('.menu-btn'), siteNav = document.getElementById('site-nav');
  if (menuBtn && siteNav && window.matchMedia) {
    var mq = window.matchMedia('(max-width: 1180px)');
    var apply = function () {
      menuBtn.hidden = !mq.matches;
      if (mq.matches) { siteNav.classList.add('is-collapsed'); menuBtn.setAttribute('aria-expanded', 'false'); }
      else { siteNav.classList.remove('is-collapsed'); }
    };
    menuBtn.addEventListener('click', function () {
      var open = siteNav.classList.toggle('is-collapsed') === false;
      menuBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    if (mq.addEventListener) { mq.addEventListener('change', apply); }
    apply();
  }

  // Leden verplaatsen: alles selecteren
  var selAll = document.getElementById('select-all-members');
  if (selAll) {
    selAll.addEventListener('change', function () {
      document.querySelectorAll('.member-pick').forEach(function (c) { c.checked = selAll.checked; });
    });
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
