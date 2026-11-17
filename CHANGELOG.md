# Changelog – HZS Clubapp

Alle wijzigingen aan de app, nieuwste bovenaan. Elke push naar `dev` krijgt hier een regel onder **Op dev**.
Zodra `dev` naar `main` gaat (enkel na goedkeuring), verhuizen die regels naar een **Op main**-sectie met versienummer, datum en commit (`v0.x.0`, telkens +1 per merge).

## Op dev (nog niet op main)

_v0.27 – Database terugzetten via de installer – commit `2b8acaf`_

- **Lokale gegevens naar de server zonder SSH:** `install.php` heeft onderaan *Of: bestaande database terugzetten*. Laad een SQLite-database van je computer (`clubapp.sqlite`) of een back-up van de app (`hzs-….sqlite.gz`) op; je meldt daarna aan met je bestaande accounts.
- Controles: echt een SQLite-database, niet beschadigd, minstens één gebruiker; staan er versleutelde medische gegevens in, dan is de `app_key` van de oorspronkelijke installatie verplicht én wordt nagekeken of die klopt. Een database met gebruikers wordt nooit overschreven; na het terugzetten is de installer vergrendeld en wordt de database bij het eerste bezoek bijgewerkt.
- Een nieuwe `config.php` van de installer staat op de server nu meteen op `debug => false` (enkel lokaal `true`).
- Tests: nieuwe suite `v27_terugzetten` (15 controles), 628 in totaal; ook getest met FrankenPHP (Railpack) en `HZS_CONFIG`.

## Op main

### v0.26.0 – 2026-11-17 – `ceda951` – draaien op Coolify met Railpack, zoekveld menubalk

#### v0.26 – Draaien op Coolify met Railpack – commit `316f0d4`

- **Railpack (FrankenPHP):** `composer.json` (PHP 8.4, geen pakketten) zodat Railpack de app als PHP herkent, en een eigen `Caddyfile` met **enkel `public/` als webmap**. De standaard van Railpack levert de hele projectmap uit, inclusief `storage/` met de database; die gaf ook de 404 omdat er in de hoofdmap geen `index.php` staat. Verborgen bestanden worden geweigerd, CSS/JS krijgen een jaar cache.
- **`config.php` op het volume:** met de omgevingsvariabele `HZS_CONFIG` (bv. `/app/storage/config.php`) schrijft de installer `config.php` op de blijvende opslag in plaats van in de app-map, die bij elke deploy leeg is. Zonder `HZS_CONFIG` blijft alles zoals vroeger (`config.php` in de projectmap; anders `storage/config.php`).
- README: nieuwe sectie *Op Coolify (Railpack)* met de instellingen per app (volume `/app/storage`, `HZS_CONFIG`, `http://`-domein achter de Cloudflare Tunnel, auto deploy).
- CI draait nu op PHP 8.4 (zoals Railpack). Tests: nieuwe suite `v26_deploy` (10 controles), 613 in totaal. Lokaal ook getest met FrankenPHP: installatie, aanmelden en alle hoofdpagina's werken; database, `config.php`, `README.md` en verborgen bestanden geven 404.

#### Menubalk: zoekveld even breed als de geselecteerde pagina – commit `a328518`

- Het zoekveld in de menubalk links heeft nu dezelfde breedte, inspringing en afronding als de witte markering van de geselecteerde pagina.

### v0.25.0 – 2026-11-17 – `3f03beb` – vinkje "Ik ben geen robot" in plaats van tweestapsverificatie

**Let op bij de update:** de tweestapsverificatie verdwijnt voor iedereen (de geheimen worden uit de database gewist); bij het aanmelden staat voortaan het vinkje *Ik ben geen robot*. Neem eerst een back-up van de database.

#### Vinkje "Ik ben geen robot" in plaats van de rekenvraag – commit `07d6f63`

- Bij *Aanmelden* en *Wachtwoord vergeten* staat nu een vinkje **Ik ben geen robot** in plaats van de rekenvraag. Een klik en de browser controleert (meestal minder dan een seconde, met een draaiend icoontje en daarna een groen vinkje). Vergeet je het vinkje, dan gebeurt de controle vanzelf bij *Aanmelden*.
- Achter de schermen lost de browser een kleine rekenopgave op die per poging verandert (proof of work, zoals ALTCHA): voor een mens onzichtbaar, voor een bot die duizenden pogingen wil doen duur. Nog altijd zonder externe dienst, cookies van derden of sleutels, en werkt ook zonder HTTPS. Zonder JavaScript verschijnt de rekenvraag als reserve.
- Tests: `v25_captcha` herschreven (25 controles); in een echte browser nagekeken (vinkje 0,4 s, aanmelden zonder eerst aan te vinken werkt ook).

#### v0.25 – Controlevraag in plaats van tweestapsverificatie – commit `601a62c`

- **Tweestapsverificatie is weg:** niemand moet nog een code uit een authenticator-app invullen, ook beheerders en bestuur niet. Het scherm in *Mijn account*, de knop op de gebruikersfiche en de melding op het overzicht zijn verdwenen. De bewaarde 2FA-geheimen worden bij de update uit de database gewist (migratie 017).
- **Controlevraag (captcha)** bij *Aanmelden* en *Wachtwoord vergeten*: een eenvoudige rekenvraag zoals *Hoeveel is drie plus 5?* (antwoord in cijfers). Daarnaast een verborgen veld dat enkel bots invullen en een minimumtijd van 2 seconden. Zonder externe dienst (geen Google of Cloudflare, geen cookies van derden, geen sleutels nodig). Een fout antwoord telt niet als fout wachtwoord; de limiet van 5 foute wachtwoorden per 15 minuten blijft.
- Instelling `security.require_2fa_admin` in `config.php` is niet meer nodig (mag blijven staan, wordt genegeerd).
- Tests: nieuwe suite `v25_captcha` (23 controles); aanmelden in de tests beantwoordt de controlevraag.

### v0.24.0 – 2026-11-17 – `bf8a873` – back-up, versleuteling, sessies, wachtwoordregels, migraties en CI

#### v0.24 – Onderhoud, back-up en beveiliging (review senior developer, ronde 2) – commit `ed50524`

- **Niet meer "willekeurig" afgemeld:** sessies staan in een eigen map (`storage/sessions`) in plaats van de gedeelde `/tmp` van de hosting, waar PHP ze al na 24 minuten kon opruimen.
- **Dagelijkse back-up:** de app maakt zelf elke dag een back-up (na een bezoek, geen cron nodig), bewaart ze 14 dagen naast het databasebestand en toont beheerders een rode melding als de laatste ouder is dan 2 dagen. Ook via `php tools/backup.php`. Terugzetten: zie README.
- **Versleuteling:** medische opmerkingen en 2FA-geheimen staan versleuteld in de database. Bestaande gegevens worden bij de update versleuteld. De sleutel komt bij een bestaande installatie in `storage/app.key`; beheerders krijgen de raad hem naar `config.php` te verplaatsen en **apart te bewaren** (zonder sleutel zijn die gegevens onleesbaar). Wie medische info bekijkt, komt in de auditlog.
- **Wachtwoord vergeten:** hooguit 3 mails per uur per account en 10 aanvragen per uur per toestel; de responstijd verraadt niet meer of een adres een account heeft.
- **Wachtwoordregels:** geen veelgebruikte wachtwoorden, reeksen of iets dat op je naam/e-mail lijkt (nog steeds minstens 10 tekens). Oudere wachtwoordhashes worden bij het aanmelden bijgewerkt.
- **Links vervallen:** een deellink weekplanning is 365 dagen geldig (in de instellingen staat tot wanneer); een iCal-link vervalt na 180 dagen zonder gebruik.
- **Sneller op mobiel:** CSS en JavaScript blijven een jaar in de cache (nieuwe versie na een update toch meteen), en pagina's worden gecomprimeerd.
- **Onderhoud:** genummerde migraties (`db/migrations/`), oude kolommen van verwijderde onderdelen opgeruimd, een test die bewaakt dat bij definitief verwijderen geen enkele tabel vergeten wordt, en `tools/legacy-data.php` om oude tabellen van verwijderde onderdelen te exporteren en te wissen (beslissing van het bestuur).
- **CI:** GitHub controleert bij elke push naar `dev` en `main` de syntax, voert PHPStan (level 5) uit en draait alle e2e-tests.
- Tests: nieuwe suite `v24_onderhoud` (57 controles), 583 in totaal.

### v0.22.0 – 2026-11-17 – `d9ae867` – ouders passen gegevens aan, fix voor Apache-hosting en UX-analyse ronde 2

#### v0.23 – UX-analyse ronde 2 – commit `8209323`

- **Ouders en zwemmers, overzicht:** de weekkalender begint vandaag (altijd de komende 7 dagen in beeld, ook midden in de week). In de *Lijst* kan je ook voor latere trainingen meteen afmelden, per kind. *Afmelden* vraagt pas na de tik een reden: *Ziek*, *School*, *Op reis* of zelf typen (optioneel), met *Toch niet* om terug te gaan. De melding zegt nu *Lucas is afgemeld voor ma 23 nov 18:00. De trainer ziet dit bij de training.* (en verwijst niet meer naar de verborgen aanwezigheden).
- **Noodcontact:** zolang het ontbreekt, staat er op het overzicht een herinnering met een link; op de kindpagina staat *Nog niet ingevuld · toevoegen*. Het formulier heeft een duidelijke tussentitel *Noodgegevens* met een korte privacyzin (wie ziet dit en waarvoor).
- **Mijn kinderen:** met één kind opent de tab meteen de pagina van dat kind; met meerdere kinderen een lijst zonder zoekveld of filters. *Status: Actief* staat er enkel nog als het lid inactief is.
- **Kalender:** vandaag gemarkeerd (*Vandaag*, ook als er niets gepland is), voorbije dagen gedimd, op telefoon scrolt de maand bij het openen naar vandaag. De groepsfilter staat er enkel als er meer dan één groep te kiezen is; de uitleg over grijze wedstrijden staat in de legende en enkel als er zo'n wedstrijd is.
- **Activiteitspagina:** *Deelnemers en afmeldingen* is een compacte lijst met bovenaan *4 deelnemers · 1 afgemeld* en de afmeldingen (met reden) eerst. Geen *Afgelasten* meer bij een voorbije activiteit.
- **Consistentie:** een lid verwijderen zit nu ook in een ingeklapte gevarenzone onderaan (zoals bij een activiteit); *Terug* staat er nog maar één keer (ook op de groeps- en uitnodigingspagina); *Annuleren* is in alle formulieren een link naast *Opslaan*; namen staan overal als *voornaam achternaam* (lijsten blijven op achternaam gesorteerd); de groep van een lid wijzig je met de keuzelijst, die meteen opslaat; *+ Ouder of voogd toevoegen* is een link in plaats van een losse kaart.
- Tests: nieuwe suite `v23_ux2` (29 controles); alle suites geslaagd (526 controles).

#### Fix: aanmelden op de hosting gaf "Forbidden" – commit `fdc0184`

- Sinds v0.19 stond er een `.htaccess` met *Require all denied* in de map `app/`. Op Apache-hosting waar de hoofdmap de webroot is, leest Apache die vóór de herschrijfregel naar `public/`, en weigert dus elke pagina onder `/app` – ook het overzicht na het aanmelden. Die `.htaccess` is weg (de hoofd-`.htaccess` stuurt nog altijd alles naar `public/`), en `DirectorySlash Off` voorkomt een omweg van `/app` naar `/app/`. Nagekeken op een echte Apache met `AllowOverride All`: aanmelden en `/app/...` werken, `config.php`, de database en `db/`/`docs/` blijven geweigerd.
- Tests: 2 regressiecontroles in `v19_beveiliging`.

#### v0.22 – Ouders passen de gegevens van hun kind aan – commit `bd37cf8`

- **Ouders** zien op de pagina van hun kind nu ook e-mail, telefoon, adres en noodgegevens, en kunnen die via **Gegevens wijzigen** zelf aanpassen: naam, geboortedatum, e-mail, telefoon, adres, noodcontact en medische opmerking. Groep en status blijven bij het bestuur. Ook bereikbaar via *Gegevens* op de kaart *Volgende training* op het overzicht.
- **Volwassen leden** met een eigen account kunnen op dezelfde manier hun eigen gegevens aanpassen.
- Beveiliging: enkel voor het eigen kind (of jezelf); een geboortedatum waardoor een kind 18+ wordt, kan enkel het bestuur invullen; elke wijziging staat in het auditlog met de gewijzigde velden.
- Tests: nieuwe suite `v22_ouders` (20 controles).

### v0.21.0 – 2026-11-17 – `75499fb` – Docker verwijderd

#### Docker verwijderd – commit `7b3850c`

- **Dockerfile** en **`.dockerignore`** weer verwijderd, net als de sectie *Docker* in de README.

### v0.20.0 – 2026-11-17 – `3fdbe0b` – Docker

#### Docker – commit `b853f83`

- **Dockerfile** (php 8.3 + Apache, webmap `public/`, `.htaccess` aan, HTTPS achter een proxy) en **`.dockerignore`** (geen `config.php`, database, mails of `.git` in de image). Uitleg in de README onder *Docker*.

### v0.19.0 – 2026-11-16 – `bc63da9` – minimale competitie-app met trainersplanning, nieuwe kalenderweergaven, UX en beveiliging

Samenvatting: de app is teruggebracht tot een minimale competitie-app (geen website, lidgeld, inschrijvingen, zwemtest of zwemschool meer; aanwezigheden verborgen) met een volledige **trainersplanning** (groepstrainers als vaste trainers, afmelden met vervanger, niet op training en wedstrijd tegelijk, mails bij tekorten, meerdere tegelijk), **weekkalender** als standaard, overzicht per rol, definitief verwijderen van leden, gebruikers, groepen en activiteiten, en een beveiligingsronde. **Let op bij de update:** neem eerst een back-up van de database; bestaande zwemschoolgroepen en de gegevens van verwijderde modules worden bij de eerste aanvraag opgeruimd; iedereen moet één keer opnieuw aanmelden; beheerders en bestuur moeten tweestapsverificatie instellen.

Details (nieuwste eerst):

#### Bladeren zonder naar boven te springen – commit `d830b73`

- In de weekkalender (overzicht en trainersplanning) en de maandkalender blijf je bij ‹ ›, *Naar nu* en *Week*/*Lijst* op dezelfde hoogte op de pagina, in plaats van telkens naar boven te springen (vooral op telefoon storend).
- Tests: 2 extra controles in `v18_ux`.

#### v0.19 – Veiliger en sneller (review senior developer) – commit `36da600`

- **Database buiten de webmap:** de installer vraagt waar het SQLite-bestand moet staan (op een server: buiten `www/`). Staat het toch in de webmap, dan ziet een beheerder een rode waarschuwing op het overzicht. Extra `.htaccess`-regels weigeren `.sqlite`, `config.php`, logs en de mappen `app/`, `db/`, `docs/`, `tools/`, ook als `mod_rewrite` ontbreekt. Uitleg in de README (*Database buiten de webmap*).
- **Afmelden op andere toestellen:** na een nieuw wachtwoord (zelf, via *Wachtwoord vergeten* of door de beheerder) of het uitzetten/resetten van 2FA zijn alle andere sessies van die gebruiker meteen ongeldig; je huidige sessie blijft. Een sessie verloopt nu ook ten laatste 7 dagen na het aanmelden (naast 12 uur zonder activiteit).
- **Tweestapsverificatie verplicht voor beheerders én bestuur** op de server (`security.require_2fa_admin`; niet ingesteld = verplicht, behalve lokaal op localhost).
- **Secure cookie** automatisch over HTTPS (ook achter de proxy van de hosting).
- **Sneller:** 17 indexen op de veelgebruikte kolommen (kalender, scope, aanmelden, tokens); SQLite in WAL-modus; de schemaversie staat nu in de database (`app_meta`), zodat de upgrades en de controle van rollen/rechten niet meer bij elk verzoek zonder sessie (iCal, deellink, nieuwe bezoekers) lopen; de maandkalender doet geen extra query meer per wedstrijd.
- Na deze update moet iedereen één keer opnieuw aanmelden.
- Tests: nieuwe suite `v19_beveiliging` (33 controles); migratietests simuleren een oude database via `T::oldDatabase()` (474 in totaal).

#### Overzicht: weekkalender ook standaard voor ouders en zwemmers – commit `cb74c97`

- Ook ouders en zwemmers zien de activiteiten op het overzicht nu standaard als weekkalender (onder de kaart *Volgende training*). De lijst blijft één klik verder.
- Test `v18_ux` hangt niet meer af van de weekdag waarop hij loopt.

#### v0.18 – UX-advies front-end doorgevoerd – commit `8fd9f7f`

- **Overzicht per rol.** Ouders en zwemmers: bovenaan *Volgende training* per kind met meteen *Afmelden* / *Toch aanwezig*, daaronder de komende activiteiten als lijst (standaard). Trainers en bestuur: weekkalender en *Waar is actie nodig* (tekorten, afmeldingen van zwemmers, niet-geactiveerde uitnodigingen, minderjarigen zonder ouder) in plaats van de tegels met aantallen.
- **Weekkalender kijkt vooruit:** op zaterdag en zondag toont hij de volgende week. Compacte navigatie ‹ 16–22 nov › met *Naar nu*; dagkoppen als "ma 16"; datums zonder jaartal als het dit jaar is.
- **Eén tekortsignaal:** menubadge, overzicht en trainersplanning tonen hetzelfde aantal over dezelfde periode (14 dagen). De misleidende groene melding is weg: zonder tekorten staat er *✓ Geen tekorten* met het volgende tekort verder weg. In de kalenders behoudt een activiteit met tekort haar groepskleur en krijgt ze een rode rand en `!`-badge. Ouders zien geen tekorten.
- **Minder uitleg en knoppen:** trainersplanning met uitleg achter *ⓘ Hoe werkt dit?* en acties achter ⋯; kalender met *+ Nieuw* en ⋯ (afgelasten/verplaatsen, reeksen). Het × per dag in de kalender is weg.
- **Activiteitspagina:** *Training afgelasten* als gewone knop, *Verwijderen* onderaan in een ingeklapte gevarenzone. De trainerskaart herhaalt datum, uur, groep en locatie niet meer; *Terug* staat er nog één keer.
- **Kleur:** wedstrijden herkenbaar aan 🏆 in plaats van een gele rand; legende = groepskleuren + één tekenmarkering. Grijze tekst donkerder (beter contrast).
- **Mobiel:** vaste tabbalk onderaan (Overzicht, Kalender, Planning of Leden, Menu, met teller); compacte ledenlijst (naam, groep, ouder; status enkel als die afwijkt); filters passen meteen toe en staan naast elkaar; geen schaduwrand meer links.
- Tests: nieuwe suite `v18_ux` (28 controles), bestaande suites aangepast (441 in totaal).

#### Clubkampioenschap terug in de trainersplanning – commit `0600c9a`

- Wedstrijden voor de hele club (zoals het clubkampioenschap) staan weer in de trainersplanning, met de vinkjes om meerdere trainers in te plannen. Enkel **clubactiviteiten** (samenkomst, pastadag, teambuilding, …) vragen geen trainers; die stonden er al niet in. Dit draait het tweede punt van de vorige versie terug.

#### Meerdere trainers per wedstrijd, geen trainers voor clubactiviteiten – commit `57da167`

- **Wedstrijd:** *Trainers toevoegen* toont nu een lijst met vinkjes; vink iedereen aan die meegaat en klik *Inplannen*. Wie al ingepland is of op dat moment al op een andere wedstrijd staat, wordt overgeslagen met een melding. De knop voor jezelf heet bij een wedstrijd *Ik ga mee*.
- **Activiteiten voor de hele club** (zoals het clubkampioenschap) staan niet meer in de trainersplanning en tellen niet meer als tekort (geen rode markering, geen mails). Clubactiviteiten stonden er al niet in.
- Tests: 8 extra controles (413 in totaal).

#### Kalender: filter op soort activiteit – commit `25ceabf`

- In de kalender kan je naast de groep nu ook filteren op **soort**: Training, Wedstrijd of Clubactiviteit. De filter blijft behouden bij *Vorige*/*Volgende*; *Wis filter* zet alles terug.
- Tests: 5 extra controles in `smoke` (405 in totaal).

#### Activiteiten verwijderen – commit `14d1e46`

- **Nieuwe rode knop *Verwijderen*** op de activiteitspagina (naast *Schrappen*), voor wie activiteiten mag beheren. Na bevestiging is de activiteit definitief weg, ook als er al aanwezigheden geregistreerd waren; afmeldingen en trainersplanning ervan worden mee gewist. Een training uit een reeks komt niet terug bij het bijwerken van de reeks. *Schrappen* werkt zoals voorheen.
- Knoppen op de activiteitspagina netjes onder de gegevens; groepsblokjes buiten de kalenderlegende weer vol gekleurd.
- Tests: 8 extra controles in `v15_verwijderen` (400 in totaal).

#### Kalender: legende in dezelfde kleuren – commit `988daad`

- De legende onder de maandkalender gebruikt nu dezelfde lichte tint met gekleurde rand als de activiteiten (en hetzelfde geel voor wedstrijden).

#### Kalender: lichtere groepskleuren – commit `21df560`

- In de maandkalender zijn de activiteiten nu licht getint in de groepskleur met een gekleurde rand links en donkere tekst, in plaats van een vol gekleurd vlak. Tekorten (rood met `!`) springen zo duidelijk in het oog. Wedstrijden houden hun gele rand; activiteiten van andere groepen en geschrapte activiteiten zijn lichtgrijs.

#### Tekorten beter zichtbaar – commit `50a725d`

- **Kalender (maand):** een training of wedstrijd zonder genoeg trainers is nu een rood vlak met `!` in plaats van enkel een rode rand.
- **Weekkalenders en lijst:** volledig rood kader, rode achtergrond en een rood label *Geen trainer* / *Trainer gezocht*.
- **Overzicht:** de weekkalender toont tekorten nu ook (voor trainers en coördinator); klik opent de trainerskaart.
- Tests: 1 extra controle (392 in totaal).

#### Overzicht: kalender bovenaan – commit `f93ec13`

- Op het overzicht staat het blok met de activiteiten (weekkalender of lijst) nu bovenaan; *Trainer gezocht* en *Jij geeft training* komen eronder.

#### Weekkalender is de standaardweergave – commit `e4c3106`

- Het overzicht en de trainersplanning openen standaard als **weekkalender**; de lijst blijft één klik verder. Wie de lijst kiest, houdt die zolang hij aangemeld is. De knop *Weekkalender* staat nu eerst.
- Tests: 2 extra controles (391 in totaal).

#### Eigen trainingen gemarkeerd – commit `72db314`

- **De trainingen van de aangemelde trainer vallen op** met een blauwe rand, lichtblauwe achtergrond en het label *Jij geeft training* (bij een wedstrijd *Jij gaat mee*; in de weekkalender kort *Jij*). Dit geldt in de trainersplanning (lijst en weekkalender) en op het overzicht (lijst en weekkalender). Wie afgemeld is of op wedstrijd staat, wordt niet gemarkeerd.
- Test voor de automatische herinnering hangt niet meer af van de weekdag waarop hij loopt.
- Tests: 4 extra controles (389 in totaal).

#### Overzicht als weekkalender – commit `e54ede9`

- **Op het overzicht (startpagina) kan je de activiteiten nu als Lijst of als Weekkalender bekijken**, net als in de trainersplanning. De weekkalender toont maandag tot zondag met uur, soort, groep en locatie; bladeren met *Vorige* en *Volgende*; de keuze wordt onthouden. Werkt voor iedereen, ook ouders (enkel hun eigen groepen).
- Weeknavigatie op telefoon compacter (ook in de trainersplanning).
- Tests: 4 extra controles (385 in totaal).

#### Trainersplanning als weekkalender – commit `f3e7bc5`

- **Wisselen tussen Lijst en Weekkalender** (knoppen rechtsboven in de trainersplanning; de app onthoudt je keuze). De weekkalender toont maandag tot zondag naast elkaar, met per training of wedstrijd het uur, *In orde* / *Trainer gezocht* en de trainers (afgemeld of op wedstrijd doorstreept). Bladeren met *Vorige* en *Volgende*.
- Klik op een training in de weekkalender om je af te melden, over te nemen of iemand in te plannen. Op telefoon staan de dagen onder elkaar.
- Tests: 6 extra controles (381 in totaal).

#### Groepenoverzicht toont de vaste trainers – commit `57d58aa`

- Elke groepskaart in *Groepen* toont de vaste trainer(s) van de groep (de groepstrainers die standaard training geven), of *Geen trainer* als er nog niemand is.
- Tests: 1 extra controle in `v12_trainersplanning` (375 in totaal).

#### Vaste trainers = groepstrainers, vervanger verplicht, niet op training en wedstrijd tegelijk – commit `75ede6b`

- **De trainers van een groep zijn de vaste trainers** van alle trainingen van die groep (aanpassen via *Groep wijzigen*). De aparte instelling *vaste trainer per blok* is weg; *Vaste trainers en instellingen* toont per groep de trainers en het minimum.
- **Afmelden vraagt een vervanger** als er anders een tekort is: kies wie overneemt, of *Nog geen vervanger – trainer gezocht* (dan gaat er meteen een mail naar de collega's). Ook bij *Meerdere tegelijk*.
- **Niet op training en wedstrijd tegelijk:** wie op een wedstrijd staat, staat op trainingen op hetzelfde moment automatisch *Op wedstrijd*. Inplannen op een training tijdens je wedstrijd of op twee wedstrijden tegelijk wordt geweigerd.
- De coördinator ziet nu alle groepen in kalender en activiteiten.
- Tests: `v12_trainersplanning` herschreven (67 controles, 374 in totaal).

#### Meerdere tegelijk: duidelijkere werkwijze – commit `7b6eacd`

- **Bulk toewijzen herwerkt** tot een aparte pagina *Meerdere trainingen tegelijk* (knop bovenaan de trainersplanning) met 3 stappen: 1) trainer en actie (inplannen of afmelden), 2) groep, dag en periode, 3) een lijst met wat er verandert, vooraf aangevinkt, met één knop "X inplannen voor N trainingen". Trainingen waar niets verandert staan grijs met de reden.
- De vinkjes per kaart en het bulkvak in de gewone trainersplanning zijn weg. Werkt ook op telefoon.

#### Reeksen importeren weer verwijderd – commit `79b3156`

- **Reeksen importeren** (commit `fa9b2f8`) is teruggedraaid: geen knop *Importeren* meer bij Trainingsreeksen, `SeriesImport` en de testsuite `v16_import` zijn weg. Reeksen maak je zoals voorheen via *+ Nieuwe reeks*.

#### Leden en gebruikers definitief verwijderen – commit `431ef98`

- **Lid verwijderen** (knop op de ledenpagina, bestuur en beheerder): het lid verdwijnt met zijn groep, ouderkoppelingen, aanwezigheden en afmeldingen, en ook uit oude tabellen (lidgeld, resultaten, ...) als die nog bestaan. Een gekoppeld account blijft bestaan zonder lid.
- **Gebruiker verwijderen** (onderaan *Gebruiker wijzigen*, enkel beheerder): rollen, koppelingen met kinderen en groepen en de plaats in de trainersplanning verdwijnen; leden blijven. Niet voor je eigen account of de laatste beheerder; waarschuwing als een minderjarige zo geen ouder meer heeft.
- Groepen konden al verwijderd worden. Alle verwijderingen staan in het auditlog. Inactief zetten blijft mogelijk voor wie tijdelijk weg is.
- Tests: nieuwe suite `v15_verwijderen` (32 controles, 410 in totaal).

#### Aanwezigheden voorlopig verborgen – commit `17d3120`

- **Aanwezigheden verborgen** achter een schakelaar (`'features' => ['attendance' => false]` in `config.php`, standaard uit): geen menu-item *Aanwezigheden*, geen aanwezigheidsrapport (404), geen presentielijst bij een activiteit, geen blok *Aanwezigheid nog in te vullen*. Code en bestaande gegevens blijven; met `true` komt alles terug.
- Bij een activiteit staat nu *Deelnemers en afmeldingen*: ouders en leden kunnen zich nog altijd afmelden, trainers en bestuur zien wie afgemeld is.
- Tests: testserver zonder opcache (instellingen wijzigen tijdens een test); 378 controles.

#### Wordt v0.14.0 – zwemschool uit de app – commit `e94820e`

- **Zwemschool volledig uit de app:** geen keuze meer tussen zwemschool en competitie (alle groepen zijn competitiegroepen), geen filter daarop in leden, kalender, reeksen en aanwezigheden; zwemblokken weg (indeling per lid, lijst *Nog niet ingedeeld*, *Weekoverzicht zwemschool*, *Trainingsmoment per lid*). Alle leden van een groep staan op de lijsten van hun groep.
- **Update (schema 14) verwijdert bestaande zwemschoolgroepen** zoals *Groep verwijderen*: leden blijven bestaan zonder groep; reeksen en activiteiten enkel voor die groepen verdwijnen met hun aanwezigheden en afmeldingen; de blokindeling (`member_series`) wordt gewist. Dit is onomkeerbaar: maak eerst een back-up van de database.
- Groep verwijderen ruimt nu ook afmeldingen van leden bij de verwijderde activiteiten op (voorkomt een fout door de databasekoppeling).
- Demodata: groepen Miniemen, Kadetten en Wedstrijdploeg (allemaal competitie). Tests: 376 controles (o.a. de verwijdering van een bestaande zwemschoolgroep zonder ledenverlies).

#### Trainersplanning: trainers in bulk toewijzen – commit `c34e358`

- **Meerdere tegelijk** in de trainersplanning: vinkje per training/wedstrijd, snel aanvinken (*Alle tekorten*, *Alles*, *Niets*, alle trainingen van een blok) en in één keer een trainer **inplannen** of **afmelden** met reden (bv. vakantie). Overgeslagen gevallen worden geteld in de melding; ontstaat er een tekort binnen 3 dagen, dan vertrekt meteen de mail.
- Ongebruikte JavaScript van het oude inschrijfformulier verwijderd. Tests: 14 extra controles (364 in totaal).

#### Wordt v0.13.0 – minimale app (enkel ledenapp met login) – commit `1aebc7c`

- **App teruggebracht tot de kern:** kalender voor leden en ouders, leden, groepen, gebruikers, trainingsreeksen en zwemblokken, trainersplanning, aanwezigheden, bericht sturen, account (2FA, iCal), auditlog en zoeken.
- **Verwijderd:** publieke website (pagina's, nieuws, publieke kalender, trainingsuren, contact, SEO/sitemap), websitebeheer, statistieken, lidgeld en seizoenen, inschrijvingen, zwemtestwachtlijst, contactberichten, nieuwsbrief, fotogalerij, online inschrijven voor activiteiten, wedstrijddeelname en -resultaten, capaciteit en wachtlijst per groep/blok, CSV-exports. Velden *Doelstellingen*, *Lidgeld*, *Capaciteit*, *Doorstroomgroep*, *Op website tonen* en *Foto-toestemming* zijn uit de formulieren.
- `/` stuurt naar *Aanmelden*; oude publieke adressen geven 404. Aanmeldpagina zonder link naar inschrijven. De deellink weekplanning en foutpagina's gebruiken de layout van de app (`layout_public.php`, `site.css` en de lettertypen zijn weg).
- **Database (schema 13):** nieuwe installaties maken de tabellen van de verwijderde onderdelen niet meer aan. Bestaande databases houden die tabellen en hun gegevens (niets wordt gewist); rechten van verwijderde onderdelen worden opgeruimd. Migraties lopen nu één keer per sessie.
- Config: `stats`, `registration`, `fees` en `contact` zijn niet meer nodig; `privacy_url` toont de link naar het privacybeleid op de clubwebsite onderaan elke pagina.
- Tests: suite `smoke` uitgebreid (verwijderde adressen, menu, formulieren, schema, update zonder gegevensverlies); 350 controles. README en analyse herschreven.

#### Wordt v0.12.0 – trainersplanning competitie – commit `c16f215`

- **Trainersplanning competitie** (`/app/trainersplanning`, menu *Planning*): per week alle trainingen en wedstrijden van de competitie met hun trainers en een status *In orde* / *Trainer gezocht*; tabbladen *Alles*, *Enkel tekorten*, *Mijn planning*. Zwemschool valt erbuiten.
- **Vaste trainer per blok** (reeks + weekdag) en **minimum aantal trainers** per competitiegroep (*Vaste trainers en instellingen*); per wedstrijd/activiteit een eigen aantal (*Aantal trainers nodig*).
- **Afmelden, overnemen en inplannen** per training of wedstrijd (met reden). Elke competitietrainer kan dit, ook voor een collega, zodat iedereen de coördinatie kan overnemen. Vaste trainers afmelden, extra trainers weghalen.
- **Mail vooraf bij een tekort:** binnen 3 dagen (`coverage.remind_days`) zonder genoeg trainers → één mail naar coördinator(en) en competitietrainers, elk tekort één keer (opnieuw als het groter wordt of terugkomt). Automatisch hooguit één keer per uur bij een bezoek (geen cron nodig), of via `tools/coverage-reminders.php`; knop *Nu mail sturen*. Na een afmelding binnen het venster vertrekt de mail meteen.
- **Zichtbaarheid:** rode rand en `!` in de kalender, blokken *Trainer gezocht* en *Jij geeft training* op het overzicht, kaart *Trainers* op de activiteitspagina, teller in het menu. Ouders en zwemmers zien geen trainersnamen.
- **Weekplanning delen met ouders:** geheime, intrekbare link per competitiegroep (3 weken trainingen, wedstrijden en clubactiviteiten, ook geschrapte; zonder namen). De kalender zelf blijft privé.
- **Nieuwe rol *Coördinator competitie*** en permissie `coverage.manage` (ook bestuur en beheerder). Demo-accounts `coordinator@demo.local` en `competitie@demo.local`.
- Database (schema 12, automatisch bij de eerste aanvraag): tabellen `series_trainers`, `activity_trainers`, `coverage_alerts`; kolommen `groups.min_trainers`, `groups.share_token`, `activities.min_trainers`. Opruimen bij het verwijderen van activiteiten, reeksen en groepen.
- **Testsuite in de repository:** `php tests/e2e/run.php` (suites `smoke` en `v12_trainersplanning`, 296 controles). README en analyse bijgewerkt.

### v0.11.0 – 2026-10-26 – `f8dd83d` – grote functionele uitbreiding: 20 verbeteringen in 4 blokken
**Blok A – dagelijks werk**
- **Aanwezigheidsrapport** (`/app/aanwezigheidsrapport`) per groep en periode, met lijst *Nog in te vullen* (voorbije trainingen zonder aanwezigheidslijst).
- **Bericht sturen** (`/app/bericht-sturen`) naar een groep, trainingsblok, deelnemers van een activiteit of de hele club; met voorbeeldscherm, maximum 400 ontvangers, een trainer bereikt enkel de eigen groepen; elke verzending staat in `broadcasts` (nieuwe permissie `mail.send`).
- **Afmelden:** ouders en leden melden zich af voor een activiteit (“Kan niet komen”) met reden; trainers zien de afmeldingen en “Afgemeld”-labels op de lijst.
- **Agenda-link (iCal):** persoonlijke, herroepbare link om de kalender in Google/Apple/Outlook te abonneren (`/app/account`).
- **Exporteren** (`/app/exporteren`, `export.data`): leden, aanwezigheden, inschrijvingen, zwemtests, lidgeld en abonnees als CSV (UTF-8 met BOM, `;`, beveiligd tegen formule-injectie). Medische gegevens worden nooit geëxporteerd.
- **Auditlog** (`/app/auditlog`, `audit.read`, enkel beheerders) met filter op actie en gebruiker.
- **Globaal zoeken** (zoekveld in het menu): leden, groepen, activiteiten en gebruikers, binnen de eigen scope.
- **Tweestapsverificatie (TOTP, RFC 6238)** voor elk account via `/app/account`; configuratie-optie `security.require_2fa_admin` verplicht ze voor beheerders. Een beheerder kan 2FA van een gebruiker uitzetten (auditlog `2FA_RESET`).
**Blok B – administratie**
- **Lidgeld:** seizoenen, bedrag per groep, rekeningen genereren, betaalstatus, Belgische gestructureerde mededeling (+++xxx/xxxx/xxxxx+++, modulo 97), herinneringen per mail (`fees.manage`; config `fees.iban`, `fees.beneficiary`).
- **Seizoensovergang:** leden doorschuiven naar de doorstroomgroep van hun groep (voorbeeldscherm vóór toepassen).
- **Capaciteit en wachtlijst** per groep en per trainingsblok, met melding “Er is plaats vrij” en inschrijven vanaf de wachtlijst (mail naar ouder).
- **Wedstrijden:** deelname bevestigen of weigeren door ouders/leden; resultaten per lid en onderdeel (tijd `1:05,32`), persoonlijke records.
- **Noodcontact en medische opmerking** op de ledenfiche (enkel zichtbaar met `members.medical`: bestuur en trainers van de eigen groep), noodgegevens op de deelnemerslijst, redenen bij stopzetting (`status_reason`).
- **Dubbele leden:** waarschuwing bij een lid met dezelfde naam en geboortedatum (bevestigen om toch aan te maken).
- **Zwemtest-opvolging:** herinneringen aan wachtenden (max. 3, 7 dagen tussen), lijst met aanvragen die te lang op vervolg wachten.
**Blok C – dashboards**
- Overzicht per rol: ouder/lid (open lidgeld, wedstrijden zonder antwoord), trainer (aanwezigheid nog in te vullen, afmeldingen), bestuur (inschrijvingen, berichten, zwemtest-opvolging, open lidgeld, groepen met wachtlijst) en melding als een beheerder/bestuurslid nog geen 2FA heeft.
**Blok D – website**
- **Online inschrijven voor activiteiten** (proeflessen, clubevenementen): per activiteit aan te zetten met aantal plaatsen, publiek formulier met bevestigingsmail, robotfilter en limiet per uur, overzicht + CSV voor het bestuur (`/app/activiteit-inschrijvingen`).
- **Nieuwsbrief:** aanmelden onderaan de website met dubbele bevestiging, afmelden met één klik, een nieuwsbericht doorsturen naar alle abonnees (`/app/nieuwsbrief`), abonnees als CSV.
- **SEO:** meta description, canonical, Open Graph, structured data (SportsClub), dynamische `sitemap.xml` en `robots.txt`.
- **Fotogalerij** met toestemmingscontrole (`/fotos`, beheer `/app/fotogalerij`): een foto is enkel publiek als het album gepubliceerd is, de toestemming bevestigd is en alle getagde leden toestemming voor foto's hebben; trekt een lid de toestemming in, dan verdwijnt de foto meteen.
- **Privacybeleid aangevuld** voor de nieuwe verwerkingen (lidgeld, wedstrijdresultaten, afmeldingen, noodcontact/medische opmerking, berichten, activiteitsinschrijvingen, nieuwsbrief, foto's, agenda-link, 2FA) met bewaartermijnen. Bestaande sites krijgen de nieuwe tekst automatisch **enkel als de pagina nog letterlijk de vorige tekst is**; een aangepaste tekst blijft staan (dan de nieuwe passages handmatig overnemen, of `php tools/seed-site.php privacy` om te overschrijven).
- Nieuwe databasetabellen en -kolommen worden bij de eerste aanvraag automatisch aangemaakt (schema 11); bestaande gegevens blijven ongewijzigd.
- Tests: reeksen v11a–v11d (360 controles) en alle oudere reeksen blijven groen.

### v0.10.4 – 2026-10-26 – `29b3a79`
- **Documentatie bijgewerkt:** README en analyse beschrijven nu zwemblokken, weekoverzicht, statistieken, privacybeleid, opmaakregels en de configuratie-opties.
- **Keuzelijsten op de website:** de dropdowns in het contactformulier, de zwemtestaanvraag en de inschrijving hebben nu dezelfde duidelijke stijl als in de app (veld met pijltje), in de kleuren van de website; invulvelden hebben dezelfde duidelijke rand.
- **Opmaak: alle spatie-achtige tekens opgevangen** (ook Braille-blank, Hangul-vulteken en andere Unicode-spaties) rond `##` en `- `, zodat plakwerk altijd tot een kop of opsomming leidt.
- **Koppen nog tolerantere herkenning + diagnose voor beheerders:** `##Kop` zonder spatie, `\## Kop` (door editors "ge-escaped"), `&nbsp;`, regelscheiders van tekstverwerkers en alle onzichtbare opmaaktekens worden opgevangen. Wordt een regel die op een kop lijkt toch niet herkend, dan toont de publieke pagina of het bericht (enkel aan beheerders) een waarschuwing met de exacte tekens van die regel (bv. `[U+2063]## Kop`).
- **Versie zichtbaar voor beheerders:** onderaan het menu staat klein de branch en commit die draait (bv. `dev cc837b1`), zodat je meteen ziet of een update binnen is. Enkel voor beheerders en enkel als de map een git-kopie is.
- **Statistieken: genegeerde bezoeken en fouten zichtbaar:** het dashboard toont nu per periode hoeveel bezoeken *niet* meegeteld werden en waarom (*Do Not Track*/*Global Privacy Control* — privévensters van Firefox en Brave sturen dat standaard mee —, bots, beheerders/bestuur op de website). Dit zijn anonieme tellers zonder bezoekerscode of pagina. Lukt het wegschrijven niet, dan toont het dashboard de laatste fout (ook in `storage/stats-error.log`). De controle op het antwoord (enkel 200 telt) is robuuster voor servers die geen statuscode teruggeven.
- **Koppen (`## Kop`) en lijsten werken ook na plakken uit chats of andere programma's:** onzichtbare tekens (vaste spatie, zero-width spatie, BOM) en "mooie" varianten (`＃＃`, `•`) tussen of vóór `##`/`-` worden bij het tonen opgeschoond, ook voor de introtekst bovenaan een pagina. Eerder bleef `## Kop` dan letterlijk staan.
- **Statistieken: uitleg bij het testen:** het dashboard toont nu waarom je eigen bezoek niet meetelt (ingelogd als beheerder/bestuur op de website, *Do Not Track*/*Global Privacy Control* in je browser, bot) en wanneer de laatste meting was. Nieuwe instelling `'stats' => ['count_staff' => true]` in `config.php` telt bezoek van beheerders/bestuur wel mee (handig om te testen). Fouten van de teller komen in het serverlog.
- **Opmaak van websiteteksten tolerant bij plakken:** een regel die met `##` of `###` begint wordt altijd een kop en een opsomming (`- `) start ook rechtstreeks na een gewone regel, ook als er bij het plakken (bv. uit een ander programma) lege regels verloren gingen. Eerder verscheen `##` dan letterlijk in de tekst.
- **Privacybeleid: vervanging robuuster:** de oude tekst van 2018 wordt nu herkend aan de datum (*24 mei 2018*), ook als de editor de opmaak lichtjes heeft aangepast. Nieuwe opdracht `php tools/seed-site.php privacy` forceert de nieuwe tekst (maakt de pagina aan als ze ontbreekt).
- **Privacybeleid herschreven (AVG):** nieuwe tekst op de pagina *Privacybeleid* (Club), afgestemd op wat de website en de Clubapp echt doen: verwerkingen met doel en rechtsgrond (statistieken, zwemtest, inschrijving, ledenadministratie, accounts, contactformulier, beveiliging), cookies (enkel de technische sessiecookie `hzs_session`, geen banner nodig), met wie gegevens gedeeld worden, concrete bewaartermijnen (7 dagen onbevestigde aanvragen, 24 uur mislukte aanmeldingen, 400 dagen statistieken, uitnodigingslink 7 dagen, wachtwoordlink 60 minuten), beveiliging, kinderen (13 jaar), rechten, klacht bij de Gegevensbeschermingsautoriteit en wijzigingen. Bestaande sites krijgen de nieuwe tekst automatisch **zolang de oude overgenomen tekst van 2018 nog onaangepast is**; een door de club aangepaste tekst blijft staan. De link *Privacy* staat nu altijd in de footer van de website en naast de formulieren (inschrijven, zwemtest, contact), ook zonder instelling `privacy_url` in `config.php` (die blijft voorrang hebben).
- **Statistieken (enkel beheerders):** nieuw menu-item *Statistieken* (`/app/statistieken`, nieuwe permissie `stats.read`, enkel de rol Beheerder; bestuur, trainers en ouders krijgen een 403). Toont per periode (7/30/90 dagen, 1 jaar) voor de **website** en de **app** apart: bezoeken, paginaweergaves, gemiddelde per dag, bezoekers vandaag, staafdiagram per dag, meest bezochte pagina's/schermen, apparaten (computer/gsm/tablet) en bij de website de herkomst (zoekmachine, sociale media, rechtstreeks) plus een tabel per dag. **Privacy:** geen cookies, geen IP-adres, geen namen of gebruikers-id bewaard; een bezoeker is een dagelijks wisselende hash (daarom enkel unieke bezoekers per dag). Niet geteld: bots, POST-aanvragen, mislukte aanvragen, bezoekers met *Do Not Track*/*Global Privacy Control*, beheerders/bestuur op de website en het dashboard zelf. Gegevens ouder dan 400 dagen worden automatisch gewist. Nieuwe tabel `page_views` (wordt automatisch aangemaakt) en bestand `storage/stats.salt`.
### v0.10.3 – 2026-10-26 – `ab99feb`
- **Website: balk onder het menu en boven de footer** is geen blauw-geel baantouw meer maar een golvend water in drie blauwtinten (donker, midden, licht), zonder geel.
- **Kalender:** knop *Weekoverzicht zwemschool* naast *Trainingsreeksen* (voor wie activiteiten mag beheren).
- **Uniforme keuzelijsten en knoppen:** overal (ledenpagina's, filters, formulieren, publieke zwemtestaanvraag) hebben keuzelijsten nu het duidelijke veld met pijltje, invulvelden dezelfde duidelijke rand, en knoppen een stevigere vorm (vet, ruimere padding, duidelijke rand bij de lichte knoppen). Op smalle schermen zijn de kleine knoppen groter om aan te raken.
- **Duidelijkere indeling:** in *Nog niet ingedeeld* heeft de blokkeuze een duidelijk keuzeveld met pijltje en een volwaardige knop *Indelen* ernaast (op smalle schermen onder elkaar, volle breedte).
- **Nog niet ingedeeld:** de blokkeuze toont enkel dag en uur (bv. *Ma 18:00–19:00*), zonder reeksnaam.
- **Weekoverzicht zwemschool:** nieuwe pagina (knop op *Trainingsreeksen*): dagen als kolommen, uurblokken als rijen, per blok de groepen (met kleur) en het aantal ingedeelde leden (link naar de blokpagina), plus een melding hoeveel leden nog niet ingedeeld zijn.
- **Trainingsmoment zonder reeksnaam:** op de ledenpagina en in het rooster op de groepspagina staat enkel nog dag en uur (bv. *Ma 18:00–19:00*), niet meer de naam van de reeks.
- **Zwemblokken (zwemschool):** een zwemschoolreeks (dag + uur) is een *blok*. Op de blokpagina staat de sectie **Leden in dit blok** (leden toevoegen uit de toegelaten groepen, of uit het blok halen); op *Trainingsreeksen* staat de lijst **Nog niet ingedeeld** met een keuzelijst per lid. Een lid mag in meerdere blokken, leden van verschillende groepen mogen samen in één blok, geen capaciteitslimiet. **Nieuwe regel:** een zwemschoollid zonder blok staat op geen enkele aanwezigheidslijst en ziet (ouder/zwemmer) geen trainingen van die groep tot het is ingedeeld; competitie blijft ongewijzigd (vast schema per groep). Verandert een lid van groep (of wordt het uit de groep gehaald/verplaatst), dan vervalt zijn indeling en komt hij terug in *Nog niet ingedeeld*. Het rooster op de groepspagina verschijnt enkel voor zwemschoolgroepen. Bestaande zwemleden zonder indeling verdwijnen dus van de lijsten tot ze ingedeeld zijn.
- **Trainingsmoment per lid = per reeks:** het rooster op de groepspagina heeft één kolom per reeks (een reeks is één trainingsmoment: dag + uur, bv. *ma 18:00–19:00*) in plaats van per weekdag binnen een reeks. Maak dus één reeks per trainingsmoment en vink per lid aan welke reeks(en) hij volgt. De ledenpagina toont de gekozen reeks(en) met hun dag en uur.
### v0.10.2 – 2026-10-25 – `6020d3b`
- **Trainingsmoment per lid:** *Trainingsdag per lid* heet nu *Trainingsmoment per lid*. Een trainingsmoment is een dag mét uur (bv. *Dinsdag 17:00–18:00*); de kolommen staan op dag en uur gesorteerd, met de naam van de reeks eronder. Op de ledenpagina staat *Trainingsmoment: dinsdag 17:00–18:00 (Duo)*.
- **Terugknop opnieuw nagekeken:** *Terug* gaat altijd naar de bovenliggende pagina én onthoudt waar je was: terug naar *Leden* behoudt je zoekterm en filters, terug naar de kalender opent de maand van de activiteit (niet de huidige maand), en ook *Schrappen / verplaatsen* en *Uitnodigen* hadden geen eigen bovenliggende pagina meer (gingen naar het overzicht). Op de hoofdpagina's uit het menu (Leden, Groepen, Kalender, …) staat geen *Terug* meer: daar is het menu.
### v0.10.1 – 2026-10-25 – `bd58daf`
- **Trainingsreeksen: zwemschool en competitie apart:** op het overzicht staan de reeksen onder een kleurbanner *Zwemschool* (blauw) en *Competitie* (oranje). Bij de competitie is de verdeling per groep; bij de zwemschool per weekdag (maandag tot zondag, op uur gesorteerd), met op de kaart de betrokken groepen. Een reeks met meerdere weekdagen staat onder elke dag waarop ze loopt.
- **Trainingsdag per lid: nu per weekdag en altijd zichtbaar:** het blok *Trainingsdag per lid* staat altijd op de groepspagina (voor wie reeksen mag beheren) en toont per kolom elk trainingsmoment: dus ook de verschillende weekdagen binnen één reeks (bv. Zwemmen – dinsdag en Zwemmen – donderdag), niet enkel losse reeksen. Heeft de groep minder dan twee trainingsmomenten, dan staat er een uitleg in plaats van het rooster. Eerder opgeslagen keuzes uit v0.10.0 worden bij de update gewist (de functie was nog nergens te vinden); opnieuw invullen.
### v0.10.0 – 2026-10-25 – `bf61178`
- **Trainingsdag per lid (zwemschool):** een groep kan meerdere trainingsmomenten hebben waarbij elk lid maar op één of enkele dagen zwemt. Op de groepspagina staat nu *Trainingsdag per lid*: een rooster met de actieve reeksen van de groep, waarin je per lid aanvinkt op welke reeks(en) hij zwemt (nieuwe tabel `member_series`). Niets aangevinkt = alle trainingen van de groep, zodat competitiegroepen en bestaande gegevens ongewijzigd blijven. Gevolgen: de aanwezigheidslijst van een training toont enkel de leden die daar horen; de persoonlijke kalender van een lid of ouder toont enkel de trainingen van de eigen dag(en) (trainers en bestuur zien alles); op de ledenpagina staat bij *Groep* welke trainingen het lid volgt. De keuze geldt enkel voor de reeksen van de huidige groep en vervalt vanzelf bij een groepswissel.
- **Trainingsreeksen verlengen:** op de pagina van een reeks staat *Reeks verlengen* (nieuwe einddatum, standaard een jaar later) en op het overzicht *Alle getoonde reeksen verlengen tot …* (ook per categorie of groep gefilterd). De extra trainingen worden meteen aangemaakt met hetzelfde schema; reeds geschrapte dagen en individueel aangepaste trainingen blijven zoals ze zijn. Vakantieperiodes schrap je daarna in één keer via *Schrappen / verplaatsen* (die dagen komen niet terug). De einddatum mag tot ±2 jaar na vandaag liggen (voorheen werd de totale lengte vanaf de startdatum beperkt tot 18 maanden, waardoor verlengen vaak niet lukte).
- **Vaste kleur per groep:** elke groep heeft een eigen kleur die bewaard wordt in de groep zelf (nieuwe kolom `groups.color`, bestaande groepen krijgen er vanzelf één in de volgorde zoals ze nu al in de kalender stonden). Een nieuwe groep krijgt de eerste nog ongebruikte kleur van 12; een kleur verandert nooit door een andere groep toe te voegen of te verwijderen, en een vrijgekomen kleur wordt hergebruikt. Dezelfde kleurstip staat nu bij de groepsnaam op *Groepen*, de groepspagina, de ledenlijst, de koppen bij *Trainingsreeksen* en in de kalender.
- **Kalender met groep en kleur:** elke activiteit in de app-kalender toont nu ook voor welke groep(en) ze is (tweede regel in het blokje, volledige info bij aanwijzen). Elke groep heeft een eigen kleur, hele-clubactiviteiten zijn paars, wedstrijden hebben een gele rand en wedstrijden van een andere groep blijven grijs. Onder de kalender staat een legende met de groepen van die maand.
- **Schrappen = annuleren én verwijderen:** een activiteit schrappen (op de activiteitspagina, bij *Komende trainingen* van een reeks, per dag via het ×-kruisje of per periode/groep via *Schrappen / verplaatsen*) verwijdert ze nu echt, na een bevestiging. Is er al aanwezigheid geregistreerd, dan blijft de activiteit uit voorzorg bewaard als *Geannuleerd*. Een geschrapte reekstraining komt niet terug wanneer de reeks later wordt bijgewerkt (nieuwe tabel `activity_series_skips`, komt vanzelf in bestaande databases). Het veld *Reden* is vervallen. *Herstellen* bestaat enkel nog voor activiteiten die eerder geannuleerd werden.
- **Alle activiteiten van een dag schrappen:** in de kalender staat bij elke dag met activiteiten een rood kruisje (×) naast de +; dat opent *Activiteiten schrappen of verplaatsen* met die dag voor ingevuld. Het scherm (voorheen enkel voor reeksen, bereikbaar via Trainingsreeksen → *Schrappen / verplaatsen*) werkt nu standaard op **alle activiteiten** (ook losse trainingen, wedstrijden en clubevenementen) met een reden; de keuze *Enkel trainingen uit een reeks* blijft mogelijk. Je kan ook per groep, een periode, herstellen of de locatie wijzigen.
- **Groepen en reeksen:** de blokken op de pagina *Groepen* zijn kleiner (meer groepen naast elkaar). *Trainingsreeksen* zijn gesorteerd per groep, met een kop per groep (eerst zwemschool, dan competitie, alfabetisch); een reeks voor meerdere groepen staat onder de eerste groep met "Ook: …".
- **Filters en knoppen opgeruimd:** filterbalken (leden, reeksen, kalender, …) staan nu in een nette kaart met velden van gelijke hoogte en een knop die mee uitlijnt; het zoekveld rekte voorheen over de volledige breedte. Knoppenrijen (zoals *Alles op de website zetten / halen*) hebben overal dezelfde tussenruimte en ruimte eronder; op telefoon staan velden en knoppen netjes onder elkaar.
- **Nieuw menu in de beheer-app:** een vaste zijbalk links (donkerblauw) in plaats van de lange rij links bovenaan. Het menu is gegroepeerd in *Overzicht*, **Club** (Leden, Groepen, Gebruikers), **Planning** (Kalender, Trainingsreeksen), **Aanvragen** (Inschrijvingen, Zwemtest, Berichten, met teller van openstaande items) en **Website** (Beheren, Website bekijken). Elk item heeft een icoon, de actieve pagina is duidelijk gemarkeerd en je ziet enkel wat je rol mag. Onderaan staat wie is aangemeld met *Afmelden*. Op telefoon en tablet (< 960 px) staat bovenaan het logo met een *Menu*-knop; het menu schuift dan over het scherm (sluit met overlay, Esc of een klik op een link). Zonder JavaScript blijft het menu gewoon zichtbaar. Technisch: `app/views/_nav.php`, `public/assets/nojs.css`.
- **Imports uit de app gehaald:** de knoppen *Inhoud van khzs.be importeren* (Website → Pagina's) en *Competitie-uren overnemen* (Trainingsreeksen) zijn verwijderd, samen met hun routes. De reeds geïmporteerde pagina's, groepen en reeksen blijven gewoon bestaan. Voor ontwikkeling en tests blijft de commandoregel `php tools/seed-site.php [pages] [competition]`.
### v0.9.0 – 2026-10-25 – `bf8a563`
- **Leesbare adressen ook in de beheer-app:** de app staat onder `/app` zonder `?r=`: `/app` (overzicht), `/app/leden`, `/app/leden/12`, `/app/leden/12/wijzigen`, `/app/leden/nieuw`, `/app/groepen/3`, `/app/gebruikers`, `/app/kalender`, `/app/kalender/7`, `/app/reeksen/5`, `/app/inschrijvingen`, `/app/wachtlijst` (zwemtest), `/app/berichten` en `/app/website` (met `/app/website/paginas/4` en `/app/website/berichten/2`). Filters en tabbladen blijven gewone parameters (`/app/leden?status=ACTIVE`). Acties (opslaan, verwijderen, …) staan op `/app/<actie>`. Ook de links in mails zijn korter: uitnodigen en wachtwoord vergeten wijzen naar `/activeren?token=…` en `/wachtwoord-vergeten`. De oude `?r=`-adressen (en eerder verstuurde mails) blijven werken, net als de bevestigingslinks voor zwemtest en inschrijving. De naam `app` is gereserveerd.
- **Leesbare adressen (permalinks) voor de publieke site:** geen `?r=` meer in de URL. Pagina's staan op `/<naam>` (bv. `/zwemschool`, `/lidgeld`, `/contact`, dezelfde namen als op khzs.be), de kalender op `/kalender`, de trainingsuren op `/trainingsuren`, de zwemtest op `/zwemtest`, inschrijven op `/inschrijven`, aanmelden op `/aanmelden` en elk nieuwsbericht op een eigen pagina `/nieuws/<naam>` (de titel op de startpagina linkt ernaartoe). Dat adres volgt de titel van de pagina of het bericht. Links in paginateksten (`?r=page&slug=…`) worden bij het tonen automatisch omgezet. De oude adressen met `?r=` blijven werken en het beheer (app) behoudt ze. Technisch: een rewrite-regel in `public/.htaccess`, een `<base href>` in de layouts, een router in `public/index.php`. Pagina's mogen de namen `kalender`, `trainingsuren`, `zwemtest`, `inschrijven`, `aanmelden`, `nieuws` en enkele systeemnamen niet gebruiken (die krijgen vanzelf `-2`). Werkt ook als de site in een submap staat. De startpagina `/` toont voortaan altijd de publieke startpagina (ook voor wie is aangemeld; het overzicht staat onder *Mijn Clubapp*). Vereist `mod_rewrite` (Apache) of, lokaal, `php -S` zoals voorheen.
### v0.8.0 – 2026-10-25 – `3e427f3`
- **Code-review: veiligheid en techniek.** (1) *Login-throttling:* na 5 mislukte pogingen per e-mailadres (of 20 per IP-adres) binnen 15 minuten volgt een melding en een blokkade van 15 minuten; een geslaagde login wist de teller (nieuwe tabel `login_attempts`, komt vanzelf in bestaande databases). (2) *Sessie* verloopt na 12 uur zonder activiteit. (3) *Headers:* `Strict-Transport-Security` zodra de site via HTTPS draait; pagina's van de app krijgen `Cache-Control: no-store` en `X-Robots-Tag: noindex` (geen gegevens meer via de terugknop na afmelden, niet in zoekmachines); de publieke site krijgt `no-cache` in plaats van PHP's standaard `no-store`. (4) *Foutafhandeling:* een onverwachte fout toont een nette foutpagina (en wordt gelogd) in plaats van een wit scherm; bezoekers krijgen foutpagina's (bv. 404) in de stijl van de website in plaats van in de app-layout. (5) *CSP:* alle inline `style=`-attributen vervangen door klassen (ze werden door de CSP toch geblokkeerd). (6) *Uploads:* de uploadmap krijgt automatisch een `.htaccess` die scriptuitvoering blokkeert. (7) *Links in teksten:* adressen met `*` worden niet meer als link herkend (voorkomt vreemde opmaak in een href); `redirect()` verwijdert regeleindes (header-injectie).
- **Responsive controle (designer):** alle publieke pagina's en alle beheerschermen gecontroleerd op 11 schermbreedtes (320 tot 1920 px): geen horizontale scroll meer. Opgelost: de activiteitenlijst op de groepspagina liep op telefoon buiten het scherm (nu kaartweergave zoals de andere tabellen); de voet van de site toont op tablet twee kolommen in plaats van één lange kolom; de voet van de app zegt niet meer "V1".
- **Header:** boven de naam staat nu *Hasseltse Zwemvereniging* en eronder *Spartacus*; op smalle schermen (tot 820 px) blijft enkel het logo staan.
- **Documentatie:** README en analyse bijgewerkt (inlogbeveiliging, headers, responsive).
### v0.7.0 – 2026-10-25 – `64cfebd`
- **Website = wedstrijden en clubactiviteiten, app = persoonlijke kalender:** de publieke kalender en *Komende activiteiten* op de startpagina tonen voor iedereen (ook aangemelden) enkel wedstrijden en clubactiviteiten, nooit trainingen (de uren staan op de pagina *Trainingsuren*). De kalender in de app is de persoonlijke kalender: leden, ouders en trainers zien de trainingen en activiteiten van hun eigen groepen, plus alle clubactiviteiten en wedstrijden. Wedstrijden voor andere groepen staan er in grijs (niet aanklikbaar, tooltip *Niet voor jouw groep*; beheerders kunnen ze wel openen). Het vinkje *Tonen op de website* is uit het activiteitenformulier gehaald (geen effect meer); bij reeksen bepaalt het enkel de pagina *Trainingsuren*.
- **Reeksen sneller op de website tonen of verbergen:** elke reeks in het overzicht heeft nu een knop *Tonen* / *Verbergen* met een duidelijk label (*Op website* / *Niet op website*), zonder de reeks te openen en te bewerken. Bovenaan staan *Alles op de website zetten* en *Alles van de website halen* voor de reeksen in de huidige lijst (dus met het gekozen groepsfilter). Ook op de detailpagina van een reeks staat de knop. De trainingen in de kalender volgen automatisch.
- **Terugknop:** *← Terug* bovenaan gaat nu altijd naar de bovenliggende pagina en niet meer gewoon naar de vorige in de browser. Een lid, groep, bericht of reeks gaat terug naar zijn overzicht; een wijzigscherm gaat terug naar het item zelf (nieuw: naar het overzicht); overzichten gaan terug naar het dashboard. Zo blijft de knop kloppen na opslaan, filteren of bladeren.
- **Automatische pagina's beschermd:** pagina's waarvan de inhoud uit de app komt (*Trainingsuren competitie*, *Doelstellingen*, *Contact* en de startpagina met het nieuws) blijven in het overzicht staan met het label *Automatisch*, maar kunnen niet verwijderd worden en hun adres, plaats en status liggen vast. Bij de eerste twee kun je enkel de inleiding (tekst vóór de eerste kop) aanpassen, bij Contact de tekst met contactgegevens. Het bewerkscherm verwijst naar het juiste beheer (Reeksen, Groepen, Berichten). Ook de opruimknop laat ze met rust.
- **Paginacontrole en opruiming (Website → Pagina's):** bovenaan staat nu een controle met per pagina een kolom *Controle*: verouderde `doelstellingen-…`-pagina's, lege pagina's, concepten, kapotte links (onbekende pagina of route, link naar een concept) en onbereikbare pagina's (niet in het menu en nergens naar verwezen). De knop *Overbodige pagina's opruimen* verwijdert na bevestiging enkel de verouderde en lege pagina's; al de rest blijft staan tot je het zelf beslist. De paginacontrole meldt ook dat de vaste uren op *Trainingsuren competitie* genegeerd worden zodra er competitiereeksen zijn (enkel de inleiding vóór de eerste kop wordt getoond). Verse standaardinhoud geeft geen enkele opmerking. Inhoud: typfout in het gsm-nummer van Bart Bielen op de API-pagina hersteld (+32 496 32 92 52), ook in bestaande databases.
- **Menu:** *Trainingsuren* staat niet meer als aparte knop in het hoofdmenu. De pagina blijft bereikbaar via de knop op de startpagina, de voet en de pagina's Zwemschool en Competitie.
- **Contactpagina met formulier en contactpersonen:** de pagina Contact bevat nu de gegevens van de zwemschool (Jolien Vanhentenrijk, Bart Bielen, zwemschool@khzs.be, link naar de zwemtest) en de contactpersonen per competitiegroep van khzs.be, met onderaan een eigen contactformulier (onderwerp zwemschool/competitie/iets anders, voornaam, achternaam, e-mail, bericht) in plaats van de twee losse pagina's. Berichten worden altijd in de app bewaard (menu *Berichten* met teller: afhandelen of verwijderen) en daarnaast gemaild naar het adres voor dat onderwerp. Stel die in `config.php` in: `'contact' => ['swimschool_email' => '…', 'competition_email' => '…', 'other_email' => '…']`; zonder instelling gaat de mail naar het meldadres van de inschrijvingen (`registration.notify_email`). Zelfde bescherming als de andere formulieren (anti-robotveld, limiet per uur). Een nog onaangepaste oude contactpagina wordt automatisch vervangen; aangepaste pagina's blijven ongemoeid. Nieuwe tabel `contact_messages` komt vanzelf in bestaande databases.
- **Menu:** het hoofdmenu van de site klapt nu in onder 1180 px (anders passen de items niet naast het logo) en de gele knop breekt niet meer af over twee regels.
- **Zichtbaar waarom een reeks niet op de site staat:** in de lijst en op de pagina van een reeks staat nu een label *Niet op website* (vakje *Tonen op de website* uit), *Gestopt* of *Verlopen* (einddatum voorbij). Enkel zulke reeksen ontbreken op de pagina Trainingsuren.
- **Bulk verplaatsen blijft op de brongroep:** na het verplaatsen van leden blijf je op de groep waaruit je ze haalde (zodat je rustig verder kan met de rest), in plaats van naar de nieuwe groep te springen. De melding vermeldt waarheen de leden gingen.
- **Reeksen verwijderen:** knop *Verwijderen* op de pagina van een trainingsreeks. De reeks en al haar trainingen verdwijnen uit de kalender; trainingen waarvan de aanwezigheden al zijn ingevuld blijven als losse trainingen bestaan, zodat de registraties niet verloren gaan. Wil je enkel stoppen zonder te wissen, zet de reeks dan op gestopt via *Reeks wijzigen*.
- **Competitie-uren overnemen:** op *Trainingsreeksen* staat (zolang er iets ontbreekt) de knop *Competitie-uren overnemen*. Die maakt de competitiegroepen aan die nog niet bestaan (Competitie 3, Competitie 2, Beloften, Competitie 1 Provinciaal, Competitie 1 Nationaal, Kern) en per groep de reeksen *Zwemmen* en *Droogtraining* met de uren van khzs.be, publiek zichtbaar tot eind juni, met de trainingen in de kalender. Bestaande groepen en reeksen blijven ongemoeid; een tweede keer klikken doet niets. Trainers koppel je daarna bij *Groep wijzigen*. Enkel voor wie groepen en activiteiten mag beheren.
- **Fix upgrade zwemtest:** een bestaande database kreeg de tabel `swimtest_requests` niet automatisch aangemaakt, waardoor het overzicht een fout gaf ("no such table"). De tabel wordt nu bij het opstarten aangemaakt.
- **Trainingsuren competitie uit de app:** de pagina *Trainingsuren competitie* toont nu automatisch de competitiegroepen met hun publieke trainingsreeksen (zwemmen en droogtraining met dag, uur en plaats) en de trainers van de groep. Zodra er minstens één publieke reeks voor een competitiegroep bestaat, vervangt dit de oude vaste tekst (alleen de inleiding vóór de eerste kop blijft staan); tot dan blijft de oude tekst zichtbaar. De algemene pagina *Trainingsuren* toont de trainers nu ook. Groepen staan in de volgorde waarin ze zijn aangemaakt. Let op: trainers verschijnen met hun naam uit *Gebruikers*.
- **Zwemtest aanvragen:** apart formulier voor de zwemschool (`?r=swimtest`) in de stijl van de nieuwe site: kind (naam, geboortedatum), diploma (Orka, Fred-brevet, ander), andere zwemervaring, beschikbare zwemmomenten (aanvinkbaar) en gegevens van de ouder. Zelfde veiligheid als het inschrijfformulier: bevestiging per mail, anti-robotveld, limiet per uur en toestemming; per kind een aparte aanvraag, een kind kan niet dubbel op de lijst. De knoppen op de site (hoofdmenu, startpagina, voet) verwijzen nu naar de zwemtest; *Lid worden* (het bestaande inschrijfformulier) staat in de voet en blijft de officiële inschrijving als lid.
- **Wachtlijst voor het bestuur:** nieuw menu *Zwemtest* (met teller) met tabs Wachtlijst, Uitgenodigd, Test gedaan, Ingeschreven en Geweigerd. De wachtlijst staat in volgorde van bevestiging. Per aanvraag: status wijzigen, uitnodiging per mail met datum en plaats van de test, weigering met reden, interne notitie en andere kinderen met hetzelfde e-mailadres. *Als lid inschrijven* zet het kind door naar een gewone inschrijving (met diploma in de opmerking); daar kies je de groep en keur je goed, waarna lid en account worden aangemaakt. De uitleg-pagina *Inschrijven zwemtest* verwijst automatisch naar het nieuwe formulier. De zwemmomenten staan voorlopig vast in de code (`SwimTestController::SLOTS`).
- **Doelstellingen horen bij de groep:** in *Groep wijzigen* staat een veld *Doelstellingen (voor de website)*. De pagina Doelstellingen van de zwemschool toont automatisch alle actieve zwemschoolgroepen met doelstellingen (in de volgorde waarin de groepen zijn aangemaakt), met snelle sprongen per groep. Een nieuwe groep met doelstellingen verschijnt er vanzelf bij; inactieve groepen, competitiegroepen en groepen zonder doelstellingen niet. Op de groepspagina in de app staan ze ook. De 9 losse pagina's "Doelstellingen – <groep>" verhuizen automatisch naar de groepen met dezelfde naam (inhoud blijft behouden) en verdwijnen als losse pagina. De knop *Inhoud van khzs.be importeren* vult ze ook in voor groepen zonder doelstellingen.
- **Nieuwe vormgeving publieke site:** eigen stijl los van de beheer-app (`public/assets/site.css`). Donkerblauw zwembad-thema met baantouw (blauw/geel) onder de kop en boven de voet, grote startpagina met de naam van de club en twee "banen" naar Zwemschool en Competitie, datum-tegels voor activiteiten, kalender en trainingsuren als kaarten per groep, inhoudspagina's met banner en zijmenu voor de rubriek, en een voet met snelle links. Lettertypes (Barlow Condensed en Figtree) staan lokaal op de server, dus zonder Google Fonts. Werkt op gsm met uitklapmenu. Inschrijven, aanmelden en de beheer-app behouden voorlopig hun huidige stijl.
- **Groep verwijderen kan altijd:** ook met leden, activiteiten of reeksen. De leden blijven bestaan maar zitten dan in geen groep meer; trainingsreeksen en activiteiten die enkel voor die groep waren (met aanwezigheden) worden mee verwijderd; gedeelde activiteiten/reeksen blijven voor de andere groepen. De bevestigingsvraag legt dit uit.
- **Activiteiten bij een groep:** de groepspagina toont de gekoppelde trainingsreeksen (met link, voor wie activiteiten mag beheren) en de eerstvolgende activiteiten van de groep, met geschrapte trainingen gemarkeerd en een link naar de kalender gefilterd op die groep.
- **Trainers kiezen bij een groep:** in het formulier *Groep wijzigen* (en *Nieuwe groep*) vink je één of meerdere trainers aan; bij opslaan worden de trainers van de groep bijgewerkt. Enkel gebruikers met de rol Trainer verschijnen in de lijst.
- **Geen Van/Tot meer bij trainers:** een trainer is gewoon aan groepen gekoppeld, zonder begin- en einddatum of historiek. Op de gebruikerspagina kies je een groep en klik je *Toewijzen*; *Verwijderen* haalt de trainer uit de groep. Bestaande databases worden opgeruimd (afgesloten en dubbele koppelingen verdwijnen).
- **Publieke website (start):** wie niet is aangemeld ziet nu een publieke startpagina in plaats van het inlogscherm (aanmelden staat in het menu). Bevat: nieuws (enkel op de startpagina, volledige berichten met paginering; geen aparte nieuwspagina; het bestuur ziet concepten er gemarkeerd), vrije pagina's met menu en subpagina's, publieke kalender, trainingsuren per groep en een link naar inschrijven. Een pagina met adres `home` verschijnt als introtekst.
- **Groepen verwijderen:** knop *Verwijderen* op de groepspagina (Admin en Bestuur). Kan enkel voor een groep zonder leden, activiteiten of trainingsreeksen; anders volgt een uitleg en kun je de groep op inactief zetten. Trainer-koppelingen verdwijnen mee.
- **Leden verplaatsen:** op de groepspagina leden selecteren (of alles) en in één keer naar een andere groep verplaatsen. Op de ledenpagina kies je de groep van één lid (of *geen groep*).
- **Geen groepshistoriek meer:** de app houdt enkel nog bij in welke groep een lid nu zit, zonder begin- en einddatum. Bestaande databases worden automatisch opgeruimd: afgesloten koppelingen verdwijnen en bij meerdere lopende groepen blijft de laatste staan. Aanwezigheidsregistraties blijven bewaard.
- **Eén groep per lid:** een lid kan niet in een tweede groep geplaatst worden zolang het in een andere zit (melding met de huidige groep). De lijst *Lid toevoegen* toont enkel leden zonder groep.
- **Inhoud van khzs.be:** knop *Inhoud van khzs.be importeren* (Website → Pagina's) zet de pagina's van de vorige site in de app, als hoofd- en subpagina's met menu: Zwemschool (inschrijven zwemtest, doelstellingen per groep, vakantieperiodes, PACO-wedstrijden, portretrecht, lidgeld, jeugdfonds, documenten, mutualiteit, verzekering), Competitie (trainingsuren, documenten, clubrecords), Club (bestuur, links, privacybeleid) en Contact (met API). Voegt enkel toe wat nog niet bestaat en overschrijft nooit eigen aanpassingen.
- **Website beheren:** nieuw menu *Website* (Admin en Bestuur, recht `content.manage`): nieuwsberichten en pagina's als concept of gepubliceerd, hoofdafbeelding bij nieuws, afbeeldingenbibliotheek met kopieerbare code. Eenvoudige, veilige opmaak (koppen, lijsten, vet, links, afbeeldingen); alle tekst wordt ge-escaped.
- **Wat is publiek:** alle wedstrijden en clubactiviteiten staan automatisch in de publieke kalender en op de startpagina (enkel titel, tijd, plaats en omschrijving, geen groepen of leden). Trainingen en trainingsreeksen hebben een vakje *Tonen op de website*; enkel die komen in de publieke kalender en trainingsuren (geschrapte trainingen met reden). Alles anders blijft binnen de login.
- **Database:** nieuwe tabellen `posts`, `pages`, `media` en kolom `is_public` bij activiteiten en reeksen; automatisch toegevoegd aan bestaande databases. Geüploade afbeeldingen staan in `public/uploads/` (niet in git).
- **Documentatie:** README en de functionele en technische analyse zijn volledig herschreven voor de huidige app (publieke site, zwemtest, contact, reeksen, persoonlijke kalender). De analyse staat nu in de repository: `docs/FUNCTIONELE_EN_TECHNISCHE_ANALYSE.md`.
### v0.6.0 – 2026-10-25 – `b182e3b`
- **Terug-link:** bovenaan elke pagina (behalve het overzicht) staat *← Terug* om naar de vorige pagina terug te keren.
- **Filter trainingsreeksen:** de lijst met reeksen is te filteren op soort (zwemschool/competitie) of op één groep.
- **Trainingsreeksen met verleden:** een reeks met een startdatum in het verleden maakt ook de trainingen van het verleden aan, zodat ze in de kalender (en voor aanwezigheden) staan. Een vroegere startdatum bij een bestaande reeks vult het verleden aan; bestaande trainingen uit het verleden blijven altijd ongemoeid.
- **Relatie bewerken:** de relatie tussen ouder en kind (moeder, vader, voogd, …) is nu aanpasbaar (knop *Bewerken*) of leeg te maken, zowel op de ledenpagina als op de gebruikerspagina van de ouder (enkel met gebruikersbeheer).
- **Inschrijfformulier:** keuze zwemschool/competitie weggehaald (het bestuur deelt het kind in een groep in).  standaard enkel velden voor 1 kind; met de knop *+ Nog een kind inschrijven* verschijnt telkens een extra kind (zonder JavaScript blijven alle velden zichtbaar). Een per vergissing toegevoegd kind kan met het kruisje weer weg.

### v0.5.0 – 2026-10-25 – `c95670a`
- **Trainingsreeksen:** per groep (of meerdere groepen) een wekelijkse reeks aanmaken met per weekdag een eigen uur en eventueel eigen locatie (bv. zwemtraining di 18:30–20:00, wo 16:30–18:30, za 7:30–9:00; droogtraining als tweede reeks). De trainingen verschijnen automatisch in de kalender voor de gekozen periode (max. ±18 maanden).
- **Schrappen:** één training schrappen met reden (zichtbaar in kalender en op de activiteit) en herstellen; of een hele periode in één keer (vakantie, wedstrijd) voor alle of geselecteerde groepen, met herstelknop.
- **Locatie wijzigen:** voor de hele reeks, per weekdag, per training, of voor een periode/groep tijdelijk.
- **Reeks wijzigen of stoppen:** komende trainingen worden aangepast; individueel gewijzigde trainingen en trainingen met ingevulde aanwezigheid blijven ongemoeid, het verleden blijft bewaard.
- **Database:** nieuwe tabellen `activity_series*` en kolommen `series_id`, `is_custom`, `status_note` bij activiteiten; automatisch toegevoegd aan bestaande databases. Enkel Admin en Bestuur beheren reeksen.

### v0.4.0 – 2026-10-25 – `2f306ea`
- **Inschrijven:** openbare inschrijfpagina (zonder login) voor ouders en zwemmers van 18+; gegevens: kinderen (naam, geboortedatum, voorkeur zwemschool/competitie), ouder (naam, e-mail, telefoon, relatie) en adres, met akkoord voor het reglement. Bevestiging van het e-mailadres per link; beveiligd met verborgen spamveld en limieten per e-mailadres en per uur.
- **Bestuur beoordeelt:** nieuw scherm *Inschrijvingen* (te beoordelen / afgehandeld) met melding op het overzicht en teller in het menu. Goedkeuren maakt de leden aan (met adres en telefoon), koppelt de ouder, wijst optioneel een groep toe en stuurt de uitnodiging; een bestaand ouderaccount wordt hergebruikt. Afwijzen stuurt een mail met reden. Waarschuwing bij mogelijk al bestaand lid; uitnodiging opnieuw versturen kan vanuit de inschrijving.
- **Rol Bestuur:** nieuwe rol met clubbrede toegang tot leden (incl. contactgegevens), groepen, activiteiten, aanwezigheid en inschrijvingen, zonder gebruikersbeheer. Bestaande databases krijgen de rol en het nieuwe recht automatisch. Demo-account `bestuur@demo.local`.
- **Leden:** adres (straat, postcode, gemeente) bij leden: zichtbaar voor wie contactgegevens mag zien en bewerkbaar in het ledenformulier.
- **Database:** nieuwe tabellen `registrations` en `registration_persons` en adreskolommen bij leden, automatisch toegevoegd aan bestaande databases.
- **E-mail:** eigen mailfunctie zonder extra bibliotheken (`log` voor lokaal testen, `smtp`, of PHP `mail()`), in te stellen in `config.php`. Bulkverzending gebruikt één SMTP-verbinding.
- **Uitnodigingen:** ouders/voogden en leden uitnodigen per e-mail vanaf de ledenpagina, of in bulk via *Gebruikers → Leden uitnodigen* (alle actieve leden met een e-mailadres; broers/zussen op hetzelfde adres delen één ouder-account). De ontvanger kiest zelf een wachtwoord via een eenmalige link (7 dagen geldig, opgeslagen als hash). Accounts die nog niet geactiveerd zijn tonen een label en kunnen opnieuw uitgenodigd worden.
- **Wachtwoord vergeten:** aanvragen via e-mail (link 60 minuten geldig, eenmalig, max. 1 mail per minuut per account, neutrale melding zodat niet te raden is welke adressen bestaan).
- **Database:** nieuwe tabel `user_tokens`, automatisch aangemaakt bij bestaande databases.
- **Werkwijze:** changelog toegevoegd (`CHANGELOG.md`) met versienummers per merge naar `main`. (`23862b6`, `2702b6a`)

### v0.3.0 – 2026-10-25 – `3437ffb`
- **Gebruikers:** gebruikerslijst met tabbladen *Beheer en trainers* en *Ouders en zwemmers* (aantallen + zoeken). Op de ledenpagina maakt de beheerder met één klik een eigen account voor een lid (rol Zwemmer, gegevens vooraf ingevuld). (`ad8cef8`)
- **Leden:** de sectie *Ouders / voogden* is bij leden van 18+ zonder ouder ingeklapt achter de knop "+ Ouder of voogd toevoegen"; met gekoppelde ouder of bij minderjarigen blijft ze zichtbaar. (`3437ffb`)
- **Filters:** filteren op *Zwemschool/Competitie* en op groep in de ledenlijst en de kalender; clubbrede activiteiten blijven zichtbaar. (`a7e1817`)
- **Groepen:** groepen zijn opgesplitst in *Zwemschool* en *Competitie* (overzicht, groepspagina, formulier, activiteitenformulier). Bestaande databases krijgen de kolom automatisch; bestaande groepen starten als Zwemschool. (`a47e395`)
- **Mobiel:** compacte header met scrollbaar menu, tabellen als kaarten op telefoon, grotere aanraakdoelen en aanwezigheidsknoppen, knoppen en formulieren over volle breedte (getest tot 320 px). (`bfefd19`)

### v0.2.0 – 2026-10-25 – `63da560`
- **Trainers:** een trainer kan ook lid zijn (rollen Trainer + Zwemmer). Aanwezigheid registreert hij enkel voor de groepen die hij traint. Demo-trainer zwemt zelf mee in de Wedstrijdploeg. (`8f279a7`, `63da560`)
- **Lidnummer:** nergens meer zichtbaar; intern automatisch aangemaakt. Keuzelijsten tonen het geboortejaar; zoeken gebeurt op voor- en achternaam. (`8f279a7`)
- **Minderjarigen:** leden jonger dan 18 moeten minstens één ouder of voogd hebben (blokkeert groepskoppeling, ontkoppelen van de laatste ouder en het intrekken van de rol Ouder). (`3f520ff`)
- **Huisstijl:** HZS-logo en kleuren (#064CA2, #009FE0). (`38d747d`)
- **Ouders:** ouders/voogden zichtbaar in ledenlijst en ledenpagina voor wie contactgegevens mag zien. (`12d861e`)
- **Demo-data:** script `tools/seed-demo.php` om voorbeelddata achteraf toe te voegen. (`75453ff`)
- **Accountbeheer:** gebruikers, rollen, ouder-kindkoppeling en trainer-groeptoewijzing. (`406d91f`)

### v0.1.0 – 2026-10-25 – `36c5978`
- **Werkwijze:** testbranch `dev`; sync-scripts volgen `dev`. (`a50cb28`)
- **V1:** leden, groepen, activiteiten, kalender en aanwezigheid met rollen beheerder/trainer/zwemmer/ouder. (`36c5978`)
