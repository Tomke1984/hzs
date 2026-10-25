# HZS Clubapp

Publieke clubwebsite en ledenapp (ledenbeheer, groepsindeling, trainingsreeksen, kalender, aanwezigheid, inschrijvingen en zwemtestwachtlijst) voor Hasseltse Zwemvereniging Spartacus.
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
| Bestuur | `bestuur@demo.local` | alle leden, groepen, kalender en inschrijvingen (geen gebruikersbeheer) |
| Trainer | `trainer@demo.local` | traint Beginners en Gevorderden en zwemt zelf mee in de Wedstrijdploeg (Trainer + lid); persoonlijke kalender met die groepen |
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

## Wat zit erin

HZS Clubapp is twee dingen in één: de **publieke clubwebsite** (vervanger van khzs.be) en de **ledenapp** voor het beheer. Eén PHP-monoliet, één database.

### Publieke website (zonder login)
- **Leesbare adressen:** `/zwemschool`, `/kalender`, `/trainingsuren`, `/zwemtest`, `/inschrijven`, `/aanmelden`, `/nieuws/<bericht>`; pagina's staan op `/<naam>` (de naam volgt de titel en is aanpasbaar bij *Adres*). De beheer-app staat onder `/app` (`/app/leden/12`, `/app/groepen`, `/app/kalender`, `/app/website`, …); oude `?r=…`-adressen blijven werken. Op Apache is `mod_rewrite` nodig (regel in `public/.htaccess`).
- Startpagina met introtekst, de vakken Zwemschool en Competitie, nieuws en *Komende activiteiten*.
- Pagina's in een menu (hoofd- en subpagina's), nieuws, kalender en trainingsuren. Eigen vormgeving (`public/assets/site.css`, zelf gehoste lettertypen, geen externe verbindingen).
- **Kalender en komende activiteiten: voor iedereen enkel wedstrijden en clubactiviteiten**, nooit trainingen of groepen. De persoonlijke kalender zit in de app.
- **Trainingsuren** (zwemschool en competitie) komen automatisch uit de trainingsreeksen die op de website staan, per groep met de trainers.
- **Doelstellingen** van de zwemschool komen uit de groepen (veld *Doelstellingen* bij Groep wijzigen): een nieuwe groep verschijnt vanzelf.
- **Zwemtest aanvragen** (`?r=swimtest`): formulier voor kind en ouder, e-mailbevestiging, wachtlijst die het bestuur opvolgt (zie hieronder).
- **Lid worden** (`?r=register`): inschrijfformulier voor ouders (één of meer kinderen) en zwemmers van 18+.
- **Contact**: pagina met de contactpersonen van zwemschool en competitie en een contactformulier; berichten komen in de app en per mail bij het juiste adres.
- Beveiliging van de formulieren: verborgen spamveld, limiet per e-mailadres/uur, CSRF, bevestiging per e-mail.

### Website beheren (menu *Website*, recht `content.manage`: beheerder en bestuur)
- **Nieuws**: berichten als concept of gepubliceerd, met hoofdafbeelding.
- **Pagina's**: tekst met eenvoudige opmaak (`## kop`, lijsten, **vet**, links), hoofd- en subpagina's, menu aan/uit, concept/gepubliceerd.
- **Automatische pagina's** (Trainingsuren competitie, Doelstellingen, Contact, startpagina) staan in het overzicht met het label *Automatisch*: adres, plaats en status liggen vast, ze kunnen niet verwijderd worden en bij de eerste twee pas je enkel de inleiding aan; de rest komt uit Reeksen/Groepen.
- **Paginacontrole**: meldt verouderde `doelstellingen-…`-pagina's, lege pagina's, concepten, kapotte links en onbereikbare pagina's; de knop *Overbodige pagina's opruimen* verwijdert enkel de verouderde en lege.
- **Afbeeldingen**: bibliotheek met kopieerbare code voor in teksten.

### Leden, groepen en gebruikers
- Aanmelden met e-mail en wachtwoord; rollen **Beheerder, Bestuur, Trainer, Zwemmer, Ouder** (combineerbaar, een trainer die ook lid is krijgt Trainer + Zwemmer); permissies in de database.
- Gegevensscope: trainer = toegewezen groepen, zwemmer = zichzelf, ouder = gekoppelde kinderen, beheerder/bestuur = clubbreed.
- **Leden**: zoeken, toevoegen, wijzigen (nooit verwijderen, wel inactief), adres en contactgegevens (enkel met recht `members.contact`). Het lidnummer is intern en wordt nergens getoond.
- **Minderjarigen** hebben minstens één ouder of voogd (waarschuwingen; zonder ouder geen groep).
- **Groepen** zijn **Zwemschool** of **Competitie**. Een lid zit in **maximaal één groep, zonder historiek** (geen van/tot). Leden in bulk verplaatsen (je blijft op de brongroep), groep verwijderen kan altijd (leden blijven bestaan zonder groep). Op de groepspagina: leden, trainers, gekoppelde reeksen, komende activiteiten.
- **Trainers** zijn aan groepen gekoppeld zonder datums; meerdere trainers per groep, in te stellen bij Groep wijzigen of bij de gebruiker.
- **Gebruikers** (enkel beheerders): accounts, rollen, wachtwoord resetten, deactiveren, ouders aan kinderen koppelen (met relatie), trainers aan groepen. Twee tabbladen (beheer/trainers en ouders/zwemmers).
- **Uitnodigingen per e-mail**: ouders en leden kiezen zelf een wachtwoord via een persoonlijke, eenmalige link (7 dagen); *Wachtwoord vergeten?* via mail (60 minuten).

### Inschrijvingen, zwemtest en berichten (bestuur)
- **Inschrijvingen** (`registrations.manage`): bevestigde aanvragen beoordelen. Goedkeuren maakt de leden aan, koppelt de ouder en nodigt uit; afwijzen stuurt een mail met reden.
- **Zwemtest** (menu *Zwemtest*, met teller): wachtlijst in volgorde van bevestiging met de statussen Wachtlijst, Uitgenodigd, Test gedaan, Ingeschreven en Geweigerd; per aanvraag een notitie, optionele mails en *Als lid inschrijven* (maakt een inschrijving die de gewone goedkeuring volgt).
- **Berichten** (menu *Berichten*, met teller): berichten van het contactformulier afhandelen of verwijderen.

### Kalender, trainingsreeksen en aanwezigheid
- **Persoonlijke kalender** (in de app): je ziet de trainingen en activiteiten van je eigen groepen (als trainer, lid of via je kinderen) plus alle clubactiviteiten en wedstrijden. **Wedstrijden voor andere groepen staan in grijs** en zijn niet aanklikbaar (beheerders kunnen alles openen). Filter op zwemschool/competitie en groep.
- **Activiteiten** voor groep(en) of de hele club (training, wedstrijd, clubactiviteit); schrappen = annuleren en verwijderen (met aanwezigheden blijft ze geannuleerd bewaard); **alle activiteiten van een dag of periode in één keer schrappen** (× in de kalender, of *Schrappen / verplaatsen*), eventueel per groep.
- **Trainingsreeksen** (*Kalender → Trainingsreeksen*, Admin/Bestuur): groepen, periode en per weekdag begin/einde (en locatie); de app maakt de trainingen aan. Een tweede training op dezelfde dag (droogtraining) is een aparte reeks. Schrappen per training of per periode, reeks wijzigen/stoppen/verwijderen (ingevulde aanwezigheden blijven bewaard). Per reeks en in bulk **op de website tonen of verbergen** en **verlengen** (bv. naar het volgende seizoen) zonder nieuwe reeks of trainingen te verwijderen.
- **Presentielijst** met Onbekend / Aanwezig / Afwezig; een trainer registreert enkel voor groepen die hij traint.
- *← Terug* bovenaan gaat naar de bovenliggende pagina, niet naar de vorige in de browser.

### Mobiel en techniek
Responsive (getest tot 320 px): tabellen worden kaarten, grote aanraakdoelen. Zie de analyse voor architectuur, datamodel en beveiliging.

Nog niet aanwezig: MFA, betalingen, exports, documentenbeheer, CSV-import van bestaande leden, volledige audit-triggers.

## Structuur

```
public/index.php        front controller: leesbare adressen → route, routetabel (route, methode, vereiste permissie)
public/install.php      eenmalige installer (vergrendelt zichzelf)
app/Auth.php            login, sessie, permissiecontrole
app/Scope.php           centrale gegevensscope per rol (alle queries lopen hier via)
app/Controllers/        Auth, Dashboard, Member, Group, Activity, Series, User, Invite, Registration,
                        SwimTest, Contact, Content (website-beheer), Site (publieke site)
app/Installer.php       installatie, migraties bij elke start, referentiedata (rollen/permissies)
app/Markup.php          veilige opmaak voor paginateksten; app/PageCheck.php = paginacontrole
app/SiteSeed.php        startinhoud khzs.be; app/CompetitionSeed.php = competitie-uren
app/views/              PHP-templates met escaping via e() (site/ = publieke site)
db/schema.sql           schema voor SQLite en MySQL (portable tokens {{PK}}/{{ENGINE}})
docs/                   functionele en technische analyse
tools/                  seed-demo.php (demodata), seed-site.php (ontwikkelhulp: startinhoud khzs.be en competitie-uren), sync-scripts
```

Beveiliging: `password_hash`, sessieregeneratie bij login, CSRF-token op elke POST, prepared statements, uitvoer-escaping, CSP-header zonder inline scripts, permissiecheck per route, geen fysieke deletes van historische kerngegevens.
Login-throttling (5 pogingen per 15 minuten), sessie-timeout van 12 uur, HSTS bij HTTPS en `no-store` voor app-pagina's zijn ingebouwd. Nog te doen voor productie: MFA voor beheerders, HSTS op serverniveau, back-ups, privacy/AVG-proces.

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

Optioneel: `'registration' => ['notify_email' => 'bestuur@jouwdomein.be']` mailt het bestuur bij elke bevestigde inschrijving, en `'privacy_url' => '...'` voegt een link naar de privacyverklaring toe aan het formulier.

Berichten van het contactformulier gaan naar `'contact' => ['swimschool_email' => '…', 'competition_email' => '…', 'other_email' => '…']` (leeg = het meldadres van de inschrijvingen); ze staan altijd ook in de app onder *Berichten*. Geüploade afbeeldingen komen in `public/uploads/`: die map moet schrijfbaar zijn en in de back-up zitten.

Lokaal testen: laat `driver` op `log` staan. Na een uitnodiging staat de activatielink in de melding en in `storage/mail/outbox.log`.
