# Changelog – HZS Clubapp

Alle wijzigingen aan de app, nieuwste bovenaan. Elke push naar `dev` krijgt hier een regel onder **Op dev**.
Zodra `dev` naar `main` gaat (enkel na goedkeuring), verhuizen die regels naar een **Op main**-sectie met versienummer, datum en commit (`v0.x.0`, telkens +1 per merge).

## Op dev (nog niet op main)

- **Inschrijfformulier:** standaard enkel velden voor 1 kind; met de knop *+ Nog een kind inschrijven* verschijnt telkens een extra kind (zonder JavaScript blijven alle velden zichtbaar).

## Op main

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
