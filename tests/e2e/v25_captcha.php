<?php
/**
 * v0.25: tweestapsverificatie weg; vinkje "Ik ben geen robot" (proof of work) bij aanmelden en wachtwoord vergeten.
 */
T::autoRan();

echo "Aanmelden met het vinkje\n";
$b = new Browser();
$b->get('/aanmelden');
T::ok($b->has('Ik ben geen robot') && $b->has('role="checkbox"') && preg_match('/data-salt="[a-f0-9]{24}" data-challenge="[a-f0-9]{64}"/', $b->body) === 1, 'aanmeldscherm toont het vinkje met een uitdaging');
T::ok(preg_match('/<noscript>.*Hoeveel is \w+ plus \w+\?.*<\/noscript>/s', $b->body) === 1, 'zonder JavaScript: reservevraag');
T::ok($b->has('class="hp-field"') && $b->has('name="website"'), 'verborgen veld voor bots');
T::ok(!preg_match('/name="captcha_n" value="\d/', $b->body), 'de oplossing staat niet in de pagina');
$b->post('/aanmelden', ['email' => 'ouder@demo.local', 'password' => 'Demo1234!']);
T::ok($b->status === 422 && $b->has('Ik ben geen robot” aan'), 'zonder vinkje: geweigerd');
T::ok($b->has('value="ouder@demo.local"'), 'e-mailadres blijft ingevuld');
$n = $b->captcha();
$b->post('/aanmelden', ['email' => 'ouder@demo.local', 'password' => 'Demo1234!', 'captcha_n' => (string) ((int) $n + 1)]);
T::ok($b->status === 422, 'verkeerde oplossing: geweigerd');
T::ok((int) T::val("SELECT COUNT(*) FROM login_attempts WHERE email = 'ouder@demo.local'") === 0, 'telt niet als fout wachtwoord');
$b->post('/aanmelden', ['email' => 'ouder@demo.local', 'password' => 'Demo1234!', 'captcha_n' => $b->captcha()]);
T::ok($b->status === 302 && str_contains($b->location, '/app'), 'met vinkje: aangemeld');
$b->get('/app');
T::ok($b->status === 200 && $b->has('Welkom'), 'overzicht na aanmelden');

echo "Eenmalig\n";
$c = new Browser();
$c->get('/aanmelden');
$old = $c->captcha();
$c->post('/aanmelden', ['email' => 'ouder@demo.local', 'password' => 'fout-wachtwoord', 'captcha_n' => $old]);
T::ok($c->status === 401, 'fout wachtwoord met geldig vinkje: wachtwoord geweigerd');
$new = $c->captcha();
$c->post('/aanmelden', ['email' => 'ouder@demo.local', 'password' => 'Demo1234!', 'captcha_n' => $new === $old ? (string) ((int) $old + 1) : $old]);
T::ok($c->status === 422, 'een oude oplossing werkt niet meer (elke poging een nieuwe uitdaging)');

echo "Honeypot en reservevraag\n";
$d = new Browser();
$d->get('/aanmelden');
$d->post('/aanmelden', ['email' => 'ouder@demo.local', 'password' => 'Demo1234!', 'captcha_n' => $d->captcha(), 'website' => 'http://spam.example']);
T::ok($d->status === 422, 'ingevuld verborgen veld: geweigerd');
$d->post('/aanmelden', ['email' => 'ouder@demo.local', 'password' => 'Demo1234!', 'captcha' => $d->mathAnswer()]);
T::ok($d->status === 302, 'zonder JavaScript: aanmelden met de reservevraag');

echo "Wachtwoord vergeten\n";
T::clearMails();
$f = new Browser();
$f->get('/wachtwoord-vergeten');
T::ok($f->has('Ik ben geen robot'), 'vinkje bij wachtwoord vergeten');
$f->post('/wachtwoord-vergeten', ['email' => 'ouder@demo.local']);
T::ok($f->status === 422 && T::outbox() === '', 'zonder vinkje: geen mail');
$f->post('/wachtwoord-vergeten', ['email' => 'ouder@demo.local', 'captcha_n' => $f->captcha()]);
T::ok($f->status === 200 && $f->has('Bestaat er een account'), 'met vinkje: melding');

echo "Moeilijkheid\n";
T::config(['captcha' => ['max_number' => 60000]]);
$g = new Browser();
$g->get('/aanmelden');
T::ok($g->has('data-max="60000"'), 'standaard tot 60 000 pogingen (op een telefoon meestal minder dan een seconde)');
T::config(['captcha' => ['max_number' => 2000]]);

echo "Geen tweestapsverificatie meer\n";
$admin = Browser::login('admin@demo.local');
$admin->get('/app/account');
T::ok($admin->status === 200 && !$admin->has('Tweestapsverificatie') && !$admin->has('id="2fa"'), 'account: geen 2FA meer');
$admin->get('/app');
T::ok(!$admin->has('tweestapsverificatie'), 'overzicht: geen 2FA-melding');
$uid = (int) T::val("SELECT id FROM users WHERE email = 'ouder@demo.local'");
$admin->get('/app/gebruikers/' . $uid . '/wijzigen');
T::ok(!$admin->has('Tweestapsverificatie'), 'gebruikersfiche: geen 2FA');
foreach (['/app/login-2fa', '/app/account-2fa-start'] as $path) {
    $admin->get($path);
    T::ok($admin->status === 404, "$path bestaat niet meer");
}
T::ok((int) T::val("SELECT COUNT(*) FROM pragma_table_info('users') WHERE name LIKE 'totp%'") === 0, 'geen 2FA-kolommen in de database');
T::ok(!is_file(T::$dir . '/app/Totp.php'), 'Totp-code verwijderd');
