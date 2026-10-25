# HZS Clubapp V1

Ledenbeheer, groepsindeling, activiteitenplanning en aanwezigheidsregistratie voor Hasseltse Zwemvereniging Spartacus.
PHP 8.1+ (zonder externe pakketten), server-rendered, responsive.

## Lokaal starten (2 minuten)

Vereist: PHP 8.1 of hoger met `pdo_sqlite` (standaard aanwezig in de meeste PHP-installaties).

```bash
cd hzs-clubapp
php -S 127.0.0.1:8080 -t public
```

Open daarna <http://127.0.0.1:8080/install.php>:

1. Kies **SQLite** (geen database-installatie nodig).
2. Vul naam, e-mail en wachtwoord (min. 10 tekens) van de beheerder in.
3. Vink **Voorbeelddata toevoegen** aan om alle rollen meteen te kunnen testen.
4. Klik op *Installeren* en meld je aan.

Demo-accounts (enkel lokaal; wachtwoord voor allemaal `Demo1234!`):

| Rol | Account | Wat je ziet |
|---|---|---|
| Beheerder | je eigen account | alles, clubbreed |
| Trainer | `trainer@demo.local` | traint Beginners en Gevorderden en zwemt zelf mee in de Wedstrijdploeg (Trainer + lid) |
| Zwemmer | `zwemmer@demo.local` | enkel zichzelf |
| Ouder | `ouder@demo.local` | enkel het gekoppelde kind |

Voorbeelddata vergeten aan te vinken? Voer in de projectmap `php tools/seed-demo.php` uit. Het script voegt de demo-groepen, -leden, -activiteiten en -accounts toe en weigert te draaien als er al groepen of leden zijn.

Opnieuw beginnen: stop de server, verwijder `config.php` en `storage/data/clubapp.sqlite`, en open `install.php` opnieuw.

## Op MySQL / shared hosting

1. Maak een lege MySQL-database en een gebruiker met rechten op enkel die database.
2. Upload de volledige map; de webroot moet naar `public/` wijzen (of gebruik de meegeleverde `.htaccess` in de hoofdmap).
3. Open `install.php`, kies **MySQL** en vul de gegevens in.
4. Verwijder daarna `public/install.php`.
5. Zet in `config.php` `cookie_secure` op `true` zodra de site via HTTPS draait, en `debug` op `false`.

## Wat zit erin (V1-scope)

- Aanmelden met e-mail en wachtwoord; rollen beheerder, trainer, zwemmer, ouder; permissies in de database.
- Gegevensscope: trainer = toegewezen groepen, zwemmer = zichzelf, ouder = gekoppelde kinderen, beheerder = clubbreed.
- Groepen zijn opgesplitst in **Zwemschool** en **Competitie** (keuze per groep; overzicht, groepspagina en activiteitenformulier tonen de indeling). Bestaande databases krijgen de kolom automatisch, alle bestaande groepen starten als Zwemschool.
- Filteren op **zwemschool/competitie** en op groep in de ledenlijst en de kalender (filter blijft behouden bij maand vooruit/terug; clubbrede activiteiten blijven zichtbaar).
- Gebruikerslijst met twee tabbladen: **Beheer en trainers** en **Ouders en zwemmers** (met zoekbalk). Leden zijn niet automatisch gebruikers: op de ledenpagina maakt de beheerder met één klik een eigen account aan (rol Zwemmer, gegevens vooraf ingevuld). Bij minderjarigen meldt de ouder of voogd aan.
- **E-mail en uitnodigingen:** beheerders nodigen ouders/voogden en leden per e-mail uit (per lid of in bulk voor alle leden met een e-mailadres). De ontvanger kiest zelf een wachtwoord via een persoonlijke, eenmalige link (7 dagen geldig). Ook *Wachtwoord vergeten?* werkt via mail (link 60 minuten geldig). Bij minderjarige leden wordt het e-mailadres van het lid als adres van de ouder gebruikt; broers/zussen op hetzelfde adres delen één account. Zo hoeft er geen wachtwoord uitgedeeld te worden.
- Mobielvriendelijk: compacte header met scrollbaar menu, tabellen worden kaarten op telefoon, grote aanraakdoelen en radioknoppen voor aanwezigheid, formulieren en knoppen over de volledige breedte (getest tot 320 px).
- Rollen zijn combineerbaar: een **trainer die ook lid is** krijgt de rollen Trainer + Zwemmer en wordt gekoppeld aan zijn lidprofiel. Aanwezigheid registreert hij enkel voor de groepen die hij traint, niet voor groepen waar hij zelf lid van is.
- Het lidnummer bestaat enkel intern in de database (automatisch aangemaakt) en wordt nergens getoond; gelijknamige leden onderscheid je via het geboortejaar in keuzelijsten.
- Leden zoeken, toevoegen, wijzigen (nooit verwijderen; wel inactief zetten).
- Groepen aanmaken en wijzigen; leden historisch koppelen met start- en einddatum.
- Activiteiten voor groep(en) of hele club; kalender met trainingen, wedstrijden en clubactiviteiten.
- Presentielijst met Onbekend / Aanwezig / Afwezig.
- Regel voor minderjarigen: een actief lid jonger dan 18 heeft minstens één ouder of voogd. Zonder ouder verschijnt een waarschuwing (ledenlijst, ledenpagina, overzicht), kan het lid niet aan een groep gekoppeld worden en kan de laatste ouder niet ontkoppeld worden. Leden zonder geboortedatum vallen buiten de regel.
- Gebruikersbeheer (enkel beheerders, menu *Gebruikers*): accounts aanmaken, rollen toekennen, wachtwoord resetten, accounts deactiveren, **ouders aan kinderen koppelen** en trainers aan groepen toewijzen. Je kunt jezelf niet buitensluiten en de laatste actieve beheerder kan niet uitgeschakeld worden.

Bewust nog niet in V1: zelfregistratie van ouders, wachtwoord vergeten, MFA, herhalende activiteiten, inschrijvingen, betalingen, exports, volledige audit-triggers.
Ouders kijk mee (kalender, aanwezigheid van hun kind/kinderen); ze kunnen zelf niets wijzigen. Een ouder maak je aan onder *Gebruikers* met de rol Ouder, en koppel je daarna aan een of meer kinderen.

## Structuur

```
public/index.php        front controller + routetabel (route, methode, vereiste permissie)
public/install.php      eenmalige installer (vergrendelt zichzelf)
app/Auth.php            login, sessie, permissiecontrole
app/Scope.php           centrale gegevensscope per rol (alle queries lopen hier via)
app/Controllers/        Auth, Dashboard, Member, Group, Activity, User
app/views/              PHP-templates met escaping via e()
db/schema.sql           schema voor SQLite en MySQL
```

Beveiliging: `password_hash`, sessieregeneratie bij login, CSRF-token op elke POST, prepared statements, uitvoer-escaping, CSP-header zonder inline scripts, permissiecheck per route, geen fysieke deletes van historische kerngegevens.
Nog te doen voor productie: login-throttling, wachtwoordreset, MFA voor beheerders, HSTS op serverniveau, back-ups, privacy/AVG-proces.

## Werkwijze met GitHub

- `main` = goedgekeurde versie. `dev` = testversie waar alle aanpassingen eerst op komen.
- Lokaal testen: `git clone https://github.com/Tomke1984/hzs.git`, daarna `./tools/sync.sh` (Windows: `.\tools\sync.ps1`). Het script zet je op `dev` en haalt elke 60 seconden nieuwe aanpassingen binnen.
- Is `dev` goedgekeurd, dan wordt het samengevoegd met `main`.
- Je lokale database (`storage/data/`) en `config.php` staan niet in git en blijven dus behouden.

## Changelog

Alle wijzigingen staan in [`CHANGELOG.md`](CHANGELOG.md). Elke push naar `dev` voegt er een regel aan toe; bij een merge naar `main` verhuizen die regels naar de sectie *Op main* met datum en commit.

## E-mail instellen

In `config.php` (zie `config.sample.php`):

```php
'app_url' => 'https://clubapp.jouwdomein.be',   // basisadres voor de links in e-mails
'mail' => [
    'driver'     => 'log',   // 'log' = lokaal testen: mails komen in storage/mail/ (niets wordt verstuurd)
                             // 'smtp' = echte mail via je mailserver, 'mail' = PHP mail() van de host
    'from_email' => 'noreply@jouwdomein.be',
    'from_name'  => 'HZS Clubapp',
    'smtp' => ['host' => 'smtp.jouwdomein.be', 'port' => 587, 'encryption' => 'tls', 'user' => '...', 'pass' => '...'],
],
```

Lokaal testen: laat `driver` op `log` staan. Na een uitnodiging staat de activatielink in de melding en in `storage/mail/outbox.log`.
