# HZS Clubapp – Functionele en technische analyse

Versie v0.14 (november 2026). Minimale versie: enkel de ledenapp met login, enkel voor de competitie. Vervangt de analyse van v0.12 (met publieke website, lidgeld, inschrijvingen en zwemtest).

## 1. Doel
HZS Clubapp is de **ledenapp** van Hasseltse Zwemvereniging Spartacus: leden en ouders zien wanneer er training of wedstrijd is, trainers en bestuur beheren groepen, trainingsreeksen, de trainersplanning van de competitie, aanwezigheden en berichten. De publieke website staat apart (repository hzs-basic). Eén PHP-monoliet die draait op klassieke shared hosting (MySQL) en lokaal op SQLite.

## 2. Scope
### In scope (v0.14)
- Authenticatie (wachtwoord, tweestapsverificatie, uitnodiging en wachtwoord vergeten per e-mail), rollen (Beheerder, Bestuur, Trainer, Coördinator competitie, Zwemmer, Ouder), permissies, gegevensscope.
- Leden, groepen (allemaal competitiegroepen), trainers per groep, ouder-kindkoppeling, gebruikersbeheer, noodcontact en medische opmerking.
- Activiteiten, trainingsreeksen, persoonlijke kalender, schrappen/verplaatsen, iCal-agendalink, afmelden door ouders/leden.
- Trainersplanning competitie: groepstrainers als vaste trainers, afmelden met vervanger, niet op training en wedstrijd tegelijk, trainers per training/wedstrijd, afmelden en overnemen, minimum per groep/activiteit, mail vooraf bij tekorten, deelbare weekplanning per competitiegroep.
- Bericht sturen naar groep, trainingsreeks, activiteit of club.
- Presentielijst en aanwezigheidsrapport: **voorlopig verborgen** (feature `attendance`, standaard uit in `config.php`: `'features' => ['attendance' => true]` zet ze aan). Code en gegevens blijven; afmelden door ouders/leden werkt altijd.
- Auditlog, globaal zoeken, dashboards per rol.

### Verwijderd in v0.13
Publieke website (pagina's, nieuws, publieke kalender, trainingsuren, doelstellingen, contactformulier, SEO, sitemap), websitebeheer, bezoekersstatistieken, lidgeld en seizoenen, inschrijvingen (lid worden), zwemtestwachtlijst, contactberichten, nieuwsbrief, fotogalerij, online inschrijven voor activiteiten, wedstrijddeelname en -resultaten, capaciteit en wachtlijst per groep/blok, CSV-exports. De code staat in de Git-geschiedenis (v0.12). Bestaande databases houden hun tabellen en gegevens; de app gebruikt ze niet meer.

### Verwijderd in v0.14
De **zwemschool**: soort groep (zwemschool/competitie), filters daarop, zwemblokken (indeling per lid in een blok, lijst *Nog niet ingedeeld*, weekoverzicht zwemschool, trainingsmoment per lid). Bij de update worden bestaande zwemschoolgroepen **verwijderd** zoals met *Groep verwijderen* (leden blijven bestaan zonder groep; reeksen en activiteiten enkel voor die groepen verdwijnen met hun aanwezigheden en afmeldingen) en wordt de tabel `member_series` leeggemaakt. Alle overige groepen zijn competitiegroepen.

## 3. Actoren en rechten
| Rol | Rechten (permissies) | Zichtbaarheid |
|---|---|---|
| Beheerder | alle (`*`), ook `users.manage` en `audit.read` | clubbreed |
| Bestuur | members.read/write/contact, groups.read/write, activities.read/write, attendance.read/write, mail.send, members.medical, coverage.manage | clubbreed, geen gebruikersbeheer, geen auditlog |
| Trainer | members.read, groups.read, activities.read, attendance.read/write, mail.send, members.medical | leden en groepen van zijn actieve groepen; aanwezigheid enkel voor groepen die hij traint |
| Coördinator competitie | members.read, groups.read, activities.read, attendance.read, coverage.manage | trainersplanning van alle competitiegroepen; meestal gecombineerd met Trainer |
| Zwemmer | members.read, activities.read, attendance.read | zichzelf en de eigen groep |
| Ouder | members.read, activities.read, attendance.read | gekoppelde kinderen en hun groepen |
| Bezoeker | geen login | enkel aanmelden, uitnodiging activeren, wachtwoord vergeten, deellink weekplanning en iCal-feed |

Rollen zijn combineerbaar (trainer die ook lid is). De scope is de unie van de rollen. De trainersplanning (bekijken, afmelden, overnemen, inplannen) is open voor wie `coverage.manage` heeft én voor elke Trainer die aan een actieve competitiegroep gekoppeld is (`Coverage::canView()`). Bij de update naar v0.13 worden de rechten van verwijderde onderdelen (`content.manage`, `registrations.manage`, `fees.manage`, `stats.read`, `export.data`) uit de database gehaald.

## 4. Kernflows
1. **Account**: beheerder maakt een gebruiker of nodigt een ouder/lid uit → persoonlijke link (7 dagen) → wachtwoord kiezen. Nieuwe leden komen er via het bestuur (Leden → Nieuw lid), niet meer via een publiek formulier.
2. **Trainingen**: bestuur maakt per groep een reeks (weekdagen, uren, periode) → de app maakt de trainingen aan → schrappen per training of periode → trainer registreert aanwezigheid. Alle leden van de groep staan op de lijst.
3. **Trainersplanning competitie**: de trainers van een groep staan als vaste trainers op elke training van die groep → een trainer meldt zich af en kiest wie overneemt (of *Nog geen vervanger* → meteen mail) → tekort zichtbaar (planning, kalender, overzicht, menu) → een collega neemt over of wordt ingepland → staat er binnen 3 dagen nog een tekort, dan krijgen coördinator en competitietrainers één mail.
4. **Dagelijks gebruik**: leden, ouders en trainers openen hun persoonlijke kalender in de app (of via de iCal-link in hun agenda); ouders melden hun kind af voor een training.
5. **Communicatie**: trainer of bestuur stuurt een bericht naar een groep, trainingsreeks, activiteit of de club (voorbeeldscherm, gelogd in `broadcasts`).

## 5. Domeinmodel
- **Identiteit en RBAC:** `users` (optioneel gekoppeld aan één `members`), `user_tokens` (uitnodiging/reset, hash + vervaldatum), `roles`, `permissions`, `user_roles`, `role_permissions`, `login_attempts`.
- **Leden:** `members` (profiel, adres, status, noodcontact, medische opmerking), `guardian_links` (ouder ↔ kind met relatie). `member_series` (vroegere indeling in zwemblokken) staat nog in het schema maar is leeg en ongebruikt.
- **Groepen:** `groups` (`category` altijd COMPETITION, `is_active`, `color`, `min_trainers`, `share_token`), `group_memberships` (**maximaal één groep, geen datums**), `group_trainers` (**zonder datums**, meerdere trainers per groep).
- **Planning:** `activity_types` (TRAINING, COMPETITION, CLUB), `activities` (doelgroep CLUB of GROUPS, status PLANNED/CANCELLED, `series_id`, `is_custom`, `status_note`, `min_trainers`), `activity_groups`, `attendance` (UNKNOWN/PRESENT/ABSENT), `absence_reports` (afmeldingen).
- **Reeksen:** `activity_series`, `activity_series_slots` (uniek per reeks en weekdag), `activity_series_groups`, `activity_series_skips`. `SeriesService::sync()` maakt en onderhoudt de trainingen; individueel gewijzigde trainingen en trainingen met aanwezigheid blijven ongemoeid.
- **Trainersplanning:** `activity_trainers` (afwijking per activiteit en trainer: ASSIGNED = vervanger/extra, ABSENT = afgemeld, met reden), `coverage_alerts` (welke activiteit met welk tekort al gemeld werd). Bezetting = vaste trainers (de `group_trainers` van de groepen van een **training**; wedstrijden hebben er geen) + ASSIGNED − ABSENT − trainers die op een overlappende wedstrijd ingepland staan (*Op wedstrijd*), enkel actieve gebruikers; `series_trainers` (vroegere vaste trainer per blok) staat nog in het schema maar wordt niet meer gebruikt; wordt altijd berekend (`Coverage::enrich()`), niet opgeslagen.
- **Communicatie en audit:** `broadcasts` (log van verzonden berichten), `audit_log`.
- **Restkolommen:** `activities.is_public`/`signup_capacity`, `activity_series.is_public`/`capacity`, `groups.goals`/`capacity`/`fee_cents`/`next_group_id` en `members.photo_consent` staan nog in het schema maar worden niet meer gebruikt (geen risico, eenvoudiger dan alle queries aanpassen).

## 6. Architectuur
- Server-rendered PHP-monoliet zonder externe pakketten; PDO met prepared statements (SQLite lokaal, MySQL in productie).
- **Adressen:** `/aanmelden`, `/activeren`, `/wachtwoord-vergeten` (`PRETTY_ROUTES`) en de app onder `/app` (`APP_PATHS` voor de hoofdschermen met id in het pad, bv. `/app/leden/12/wijzigen`; andere routes op `/app/<route-met-streepjes>`); `app_route_from_path()` vertaalt het pad terug. De deellink weekplanning blijft op `?r=weekplanning&t=…` (`PLAIN_ROUTES`). `/` toont het overzicht (en stuurt dus naar aanmelden zonder login); elk ander pad buiten `/app` geeft 404. `public/.htaccess` stuurt alles wat geen bestand is naar `index.php`.
- `public/index.php`: front controller met routetabel `route → methode → [Controller, actie, permissie|'public'|null]`; centrale CSRF-controle op elke POST (fout = 419).
- `app/Controllers/*`: Auth, Account, Dashboard, Member, Group, Activity, Series, User, Invite, Report, Mail, Coverage. `app/Scope.php`: centrale gegevensscope; `app/Auth.php`: sessie en permissies; verder `SeriesService`, `Coverage`, `Mailer`, `Mailing`, `Invites`, `Guardianship`, `Totp`.
- **Schema en migraties:** `db/schema.sql` met tokens `{{PK}}`/`{{ENGINE}}`. `Installer::migrate()` loopt één keer per sessie (schemaversie 14): ontbrekende tabellen aanmaken, ontbrekende kolommen toevoegen, opruimmigraties (groepshistoriek, zwemschoolgroepen verwijderen), groepskleuren en het opruimen van verouderde rechten; daarna `syncReferenceData()` voor nieuwe rollen/rechten.
- **Vormgeving:** één layout (`layout.php` + `style.css`), ook voor aanmelden, foutpagina's en de deellink.
- **Activiteit verwijderen:** `occurrence` met `do=delete` (permissie `activities.write`) roept `SeriesService::erase($id, '', true)` aan: altijd definitief, ook met aanwezigheden; wist `attendance`, `activity_groups`, `absence_reports`, `activity_trainers`, `coverage_alerts`; voor een reeks-training komt er een `activity_series_skips`-rij zodat ze niet terugkomt. Auditlog `DELETE activity`.
- **Reeksen, groepskleuren, schrappen, navigatie:** ongewijzigd ten opzichte van v0.12 (zie `SeriesService`, `Scope::targetMembers()`, `Scope::activityFilter()`, `ensure_group_colors()`, `back_url()`); `targetMembers()` = alle actieve leden van de groepen van de activiteit. Een groep verwijderen gebeurt via `GroupController::purge()` (ook gebruikt door de migratie).
- **Trainersplanning:** `Coverage` (bezetting, tekorten, herinneringen) en `CoverageController`. Herinneringen lopen zonder cron: `Coverage::autoRemind()` in `public/index.php` hooguit één keer per uur (bestand `storage/coverage-last-run`), na het antwoord; alternatief `tools/coverage-reminders.php` via cron.
- **Feature-schakelaars:** `feature('naam')` leest `config features.naam` (standaard uit). `ROUTE_FEATURES` in `public/index.php` geeft 404 voor routes van een uitgeschakeld onderdeel; menu, dashboard en activiteitspagina verbergen de bijhorende delen. Nu enkel `attendance` (routes `attendance_report`, `attendance_save`).
- **Menu:** vaste zijbalk (`_nav.php`) met secties Club (Leden, Groepen, Gebruikers, Auditlog) en Planning (Kalender, Trainingsreeksen, Trainersplanning, Aanwezigheden, Bericht sturen), gefilterd op rechten.
- **Mail:** driver `log` (lokaal, `storage/mail/outbox.log`), `smtp` of PHP `mail()`. Links in mails gebruiken `app_url`.
- **Privacy:** `privacy_href()` = `config privacy_url` (link onderaan elke pagina); het privacybeleid zelf staat op de clubwebsite.

## 7. Beveiliging
### Geïmplementeerd
- `password_hash`, sessieregeneratie bij login, HttpOnly/SameSite-cookie, `cookie_secure` configureerbaar. Enige cookie: `hzs_session` (technisch).
- CSRF op alle POST-acties; prepared statements; uitvoer-escaping via `e()`; CSP zonder inline scripts of stijl.
- Permissiecheck per route en gegevensscope in elke query (`Scope`). Leden en gebruikers kunnen definitief verwijderd worden (`Purge`, met auditlog); wie enkel tijdelijk weg is, zet je op inactief.
- **Login-throttling:** na 5 mislukte pogingen per e-mailadres of 20 per IP-adres binnen 15 minuten volgt een blokkade van 15 minuten (HTTP 429).
- **Sessie:** verloopt na 12 uur zonder activiteit. **Headers:** `X-Frame-Options: DENY`, `nosniff`, `Referrer-Policy`, HSTS bij HTTPS; app-routes `no-store` en `noindex`.
- Tokens voor uitnodiging (7 dagen) en reset (60 minuten) zijn eenmalig en gehasht bewaard; de iCal-link en de deellink weekplanning zijn herroepbare, willekeurige tokens (de deellink toont geen namen).
- **Tweestapsverificatie:** TOTP (RFC 6238); `security.require_2fa_admin` dwingt ze af voor beheerders.
- **Gevoelige gegevens:** medische opmerking en noodcontact enkel met `members.medical`.
- Kleiner aanvalsoppervlak dan v0.12: geen publieke formulieren, geen uploads, geen publieke pagina's meer.
### Voor productie nog te doen
Afdwingen van 2FA voor beheerders, headers/HSTS ook op serverniveau, back-ups van de database, minimale databaserechten, logging en alerting. Privacy/AVG: bewaartermijnen voor ledengegevens, verwerkersovereenkomsten met hosting en mail, en `privacy_url` naar het privacybeleid op de clubwebsite.
### MySQL-beperking
Geen Row Level Security: de scope wordt in de querylaag afgedwongen. Elke nieuwe query op leden, groepen, activiteiten of aanwezigheid moet `Scope` gebruiken en getest worden met autorisatietests.

## 8. Functionele regels
- Account-e-mail uniek; inactieve gebruikers kunnen niet aanmelden.
- Definitief verwijderen (v0.15): een **lid** (`members.write`, route `member_delete`) neemt zijn groepslidmaatschap, ouderkoppelingen, aanwezigheden, afmeldingen, blokindeling en rijen in oude tabellen (lidgeld, resultaten, ...) mee; een gekoppeld account blijft bestaan met `member_id = NULL`. Een **gebruiker** (`users.manage`, route `user_delete`) neemt rollen, tokens, ouder- en trainerkoppelingen en de trainersplanning mee; verwijzingen als `created_by`/`updated_by`/`reported_by`/`sender_id` worden leeggemaakt. Je eigen account en de laatste actieve beheerder kunnen niet verwijderd worden; verliest een minderjarige zo zijn laatste ouder, dan volgt een waarschuwing. Een **groep** verwijderen bestond al (`GroupController::purge`). Alles gebeurt in één transactie en staat in het auditlog.
- Een actief lid jonger dan 18 heeft minstens één ouder of voogd (zonder ouder geen groep; de laatste ouder kan niet ontkoppeld worden).
- Een lid zit in maximaal één groep; een groep verwijderen kan altijd (leden blijven bestaan zonder groep; reeksen en activiteiten enkel voor die groep verdwijnen, trainingen met aanwezigheden blijven als losse trainingen).
- Activiteit: einde na start; doelgroep GROUPS vereist minstens één groep; één aanwezigheid per activiteit en lid.
- Reeks: één tijdslot per weekdag per reeks (een droogtraining op dezelfde dag is een aparte reeks).
- Trainersplanning in bulk: pagina *Meerdere tegelijk* (`coverage_bulk_form`, GET met filters trainer, actie, groep, weekdag, periode van max. 1 jaar) toont de gevonden activiteiten met wat er zou veranderen (niet-van-toepassing grijs, zonder vinkje); bevestigen post naar `coverage_bulk`: één trainer inplannen of afmelden voor max. 200 aangevinkte activiteiten in één transactie, daarna terug naar dezelfde lijst; overgeslagen worden voorbije activiteiten, activiteiten buiten de planning, en wie al ingepland (inplannen) of niet ingepland (afmelden) is. Ontstaat er zo een tekort binnen het mailvenster, dan vertrekt meteen de herinnering. Zelfde toegang als de gewone acties.
- Overzicht (dashboard), activiteiten: `?view=week` (standaard) of `?view=list` (eerstvolgende 8) (`w` zoals hieronder, met `Scope::activityFilter`); keuze in de sessie (`dashboard_view`).
- Trainersplanning, weergave: `?view=list` (periode `weeks` 1–8) of `?view=week` (standaard; één week ma–zo, `w` = weken t.o.v. deze week, −8 tot 52); de keuze staat in de sessie (`coverage_view`). De weekkalender toont enkel; acties gebeuren op de activiteitspagina (kaart *Trainers*).
- Trainersplanning: enkel trainingen en wedstrijden (status PLANNED) van actieve groepen plus clubbrede wedstrijden. Nodig = `activities.min_trainers`, anders het hoogste `groups.min_trainers` van de competitiegroepen, standaard 1. Voorbije activiteiten zijn niet meer te wijzigen. Inplannen kan enkel met actieve competitietrainers of coördinatoren; een vaste trainer kan je afmelden maar niet weghalen. Afmelden waardoor een tekort ontstaat vraagt een vervanger (een andere trainer uit de pool die dan vrij is) of uitdrukkelijk `none` (*Nog geen vervanger*); de herinnering vertrekt dan meteen. Conflict (`Coverage::conflict()`): niemand wordt ingepland op een activiteit die overlapt met een wedstrijd waarop hij staat, of op twee overlappende wedstrijden; een wedstrijd inplannen terwijl je op een training staat mag wel (de wedstrijd gaat voor, de training wordt *Op wedstrijd*). De coördinator ziet alle actieve groepen (`Scope`). Herinneringen: tekorten die starten binnen `coverage.remind_days` (standaard 3) dagen, één mail per run naar alle coördinatoren en competitietrainers; per activiteit opnieuw enkel als het tekort groter wordt of na een oplossing terugkomt.
- Deellink weekplanning: 128-bit token per competitiegroep, intrekbaar/vernieuwbaar, `noindex`, toont 3 weken trainingen, wedstrijden en clubactiviteiten zonder namen van personen.
- Berichten: max. 400 ontvangers per bericht; een trainer enkel voor zijn eigen groepen.

## 9. Testaanpak
End-to-end suite in de repository: `php tests/e2e/run.php` (zie README). Elke suite installeert een verse kopie met SQLite en demodata, start `php -S` en test via HTTP (cookies, CSRF) en de database; PHP-waarschuwingen in het serverlog laten de test falen. Suites: `v15_verwijderen` (lid, gebruiker en groep definitief verwijderen, rechten, opruimen van gekoppelde gegevens), `smoke` (hoofdpagina's per rol, aanwezigheden verborgen en weer aan te zetten, verwijderde adressen geven 404, geen zwemschool meer en verwijdering van bestaande zwemschoolgroepen zonder ledenverlies, menu en formulieren zonder verwijderde onderdelen, geen verwijderde tabellen of rechten in een nieuwe installatie, update van een bestaande database zonder gegevensverlies) en `v12_trainersplanning` (toegang per rol, groepstrainers als vaste trainers, weekkalender, afmelden met vervanger, geen training en wedstrijd tegelijk, overnemen/inplannen, meerdere tegelijk, minimum, mails en hun ontvangers, automatische herinnering, deellink, opruimen, migratie).

## 10. Responsive
De app is gecontroleerd van 320 tot 1920 px (geen horizontale scroll). Het menu klapt in onder 960 px; tabellen worden kaarten op telefoon; formuliervelden zijn 16 px op telefoon (geen zoom op iOS).

## 11. Deployment en exploitatie
- Subdomein (bv. app.khzs.be) met HTTPS; webroot naar `public/`.
- `config.php` buiten versiebeheer; `debug` uit; `cookie_secure` aan; `app_url` en `privacy_url` instellen.
- `public/install.php` na installatie verwijderen.
- Mail instellen (smtp aanbevolen); de herinneringen van de trainersplanning gebruiken dezelfde mail en `app_url`.
- Dagelijkse back-up van de database; restore testen. De map `storage/` moet schrijfbaar zijn (database, mail, `coverage-last-run`).
- Na een update: controleer onderaan het menu (beheerder) welke commit draait.
- Releases: `dev` = testversie, `main` = goedgekeurde versie (alleen na goedkeuring); versies in `CHANGELOG.md` (v0.14.0 = deze release, nog op `dev`). Migraties lopen automatisch bij de eerste aanvraag na een update.
- Opgelet bij de update van een installatie die nog de publieke website draaide: na v0.13 is er geen website meer op dat adres. Zet de website (hzs-basic) eerst elders online of stuur het domein door.

## 12. Risico's
- **Hoog:** een nieuwe query zonder `Scope` kan gegevens lekken (geen RLS).
- **Middel:** 2FA is optioneel en zonder QR-code; audit enkel op de belangrijkste mutaties; oude tabellen (lidgeld, inschrijvingen, …) blijven met persoonsgegevens in bestaande databases staan tot ze bewust gewist worden.
- **Laag:** geen offline modus of pushmeldingen.

## 13. Aanbevolen volgende stappen
Bewaartermijnen en verwerkersovereenkomsten vastleggen; beslissen of de oude gegevens (inschrijvingen, zwemtestaanvragen, lidgeld, contactberichten, nieuwsbriefabonnees) gewist of geëxporteerd moeten worden; CSV-import van bestaande leden; afdwingen van 2FA en QR-code; een mail wanneer een tekort opgelost is; geautomatiseerde back-up.
