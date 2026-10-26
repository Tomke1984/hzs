# HZS Clubapp – Functionele en technische analyse

Versie v0.10.4 (oktober 2026). Vervangt de analyse van V1 (JSON-testarchitectuur, historische koppelingen en aparte beheer-UI als vervolgwerk zijn achterhaald).

## 1. Doel
HZS Clubapp is de **publieke clubwebsite** én de **ledenapp** van Hasseltse Zwemvereniging Spartacus. Ze vervangt de WordPress-site khzs.be en ondersteunt ledenbeheer, groepsindeling, trainingsreeksen, kalender, aanwezigheid, inschrijvingen, de zwemtestwachtlijst en het contactkanaal. Eén PHP-monoliet die draait op klassieke shared hosting (MySQL) en lokaal op SQLite.

## 2. Scope
### In scope (v0.10.4)
- Publieke website: startpagina, pagina's met menu, nieuws, kalender, trainingsuren, doelstellingen, contact, zwemtestformulier en inschrijfformulier.
- Website-beheer: nieuws, pagina's, afbeeldingen, paginacontrole (de eenmalige import van khzs.be is uit de app gehaald; `tools/seed-site.php` blijft als ontwikkelhulp).
- Authenticatie, rollen (Beheerder, Bestuur, Trainer, Zwemmer, Ouder), permissies, gegevensscope, uitnodigingen en wachtwoord vergeten per e-mail.
- Leden, groepen (zwemschool/competitie), trainers per groep, ouder-kindkoppeling, gebruikersbeheer.
- Activiteiten, trainingsreeksen, persoonlijke kalender, schrappen/verplaatsen (ook alle activiteiten van een dag of periode), presentielijst.
- Inschrijvingen (lidmaatschap) met goedkeuring door het bestuur; zwemtestwachtlijst met statussen; contactberichten.
- Zwemblokken: zwemschoolreeksen als blokken (dag + uur) met indeling per lid, lijst *Nog niet ingedeeld* en weekoverzicht.
- Bezoekersstatistieken voor website en app (enkel beheerders), cookieloos en zonder persoonsgegevens.
- Privacybeleid (AVG) als pagina van de site, met een link in footer en formulieren.

### Niet in scope / vervolgwerk
MFA, betalingen, exports, documentenbeheer (upload van reglementen e.d.), CSV-import van bestaande leden (bewust uitgesteld), clubrecords als tabel, volledige audit-triggers, herhalende activiteiten buiten reeksen, restyling van login/app in de stijl van de site.

## 3. Actoren en rechten
| Rol | Rechten (permissies) | Zichtbaarheid |
|---|---|---|
| Beheerder | alle (`*`), ook `users.manage` en `stats.read` | clubbreed |
| Bestuur | members.read/write/contact, groups.read/write, activities.read/write, attendance.read/write, registrations.manage, content.manage | clubbreed, geen gebruikersbeheer, geen statistieken |
| Trainer | members.read, groups.read, activities.read, attendance.read/write | leden en groepen van zijn actieve groepen; aanwezigheid enkel voor groepen die hij traint |
| Zwemmer | members.read, activities.read, attendance.read | zichzelf en de eigen groep |
| Ouder | members.read, activities.read, attendance.read | gekoppelde kinderen en hun groepen |
| Bezoeker | geen login | publieke site en de openbare formulieren |

Rollen zijn combineerbaar (trainer die ook lid is). De scope is de unie van de rollen. `registrations.manage` dekt inschrijvingen, zwemtestwachtlijst en contactberichten; `content.manage` dekt het websitebeheer; `stats.read` (enkel Beheerder) dekt het statistiekendashboard.

## 4. Kernflows
1. **Zwemtest**: bezoeker vult het formulier in → bevestigt per e-mail (token) → staat op de wachtlijst (WAITING) → bestuur zet op INVITED / TESTED / DECLINED (optionele mails) → *Als lid inschrijven* maakt een gewone inschrijving (PENDING) die de goedkeuring volgt → ENROLLED.
2. **Lid worden**: openbaar inschrijfformulier → bevestiging per e-mail → bestuur keurt goed (leden + ouderaccount + uitnodiging) of af (mail met reden).
3. **Trainingen**: bestuur maakt per groep een reeks (weekdagen, uren, periode) → de app maakt de trainingen aan → schrappen per training of periode → trainer registreert aanwezigheid. **Zwemschool:** de reeks is een blok (dag + uur) voor meerdere groepen; het bestuur deelt de leden in (blokpagina, lijst *Nog niet ingedeeld* of rooster op de groepspagina); alleen ingedeelde leden staan op de lijst en zien het blok.
4. **Website**: bestuur beheert pagina's en nieuws; trainingsuren, doelstellingen, contactformulier en agenda komen automatisch uit de app.
5. **Contact**: bezoeker stuurt een bericht → opgeslagen in de app en gemaild naar het adres van het gekozen onderwerp → bestuur handelt af.
6. **Dagelijks gebruik**: leden, ouders en trainers openen hun persoonlijke kalender in de app.
7. **Statistieken**: beheerders volgen het verkeer op de site en in de app (zie 7, Statistieken).

## 5. Publieke site versus app
- **Publieke kalender en *Komende activiteiten*:** voor iedereen, ook aangemelden, uitsluitend wedstrijden (type COMPETITION) en clubactiviteiten (type CLUB), met titel, tijd, plaats en omschrijving; nooit groepen, leden of trainingen.
- **Trainingsuren (site):** de trainingsreeksen met vinkje *Tonen op de website*, actief en niet verlopen, per groep met de trainers. Pagina *Trainingsuren competitie* toont ze automatisch.
- **Persoonlijke kalender (app):** trainingen en activiteiten van de eigen groepen (trainer, lid, ouder via kinderen) plus alle clubactiviteiten en wedstrijden. Wedstrijden voor andere groepen staan in grijs en zijn niet aanklikbaar; beheerders en bestuur kunnen alles openen.
- **Automatische pagina's:** `trainingsuren-competitie`, `doelstellingen`, `contact` en `home` halen inhoud uit de app. In het beheer: label *Automatisch*, vast adres/plaats/status, niet verwijderbaar; de eerste twee laten enkel de inleiding (tekst vóór de eerste kop) toe.
- **Paginacontrole:** `PageCheck` meldt verouderde `doelstellingen-*`-pagina's, lege pagina's, concepten, kapotte interne links (onbekende pagina of route, link naar een concept), onbereikbare pagina's (niet in menu en nergens naar verwezen) en vaste uren die door reeksen vervangen worden. Opruimen verwijdert enkel verouderde en lege pagina's, nooit automatische pagina's.

## 6. Domeinmodel
- **Identiteit en RBAC:** `users` (optioneel gekoppeld aan één `members`), `user_tokens` (uitnodiging/reset/bevestiging, hash + vervaldatum), `roles`, `permissions`, `user_roles`, `role_permissions`.
- **Leden:** `members` (profiel, adres, status), `guardian_links` (ouder ↔ kind met relatie), `member_series` (indeling in zwemblokken, zie 7).
- **Groepen:** `groups` (categorie SWIM_SCHOOL/COMPETITION, `is_active`, `goals` voor de website), `group_memberships` (één rij per lid: **maximaal één groep, geen datums**), `group_trainers` (**zonder datums**, meerdere trainers per groep).
- **Planning:** `activity_types` (TRAINING, COMPETITION, CLUB), `activities` (doelgroep CLUB of GROUPS, status PLANNED/CANCELLED, `series_id`, `is_custom`, `status_note`, `is_public`), `activity_groups`, `attendance` (UNKNOWN/PRESENT/ABSENT, uniek per activiteit en lid).
- **Reeksen:** `activity_series` (periode, `is_active`, `is_public`), `activity_series_slots` (uniek per reeks en weekdag), `activity_series_groups`. `SeriesService::sync()` maakt en onderhoudt de trainingen; individueel gewijzigde trainingen en trainingen met aanwezigheid blijven ongemoeid.
- **Inschrijven:** `registrations` + `registration_persons` (PENDING/APPROVED/REJECTED, bevestigingstoken), `swimtest_requests` (UNCONFIRMED, WAITING, INVITED, TESTED, ENROLLED, DECLINED), `contact_messages` (onderwerp, afgehandeld).
- **Website:** `posts` (nieuws), `pages` (hoofd- en subpagina's, max. één niveau, slug, menu, status), `media`.
- **Statistieken:** `page_views` (dag, gebied SITE/APP of SKIP, route, pad zonder querystring, dagelijkse hash als bezoeker, apparaat, herkomsthost). Rijen met gebied SKIP zijn anonieme tellers van genegeerde bezoeken (reden in `route`: DNT, BOT, STAFF), zonder bezoeker of pad. Bewaartermijn 400 dagen.
- **Audit:** `audit_log` wordt gevuld door `audit()` bij de belangrijkste mutaties (aanmaken, wijzigen, verwijderen, import, opruimen).

## 7. Architectuur
- Server-rendered PHP-monoliet zonder externe pakketten; PDO met prepared statements (SQLite lokaal, MySQL in productie).
- **Adressen:** publieke pagina's hebben leesbare permalinks (`/<paginanaam>`, `/kalender`, `/trainingsuren`, `/zwemtest`, `/inschrijven`, `/aanmelden`, `/nieuws/<berichtnaam>`). `public/.htaccess` stuurt alles wat geen bestand is naar `index.php`; de router vertaalt het pad naar een route, `url()` bouwt de adressen (relatief aan `<base href>`, met `base_path()` voor submappen). De beheer-app staat onder `/app` (`APP_PATHS` voor de hoofdschermen met id in het pad, bv. `/app/leden/12/wijzigen`; alle andere routes, vooral POST-acties, op `/app/<route-met-streepjes>`); `app_route_from_path()` vertaalt het pad terug. Bevestigingslinks (`swimtest_confirm`, `register_confirm`) blijven op `?r=` (`PLAIN_ROUTES`). `?r=route` blijft overal werken (oude links en mails). Gereserveerde namen (`RESERVED_SLUGS`) kunnen niet als paginanaam dienen. Links in paginateksten worden bij het renderen omgezet (`pretty_internal`).
- `public/index.php`: front controller met routetabel `route → methode → [Controller, actie, permissie|'public'|null]`; centrale CSRF-controle op elke POST (fout = 419).
- `app/Controllers/*`: use-cases per domein; `app/Scope.php`: centrale gegevensscope; `app/Auth.php`: sessie en permissies; `app/Markup.php`: veilige opmaak voor teksten (eerst escapen, dan een beperkte set opmaak en een whitelist voor links); `app/SeriesService.php`, `app/PageCheck.php`, `app/Mailer.php`, `app/Invites.php`, `app/Guardianship.php`.
- **Schema en migraties:** `db/schema.sql` met tokens `{{PK}}`/`{{ENGINE}}` (portable SQL, datums als tekst). `Installer::migrate()` draait bij elke start: ontbrekende tabellen aanmaken (nieuwe tabellen moeten in de probe-lijst staan), kolommen toevoegen, opruimmigraties (groepshistoriek, doelstellingen naar de groep, inhoudscorrecties) en referentiedata (rollen/permissies) synchroniseren. Bestaande databases worden dus automatisch bijgewerkt.
- **Publieke vormgeving:** `layout_public.php` + `public/assets/site.css`, zelf gehoste lettertypen; de beheer-app gebruikt `layout.php` + `style.css`.
- **Zwemblokken en trainingsmoment per lid:** tabel `member_series (member_id, series_id, weekday)`; de app slaat de indeling per reeks op (weekday 0 = de hele reeks; een reeks is één trainingsmoment); de filters begrijpen ook rijen met een weekdag 1–7. **Regel:** voor *zwemschoolgroepen* geldt dat een lid enkel op de lijst staat van reeksen waaraan het gekoppeld is (zonder koppeling = nergens); voor *competitiegroepen* volgt het lid het vaste schema van de groep, tenzij er toch een eigen keuze is. `Scope::targetMembers()` filtert de aanwezigheidslijst met die regel; `Scope::activityFilter()` verbergt via `hiddenSlots()` de reeksen waaraan een lid van een zwemschoolgroep niet gekoppeld is (een niet-ingedeeld kind ziet dus niets van die groep); trainers van de groep en clubbrede rollen zien alles. Beheer: `SeriesController::show` (sectie *Leden in dit blok*), `memberAdd`/`memberRemove` (routes `series_member_add`/`series_member_remove`), `index` (lijst *Nog niet ingedeeld* via `SeriesService::unassignedSwimMembers()`), `GroupController::saveDays` (rooster op de groepspagina, route `member_days`). `SeriesService::assign()` controleert dat de groep van het lid in het blok is toegelaten; `SeriesService::clearMemberBlocks()` wist de indeling bij groepswissel, verplaatsen en verwijderen uit de groep. Het weekoverzicht (`series_week`, `SeriesController::week`) toont dag × uur met groepen en aantal leden.
- **Reeks verlengen:** `SeriesController::extend` (route `series_extend`, één of meerdere reeksen) zet de einddatum later en roept `SeriesService::sync()` aan; de limiet (`MAX_DAYS` = 800) geldt vanaf vandaag, niet vanaf de startdatum.
- **Groepskleuren:** `groups.color` (0–11) wordt toegekend door `ensure_group_colors()` (eerste ongebruikte, anders minst gebruikte) bij aanmaken en bij elke migratie; bestaande kleuren veranderen nooit. `group_chip()` toont naam + kleurstip; CSS-klassen `.g-0`…`.g-11` (geen inline styles wegens CSP).
- **Schrappen:** `SeriesService::erase()` verwijdert een activiteit (incl. aanwezigheden/groepskoppelingen) en onthoudt bij reekstrainingen de dag in `activity_series_skips`, zodat `sync()` ze niet opnieuw aanmaakt; met geregistreerde aanwezigheden wordt de activiteit enkel op CANCELLED gezet.
- **Menu van de app:** vaste zijbalk (`app/views/_nav.php`) met secties Club, Planning, Aanvragen (met tellers) en Website, gefilterd op rechten; onder 960 px een uitschuifbaar menu via een *Menu*-knop (`app.js`), zonder JavaScript altijd zichtbaar (`nojs.css`).
- **Navigatie:** de knop *← Terug* gaat naar de bovenliggende pagina via `back_url()` (geen browsergeschiedenis); filters, tab en kalendermaand van de lijst worden in de sessie onthouden (`remember_list_context()`), activiteiten keren terug naar hun eigen maand; op de hoofdpagina's uit het menu (`BACK_TOP_ROUTES`) staat geen Terug.
- **Statistieken:** `Stats::track()` wordt in `public/index.php` vlak voor de actie aangeroepen (na de rechtencontrole). Het kiest gebied (SITE = publieke routes, APP = ingelogde routes), bepaalt of het bezoek meetelt en registreert een shutdown-functie die enkel bij een 200-antwoord (of een server die geen statuscode teruggeeft) één rij in `page_views` schrijft; fouten komen in het serverlog en in `storage/stats-error.log` en nooit in de pagina. Bezoeker = `hmac(dag | IP+user-agent of gebruikers-id, geheim)` afgekapt tot 16 tekens; het geheim staat in `storage/stats.salt` (per server aangemaakt, niet in git). Niet geteld (anoniem als SKIP-teller): `Do Not Track`/`Sec-GPC`, bots (user-agent), beheerders/bestuur op de website (`config stats.count_staff` zet dat om). Dashboard: route `stats` (`StatsController`, permissie `stats.read`); de grafiek is inline SVG en de meters zijn `<progress>`, omdat de CSP inline `style` verbiedt. `Stats::whyNotCounted()` legt aan de beheerder uit waarom het eigen bezoek niet telt.
- **Privacybeleid:** pagina `privacybeleid` (tekst in `SiteSeed::privacyBody()`; `Installer::migrate()` vervangt enkel de oorspronkelijke tekst van 2018, herkend aan de datum; `php tools/seed-site.php privacy` forceert). `privacy_href()` geeft het adres: `config privacy_url` of anders de gepubliceerde pagina; de footer en de formulieren gebruiken dat. Controleer de tekst bij elke nieuwe verwerking (nieuwe velden, nieuwe externe diensten, nieuwe bewaartermijnen).
- **Opmaak (`Markup`):** `Markup::clean()` normaliseert plakwerk (regelscheiders, `&nbsp;`, `\##`, Unicode-spaties en onzichtbare tekens, `＃`, `•`); koppen en lijsten starten ook zonder lege regels. `Markup::problems()` geeft de exacte tekens van regels die op een kop lijken maar niet herkend worden; de publieke pagina en het bericht tonen die aan beheerders. Het intro-deel van een pagina splitst op de opgeschoonde tekst.
- **Versie-aanduiding:** `app_build()` leest branch en commit uit `.git` en toont ze aan beheerders onderaan het menu.
- **Mail:** driver `log` (lokaal, `storage/mail/outbox.log`), `smtp` of PHP `mail()`. Links in mails gebruiken `app_url`.
- **Uploads:** `public/uploads/` (enkel afbeeldingen, type- en groottecontrole).

## 8. Beveiliging
### Geïmplementeerd
- `password_hash`, sessieregeneratie bij login, HttpOnly/SameSite-cookie, `cookie_secure` configureerbaar. Enige cookie: `hzs_session` (technisch, verdwijnt bij sluiten van de browser); geen tracking- of analysecookies en geen externe verbindingen.
- CSRF op alle POST-acties; prepared statements; uitvoer-escaping via `e()`; CSP zonder inline scripts.
- Permissiecheck per route en gegevensscope in elke query (`Scope`); geen fysieke deletes van leden.
- Publieke formulieren: verborgen spamveld, limieten per e-mailadres en uur, e-mailbevestiging met eenmalig token, onbevestigde aanvragen worden na 7 dagen opgeruimd.
- **Login-throttling:** tabel `login_attempts`; na 5 mislukte pogingen per e-mailadres of 20 per IP-adres binnen 15 minuten volgt een blokkade van 15 minuten (HTTP 429); een geslaagde login wist de teller. Constante responstijd bij onbekende accounts.
- **Sessie:** verloopt na 12 uur zonder activiteit; sessie-ID wordt vernieuwd bij login.
- **Headers:** CSP zonder inline stijl of script, `X-Frame-Options: DENY`, `nosniff`, `Referrer-Policy`, HSTS bij HTTPS; app-routes met `Cache-Control: no-store` en `X-Robots-Tag: noindex`.
- **Foutafhandeling:** globale exception handler (logt, toont een neutrale foutpagina, in de stijl van de site voor bezoekers); `debug` toont details enkel lokaal.
- **Uploads:** enkel jpg/png/gif/webp op basis van de echte afbeeldingsgegevens, willekeurige bestandsnamen, `.htaccess` die scriptuitvoering in `public/uploads/` blokkeert.
- Tokens voor uitnodiging (7 dagen) en reset (60 minuten) zijn eenmalig en worden gehasht bewaard.
- **Privacy van de statistieken:** geen cookies, geen IP-adres, naam of gebruikers-id opgeslagen; dagelijks wisselende hash; DNT/GPC gerespecteerd; bewaartermijn 400 dagen; enkel toegankelijk voor `stats.read` (Beheerder). Mislukte aanmeldingen (e-mail + IP) worden na 24 uur gewist; onbevestigde aanvragen na 7 dagen.
- Publieke site toont nooit persoonsgegevens: enkel wedstrijden/clubactiviteiten en de uren per groep met de naam van de trainers.
- Installer vergrendelt zichzelf; laatste actieve beheerder kan niet uitgeschakeld worden.
### Voor productie nog te doen
MFA voor beheerders, headers/HSTS ook op serverniveau, back-ups van database en `public/uploads/`, minimale databaserechten, logging en alerting. Privacy/AVG: het privacybeleid staat in de app; het bestuur moet nog concrete bewaartermijnen voor ledengegevens vastleggen, verwerkersovereenkomsten sluiten met hosting- en mailprovider, de punten over de federatie en foto's in het beleid bevestigen, en een proces voor inzage- en wisverzoeken afspreken.
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
- Zwemblok: een lid wordt enkel ingedeeld in een blok waarvoor zijn huidige groep is toegelaten; meerdere blokken per lid kan, geen capaciteitslimiet; wisselt de groep, dan vervalt de indeling. Een zwemschoollid zonder blok staat nergens op een lijst.
- Statistieken: enkel GET-aanvragen met antwoord 200 tellen; het dashboard en bezoek van beheerders/bestuur op de site tellen niet.

## 10. Testaanpak
Er is geen PHPUnit-suite; de levering werd getest met end-to-end shellscripts tegen `php -S` (installatie met demodata, aanmelden met de demo-accounts, formulieren posten en resultaten controleren in HTML en database). Dekking (o.a. nieuw: zwemblokken, statistieken en privacy, opmaak van plakwerk): authenticatie en CSRF, autorisatie per rol, ledenregels (minderjarigen, één groep), groepsbeheer en verwijderen, trainingsreeksen (aanmaken, schrappen, verplaatsen, verwijderen, tonen/verbergen), uitnodigingen en mails, inschrijven en goedkeuren, zwemtestwachtlijst, contactformulier, publieke site en kalender (wat bezoekers wel/niet zien), websitebeheer, paginacontrole, automatische pagina's, terugknop en migraties van bestaande databases. Aanbeveling: deze scripts omzetten naar een bewaarde suite in de repository (bv. `tests/`).

## 10b. Responsive
Publieke site en beheerschermen zijn gecontroleerd op 320, 360, 390, 414, 600, 768, 820, 1024, 1180, 1366 en 1920 px (geen horizontale scroll). Publiek menu klapt in onder 1180 px, het app-menu onder 960 px; onder 820 px blijft in de header enkel het logo; tabellen worden kaarten op telefoon (elke tabel heeft een `thead` voor de labels); formuliervelden zijn 16 px op telefoon (geen zoom op iOS).

## 11. Deployment en exploitatie
- Apart subdomein of de hoofddomeinnaam met HTTPS; webroot naar `public/`.
- `config.php` buiten versiebeheer; `debug` uit; `cookie_secure` aan; `app_url` instellen.
- `public/install.php` na installatie verwijderen.
- Mail instellen (smtp aanbevolen) en `contact`-adressen per onderwerp.
- Dagelijkse back-up van de database en `public/uploads/`; restore testen. De map `storage/` moet schrijfbaar zijn (database, mail, `stats.salt`, `stats-error.log`); `storage/stats.salt` is geheim en per server verschillend.
- Na een update: controleer onderaan het menu (beheerder) welke commit draait en of het privacybeleid op de site staat.
- Releases: `dev` = testversie, `main` = goedgekeurde versie (alleen fast-forward na goedkeuring); versies in `CHANGELOG.md` (v0.10.4 = deze release). Migraties lopen automatisch bij de eerste aanvraag na een update.

## 12. Risico's
- **Hoog:** een nieuwe query zonder `Scope` kan gegevens lekken (geen RLS).
- **Middel:** geen MFA; audit enkel op de belangrijkste mutaties; end-to-end-scripts staan niet in de repository; migraties draaien bij elke start (nu ±6 ms; een schemaversie in de database zou netter zijn).
- **Laag:** routering via queryparameter; geen offline modus of pushmeldingen.

## 13. Aanbevolen volgende stappen
Bewaartermijnen en verwerkersovereenkomsten vastleggen en het privacybeleid laten nalezen; Documentenbeheer (reglementen, formulieren uploaden); CSV-import van bestaande leden; clubrecords als tabel; zwemtestmomenten bewerkbaar maken; login, registratie en app-layout in de stijl van de site; MFA voor beheerders; schemaversie in plaats van migraties bij elke start; testsuite in de repository; geautomatiseerde back-up.
