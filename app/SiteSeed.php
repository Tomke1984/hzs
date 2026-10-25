<?php
/**
 * Startinhoud voor de website, overgenomen (en herschreven) van de vorige site khzs.be.
 * Elke pagina: [slug, titel, bovenliggende slug|null, volgorde, in menu, tekst]. Bestaande adressen worden nooit overschreven.
 */
final class SiteSeed
{
    public static function pages(): array
    {
        $api = '?r=page&slug=ombudsman';
        return [
            ['home', 'Home', null, 0, 0, <<<'T'
Welkom bij de Hasseltse Zwemvereniging Spartacus (HZS). In onze **zwemschool** leren kinderen zwemmen op hun eigen niveau, in onze **competitieafdeling** trainen zwemmers voor wedstrijden.

Bekijk de trainingsuren, schrijf je in of lees het laatste nieuws van de club.
T],

            // ---------- Zwemschool ----------
            ['zwemschool', 'Zwemschool', null, 1, 1, <<<'T'
## Algemene informatie

- **Zwemstijlen:** kinderen leren in de zwemschool vier stijlen: crawl, rugslag, schoolslag en vlinderslag, tijdens wekelijkse lessen van één uur.
- **Lesperiode:** van begin september tot eind juni. Tijdens de kerst- en paasvakantie is er geen les (zie [vakantieperiodes](?r=page&slug=vakantieperiodes)).
- **Inschrijven (5 tot 12 jaar):** via de pagina [Inschrijven zwemtest](?r=page&slug=inschrijven-zwemtest). Je kind komt daarna op een wachtlijst. Het kind heeft bij voorkeur het diploma Orka, behaald bij de sportdienst van de Stad Hasselt.
- **Recreatie (vanaf 12 jaar):** oudere kinderen kunnen terecht in de recreatiegroep, met lessen op hun niveau.
- **Niveaugroepen:** er zijn vijf niveaugroepen. Elk kind krijgt de tijd om op zijn eigen niveau te leren. Doorstromen naar een hogere groep gebeurt pas wanneer trainers en verantwoordelijken, per kind bekeken, vinden dat het kind er klaar voor is.
- **Pre-competitie:** twee voorbereidende groepen voor het competitiezwemmen: pre-competitie 1 en 2. De verantwoordelijken van de zwemschool brengen kinderen op de hoogte wanneer ze in aanmerking komen.
T],
            ['inschrijven-zwemtest', 'Inschrijven zwemtest', 'zwemschool', 1, 1, <<<'T'
De club heeft momenteel een zeer grote wachtlijst. Kinderen kunnen daarom niet meteen getest of ingeschreven worden.

Je kind aanmelden voor de zwemtest doe je via het [formulier voor de zwemtest](?r=swimtest). Zorg dat alle gegevens correct zijn.

## Diploma's en ervaring

- Vermeld of je kind een diploma van de nieuwe leerlijn zwemmen (Fred-brevet) heeft behaald.
- De club hecht veel belang aan het diploma Orka, door de samenwerking met de Stad Hasselt.
- Andere zwemervaring mag je altijd doorgeven.

## Beschikbare zwemmomenten

Duid in het formulier aan wanneer je kind zou kunnen zwemmen:

- dinsdag 17u30–18u30
- donderdag 16u30–17u30
- vrijdag 16u30–17u30
- vrijdag 17u30–18u30
- zaterdag 8u–9u
- zaterdag 9u–10u

## Na het inschrijven

Je kind komt op de wachtlijst, zodra je je e-mailadres hebt bevestigd. Je wordt uitgenodigd voor een zwemtest zodra de club die organiseert, meestal tijdens de vakantieperiodes.
T],
            ['doelstellingen', 'Doelstellingen', 'zwemschool', 2, 1, <<<'T'
Per niveau werken de zwemmers naar vaste doelstellingen. Doorstromen gebeurt op basis van een evaluatie door de trainers.
T],
            ['vakantieperiodes', 'Vakantieperiodes', 'zwemschool', 3, 1, <<<'T'
## Groepen 1 t/m 6

- **Herfstvakantie (november):** lessen volgens de gewone lesuren
- **Kerstvakantie (december):** geen zwemlessen
- **Krokusvakantie (februari):** lessen volgens de gewone lesuren
- **Paasvakantie (april):** geen zwemlessen
- **Zomervakantie (juli en augustus):** geen zwemlessen

## Pre-competitie 1 en 2

- **Herfstvakantie (november):** lessen volgens de gewone lesuren
- **Kerstvakantie (december):** lessen volgens de gewone lesuren; afwijkingen worden meegedeeld
- **Krokusvakantie (februari):** lessen volgens de gewone lesuren
- **Paasvakantie (april):** lessen volgens de gewone lesuren; afwijkingen worden meegedeeld
- **Zomervakantie (juli en augustus):** geen zwemlessen
T],
            ['paco-wedstrijden', 'PACO-wedstrijden', 'zwemschool', 4, 1, <<<'T'
Elk jaar neemt de zwemschool deel aan enkele Paco-zwemfeesten. Dat zijn kindvriendelijke zwemwedstrijden, georganiseerd door clubs samen met de Vlaamse Zwemfederatie. Kinderen tonen er aan trainers en familie wat ze al kunnen en krijgen een beloning voor hun deelname. Het motto: *meedoen is belangrijker dan winnen!*

## Wie kan deelnemen?

- Kinderen van 6 tot 12 jaar uit de zwemschool, zonder vergunning
- Meestal worden kinderen vanaf groep 3 uitgenodigd; kinderen uit groep 1 en 2 nemen deel aan minder wedstrijden
- Uitnodigingen komen per brief en per mail. Schrijf je in vóór de uiterste datum, anders kun je niet deelnemen.

## Wat neem je mee?

- Handdoek, eventueel een reservehanddoek
- Zwemkledij en zwembril
- T-shirt en short
- Slippers
- Drinken en eventueel een koekje (geen snoep)
- Een badmuts, die je van de club krijgt en na de wedstrijd terugbezorgt

## Tijdens de wedstrijd

- Meld je op tijd aan bij de verantwoordelijke trainer ter plaatse
- Laat de verantwoordelijke weten als je niet aanwezig kunt zijn
- Blijf bij de trainers; wil je naar het toilet of naar je ouders, vraag het dan eerst aan hen
- Elke zwemmer ontvangt op het einde een medaille. Moet je vroeger weg, meld dat dan aan de trainer.

## Paco-zwemfeest in Hasselt

Jaarlijks organiseert de club ook zelf een Paco-zwemfeest in Hasselt. Alle zwemmers ontvangen daarvoor een uitnodiging. We zoeken ook ouders die willen helpen met de organisatie.

Meer informatie vind je op de [Paco-website](http://www.zwemfed.be/splashweb/). Over foto's tijdens activiteiten lees je meer bij [portretrecht](?r=page&slug=portretrecht).
T],
            ['portretrecht', 'Portretrecht', 'zwemschool', 5, 1, <<<'T'
Bij clubactiviteiten of evenementen waaraan de club deelneemt, kunnen foto's worden genomen waarop kinderen herkenbaar zijn. Wie deelneemt aan zulke activiteiten, geeft de club toestemming om die beelden op de website te plaatsen.

Ouders die dit niet willen, maar wel willen dat hun kind meedoet, kunnen contact opnemen met de verantwoordelijken van de zwemschool: Bart of Jolien.
T],
            ['lidgeld', 'Lidgeld', 'zwemschool', 6, 1, <<<'T'
- Het lidgeld wordt betaald **vóór de start van het nieuwe zwemseizoen**, meestal tussen begin en midden juli.
- Het geldt voor het hele zwemseizoen, van september tot juni.
- Lidgeld zwemschool (groepen 1 t/m 6): **€205**.
- Pre-competitiezwemmers (niveau 1 en 2) betalen iets meer. Het bedrag wordt aan de ouders meegedeeld.
- Betaal op het rekeningnummer dat de club meedeelt, met vermelding van de voor- en achternaam van de zwemmer.
- Bij meerdere kinderen betaal je voor elk kind apart.
- Leden die de club in de loop van het jaar verlaten, kunnen hun lidgeld niet terugvorderen.
- Kinderen die in de loop van het jaar in de zwemschool instappen, betalen een bedrag dat aangepast is aan het aantal resterende maanden. Meer info staat in het [huishoudelijk reglement](?r=page&slug=documenten-zwemschool).

## Wat zit erin?

Het lidgeld dekt de ingang van het zwembad, begeleiding en toezicht, de verzekering, de verplichte bijdragen aan de Vlaamse Zwemfederatie en, indien van toepassing, deelname aan Paco-zwemfeesten.

Een deel van het lidgeld kun je mogelijk terugkrijgen via je [mutualiteit](?r=page&slug=mutualiteit) of het [Jeugdfonds](?r=page&slug=jeugdfonds).
T],
            ['jeugdfonds', 'Jeugdfonds', 'zwemschool', 7, 1, <<<'T'
Het Jeugdfonds steunt en beloont sportclubs die investeren in een kwaliteitsvolle jeugdwerking, met steun van de Vlaamse overheid via Sport Vlaanderen. Clubs met een voldoende hoge score krijgen een officieel kwaliteitslabel en financiële ondersteuning.

HZS neemt elk jaar deel en behaalde telkens het kwaliteitslabel. Dat bevestigt onze inzet voor een sterke, veilige en kwalitatieve jeugdwerking.
T],
            ['documenten-zwemschool', 'Documenten zwemschool', 'zwemschool', 8, 1, <<<'T'
- [Algemeen huishoudelijk reglement afdeling zwemschool KHZS (versie 30.08.2017, pdf)](https://khzs.be/wp-content/uploads/2017/09/Algemeen-huishoudelijk-reglement-afdeling-zwemschool-KHZS-definitieve-versie-1-30.08.2017.pdf)
- [Mutualiteit: terugbetaling van lidgeld](?r=page&slug=mutualiteit)
- [Verzekering bij een sportongeval](?r=page&slug=verzekering)
T],
            ['mutualiteit', 'Mutualiteit', 'zwemschool', 30, 0, <<<'T'
Na betaling van het lidgeld kun je bij sommige mutualiteiten een deel terugkrijgen. Het bedrag verschilt per mutualiteit en wordt rechtstreeks door je mutualiteit uitbetaald; zij geven ook meer informatie.

## Wat moet je doen?

- Print het attest van je mutualiteit af
- Vul de gegevens van je kind in, eventueel met een kleefbriefje van de mutualiteit
- Bezorg het ingevulde attest aan Bart Bielen (secretaris zwemschool)
- Hij vult het verder in en bezorgt het aan je terug

## Terugbetaling per mutualiteit

- **Christelijke mutualiteit:** €15 – [website](http://www.cm.be)
- **Socialistische mutualiteit, De Voorzorg:** €15 – [website](http://www.devoorzorg.be)
- **Liberale mutualiteit:** €25 – [website](http://liberalemutualiteit.be)
- **Partena:** €15 – [website](http://www.partena-ziekenfonds.be/nl)

Download het attest op de website van je mutualiteit.
T],
            ['verzekering', 'Verzekering', 'zwemschool', 31, 0, <<<'T'
Op deze pagina vind je de verzekeringspapieren voor een sportongeval. Meer informatie vind je op de [website van de zwemfederatie](http://www.zwemfed.be/clubsite/verzekering/formulieren).

Wil je de papieren gebruiken? Meld het ongeval dan zo snel mogelijk, liefst binnen 24 uur, aan de secretaris. De papieren moeten binnen **acht kalenderdagen** bij de zwemfederatie zijn.

## Verzekeringspapieren

- [Verzekeringspolis N.V. Arena (pdf)](https://khzs.be/wp-content/uploads/2016/09/VZF-OVERZICHT-VERZEKERDE-WAARBORGEN.pdf)
- [Aangifteformulier N.V. Arena (pdf)](https://khzs.be/wp-content/uploads/2016/09/VZF-ONGEVALSAANGIFTEFORMULIER-2016.pdf)
T],

            // ---------- Competitie ----------
            ['competitie', 'Competitie', null, 2, 1, <<<'T'
In de competitieafdeling trainen zwemmers voor wedstrijden. Bekijk de trainingsuren per groep, de documenten en de clubrecords.

- [Trainingsuren competitie](?r=page&slug=trainingsuren-competitie)
- [Documenten competitie](?r=page&slug=documenten-competitie)
- [Clubrecords](?r=page&slug=clubrecords)
T],
            ['trainingsuren-competitie', 'Trainingsuren competitie', 'competitie', 1, 1, <<<'T'
## Competitie 3
Trainer: Jorien

- **Dinsdag:** 18u30–20u, zwemmen
- **Woensdag:** 16u30–18u30, zwemmen
- **Donderdag:** 17u30–18u30, droogtraining
- **Zaterdag:** 7u–7u30 droogtraining, 7u30–9u zwemmen

## Competitie 2
Trainers: Dimitry en Jelle

- **Maandag:** 18u–20u, zwemmen
- **Woensdag:** 18u30–20u30, zwemmen
- **Donderdag:** 17u30–18u30, droogtraining
- **Vrijdag:** 16u30–18u, zwemmen
- **Zaterdag:** 7u–9u, zwemmen

## Beloften
Trainers: Dimitry en Jelle

- **Maandag:** 18u–20u, zwemmen
- **Woensdag:** 16u30–18u30, zwemmen
- **Donderdag:** 17u30–18u30 droogtraining, 18u30–20u zwemmen
- **Vrijdag:** 16u30–18u, zwemmen
- **Zaterdag:** 7u–9u, zwemmen

## Competitie 1 Provinciaal
Trainers: Jelle, Lander en Dimitry

- **Woensdag:** 16u30–18u30, zwemmen
- **Vrijdag:** 18u30–20u, zwemmen
- **Zaterdag:** 7u–9u, zwemmen

## Competitie 1 Nationaal
Trainers: Lander en Antoinette

- **Maandag:** 18u–20u, zwemmen
- **Woensdag:** 18u30–20u30, zwemmen
- **Donderdag:** 18u–18u30 droogtraining (zwembad), 18u30–20u zwemmen
- **Vrijdag:** 18u–20u, zwemmen
- **Zaterdag:** 7u–9u, zwemmen

## Kern
Trainers: Lander en Antoinette

- **Maandag:** 18u–20u, zwemmen
- **Dinsdag:** 17u30–18u30 droogtraining, 18u30–20u zwemmen
- **Woensdag:** 18u30–20u30, zwemmen
- **Donderdag:** 18u–18u30 droogtraining (zwembad), 18u30–20u zwemmen
- **Vrijdag:** 18u–20u, zwemmen
- **Zaterdag:** 7u–9u, zwemmen
T],
            ['documenten-competitie', 'Documenten competitie', 'competitie', 2, 1, <<<'T'
- [Huishoudelijk reglement competitieafdeling KHZS (versie 6 – 14.09.2019, pdf)](https://khzs.be/wp-content/uploads/2019/09/Huishoudelijk-reglement-competitieafdeling-KHZS-versie-6-14.09.2019.pdf)
T],
            ['clubrecords', 'Clubrecords', 'competitie', 3, 1, <<<'T'
De clubrecords van HZS per leeftijdscategorie (9–10 jaar, benjamins, miniemen, kadetten, junioren en senioren) en per slag en afstand worden hier opnieuw gepubliceerd.

Bekijk ondertussen de records van de Vlaamse Zwemfederatie Limburg:

- [Records dames](http://www.vzfplim.be/recordsdames.php)
- [Records heren](http://www.vzfplim.be/recordsheren.php)
T],

            // ---------- Club ----------
            ['club', 'Club', null, 3, 1, <<<'T'
Alles over de werking van de Hasseltse Zwemvereniging Spartacus: het bestuur, nuttige links en ons privacybeleid.
T],
            ['bestuur', 'Bestuur', 'club', 1, 1, <<<'T'
- **Voorzitter:** Joeri Klaykens
- **Algemeen secretariaat:** Evi Custers
- **Penningmeester:** Eline Schraepen
- **Algemeen sportsecretariaat:** Nathalie Bos
- **Secretaris zwemschool:** Bart Bielen
- **Coördinator zwemschool:** Jolien Vanhentenrijk
- **Coördinator competitie:** Lander Mulkers
- **Afgevaardigde waterpolo:** Mark Vanoppen
- **Leden:** Björn Scheepers, Jorien Bielen, Marijke Marceniuk

Contact opnemen? Zie de pagina [Contact](?r=page&slug=contact).
T],
            ['links', 'Links', 'club', 2, 1, <<<'T'
- [Belgische Zwemfederatie](http://www.belswim.be/index.php?page=zwemmen)
- [Vlaamse Zwemfederatie vzw](http://zwemfed.be/)
- [Vlaamse Zwemfederatie Limburg](http://www.vzfplim.be/)
- [Swimrankings](https://www.swimrankings.net/)
- [Toptime](http://www.toptime.be/nl/)
T],
            ['privacybeleid', 'Privacybeleid', 'club', 3, 1, <<<'T'
*Dit privacybeleid is een overname van de vorige website (laatst bijgewerkt op 24 mei 2018) en wordt nog herzien voor de nieuwe werking van de club.*

## Toepassingsgebied
Dit beleid geldt voor de diensten van de Hasseltse Zwemvereniging Spartacus. De club is niet verantwoordelijk voor het privacybeleid van andere websites waarnaar wordt verwezen. Wie de website gebruikt, gaat akkoord met dit beleid. De club wil de privacy van alle gebruikers respecteren en persoonlijke informatie vertrouwelijk behandelen.

## Gebruik van verzamelde gegevens
- **Diensten:** bij aanmelding voor een dienst vragen we persoonsgegevens. Die dienen om de dienst uit te voeren en worden opgeslagen op beveiligde servers. Ze worden niet gecombineerd met andere persoonsgegevens.
- **Communicatie:** e-mails en andere berichten aan de club kunnen worden bewaard, om vragen te beantwoorden en verzoeken te verwerken.
- **Cookies:** de website kan cookies gebruiken om te analyseren hoe de site wordt gebruikt, zodat de diensten beter kunnen worden afgestemd.

## Doeleinden en derden
We gebruiken of verzamelen geen gegevens voor andere doeleinden dan hierboven, tenzij we vooraf toestemming hebben gekregen. Gegevens worden niet met derden gedeeld. Intern kunnen gegevens in enkele gevallen worden gedeeld; medewerkers zijn verplicht de vertrouwelijkheid te respecteren.

## Jouw keuzes
Je kunt je persoonsgegevens die je aan de club verstrekte inzien, wijzigen of laten verwijderen. Neem hiervoor contact op met de club. Je kunt cookies in je browser uitschakelen; sommige functies werken dan mogelijk niet correct.

## Contact
Hasseltse Zwemvereniging Spartacus, Koning Boudewijnlaan 22, 3500 Hasselt. Zie ook de pagina [Contact](?r=page&slug=contact).
T],

            // ---------- Contact ----------
            ['contact', 'Contact', null, 4, 1, <<<'T'
Hasseltse Zwemvereniging Spartacus
Koning Boudewijnlaan 22
3500 Hasselt

Vragen over de **zwemschool** gaan naar de secretaris of de coördinator van de zwemschool (Bart Bielen, Jolien Vanhentenrijk). Vragen over de **competitie** gaan naar de coördinator competitie (Lander Mulkers). Alle bestuursleden vind je op de pagina [Bestuur](?r=page&slug=bestuur).

Voor problemen of klachten kun je ook terecht bij het [aanspreekpunt integriteit](?r=page&slug=ombudsman).
T],
            ['ombudsman', 'API – aanspreekpunt integriteit', 'contact', 1, 1, <<<'T'
## Wat doet de API?

De API neemt opmerkingen, suggesties en klachten van leden van de zwemschool en de zwemclub in behandeling. Samen met het lid zoekt de API naar een oplossing, in een constructief en vertrouwelijk kader.

De Vlaamse Zwemfederatie, afdeling Limburg, waartoe onze club behoort, ondertekende de Panathlon-verklaring. Die wil de negatieve kanten van de sport aanpakken: vriendschap en fair play horen bij sportiviteit. Meer informatie op [panathlon-vlaanderen.be](https://www.panathlon-vlaanderen.be/panathlonverklaring-ethiek-jeugdsport/).

## Hoe bereik je de API?

- **Bart Bielen**: gsm +32 96 32 92 52
- **Jolien Vanhentenrijk**: gsm +32 474 60 22 52

Je kunt hen ook altijd persoonlijk aanspreken.
T],
        ];
    }

    /** Voegt ontbrekende pagina's toe (bestaande adressen blijven ongemoeid). @return array{added:int,skipped:int} */
    /** Doelstellingen per groep (naam => tekst); worden enkel ingevuld bij groepen waar nog niets staat. */
    public static function goals(): array
    {
        return [
            'Startersgroep' => <<<'T'
Evaluatie elke twee maanden. We werken op 12,5 m.

- Vijf seconden kunnen drijven op de rug
- Vijf seconden kunnen drijven op de buik
- Aquatisch ademhalen onder de knie hebben (door de neus)
- Kunnen roteren rond de lengte- en de breedteas
- Naar de bodem kunnen zwemmen met behulp van aquatisch ademhalen
- Van de muur kunnen afduwen en een rechte ligging behouden, op de buik en op de rug
- Onderwater van de muur kunnen afduwen en een rechte ligging behouden, op de buik en op de rug
- Het water leren aanvoelen om naar een stuwende beweging toe te werken
- Durven springen in het water
T,
            'Groep 1 – Grijs' => <<<'T'
**Doel:** de zwemmer voert de vaardigheden goed uit over 12,5 meter.

- Basisvaardigheden uit het startersgroepje verder ontwikkelen
- De horizontale ligging op de rug onder controle houden, met een juiste hoofdhouding
- Zich op de rug voortbewegen met een soepele beenslag vanuit de heupen, met gestrekte voeten (12,5 m)
- De armbeweging van de grove rugslag aanleren bij de afslag
- Zich op de buik voortbewegen met een soepele beenslag vanuit de heupen, met gestrekte voeten (12,5 m)
- Zijwaarts ademhalen op de buik beheersen
- Zich voortbewegen met een vlinderbeenslag vanuit de heupen (12,5 m)
- Afzetten tegen de muur in pijlvorm, op de rug en op de buik, met behoud van de gestrekte vorm
- In het water springen via een potloodsprong, met behoud van de gestrekte vorm
T,
            'Groep 2 – Geel' => <<<'T'
**Doel:** correcte uitvoering over 25 meter.

- Basisvaardigheden verder ontwikkelen, aangevuld met de doelen van groep 1
- Een soepele rugslagarmbeweging met gestrekte onderwaterbeweging beheersen
- Een ruwe crawlarmbeweging met gestrekte armen beheersen
- De vlinderbeenslag beheersen, vertrekkend vanuit de heupen
- Een ruwe schoolslagbeenbeweging beheersen
- Het crawl-keerpunt tegen de muur beheersen
- Duiken vanaf de lage kant van de zwembadrand, met behoud van de vormspanning
T,
            'Groep 3 – Oranje' => <<<'T'
**Doel:** goede uitvoering over 25 meter.

- De basisvaardigheden en doelen van groep 1 en 2 verder ontwikkelen
- Een vloeiende rugslagarmbeweging beheersen, met een stuwende onderwaterbeweging (geplooid)
- De crawlarmbeweging verfijnen, met een hoge elleboog en een afgewerkte duwfase
- De schoolslagbeenbeweging verfijnen
- Bij de vlinderslag de hoofdpositie tijdens de beenslag beheersen
- Het crawl-keerpunt beheersen
- Het rugslag-keerpunt beheersen
- Duiken vanaf de hoge zwembadrand en daarbij de lichaamsspanning behouden
T,
            'Groep 4 – Rood' => <<<'T'
**Doel:** goed zwemmen over 50 meter.

- Verdere ontwikkeling van de basisvaardigheden en de doelen van groep 1, 2 en 3
- Een soepele rugslag over 50 m onder de knie hebben
- Een vloeiende crawl over 50 m zwemmen
- De armbeweging van de schoolslag beheersen, samen met de ademhaling
- De armbeweging van de vlinderslag in de afzet beheersen, samen met de ademhaling
- Het keerpunt van de schoolslag beheersen
- Vanaf de startblok duiken en de vormspanning behouden
T,
            'Groep 5 – Groen' => <<<'T'
**Doel:** zwemmen over 100 meter verloopt vlot en correct.

- Verder bouwen op de basisvaardigheden en de doelen van groep 1 tot en met 4
- Een soepele rugslag over 100 m onder de knie hebben
- Een vloeiende crawl over 100 m kunnen zwemmen
- Een vlotte schoolslag over 50 m zwemmen
- De armbeweging van de vlinderslag verfijnen
- De vlinderslag over 25 m beheersen
- Alle keerpunten correct kunnen uitvoeren
- Vanaf de startblok duiken en daarbij een strakke lichaamshouding bewaren
T,
            'Recreatie (12+)' => <<<'T'
- Voortzetting en verfijning van de genormeerde zwemslagen
- Verfijnen van de keerpunten
- Verfijnen van de startduik
- Kennismaking met andere watersporten, zoals waterpolo, duiken en reddend zwemmen
- Werken aan het uithoudingsvermogen
T,
            'Pre-competitie 2' => <<<'T'
Basistechniek aanpakken, nog niet verfijnen. De ruwe vorm van elke slag goed beheersen en 100 m kunnen zwemmen.

## Vormspanning
- Correct afduwen in de juiste pijl, in de juiste slag (rug op de rug, crawl op de buik), met vlinderbenen

## Schoolslag
- Grove armbeweging
- Beenslag, en beenslag combineren met ademhaling
- Neutrale hoofdpositie in de pijl

## Rugslag
- Neutrale hoofdpositie
- 4 tot 6 beentakten combineren met de armen: 25 m zwemmen
- Armwisseling correct uitvoeren, zonder afslag
- Duim uit en pink insteken
- Initiatie rolbewegingen (oefeningen)

## Vlinderslag
- Grove, golvende beweging, snoeken
- Golvende beweging met benen samen en pijlen
- Vormspanning

## Crawl
- Hoofdpositie neutraal, naar beneden, en horizontale ligging
- 4 tot 6 beentakten combineren met de armen: 25 m zwemmen
- Armwisseling correct uitvoeren, zonder ruwe afslag
- Zijwaartse ademhaling, niet draaien op de zij of rug, niet naar het plafond kijken
- Ademen om de 3 slagen
- Armen insteken op schouderhoogte en -breedte, handpalm naar beneden

## Start
- Vormspanning en correcte sprongbeweging
- Stroomlijnen onder water

## Keerpunten
- Tuimelen, en tuimelen tegen de muur: inschatten hoe ver je van de muur moet zijn

## Benaming
- Benaming van de meters gebruiken, combinatie van meters en lengtes gebruiken
T,
            'Pre-competitie 1' => <<<'T'
## Vormspanning
- Bij het begin van elke oefening correct afduwen in een pijlhouding

## Crawl
- Hoofd neutraal naar beneden, lichaam horizontaal
- 50 m zwemmen met een 6-takt beenslag gecombineerd met de armbewegingen
- Armwissel correct uitvoeren, zonder ruwe afslag
- Zijwaarts ademen op het juiste moment en met juiste timing, na elke 3 slagen
- Uitduwen tot het einde en overhaalbeweging onder een hoek van 90°
- Rollen
- Armen insteken op schouderbreedte, wanneer de handpalm bijna het water raakt, en dan doorrollen

## Rugslag
- Hoofd neutraal houden
- 50 m zwemmen met een 6-takt beenslag gecombineerd met de armbewegingen
- Armwissel correct uitvoeren, zonder afslag; duim en pink zijn geen doel
- Rollen vanuit de heupen
- Onder water uitduwen onder een hoek van 90°
- Watergevoel ontwikkelen

## Schoolslag
- Armbeweging: water vatten voor de schouders, versneld naar voren
- Pijlhouding na de armbeweging
- Ademhaling afstemmen op de armbeweging
- Hoofd in lijn met de rug houden, niet "eend" zwemmen

## Vlinderslag
- Beenbeweging in een golvende, continue beweging
- Ademen bij de zachte neerwaartse beweging
- Beginselen van de armbeweging aanleren

## Starten
- Vormspanning en correcte sprongbeweging
- Gestroomlijnd onder water blijven
- Correcte startpositie op de blok en lichaam opspannen

## Keerpunten
- Tegen de muur tuimelen: afstand goed inschatten en in de juiste positie afduwen in een pijl
- Tuimeling snel uitvoeren
- Met twee armen tegen de muur aantikken
- Benen onderin draaien tegen de muur, met juiste ademhaling

## Benaming
- De benaming van meters en lengtes samen gebruiken
T,
        ];
    }

    public static function import(): array
    {
        foreach (self::goals() as $name => $text) {
            Database::exec("UPDATE `groups` SET goals = ? WHERE name = ? AND (goals IS NULL OR goals = '')", [trim($text), $name]);
        }
        $added = $skipped = 0;
        $ids = [];
        foreach (self::pages() as [$slug, $title, $parent, $sort, $menu, $body]) {
            $exists = Database::one('SELECT id FROM pages WHERE slug = ?', [$slug]);
            if ($exists) {
                $ids[$slug] = (int) $exists['id'];
                $skipped++;
                continue;
            }
            $ids[$slug] = Database::insert(
                'INSERT INTO pages (title, slug, body, parent_id, sort_order, status, in_menu, updated_at) VALUES (?,?,?,?,?,?,?,?)',
                [$title, $slug, trim($body), $parent ? ($ids[$parent] ?? null) : null, $sort, 'PUBLISHED', $menu, now()]
            );
            $added++;
        }
        return ['added' => $added, 'skipped' => $skipped];
    }
}
