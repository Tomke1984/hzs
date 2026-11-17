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

  // Activiteit: trainers kiezen bij training en wedstrijd. Bij een nieuwe training staan de trainers van de gekozen
  // groep(en) voorgevinkt (tot je zelf iets aanvinkt of uitvinkt).
  (function () {
    var sel = document.querySelector('[data-type-select]');
    if (!sel) { return; }
    var box = document.querySelector('[data-trainer-picks]');
    var isNew = box && !box.querySelector('input[name=trainers_sync]');
    var code = function () { var o = sel.options[sel.selectedIndex]; return o ? o.getAttribute('data-code') : ''; };
    var prefill = function () {
      if (!isNew) { return; }
      var groups = [];
      if ((document.querySelector('input[name=audience]:checked') || {}).value === 'GROUPS') {
        document.querySelectorAll('input[name="group_ids[]"]:checked').forEach(function (g) { groups.push(g.value); });
      }
      box.querySelectorAll('input[name="trainer_ids[]"]').forEach(function (c) {
        if (c.hasAttribute('data-touched')) { return; }
        c.checked = code() === 'TRAINING' && (c.getAttribute('data-groups') || '').split(' ').some(function (g) { return groups.indexOf(g) !== -1; });
      });
    };
    var sync = function () {
      document.querySelectorAll('[data-show-for]').forEach(function (el) { el.hidden = el.getAttribute('data-show-for').split(' ').indexOf(code()) === -1; });
      prefill();
    };
    if (isNew) {
      var h = document.createElement('input');
      h.type = 'hidden'; h.name = 'trainers_sync'; h.value = '1';
      box.appendChild(h);
      box.addEventListener('change', function (ev) { if (ev.target.name === 'trainer_ids[]') { ev.target.setAttribute('data-touched', ''); } });
      document.querySelectorAll('input[name="group_ids[]"], input[name=audience]').forEach(function (g) { g.addEventListener('change', prefill); });
    }
    sel.addEventListener('change', sync);
    sync();
  })();

  // Trainersplanning: paneel "Ik kan niet" als bottom sheet (zonder JavaScript opent de link de pagina met het paneel open)
  (function () {
    var sheets = document.querySelectorAll('dialog.sheet');
    if (!sheets.length) { return; }
    sheets.forEach(function (d) {
      if (d.hasAttribute('open') && d.showModal) { d.removeAttribute('open'); d.showModal(); }
      d.addEventListener('click', function (ev) { if (ev.target === d) { d.close(); } });
    });
    document.addEventListener('click', function (ev) {
      var t = ev.target.closest('[data-sheet]');
      if (t) {
        var d = document.getElementById(t.getAttribute('data-sheet'));
        if (d && d.showModal) { ev.preventDefault(); d.showModal(); }
        return;
      }
      var c = ev.target.closest('[data-sheet-close]');
      if (c && c.closest('dialog')) { ev.preventDefault(); c.closest('dialog').close(); }
    });
  })();

  // Raster trainersplanning: op een groot scherm blijft de pagina op dezelfde hoogte (paneel staat rechts);
  // op een smal scherm spring je naar het paneel onder het raster.
  (function () {
    var cells = document.querySelectorAll('.plan-grid a.cell');
    if (!cells.length) { return; }
    var wide = window.matchMedia && window.matchMedia('(min-width: 1100px)').matches;
    cells.forEach(function (a) {
      if (wide) { a.setAttribute('data-keep-scroll', ''); } else { a.setAttribute('href', a.getAttribute('href') + '#paneel'); }
    });
  })();

  // "Ik ben geen robot": zoekt in de browser het getal n met sha256(salt + n) = challenge (proof of work, zie app/Captcha.php).
  // Eigen SHA-256 in JavaScript: crypto.subtle werkt niet op hosting zonder HTTPS.
  (function () {
    var widgets = document.querySelectorAll('[data-captcha]');
    if (!widgets.length) { return; }
    var K = [0x428a2f98, 0x71374491, 0xb5c0fbcf, 0xe9b5dba5, 0x3956c25b, 0x59f111f1, 0x923f82a4, 0xab1c5ed5, 0xd807aa98, 0x12835b01, 0x243185be, 0x550c7dc3,
      0x72be5d74, 0x80deb1fe, 0x9bdc06a7, 0xc19bf174, 0xe49b69c1, 0xefbe4786, 0x0fc19dc6, 0x240ca1cc, 0x2de92c6f, 0x4a7484aa, 0x5cb0a9dc, 0x76f988da,
      0x983e5152, 0xa831c66d, 0xb00327c8, 0xbf597fc7, 0xc6e00bf3, 0xd5a79147, 0x06ca6351, 0x14292967, 0x27b70a85, 0x2e1b2138, 0x4d2c6dfc, 0x53380d13,
      0x650a7354, 0x766a0abb, 0x81c2c92e, 0x92722c85, 0xa2bfe8a1, 0xa81a664b, 0xc24b8b70, 0xc76c51a3, 0xd192e819, 0xd6990624, 0xf40e3585, 0x106aa070,
      0x19a4c116, 0x1e376c08, 0x2748774c, 0x34b0bcb5, 0x391c0cb3, 0x4ed8aa4a, 0x5b9cca4f, 0x682e6ff3, 0x748f82ee, 0x78a5636f, 0x84c87814, 0x8cc70208,
      0x90befffa, 0xa4506ceb, 0xbef9a3f7, 0xc67178f2];
    var W = new Array(64);
    function sha256hex(str) { // enkel ASCII (salt in hex + cijfers)
      var len = str.length, words = [], i, j;
      for (i = 0; i < len; i++) { words[i >> 2] |= str.charCodeAt(i) << (24 - (i % 4) * 8); }
      words[len >> 2] |= 0x80 << (24 - (len % 4) * 8);
      var n = (((len + 8) >> 6) + 1) * 16;
      for (i = (len >> 2) + 1; i < n; i++) { words[i] = words[i] | 0; }
      words[n - 1] = len * 8;
      var h0 = 0x6a09e667, h1 = 0xbb67ae85, h2 = 0x3c6ef372, h3 = 0xa54ff53a, h4 = 0x510e527f, h5 = 0x9b05688c, h6 = 0x1f83d9ab, h7 = 0x5be0cd19;
      for (j = 0; j < n; j += 16) {
        var a = h0, b = h1, c = h2, d = h3, e = h4, f = h5, g = h6, h = h7, t1, t2, s0, s1;
        for (i = 0; i < 64; i++) {
          if (i < 16) { W[i] = words[j + i] | 0; } else {
            s0 = ((W[i - 15] >>> 7) | (W[i - 15] << 25)) ^ ((W[i - 15] >>> 18) | (W[i - 15] << 14)) ^ (W[i - 15] >>> 3);
            s1 = ((W[i - 2] >>> 17) | (W[i - 2] << 15)) ^ ((W[i - 2] >>> 19) | (W[i - 2] << 13)) ^ (W[i - 2] >>> 10);
            W[i] = (W[i - 16] + s0 + W[i - 7] + s1) | 0;
          }
          t1 = (h + (((e >>> 6) | (e << 26)) ^ ((e >>> 11) | (e << 21)) ^ ((e >>> 25) | (e << 7))) + ((e & f) ^ (~e & g)) + K[i] + W[i]) | 0;
          t2 = ((((a >>> 2) | (a << 30)) ^ ((a >>> 13) | (a << 19)) ^ ((a >>> 22) | (a << 10))) + ((a & b) ^ (a & c) ^ (b & c))) | 0;
          h = g; g = f; f = e; e = (d + t1) | 0; d = c; c = b; b = a; a = (t1 + t2) | 0;
        }
        h0 = (h0 + a) | 0; h1 = (h1 + b) | 0; h2 = (h2 + c) | 0; h3 = (h3 + d) | 0; h4 = (h4 + e) | 0; h5 = (h5 + f) | 0; h6 = (h6 + g) | 0; h7 = (h7 + h) | 0;
      }
      return [h0, h1, h2, h3, h4, h5, h6, h7].map(function (x) { return ('00000000' + (x >>> 0).toString(16)).slice(-8); }).join('');
    }
    widgets.forEach(function (w) {
      var box = w.querySelector('.robot-box'), out = w.querySelector('input[name=captcha_n]'), form = w.closest('form');
      var salt = w.getAttribute('data-salt'), challenge = w.getAttribute('data-challenge'), max = parseInt(w.getAttribute('data-max'), 10) || 60000;
      var state = 'idle', submitAfter = false;
      w.hidden = false;
      function set(s) {
        state = s;
        w.className = 'robot-check is-' + s;
        box.setAttribute('aria-checked', s === 'done' ? 'true' : 'false');
        w.querySelector('.robot-label').textContent = s === 'busy' ? 'Even controleren…' : (s === 'fail' ? 'Lukt niet: herlaad de pagina' : 'Ik ben geen robot');
      }
      function solve() {
        if (state !== 'idle') { return; }
        set('busy');
        var n = 0, started = Date.now();
        (function chunk() {
          var stop = Math.min(n + 4000, max + 1);
          for (; n < stop; n++) {
            if (sha256hex(salt + n) === challenge) {
              out.value = String(n);
              // minstens even tonen dat er gecontroleerd wordt
              setTimeout(function () { set('done'); if (submitAfter) { form.requestSubmit ? form.requestSubmit() : form.submit(); } }, Math.max(0, 350 - (Date.now() - started)));
              return;
            }
          }
          if (n > max) { set('fail'); return; }
          setTimeout(chunk, 0);
        })();
      }
      box.addEventListener('click', solve);
      form.addEventListener('submit', function (ev) {
        if (state === 'done') { return; }
        ev.preventDefault();
        submitAfter = true;
        solve();
      });
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

  // Formulier met één keuzelijst (bv. groep van een lid): meteen opslaan bij wijziging, de knop verdwijnt
  document.querySelectorAll('form[data-autosubmit]').forEach(function (f) {
    f.classList.add('is-auto');
    f.addEventListener('change', function (ev) { if (ev.target.matches('select')) f.submit(); });
  });

  // Afmelden: snelle reden aanklikken = typveld leegmaken (en omgekeerd)
  document.querySelectorAll('.absence-form').forEach(function (f) {
    var txt = f.querySelector('input[name=reason]');
    if (!txt) { return; }
    f.addEventListener('change', function (ev) { if (ev.target.name === 'reason_pick') { txt.value = ''; } });
    txt.addEventListener('input', function () { f.querySelectorAll('input[name=reason_pick]').forEach(function (r) { r.checked = false; }); });
  });

  // Afmelden openklappen: "Toch niet" klapt weer dicht (zonder JS blijft die knop verborgen)
  document.querySelectorAll('[data-close-details]').forEach(function (b) {
    b.hidden = false;
    b.addEventListener('click', function () { var d = b.closest('details'); if (d) { d.open = false; } });
  });

  // Maandkalender op telefoon: bij het openen meteen naar vandaag scrollen (niet bij bladeren of een #anker)
  (function () {
    var grid = document.querySelector('[data-scroll-today]');
    var today = grid && grid.querySelector('.cal-day.is-today');
    if (!today || location.hash || !window.matchMedia('(max-width: 959px)').matches) { return; }
    try { if (sessionStorage.getItem('hzs-keep-scroll')) { return; } } catch (e) { /* niets */ }
    if (document.referrer && new URL(document.referrer).pathname === location.pathname) { return; }
    var go = function () { today.scrollIntoView({ block: 'start' }); window.scrollBy(0, -70); };
    if (document.readyState === 'complete') { go(); } else { window.addEventListener('load', go, { once: true }); }
  })();
})();
