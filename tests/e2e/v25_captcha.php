<?php
/**
 * v0.25: tweestapsverificatie weg, controlevraag (captcha) bij aanmelden en wachtwoord vergeten.
 */
T::autoRan();

echo "Aanmelden met controlevraag\n";
$b = new Browser();
$b->get('/aanmelden');
T::ok(preg_match('/Controlevraag: Hoeveel is \w+ plus \w+\?/', $b->body) === 1 && $b->has('name="captcha"'), 'aanmeldscherm toont de controlevraag');
T::ok($b->has('class="hp-field"') && $b->has('name="website"'), 'verborgen veld voor bots');
$b->post('/aanmelden', ['email' => 'ouder@demo.local', 'password' => 'Demo1234!']);
T::ok($b->status === 422 && $b->has('controlevraag klopt niet'), 'zonder antwoord: geweigerd');
T::ok(!$b->has('Welkom') && $b->has('value="ouder@demo.local"'), 'e-mailadres blijft ingevuld');
$right = $b->captcha();
$b->post('/aanmelden', ['email' => 'ouder@demo.local', 'password' => 'Demo1234!', 'captcha' => (string) ((int) $right + 1)]);
T::ok($b->status === 422, 'fout antwoord: geweigerd');
T::ok((int) T::val("SELECT COUNT(*) FROM login_attempts WHERE email = 'ouder@demo.local'") === 0, 'fout antwoord telt niet als fout wachtwoord');
$ans = $b->captcha();
$b->post('/aanmelden', ['email' => 'ouder@demo.local', 'password' => 'Demo1234!', 'captcha' => $ans]);
T::ok($b->status === 302 && str_contains($b->location, '/app'), 'juist antwoord: aangemeld');
$b->get('/app');
T::ok($b->status === 200 && $b->has('Welkom'), 'overzicht na aanmelden');

echo "Vraag is eenmalig\n";
$c = new Browser();
$c->get('/aanmelden');
$ans = $c->captcha();
$c->post('/aanmelden', ['email' => 'ouder@demo.local', 'password' => 'fout-wachtwoord', 'captcha' => $ans]);
T::ok($c->status === 401, 'fout wachtwoord met juist antwoord: wachtwoord geweigerd');
$new = $c->captcha();
$c->post('/aanmelden', ['email' => 'ouder@demo.local', 'password' => 'Demo1234!', 'captcha' => $new === $ans ? (string) ((int) $ans + 1) : $ans]);
T::ok($c->status === 422, 'het antwoord op de vorige vraag werkt niet meer (elke poging een nieuwe vraag)');

echo "Honeypot en minimumtijd\n";
$d = new Browser();
$d->get('/aanmelden');
$d->post('/aanmelden', ['email' => 'ouder@demo.local', 'password' => 'Demo1234!', 'captcha' => $d->captcha(), 'website' => 'http://spam.example']);
T::ok($d->status === 422, 'ingevuld verborgen veld: geweigerd');
T::config(['captcha' => ['min_seconds' => 2]]);
$e = new Browser();
$e->get('/aanmelden');
$e->post('/aanmelden', ['email' => 'ouder@demo.local', 'password' => 'Demo1234!', 'captcha' => $e->captcha()]);
T::ok($e->status === 422, 'meteen versturen (bot): geweigerd');
sleep(2);
$e->post('/aanmelden', ['email' => 'ouder@demo.local', 'password' => 'Demo1234!', 'captcha' => $e->captcha()]);
T::ok($e->status === 302, 'na 2 seconden: aangemeld');
T::config(['captcha' => ['min_seconds' => 0]]);

echo "Wachtwoord vergeten\n";
T::clearMails();
$f = new Browser();
$f->get('/wachtwoord-vergeten');
T::ok($f->has('Controlevraag:'), 'controlevraag bij wachtwoord vergeten');
$f->post('/wachtwoord-vergeten', ['email' => 'ouder@demo.local']);
T::ok($f->status === 422 && T::outbox() === '', 'zonder antwoord: geen mail');
$f->post('/wachtwoord-vergeten', ['email' => 'ouder@demo.local', 'captcha' => $f->captcha()]);
T::ok($f->status === 200 && $f->has('Bestaat er een account'), 'met juist antwoord: melding');

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
