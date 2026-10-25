# Changelog – HZS Clubapp

Alle wijzigingen aan de app, nieuwste bovenaan. Elke push naar `dev` krijgt hier een regel onder **Op dev**.
Zodra `dev` naar `main` gaat (enkel na goedkeuring), verhuizen die regels naar een **Op main**-sectie met versienummer, datum en commit (`v0.x.0`, telkens +1 per merge).

## Op dev (nog niet op main)

- **E-mail:** eigen mailfunctie zonder extra bibliotheken (`log` voor lokaal testen, `smtp`, of PHP `mail()`), in te stellen in `config.php`. Bulkverzending gebruikt één SMTP-verbinding.
- **Uitnodigingen:** ouders/voogden en leden uitnodigen per e-mail vanaf de ledenpagina, of in bulk via *Gebruikers → Leden uitnodigen* (alle actieve leden met een e-mailadres; broers/zussen op hetzelfde adres delen één ouder-account). De ontvanger kiest zelf een wachtwoord via een eenmalige link (7 dagen geldig, opgeslagen als hash). Accounts die nog niet geactiveerd zijn tonen een label en kunnen opnieuw uitgenodigd worden.
- **Wachtwoord vergeten:** aanvragen via e-mail (link 60 minuten geldig, eenmalig, max. 1 mail per minuut per account, neutrale melding zodat niet te raden is welke adressen bestaan).
- **Database:** nieuwe tabel `user_tokens`, automatisch aangemaakt bij bestaande databases.
- **Werkwijze:** changelog toegevoegd (`CHANGELOG.md`) met versienummers per merge naar `main`. (`23862b6`, `2702b6a`)

## Op main

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
