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
| Trainer | `trainer@demo.local` | traint Miniemen en Kadetten en zwemt zelf mee in de Wedstrijdploeg (Trainer + lid); persoonlijke kalender met die groepen |
| Coördinator competitie | `coordinator@demo.local` | Coördinator + Trainer van de Wedstrijdploeg: trainersplanning, minimum per groep, mails bij tekorten |
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

Sinds v0.13 is de Clubapp **minimaal**: enkel de app met login, en sinds v0.14 enkel voor de **competitie** (geen zwemschool, zwemblokken of indeling per lid meer; bestaande zwemschoolgroepen worden bij de update verwijderd, de leden blijven bestaan zonder groep). De startpagina (`/`) stuurt naar *Aanmelden*. De publieke website, het websitebeheer, statistieken, lidgeld, inschrijvingen, de zwemtestwachtlijst, het contactformulier, de nieuwsbrief, de fotogalerij, online inschrijven voor activiteiten, wedstrijddeelname en -resultaten, capaciteit/wachtlijst en CSV-exports zijn verwijderd (de code staat nog in de Git-geschiedenis, tag/commit van v0.12). Leesbare adressen: `/aanmelden`, `/activeren`, `/wachtwoord-vergeten` en de app onder `/app` (`/app/kalender`, `/app/leden/12`, …). Op Apache is `mod_rewrite` nodig (regel in `public/.htaccess`).

### Leden, groepen en gebruikers
- Aanmelden met e-mail en wachtwoord; rollen **Beheerder, Bestuur, Trainer, Coördinator competitie, Zwemmer, Ouder** (combineerbaar, een trainer die ook lid is krijgt Trainer + Zwemmer); permissies in de database.
- Gegevensscope: trainer = toegewezen groepen, zwemmer = zichzelf, ouder = gekoppelde kinderen, beheerder/bestuur = clubbreed.
- **Leden**: zoeken, toevoegen, wijzigen, inactief zetten of **definitief verwijderen** (`members.write`: bestuur en beheerder; ook groep, ouderkoppelingen, aanwezigheden en afmeldingen van het lid verdwijnen; een gekoppeld account blijft zonder lid), adres en contactgegevens (enkel met recht `members.contact`). Het lidnummer is intern en wordt nergens getoond.
- **Minderjarigen** hebben minstens één ouder of voogd (waarschuwingen; zonder ouder geen groep).
- **Groepen** zijn allemaal competitiegroepen (de zwemschool zit sinds v0.14 niet meer in de app). Een lid zit in **maximaal één groep, zonder historiek** (geen van/tot). Leden in bulk verplaatsen (je blijft op de brongroep), groep verwijderen kan altijd (leden blijven bestaan zonder groep). Op de groepspagina: leden, trainers, gekoppelde reeksen, komende activiteiten. Er is geen lidnummer, capaciteit of lidgeld meer in beeld.
- **Trainers** zijn aan groepen gekoppeld zonder datums; meerdere trainers per groep, in te stellen bij Groep wijzigen of bij de gebruiker.
- **Gebruikers** (enkel beheerders): accounts, rollen, wachtwoord resetten, deactiveren of **definitief verwijderen** (niet je eigen account; leden en kinderen blijven bestaan, met een waarschuwing als een minderjarige zo zijn laatste ouder verliest), ouders aan kinderen koppelen (met relatie), trainers aan groepen. Twee tabbladen (beheer/trainers en ouders/zwemmers).
- **Uitnodigingen per e-mail**: ouders en leden kiezen zelf een wachtwoord via een persoonlijke, eenmalige link (7 dagen); *Wachtwoord vergeten?* via mail (60 minuten).

### Kalender, trainingsreeksen en aanwezigheid
- **Overzicht** (startpagina), per rol: **ouders en zwemmers** zien bovenaan per kind de kaart *Volgende training* (dag, uur, locatie) met meteen *Afmelden* (optionele reden) of *Toch aanwezig*, en daaronder de activiteiten als **weekkalender**. **Trainers en bestuur** zien ook de weekkalender, met daaronder het blok *Waar is actie nodig*: tekorten aan trainers, afmeldingen van zwemmers, uitnodigingen die nog niet geactiveerd zijn en minderjarigen zonder ouder (of *✓ Geen tekorten* met het volgende tekort verder weg). De weekkalender is voor iedereen de standaard; wisselen naar *Lijst* (komende twee weken) kan altijd en wordt onthouden. De weekkalender kijkt vooruit: op zaterdag en zondag toont hij de volgende week (‹ › om te bladeren, *Naar nu* om terug te keren). Iedereen ziet enkel de activiteiten van zijn eigen groepen.
- **Persoonlijke kalender** (in de app): je ziet de trainingen en activiteiten van je eigen groepen (als trainer, lid of via je kinderen) plus alle clubactiviteiten en wedstrijden. **Wedstrijden voor andere groepen staan in grijs** en zijn niet aanklikbaar (beheerders kunnen alles openen). Filter op groep.
- **Activiteiten** voor groep(en) of de hele club (training, wedstrijd 🏆, clubactiviteit). Op de activiteitspagina is **Afgelasten** de gewone actie (met aanwezigheden blijft ze als *Afgelast* bewaard); **definitief verwijderen** zit onderaan in een ingeklapte gevarenzone en wist ook afmeldingen, aanwezigheden en trainersplanning (een training uit een reeks komt niet terug). **Kalender filteren op groep en op soort** (meteen toegepast, blijft bij ‹ ›). **Alle activiteiten van een dag of periode in één keer afgelasten of verplaatsen** via ⋯ → *Afgelasten / verplaatsen*, eventueel per groep (geen × meer per dag).
- **Trainingsreeksen** (*Kalender → Trainingsreeksen*, Admin/Bestuur): groepen, periode en per weekdag begin/einde (en locatie); de app maakt de trainingen aan. Een tweede training op dezelfde dag (droogtraining) is een aparte reeks. Schrappen per training of per periode, reeks wijzigen/stoppen/verwijderen (ingevulde aanwezigheden blijven bewaard). Alle getoonde reeksen in één keer **verlengen** (bv. naar het volgende seizoen) zonder nieuwe reeks of trainingen te verwijderen. Het overzicht toont de reeksen per groep.
- Elke groep heeft een vast trainingsschema; alle leden van de groep staan op de lijsten van hun groep.
- **Aanwezigheden (voorlopig verborgen):** presentielijst met Onbekend / Aanwezig / Afwezig, aanwezigheidsrapport en het blok *Aanwezigheid nog in te vullen* staan standaard uit. Zet ze aan met `'features' => ['attendance' => true]` in `config.php`. Zolang ze uit staan, toont een activiteit *Deelnemers en afmeldingen*: ouders en leden kunnen zich nog altijd afmelden en trainers/bestuur zien wie afgemeld is.
- *← Terug* bovenaan gaat naar de bovenliggende pagina, niet naar de vorige in de browser.

### Trainersplanning competitie (v0.12)
- **Doel:** nooit meer een training zonder trainer.
- **Trainersplanning** (`/app/trainersplanning`, menu *Planning*): per week alle trainingen en wedstrijden van de competitiegroepen (en clubbrede wedstrijden) met hun trainers en een status *In orde* of *Trainer gezocht* (x/y). Tabbladen *Alles*, *Enkel tekorten*, *Mijn planning*. Twee weergaven, rechtsboven te wisselen (de keuze wordt onthouden): **Weekkalender** (standaard) (ma–zo naast elkaar met per training het uur, de status en de trainers; afgemelde trainers doorstreept; *Vorige*/*Volgende*/*Deze week*; klik op een training opent de activiteit met de knoppen om af te melden, over te nemen of in te plannen; op telefoon onder elkaar) en **Lijst** (per week, met alle knoppen, periode 1–8 weken).
- **Vaste trainers = groepstrainers:** de trainers van een groep (*Groep wijzigen → Trainers*) staan automatisch op elke training van die groep; een wijziging geldt meteen voor alle komende trainingen. Wedstrijden hebben geen vaste trainers: daar plan je de begeleiders zelf in. *Vaste trainers en instellingen* en het groepenoverzicht (*Groepen*) tonen per groep wie de vaste trainers zijn.
- **Afmelden met vervanger:** een trainer meldt zich af (met reden) voor één training of wedstrijd. Valt er zo een tekort, dan moet hij kiezen wie overneemt, of uitdrukkelijk *Nog geen vervanger – trainer gezocht*: dan krijgen de collega's meteen een mail. De anderen kunnen ook altijd *Ik neem over* klikken of een collega inplannen. Elke competitietrainer kan dit doen (ook voor een ander), zodat iedereen de coördinatie kan overnemen wanneer nodig. *Weghalen* is voor extra/per vergissing ingeplande trainers; een vaste trainer meld je af.
- **Wedstrijden:** via *Trainers toevoegen* vink je in één keer alle trainers aan die meegaan (of klik *Ik ga mee*). Ook wedstrijden voor de hele club (bv. clubkampioenschap) staan in de planning. **Clubactiviteiten** (samenkomst, pastadag, teambuilding, …) niet: daar zijn geen trainers nodig.
- **Niet op training en wedstrijd tegelijk:** wie op een wedstrijd ingepland staat, staat op trainingen op hetzelfde moment automatisch *Op wedstrijd* (afgemeld, het tekort is zichtbaar). Iemand inplannen op een training tijdens zijn wedstrijd, of op twee wedstrijden tegelijk, wordt geweigerd met de reden. Haal je de trainer van de wedstrijd, dan staat hij weer op zijn training.
- **Meerdere tegelijk** (knop *Meerdere trainingen tegelijk…*, `/app/trainersplanning/meerdere`): in 3 stappen één trainer inplannen of afmelden voor een reeks trainingen. 1) trainer en actie (inplannen of afmelden), 2) welke trainingen: groep, dag en periode, 3) een lijst met wat er verandert, vooraf aangevinkt (grijs = blijft zoals het is, bv. *staat al ingepland* of *staat dan al op een andere training of wedstrijd*), bij afmelden met verplichte keuze wie overneemt (of *Nog geen vervanger*) en eventueel een reden, en één knop om te bevestigen. Voorbeelden: alle dinsdagen van een groep overnemen, of een vakantie doorgeven.
- **Minimum aantal trainers** per competitiegroep (standaard 1) en per activiteit (*Activiteit wijzigen → Aantal trainers nodig*, bv. 2 begeleiders voor een wedstrijd).
- **Mail vooraf:** staat er binnen 3 dagen (`coverage.remind_days`) een training of wedstrijd zonder genoeg trainers, dan krijgen de coördinator(en) en alle competitietrainers één mail met de lijst en een link. Elke training wordt één keer gemeld (opnieuw als het tekort groter wordt of na een oplossing opnieuw ontstaat). De app doet dit zelf, hooguit één keer per uur bij een bezoek (geen cron nodig); met een cron-taak kan het ook via `php tools/coverage-reminders.php` (zet dan `coverage.auto_remind` op `false`). Knop *Nu mail sturen* voor de coördinator.
- **Jouw trainingen vallen op:** in de lijst en de weekkalender van de trainersplanning en op het overzicht krijgen de trainingen en wedstrijden waar je zelf ingepland staat (en niet afgemeld bent) een blauwe rand, lichtblauwe achtergrond en het label *Jij geeft training* (wedstrijd: *Jij gaat mee*).
- **Eén tekortsignaal:** menubadge, overzicht en trainersplanning tellen dezelfde tekorten in dezelfde periode (de komende 14 dagen). Geen tekorten? Dan staat er *✓ Geen tekorten* met het eerstvolgende tekort verder weg. In de kalenders houdt een activiteit haar groepskleur en krijgt ze een rode rand met een rode `!`-badge (*Geen trainer* / *Trainer gezocht*). Enkel trainers en bestuur zien tekorten; ouders en zwemmers zien geen trainersnamen of tekorten. De trainersplanning heeft de uitleg achter *ⓘ Hoe werkt dit?* en de extra acties (meerdere tegelijk, instellingen, mail) achter ⋯.
- **Weekplanning delen met ouders:** per competitiegroep een geheime, intrekbare link (`?r=weekplanning&t=…`) met de trainingen en wedstrijden van de komende 3 weken (ook geschrapte), zonder namen van trainers of leden. Handig in de oudergroep zolang niet iedereen een account heeft. De kalender zelf blijft privé.
- **Rol *Coördinator competitie*** (permissie `coverage.manage`, ook voor bestuur en beheerder). Geeft de coördinator zelf training, vink dan ook *Trainer* aan. Wie in de trainersplanning staat: actieve trainers van een competitiegroep en coördinatoren.

### Verder in de app
- **Communicatie:** (aanwezigheidsrapport per groep/periode als `features.attendance` aan staat); **bericht sturen** naar groep, trainingsreeks, activiteit of club (voorbeeldscherm, max. 400, trainer enkel eigen groepen, alles gelogd); **afmelden** door ouders/leden met reden; persoonlijke **agenda-link** (iCal) die je kan intrekken.
- **Noodcontact en medische opmerking** (enkel met `members.medical`); reden bij stopzetting; waarschuwing bij **dubbele leden**.
- **Auditlog** (`audit.read`, enkel beheerders), **globaal zoeken** in het menu, **dashboards per rol**.
- **Tweestapsverificatie (TOTP)** per account (`/app/account`, geheim handmatig invoeren, geen QR-code); `security.require_2fa_admin` verplicht ze voor beheerders; een beheerder kan 2FA van een gebruiker uitzetten.
- **Privacy:** zet `privacy_url` in `config.php` naar het privacybeleid op de clubwebsite; de link staat dan onderaan elke pagina.

### Mobiel en techniek
Responsive (getest tot 320 px). Op telefoon een **vaste tabbalk onderaan** (Overzicht, Kalender, Planning of Leden, Menu), een **compacte ledenlijst** (naam, groep, ouder op één rij; details na een tik; enkel afwijkende status), filters die meteen toepassen, compacte ‹ › navigatie en datums zonder jaartal als het dit jaar is. Zie de analyse voor architectuur, datamodel en beveiliging.

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

Elke suite draait in een verse tijdelijke kopie met SQLite en demodata tegen `php -S`; je eigen `config.php` en database blijven onaangeroerd. Suites: `smoke` (alle hoofdpagina's per rol zonder serverfout), `v12_trainersplanning` en `v15_verwijderen`.

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
