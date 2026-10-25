# HZS Clubapp – Functionele en technische analyse

Versie v0.8.0 (oktober 2026). Vervangt de analyse van V1 (JSON-testarchitectuur, historische koppelingen en aparte beheer-UI als vervolgwerk zijn achterhaald).

## 1. Doel
HZS Clubapp is de **publieke clubwebsite** én de **ledenapp** van Hasseltse Zwemvereniging Spartacus. Ze vervangt de WordPress-site khzs.be en ondersteunt ledenbeheer, groepsindeling, trainingsreeksen, kalender, aanwezigheid, inschrijvingen, de zwemtestwachtlijst en het contactkanaal. Eén PHP-monoliet die draait op klassieke shared hosting (MySQL) en lokaal op SQLite.

## 2. Scope
### In scope (v0.7.0)
- Publieke website: startpagina, pagina's met menu, nieuws, kalender, trainingsuren, doelstellingen, contact, zwemtestformulier en inschrijfformulier.
- Website-beheer: nieuws, pagina's, afbeeldingen, paginacontrole, import van de inhoud van khzs.be.
- Authenticatie, rollen (Beheerder, Bestuur, Trainer, Zwemmer, Ouder), permissies, gegevensscope, uitnodigingen en wachtwoord vergeten per e-mail.
- Leden, groepen (zwemschool/competitie), trainers per groep, ouder-kindkoppeling, gebruikersbeheer.
- Activiteiten, trainingsreeksen, persoonlijke kalender, schrappen/verplaatsen, presentielijst.
- Inschrijvingen (lidmaatschap) met goedkeuring door het bestuur; zwemtestwachtlijst met statussen; contactberichten.

### Niet in scope / vervolgwerk
MFA, betalingen, exports, documentenbeheer (upload van reglementen e.d.), CSV-import van bestaande leden (bewust uitgesteld), clubrecords als tabel, volledige audit-triggers, herhalende activiteiten buiten reeksen, restyling van login/app in de stijl van de site.

## 3. Actoren en rechten
| Rol | Rechten (permissies) | Zichtbaarheid |
|---|---|---|
| Beheerder | alle (`*`), ook `users.manage` | clubbreed |
| Bestuur | members.read/write/contact, groups.read/write, activities.read/write, attendance.read/write, registrations.manage, content.manage | clubbreed, geen gebruikersbeheer |
| Trainer | members.read, groups.read, activities.read, attendance.read/write | leden en groepen van zijn actieve groepen; aanwezigheid enkel voor groepen die hij traint |
| Zwemmer | members.read, activities.read, attendance.read | zichzelf en de eigen groep |
| Ouder | members.read, activities.read, attendance.read | gekoppelde kinderen en hun groepen |
| Bezoeker | geen login | publieke site en de openbare formulieren |

Rollen zijn combineerbaar (trainer die ook lid is). De scope is de unie van de rollen. `registrations.manage` dekt inschrijvingen, zwemtestwachtlijst en contactberichten; `content.manage` dekt het websitebeheer.

## 4. Kernflows
1. **Zwemtest**: bezoeker vult het formulier in → bevestigt per e-mail (token) → staat op de wachtlijst (WAITING) → bestuur zet op INVITED / TESTED / DECLINED (optionele mails) → *Als lid inschrijven* maakt een gewone inschrijving (PENDING) die de goedkeuring volgt → ENROLLED.
2. **Lid worden**: openbaar inschrijfformulier → bevestiging per e-mail → bestuur keurt goed (leden + ouderaccount + uitnodiging) of af (mail met reden).
3. **Trainingen**: bestuur maakt per groep een reeks (weekdagen, uren, periode) → de app maakt de trainingen aan → schrappen per training of periode → trainer registreert aanwezigheid.
4. **Website**: bestuur beheert pagina's en nieuws; trainingsuren, doelstellingen, contactformulier en agenda komen automatisch uit de app.
5. **Contact**: bezoeker stuurt een bericht → opgeslagen in de app en gemaild naar het adres van het gekozen onderwerp → bestuur handelt af.
6. **Dagelijks gebruik**: leden, ouders en trainers openen hun persoonlijke kalender in de app.

## 5. Publieke site versus app
- **Publieke kalender en *Komende activiteiten*:** voor iedereen, ook aangemelden, uitsluitend wedstrijden (type COMPETITION) en clubactiviteiten (type CLUB), met titel, tijd, plaats en omschrijving; nooit groepen, leden of trainingen.
- **Trainingsuren (site):** de trainingsreeksen met vinkje *Tonen op de website*, actief en niet verlopen, per groep met de trainers. Pagina *Trainingsuren competitie* toont ze automatisch.
- **Persoonlijke kalender (app):** trainingen en activiteiten van de eigen groepen (trainer, lid, ouder via kinderen) plus alle clubactiviteiten en wedstrijden. Wedstrijden voor andere groepen staan in grijs en zijn niet aanklikbaar; beheerders en bestuur kunnen alles openen.
- **Automatische pagina's:** `trainingsuren-competitie`, `doelstellingen`, `contact` en `home` halen inhoud uit de app. In het beheer: label *Automatisch*, vast adres/plaats/status, niet verwijderbaar; de eerste twee laten enkel de inleiding (tekst vóór de eerste kop) toe.
- **Paginacontrole:** `PageCheck` meldt verouderde `doelstellingen-*`-pagina's, lege pagina's, concepten, kapotte interne links (onbekende pagina of route, link naar een concept), onbereikbare pagina's (niet in menu en nergens naar verwezen) en vaste uren die door reeksen vervangen worden. Opruimen verwijdert enkel verouderde en lege pagina's, nooit automatische pagina's.

## 6. Domeinmodel
- **Identiteit en RBAC:** `users` (optioneel gekoppeld aan één `members`), `user_tokens` (uitnodiging/reset/bevestiging, hash + vervaldatum), `roles`, `permissions`, `user_roles`, `role_permissions`.
- **Leden:** `members` (profiel, adres, status), `guardian_links` (ouder ↔ kind met relatie).
- **Groepen:** `groups` (categorie SWIM_SCHOOL/COMPETITION, `is_active`, `goals` voor de website), `group_memberships` (één rij per lid: **maximaal één groep, geen datums**), `group_trainers` (**zonder datums**, meerdere trainers per groep).
- **Planning:** `activity_types` (TRAINING, COMPETITION, CLUB), `activities` (doelgroep CLUB of GROUPS, status PLANNED/CANCELLED, `series_id`, `is_custom`, `status_note`, `is_public`), `activity_groups`, `attendance` (UNKNOWN/PRESENT/ABSENT, uniek per activiteit en lid).
- **Reeksen:** `activity_series` (periode, `is_active`, `is_public`), `activity_series_slots` (uniek per reeks en weekdag), `activity_series_groups`. `SeriesService::sync()` maakt en onderhoudt de trainingen; individueel gewijzigde trainingen en trainingen met aanwezigheid blijven ongemoeid.
- **Inschrijven:** `registrations` + `registration_persons` (PENDING/APPROVED/REJECTED, bevestigingstoken), `swimtest_requests` (UNCONFIRMED, WAITING, INVITED, TESTED, ENROLLED, DECLINED), `contact_messages` (onderwerp, afgehandeld).
- **Website:** `posts` (nieuws), `pages` (hoofd- en subpagina's, max. één niveau, slug, menu, status), `media`.
- **Audit:** `audit_log` wordt gevuld door `audit()` bij de belangrijkste mutaties (aanmaken, wijzigen, verwijderen, import, opruimen).

## 7. Architectuur
- Server-rendered PHP-monoliet zonder externe pakketten; PDO met prepared statements (SQLite lokaal, MySQL in productie).
- `public/index.php`: front controller met routetabel `route → methode → [Controller, actie, permissie|'public'|null]`; centrale CSRF-controle op elke POST (fout = 419).
- `app/Controllers/*`: use-cases per domein; `app/Scope.php`: centrale gegevensscope; `app/Auth.php`: sessie en permissies; `app/Markup.php`: veilige opmaak voor teksten (eerst escapen, dan een beperkte set opmaak en een whitelist voor links); `app/SeriesService.php`, `app/PageCheck.php`, `app/Mailer.php`, `app/Invites.php`, `app/Guardianship.php`.
- **Schema en migraties:** `db/schema.sql` met tokens `{{PK}}`/`{{ENGINE}}` (portable SQL, datums als tekst). `Installer::migrate()` draait bij elke start: ontbrekende tabellen aanmaken (nieuwe tabellen moeten in de probe-lijst staan), kolommen toevoegen, opruimmigraties (groepshistoriek, doelstellingen naar de groep, inhoudscorrecties) en referentiedata (rollen/permissies) synchroniseren. Bestaande databases worden dus automatisch bijgewerkt.
- **Publieke vormgeving:** `layout_public.php` + `public/assets/site.css`, zelf gehoste lettertypen; de beheer-app gebruikt `layout.php` + `style.css`.
- **Navigatie:** de knop *← Terug* gaat naar de bovenliggende pagina via `back_url()` (geen browsergeschiedenis).
- **Mail:** driver `log` (lokaal, `storage/mail/outbox.log`), `smtp` of PHP `mail()`. Links in mails gebruiken `app_url`.
- **Uploads:** `public/uploads/` (enkel afbeeldingen, type- en groottecontrole).

## 8. Beveiliging
### Geïmplementeerd
- `password_hash`, sessieregeneratie bij login, HttpOnly/SameSite-cookie, `cookie_secure` configureerbaar.
- CSRF op alle POST-acties; prepared statements; uitvoer-escaping via `e()`; CSP zonder inline scripts.
- Permissiecheck per route en gegevensscope in elke query (`Scope`); geen fysieke deletes van leden.
- Publieke formulieren: verborgen spamveld, limieten per e-mailadres en uur, e-mailbevestiging met eenmalig token, onbevestigde aanvragen worden na 7 dagen opgeruimd.
- **Login-throttling:** tabel `login_attempts`; na 5 mislukte pogingen per e-mailadres of 20 per IP-adres binnen 15 minuten volgt een blokkade van 15 minuten (HTTP 429); een geslaagde login wist de teller. Constante responstijd bij onbekende accounts.
- **Sessie:** verloopt na 12 uur zonder activiteit; sessie-ID wordt vernieuwd bij login.
- **Headers:** CSP zonder inline stijl of script, `X-Frame-Options: DENY`, `nosniff`, `Referrer-Policy`, HSTS bij HTTPS; app-routes met `Cache-Control: no-store` en `X-Robots-Tag: noindex`.
- **Foutafhandeling:** globale exception handler (logt, toont een neutrale foutpagina, in de stijl van de site voor bezoekers); `debug` toont details enkel lokaal.
- **Uploads:** enkel jpg/png/gif/webp op basis van de echte afbeeldingsgegevens, willekeurige bestandsnamen, `.htaccess` die scriptuitvoering in `public/uploads/` blokkeert.
- Tokens voor uitnodiging (7 dagen) en reset (60 minuten) zijn eenmalig en worden gehasht bewaard.
- Publieke site toont nooit persoonsgegevens: enkel wedstrijden/clubactiviteiten en de uren per groep met de naam van de trainers.
- Installer vergrendelt zichzelf; laatste actieve beheerder kan niet uitgeschakeld worden.
### Voor productie nog te doen
MFA voor beheerders, headers/HSTS ook op serverniveau, back-ups van database en `public/uploads/`, minimale databaserechten, privacyverklaring en AVG-proces (retentie, inzage, verwijdering), logging en alerting.
### MySQL-beperking
Geen Row Level Security: de scope wordt in de querylaag afgedwongen. Elke nieuwe query op leden, groepen, activiteiten of aanwezigheid moet `Scope` gebruiken en getest worden met autorisatietests.

## 9. Functionele regels
- Account-e-mail uniek; inactieve gebruikers kunnen niet aanmelden.
- Een actief lid jonger dan 18 heeft minstens één ouder of voogd (zonder ouder geen groep; de laatste ouder kan niet ontkoppeld worden).
- Een lid zit in maximaal één groep; een groep verwijderen kan altijd (leden blijven bestaan zonder groep; reeksen en activiteiten enkel voor die groep verdwijnen, trainingen met aanwezigheden blijven als losse trainingen).
- Activiteit: einde na start; doelgroep GROUPS vereist minstens één groep; één aanwezigheid per activiteit en lid.
- Reeks: één tijdslot per weekdag per reeks (een droogtraining op dezelfde dag is een aparte reeks).
- Zichtbaarheid van een reeks op de site: `is_public = 1`, `is_active = 1` en einddatum niet verstreken (labels *Niet op website*, *Gestopt*, *Verlopen*).
- Pagina's: max. één subniveau; automatische pagina's niet verwijderbaar.

## 10. Testaanpak
Er is geen PHPUnit-suite; de levering werd getest met end-to-end shellscripts tegen `php -S` (installatie met demodata, aanmelden met de demo-accounts, formulieren posten en resultaten controleren in HTML en database). Dekking: authenticatie en CSRF, autorisatie per rol, ledenregels (minderjarigen, één groep), groepsbeheer en verwijderen, trainingsreeksen (aanmaken, schrappen, verplaatsen, verwijderen, tonen/verbergen), uitnodigingen en mails, inschrijven en goedkeuren, zwemtestwachtlijst, contactformulier, publieke site en kalender (wat bezoekers wel/niet zien), websitebeheer, paginacontrole, automatische pagina's, terugknop en migraties van bestaande databases. Aanbeveling: deze scripts omzetten naar een bewaarde suite in de repository (bv. `tests/`).

## 10b. Responsive
Publieke site en beheerschermen zijn gecontroleerd op 320, 360, 390, 414, 600, 768, 820, 1024, 1180, 1366 en 1920 px (geen horizontale scroll). Menu klapt in onder 1180 px; onder 820 px blijft in de header enkel het logo; tabellen worden kaarten op telefoon (elke tabel heeft een `thead` voor de labels); formuliervelden zijn 16 px op telefoon (geen zoom op iOS).

## 11. Deployment en exploitatie
- Apart subdomein of de hoofddomeinnaam met HTTPS; webroot naar `public/`.
- `config.php` buiten versiebeheer; `debug` uit; `cookie_secure` aan; `app_url` instellen.
- `public/install.php` na installatie verwijderen.
- Mail instellen (smtp aanbevolen) en `contact`-adressen per onderwerp.
- Dagelijkse back-up van de database en `public/uploads/`; restore testen.
- Releases: `dev` = testversie, `main` = goedgekeurde versie (alleen fast-forward na goedkeuring); versies in `CHANGELOG.md` (v0.8.0 = deze release). Migraties lopen automatisch bij de eerste aanvraag na een update.

## 12. Risico's
- **Hoog:** een nieuwe query zonder `Scope` kan gegevens lekken (geen RLS).
- **Middel:** geen MFA; audit enkel op de belangrijkste mutaties; end-to-end-scripts staan niet in de repository; migraties draaien bij elke start (nu ±6 ms; een schemaversie in de database zou netter zijn).
- **Laag:** routering via queryparameter; geen offline modus of pushmeldingen.

## 13. Aanbevolen volgende stappen
Documentenbeheer (reglementen, formulieren uploaden); CSV-import van bestaande leden; clubrecords als tabel; zwemtestmomenten bewerkbaar maken; login, registratie en app-layout in de stijl van de site; MFA voor beheerders; schemaversie in plaats van migraties bij elke start; testsuite in de repository; geautomatiseerde back-up.
