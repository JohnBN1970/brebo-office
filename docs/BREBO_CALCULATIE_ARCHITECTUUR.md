# BREBO Office — Calculatiearchitectuur

## Status

Dit document legt de nieuwe functionele hoofdstructuur van de BREBO Office-calculatie vast. De bestaande rekenvelden en koppelingen worden waar zinvol hergebruikt, maar de gebruikerswerkplek wordt opnieuw opgebouwd rond een herkenbare calculatiestructuur.

## Kernprincipe

De calculatie toont niet het onderliggende Drupal-datamodel. De gebruiker werkt in een hiërarchische calculatieopbouw:

```text
Calculatie
  -> Hoofdgroep
      -> Paragraaf niveau 1
          -> Paragraaf niveau 2 (optioneel)
              -> Paragraaf niveau 3 (optioneel)
                  -> Calculatieregels
```

De paragraafstructuur is recursief. Voor de normale gebruikerswerkplek ondersteunen we maximaal drie paragraafniveaus onder een hoofdgroep.

## Classificatiesysteem op calculatieniveau

Per calculatie wordt één primaire indelingssystematiek gekozen:

- NL-SfB
- STABU
- Eigen

## Hoofdgroep en paragrafen

Hoofdgroepen en paragrafen vormen de structurele calculatieboom. Hoofdgroepen worden uit NL-SfB, STABU of een eigen BREBO-structuur gekozen. Paragrafen kunnen uit NL-SfB-detail of een eigen invulling komen en maximaal drie niveaus diep worden genest.

Alleen een eindparagraaf bevat calculatieregels. Parent-paragrafen en hoofdgroepen zijn uitsluitend structuur- en subtotalisatieniveaus.

## Locatieaanduiding

Classificatie en locatie zijn twee afzonderlijke dimensies. Classificatie vertelt wat voor werk het betreft; locatie vertelt waar het werk plaatsvindt. Locatie verwijst zoveel mogelijk naar bestaande canonieke gebouwobjecten en creëert geen tweede gebouwstructuur.

Locatie kan worden vastgelegd op hoofdgroep, paragraaf/subparagraaf of regel en erft naar beneden wanneer een lager niveau geen expliciete afwijkende locatie heeft. Locatie beïnvloedt de primaire financiële classificatietotalisatie niet; locatietotalen zijn een alternatieve analyse uit dezelfde regels.

## Calculatieregel

Een calculatieregel hangt altijd onder de laatste/eindparagraaf in een tak.

Een normale regel kan tegelijk arbeid, materiaal, materieel, onderaanneming (OA) en overig bevatten. Deze zijn kostendragers, geen regeltypen.

### Regeltypen

- `normaal` — standaard financiële calculatieregel;
- `stelpost` — telt mee maar blijft afzonderlijk herkenbaar en totaliseerbaar;
- `optie` — volledig doorgerekend, standaard buiten de basisprijs totdat expliciet opgenomen;
- `notitie` — tekst zonder financiële werking;
- `verdisconterend` — bronbedrag wordt volgens een verdeelsleutel over geselecteerde regels verdeeld zonder dubbele telling;
- `verrekenbaar` — contracthoeveelheid en werkelijke/verrekenbare hoeveelheid worden afzonderlijk gevolgd.

### Primaire regelweergave

De spreadsheet toont minimaal omschrijving, locatie, regeltype, hoeveelheid, eenheid, arbeid, materiaal, materieel, OA, overig, directe kostprijs en status/signaal. Specialistische gegevens verschijnen in regel-detail.

## Totalisering

```text
Kostendragers -> directe kostprijs regel
Calculatieregels -> totaal eindparagraaf
Eindparagrafen -> totaal parent-paragraaf
Parent-paragrafen -> totaal hoofdgroep
Hoofdgroepen -> directe kosten calculatie
```

Totalen zijn altijd afgeleid en niet handmatig overschrijfbaar.

## Tab Parameters

Iedere calculatie krijgt een aparte tab **Parameters**. Hier wordt vastgelegd **hoe de calculatie rekent en commercieel wordt opgebouwd**. Outputpresentatie hoort nadrukkelijk niet in deze tab.

Parameters zijn onderdeel van de calculatieversie. Een wijziging van een materiële parameter veroorzaakt herberekening en moet auditbaar zijn.

### 1. Calculatiemodus

- `Open` — calculatiemethodiek waarbij transparante prijsopbouw mogelijk is;
- `Gesloten` — calculatiemethodiek waarbij BREBO commercieel met een gesloten prijsopbouw werkt;
- `Intern open / extern gesloten` kan als praktische commerciële werkvorm worden ondersteund.

Open/gesloten is een eigenschap van de calculatie/commerciële methodiek. Welke onderdelen uiteindelijk in een document zichtbaar worden, wordt door de Outputgenerator bepaald.

### 2. Commerciële prijsopbouw

Per calculatie wordt één hoofdmethodiek gekozen:

- `Staartkosten` — afzonderlijke commerciële/indirecte componenten boven op de directe kosten;
- `Enkele marge` — één gecombineerde commerciële opslag/marge op de gekozen grondslag.

Bij `Staartkosten` kunnen componenten afzonderlijk worden geactiveerd en geconfigureerd, bijvoorbeeld:

- algemene kosten / AK;
- project-/bouwplaatskosten voor zover niet als directe calculatieregels opgenomen;
- risico/onvoorzien;
- winst;
- commerciële correctie;
- overige expliciete staartcomponenten.

Iedere component toont minimaal percentage, eurobedrag, berekeningsgrondslag en volgorde.

Bij `Enkele marge` wordt minimaal vastgelegd:

- marge/opslagpercentage;
- berekeningsgrondslag;
- resulterend eurobedrag;
- doel/verantwoording indien afwijkend van de standaard.

### 3. Opslaggrondslag en uitzonderingen

Parameters bepalen waarop opslagen worden berekend:

- alle directe kosten;
- alleen geselecteerde kostendragers;
- inclusief/exclusief stelposten;
- inclusief/exclusief opgenomen opties;
- inclusief/exclusief OA;
- uitzonderingen per hoofdgroep/paragraaf/regel wanneer gemotiveerd.

### 4. Arbeid en tarieven

- standaard arbeidsuurtarief of tariefset;
- eventuele tariefgroepen;
- standaard norm-/urenmethodiek;
- loon-/prijspeildatum;
- eventuele toeslagregels voor bijzondere werktijden of omstandigheden.

### 5. Prijspeil en indexatie

- prijspeildatum calculatie;
- geldigheids-/referentiedatum;
- indexatie wel/niet toepassen;
- indexatiemethode/bron;
- indexatie tot uitvoeringsmoment of offertepeil waar relevant.

Prijspeil en indexatie blijven gescheiden.

### 6. BTW en afronding

- intern standaard excl. BTW;
- standaard BTW-regime/tarief waar relevant;
- afronding per regel, subtotaal en eindprijs;
- commerciële eindafronding optioneel afzonderlijk;
- afrondingsverschillen altijd controleerbaar.

### 7. Opties, stelposten en verrekenbare posten

Parameters bepalen de standaard rekenkundige behandeling:

- opties standaard opgenomen of niet opgenomen;
- stelposten wel/niet in basisprijs;
- standaard verrekeningsgrondslag voor verrekenbare regels.

Individuele regels mogen hiervan gecontroleerd afwijken.

### 8. Verdiscontering

Voor verdisconterende regels wordt de standaard verdeelmethode ingesteld, bijvoorbeeld:

- naar rato directe kostprijs;
- naar rato hoeveelheid;
- naar rato arbeid;
- naar rato verkoopwaarde;
- handmatige gewichten;
- geselecteerde doelregels/paragrafen.

Het verdeelde bedrag moet altijd volledig aansluiten op de bronregel; dubbele telling is verboden.

### 9. Inkoop- en prijsbronbeleid

- voorkeursvolgorde prijsbronnen;
- maximale ouderdom van prijzen voordat een signaal ontstaat;
- leverancier/offerte gewenst of verplicht vanaf ingestelde drempel/risicoklasse;
- prijs zonder actuele bron wel/niet toegestaan;
- RFQ/inkoopstatus als calculatiewaarschuwing.

### 10. Risico en onzekerheid

- centraal risicopercentage wel/niet actief;
- risico op regelniveau wel/niet toegestaan;
- dubbel tellen van centraal en lokaal risico voorkomen;
- onzekerheids-/prijsstatus zichtbaar maken;
- drempel voor waarschuwing op onbeprijsde of onzekere scope.

### 11. Calculatiestatus en lock

De parameter-tab toont tevens:

- concept / in calculatie / ter controle / vastgesteld / vervallen;
- calculator/eigenaar;
- controledatum en controleur;
- actieve versie;
- reden revisie;
- lockstatus.

Een vastgestelde calculatieversie wordt niet stilzwijgend aangepast.

## Outputgenerator

Output wordt een **aparte generieke BREBO Outputgenerator** en geen verzameling zichtbaarheidsschakelaars in de calculatieparameters.

De Outputgenerator gebruikt de calculatie als brondata, maar bepaalt zelfstandig **hoe die data in een specifiek documentmodel wordt gepresenteerd**.

### Outputmodel

Een outputmodel is een herbruikbaar en versioneerbaar sjabloon/profiel. Voorbeelden:

- interne calculatie volledig;
- interne calculatiesamenvatting;
- open-begroting opdrachtgever;
- gesloten prijsopgave;
- offerte met hoofdgroepen;
- offerte met paragrafen;
- stelposten- en optiebijlage;
- hoeveelhedenstaat;
- inkoop-/RFQ-spiegel;
- projectbegroting;
- nacalculatievergelijking.

BREBO kan eigen modellen aanmaken, kopiëren, wijzigen, vastleggen en versiebeheer geven. Een model kan organisatiebreed beschikbaar zijn of projectspecifiek worden gemaakt.

### Per model instelbaar

Een outputmodel bepaalt onder andere:

- welke hoofdgroepen/paragraafniveaus worden getoond;
- hoeveelheden wel/niet;
- eenheden wel/niet;
- eenheidsprijzen wel/niet;
- kostendragers arbeid/materiaal/materieel/OA/overig wel/niet;
- directe kostprijs, verkoopprijs of beide;
- staartkosten en margecomponenten wel/niet;
- stelposten en opties opnemen, uitsluiten of als aparte sectie tonen;
- notitieregels wel/niet;
- locatie wel/niet;
- NL-SfB/STABU/eigen codes wel/niet;
- subtotale niveaus;
- afronding/presentatieniveau;
- titelblokken, begeleidende teksten en voorwaarden;
- sortering en groepering;
- huisstijl/documentlay-out.

### Model versus snapshot

Het outputmodel is het herbruikbare recept. Een gegenereerde output is een **vastgelegde snapshot** van:

- gebruikte calculatieversie;
- gebruikte outputmodelversie;
- datum/tijd;
- gebruiker;
- toegepaste selectie/filters;
- gegenereerde documentversie.

Een later gewijzigd outputmodel verandert dus nooit stilzwijgend een eerder uitgegeven document.

### Scheiding van verantwoordelijkheden

```text
Calculatie + Parameters = financiële waarheid en rekenmethodiek
Outputmodel             = presentatierecept
Outputsnapshot           = vastgelegd document/resultaat
Offerte                  = commercieel klantdocument dat een outputmodel kan gebruiken
```

Hierdoor kan dezelfde calculatie zonder duplicatie meerdere betrouwbare outputs opleveren.

## Commerciële opbouw

De exacte eindopbouw volgt de gekozen parameters. Een staartkostenmodel kan bijvoorbeeld zijn:

```text
Directe kosten
+ Algemene kosten
+ Project-/bouwplaatskosten
+ Risico / onvoorzien
= Kostprijs
+ Winst
+ Commerciële correctie
= Verkoopprijs excl. BTW
```

Bij enkele marge:

```text
Directe kosten / gekozen kostprijsgrondslag
+ Enkele commerciële marge
= Verkoopprijs excl. BTW
```

BREBO Office toont bij iedere stap zowel percentage als eurobedrag.

## Kostprijs versus offerte

De calculatie en offerte zijn verschillende objecten. Calculatie is technische/financiële onderbouwing; verkoopprijs is commerciële uitkomst; offerte is klantgerichte aanbieding. Een offerte verwijst naar een vastgezette calculatieversie/snapshot en kan voor haar documentopbouw een vastgelegde versie van een Outputmodel gebruiken.

## Versies

Iedere materiële calculatiewijziging is herleidbaar. Vastgestelde versies blijven beschikbaar voor vergelijking en audit, inclusief wijzigingen in parameters. Outputmodellen kennen zelfstandig versiebeheer.

## Relatie met gebouw en project

Een calculatie blijft gekoppeld aan project en waar relevant werkpakket/scope. Classificatie ordent kosten; gebouw/projectscope bepaalt waar het werk plaatsvindt.

## UI-principe

De calculatiewerkplek is nadrukkelijk spreadsheetachtig en niet formulierachtig. Hoofdgroepen/paragrafen zijn inklapbaar, regels inline bewerkbaar, kolomkoppen sticky en normale regels gebruiken subtiele zebra-striping. Structurele subtotalen doorbreken deze striping en zijn duidelijk maar rustig herkenbaar.

De hoofdwerkruimte krijgt minimaal tabs voor:

- `Calculatie` — spreadsheet;
- `Parameters` — reken- en commerciële instellingen;
- `Inkoop/RFQ`;
- `Risico`;
- `Varianten`;
- `Versies`;
- `Output` — opent/gebruikt de generieke Outputgenerator;
- `Offerte` — commerciële aanbieding, desgewenst gevoed door een outputmodel.

## Bestaande data

Bestaande `brebo_calc_line`-velden worden niet verwijderd voordat per veld is vastgesteld of zij behouden, afgeleid, naar regel-detail verplaatst, gemigreerd of verwijderd worden. Bestaande koppelingen met RFQ/inkoop worden bij voorkeur behouden.

Vastgesteld: 15 augustus 2026.
