# HZS Clubapp

Ledenapp voor Hasseltse Zwemvereniging Spartacus: kalender voor leden en ouders, groepen, trainingsreeksen, trainersplanning, aanwezigheden en berichten. Enkel met login; de publieke website staat apart (hzs-basic).
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
| Bestuur | `bestuur@demo.local` | alle leden, groepen, kalender en trainersplanning (geen gebruikersbeheer) |
| Trainer | `trainer@demo.local` | traint Beginners en Gevorderden en zwemt zelf mee in de Wedstrijdploeg (Trainer + lid); persoonlijke kalender met die groepen |
| Coördinator competitie | `coordinator@demo.local` | Coördinator + Trainer van de Wedstrijdploeg: trainersplanning, vaste trainers per blok, mails bij tekorten |
| Competitietrainer | `competitie@demo.local` | Trainer van de Wedstrijdploeg: trainersplanning (afmelden, overnemen, collega inplannen) |
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

Sinds v0.13 is de Clubapp **minimaal**: enkel de app met login. De startpagina (`/`) stuurt naar *Aanmelden*. De publieke website, het websitebeheer, statistieken, lidgeld, inschrijvingen, de zwemtestwachtlijst, het contactformulier, de nieuwsbrief, de fotogalerij, online inschrijven voor activiteiten, wedstrijddeelname en -resultaten, capaciteit/wachtlijst en CSV-exports zijn verwijderd (de code staat nog in de Git-geschiedenis, tag/commit van v0.12). Leesbare adressen: `/aanmelden`, `/activeren`, `/wachtwoord-vergeten` en de app onder `/app` (`/app/kalender`, `/app/leden/12`, …). Op Apache is `mod_rewrite` nodig (regel in `public/.htaccess`).

### Leden, groepen en gebruikers
- Aanmelden met e-mail en wachtwoord; rollen **Beheerder, Bestuur, Trainer, Coördinator competitie, Zwemmer, Ouder** (combineerbaar, een trainer die ook lid is krijgt Trainer + Zwemmer); permissies in de database.
- Gegevensscope: trainer = toegewezen groepen, zwemmer = zichzelf, ouder = gekoppelde kinderen, beheerder/bestuur = clubbreed.
- **Leden**: zoeken, toevoegen, wijzigen (nooit verwijderen, wel inactief), adres en contactgegevens (enkel met recht `members.contact`). Het lidnummer is intern en wordt nergens getoond.
- **Minderjarigen** hebben minstens één ouder of voogd (waarschuwingen; zonder ouder geen groep).
- **Groepen** zijn **Zwemschool** of **Competitie**. Een lid zit in **maximaal één groep, zonder historiek** (geen van/tot). Leden in bulk verplaatsen (je blijft op de brongroep), groep verwijderen kan altijd (leden blijven bestaan zonder groep). Op de groepspagina: leden, trainers, gekoppelde reeksen, komende activiteiten. Er is geen lidnummer, capaciteit of lidgeld meer in beeld.
- **Trainers** zijn aan groepen gekoppeld zonder datums; meerdere trainers per groep, in te stellen bij Groep wijzigen of bij de gebruiker.
- **Gebruikers** (enkel beheerders): accounts, rollen, wachtwoord resetten, deactiveren, ouders aan kinderen koppelen (met relatie), trainers aan groepen. Twee tabbladen (beheer/trainers en ouders/zwemmers).
- **Uitnodigingen per e-mail**: ouders en leden kiezen zelf een wachtwoord via een persoonlijke, eenmalige link (7 dagen); *Wachtwoord vergeten?* via mail (60 minuten).

### Kalender, trainingsreeksen en aanwezigheid
- **Persoonlijke kalender** (in de app): je ziet de trainingen en activiteiten van je eigen groepen (als trainer, lid of via je kinderen) plus alle clubactiviteiten en wedstrijden. **Wedstrijden voor andere groepen staan in grijs** en zijn niet aanklikbaar (beheerders kunnen alles openen). Filter op zwemschool/competitie en groep.
- **Activiteiten** voor groep(en) of de hele club (training, wedstrijd, clubactiviteit); schrappen = annuleren en verwijderen (met aanwezigheden blijft ze geannuleerd bewaard); **alle activiteiten van een dag of periode in één keer schrappen** (× in de kalender, of *Schrappen / verplaatsen*), eventueel per groep.
- **Trainingsreeksen** (*Kalender → Trainingsreeksen*, Admin/Bestuur): groepen, periode en per weekdag begin/einde (en locatie); de app maakt de trainingen aan. Een tweede training op dezelfde dag (droogtraining) is een aparte reeks. Schrappen per training of per periode, reeks wijzigen/stoppen/verwijderen (ingevulde aanwezigheden blijven bewaard). Alle getoonde reeksen in één keer **verlengen** (bv. naar het volgende seizoen) zonder nieuwe reeks of trainingen te verwijderen. Zwemschool en competitie staan gescheiden op het overzicht.
- **Competitie:** elke groep heeft een vast trainingsschema; alle leden van de groep staan op de lijsten van hun groep.
- **Zwemschool = zwemblokken:** een zwemschoolreeks (één dag + uur) is een *blok* waarvoor je meerdere groepen kiest. Leden worden per blok ingedeeld (*Trainingsreeksen → blok → Leden in dit blok*, of de lijst *Nog niet ingedeeld* op het overzicht met een keuzelijst per lid, of het rooster *Trainingsmoment per lid* op de groepspagina). Een lid mag in meerdere blokken, leden van verschillende groepen mogen samen in één blok. **Een zwemschoollid zonder blok staat op geen enkele aanwezigheidslijst** en ziet (ouder/zwemmer) geen trainingen van zijn groep tot het is ingedeeld. Wisselt een lid van groep (of wordt het verplaatst of uit de groep gehaald), dan vervalt zijn indeling. Het **weekoverzicht zwemschool** (knop op de kalender en op Trainingsreeksen) toont dag × uur met de groepen en het aantal leden per blok. Een blok = één dag en uur: maak per moment een aparte reeks.
- **Presentielijst** met Onbekend / Aanwezig / Afwezig; een trainer registreert enkel voor groepen die hij traint.
- *← Terug* bovenaan gaat naar de bovenliggende pagina, niet naar de vorige in de browser.

### Trainersplanning competitie (v0.12)
- **Doel:** nooit meer een competitietraining zonder trainer. Zwemschool valt (voorlopig) buiten deze planning.
- **Trainersplanning** (`/app/trainersplanning`, menu *Planning*): per week alle trainingen en wedstrijden van de competitiegroepen (en clubbrede wedstrijden) met hun trainers en een status *In orde* of *Trainer gezocht* (x/y). Tabbladen *Alles*, *Enkel tekorten*, *Mijn planning*; periode 1–8 weken.
- **Vaste trainer per blok:** de coördinator kiest per trainingsreeks en weekdag de vaste trainer(s) (*Vaste trainers en instellingen*). Die staan automatisch op elke training van dat blok; een wijziging geldt meteen voor alle komende trainingen.
- **Afmelden en overnemen:** een trainer meldt zich af (met reden) voor één training of wedstrijd; de anderen zien meteen het tekort en klikken *Ik neem over*, of plannen een collega in. Elke competitietrainer kan dit doen (ook voor een ander), zodat iedereen de coördinatie kan overnemen wanneer nodig. *Weghalen* is voor extra/per vergissing ingeplande trainers; een vaste trainer meld je af.
- **Meerdere tegelijk:** vink trainingen en wedstrijden aan (of in één klik *Alle tekorten*, *Alles* of alle trainingen van een blok, bv. alle dinsdagen) en plan in één keer een trainer in of meld een trainer af, met reden (bv. vakantie). Wat al zo staat, voorbij is of niet bij de competitie hoort, wordt overgeslagen en geteld in de melding.
- **Minimum aantal trainers** per competitiegroep (standaard 1) en per activiteit (*Activiteit wijzigen → Aantal trainers nodig*, bv. 2 begeleiders voor een wedstrijd).
- **Mail vooraf:** staat er binnen 3 dagen (`coverage.remind_days`) een training of wedstrijd zonder genoeg trainers, dan krijgen de coördinator(en) en alle competitietrainers één mail met de lijst en een link. Elke training wordt één keer gemeld (opnieuw als het tekort groter wordt of na een oplossing opnieuw ontstaat). De app doet dit zelf, hooguit één keer per uur bij een bezoek (geen cron nodig); met een cron-taak kan het ook via `php tools/coverage-reminders.php` (zet dan `coverage.auto_remind` op `false`). Knop *Nu mail sturen* voor de coördinator.
- **Zichtbaar waar nodig:** rode rand en `!` in de kalender, blok *Trainer gezocht* en *Jij geeft training* op het overzicht, kaart *Trainers* op de activiteitspagina, teller in het menu (tekorten in de komende 7 dagen). Ouders en zwemmers zien geen trainersnamen.
- **Weekplanning delen met ouders:** per competitiegroep een geheime, intrekbare link (`?r=weekplanning&t=…`) met de trainingen en wedstrijden van de komende 3 weken (ook geschrapte), zonder namen van trainers of leden. Handig in de oudergroep zolang niet iedereen een account heeft. De kalender zelf blijft privé.
- **Rol *Coördinator competitie*** (permissie `coverage.manage`, ook voor bestuur en beheerder). Geeft de coördinator zelf training, vink dan ook *Trainer* aan. Wie in de trainersplanning staat: actieve trainers van een competitiegroep en coördinatoren.

### Verder in de app
- **Aanwezigheid en communicatie:** aanwezigheidsrapport per groep/periode met lijst *Nog in te vullen*; **bericht sturen** naar groep, trainingsblok, activiteit of club (voorbeeldscherm, max. 400, trainer enkel eigen groepen, alles gelogd); **afmelden** door ouders/leden met reden; persoonlijke **agenda-link** (iCal) die je kan intrekken.
- **Noodcontact en medische opmerking** (enkel met `members.medical`); reden bij stopzetting; waarschuwing bij **dubbele leden**.
- **Auditlog** (`audit.read`, enkel beheerders), **globaal zoeken** in het menu, **dashboards per rol**.
- **Tweestapsverificatie (TOTP)** per account (`/app/account`, geheim handmatig invoeren, geen QR-code); `security.require_2fa_admin` verplicht ze voor beheerders; een beheerder kan 2FA van een gebruiker uitzetten.
- **Privacy:** zet `privacy_url` in `config.php` naar het privacybeleid op de clubwebsite; de link staat dan onderaan elke pagina.

### Mobiel en techniek
Responsive (getest tot 320 px): tabellen worden kaarten, grote aanraakdoelen. Zie de analyse voor architectuur, datamodel en beveiliging.

Niet (meer) aanwezig: publieke website, lidgeld, inschrijvingen en zwemtest, wedstrijdresultaten, exports, CSV-import van bestaande leden.

## Structuur

```
public/index.php        front controller: leesbare adressen → route, routetabel (route, methode, vereiste permissie)
public/install.php      eenmalige installer (vergrendelt zichzelf)
app/Auth.php            login, sessie, permissiecontrole
app/Scope.php           centrale gegevensscope per rol (alle queries lopen hier via)
app/Controllers/        Auth, Account, Dashboard, Member, Group, Activity, Series, User, Invite, Report (aanwezigheden,
                        auditlog, zoeken), Mail (bericht sturen), Coverage (trainersplanning)
app/Installer.php       installatie, migraties (één keer per sessie), referentiedata (rollen/permissies)
app/Coverage.php        trainersplanning competitie: bezetting per activiteit, tekorten, herinneringsmails
app/views/              PHP-templates met escaping via e()
db/schema.sql           schema voor SQLite en MySQL (portable tokens {{PK}}/{{ENGINE}})
docs/                   functionele en technische analyse
tests/e2e/              end-to-end tests (php tests/e2e/run.php)
tools/                  coverage-reminders.php (herinneringen via cron), seed-demo.php (demodata), sync-scripts
```

Beveiliging: `password_hash`, sessieregeneratie bij login, CSRF-token op elke POST, prepared statements, uitvoer-escaping, CSP-header zonder inline scripts, permissiecheck per route, geen fysieke deletes van historische kerngegevens.
Login-throttling (5 pogingen per 15 minuten), sessie-timeout van 12 uur, HSTS bij HTTPS en `no-store` voor app-pagina's zijn ingebouwd. Nog te doen voor productie: MFA voor beheerders, HSTS op serverniveau, back-ups, concrete bewaartermijnen voor ledengegevens en verwerkersovereenkomsten met hosting en mail (het privacybeleid staat op de clubwebsite; zet de link in `privacy_url`).

## Tips voor ontwikkelaars
- Beheerders zien onderaan het menu klein de branch en commit die draait (bv. `dev 29b3a79`), handig om te controleren of een update binnen is.
- De CSP verbiedt inline stijl: gebruik CSS-klassen of SVG-attributen, geen `style="…"`.

## Tests

```bash
php tests/e2e/run.php                       # alle suites
php tests/e2e/run.php v12_trainersplanning  # één suite
KEEP=1 php tests/e2e/run.php smoke          # tijdelijke map (database, mails, serverlog) bewaren
```

Elke suite draait in een verse tijdelijke kopie met SQLite en demodata tegen `php -S`; je eigen `config.php` en database blijven onaangeroerd. Suites: `smoke` (alle hoofdpagina's per rol zonder serverfout) en `v12_trainersplanning`.

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

Optioneel: `'privacy_url' => 'https://…'` wijst naar het privacybeleid op de clubwebsite (link onderaan elke pagina). De map `storage/` moet schrijfbaar zijn (database, mail, `coverage-last-run`).

Lokaal testen: laat `driver` op `log` staan. Na een uitnodiging staat de activatielink in de melding en in `storage/mail/outbox.log`.
