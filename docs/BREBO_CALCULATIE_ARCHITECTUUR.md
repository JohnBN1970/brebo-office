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

De paragraafstructuur is recursief: een paragraaf kan dus een andere paragraaf als parent hebben. Voor de normale gebruikerswerkplek ondersteunen we maximaal drie paragraafniveaus onder een hoofdgroep. Dit geeft voldoende diepte zonder dat de calculatie onleesbaar wordt.

## Classificatiesysteem op calculatieniveau

Per calculatie wordt één primaire indelingssystematiek gekozen:

- NL-SfB
- STABU
- Eigen

Deze keuze bepaalt hoe hoofdgroepen worden aangemaakt, gecodeerd, gesorteerd en getotaliseerd.

## Hoofdgroep

Een hoofdgroep bevat minimaal classificatiesysteem, code, omschrijving, sorteervolgorde, actieve/inactieve status en optionele eigen BREBO-weergavenaam. Bij NL-SfB en STABU wordt de code uit de betreffende classificatie gekozen. Bij `Eigen` wordt code en omschrijving door BREBO beheerd.

Hoofdgroepen zijn structurele containers. Hun financiële waarde wordt altijd berekend als de som van alle direct onderliggende paragrafen en daarmee indirect alle onderliggende regels.

## Paragraaf

Onder iedere hoofdgroep kunnen één of meer paragrafen worden aangemaakt. Een paragraaf kan vervolgens zelf weer één of meer subparagrafen bevatten.

Een paragraaf heeft als bron `NL-SfB detailcode` of `Eigen`. De paragraaf bevat minimaal parent-hoofdgroep, optionele parent-paragraaf, niveau 1/2/3, bron, code, omschrijving, sorteervolgorde, optionele interne notitie en actieve/inactieve status.

## Regels voor de boomstructuur

- Niveau 1 hangt rechtstreeks onder een hoofdgroep; niveau 2 onder niveau 1; niveau 3 onder niveau 2.
- Alleen een eindparagraaf (leaf) bevat calculatieregels.
- Een paragraaf met subparagrafen is uitsluitend subtotalisatie-/structuurniveau en bevat zelf geen calculatieregels.
- Wanneer een paragraaf later subparagrafen krijgt, worden bestaande regels gecontroleerd naar een eindparagraaf verplaatst.

## Locatieaanduiding

Classificatie en locatie zijn twee afzonderlijke dimensies. Classificatie vertelt wat voor werk het betreft; locatie vertelt waar het werk plaatsvindt. Locatie verwijst zoveel mogelijk naar bestaande canonieke gebouwobjecten en creëert geen tweede gebouwstructuur.

Locatie kan worden vastgelegd op hoofdgroep, paragraaf/subparagraaf of regel en erft naar beneden wanneer een lager niveau geen expliciete afwijkende locatie heeft. Een parent met verschillende onderliggende locaties wordt als `meerdere locaties` weergegeven. Locatie beïnvloedt de financiële classificatietotalisatie niet; locatietotalen zijn een alternatieve analyse uit dezelfde regels.

## Calculatieregel

Een calculatieregel hangt altijd onder de laatste/eindparagraaf in een tak.

### Kostendragers binnen één normale regel

Een normale calculatieregel is bewust **samengesteld**. Er bestaan dus geen verplichte afzonderlijke regeltypen voor arbeid, materiaal, materieel en onderaanneming.

Eén regel kan tegelijk bevatten:

- arbeid;
- materiaal;
- materieel;
- onderaanneming (OA);
- overig.

Voorbeeld:

```text
Bestaand houten kozijn herstellen | 12 st | Arbeid € 2.160 | Materiaal € 780 | Materieel € 120 | OA € 0 | Totaal € 3.060
```

De directe kostprijs van de regel is de som van de ingevulde kostendragers. Niet gebruikte kostendragers blijven nul/leeg. Hierdoor kan een compleet werkonderdeel op één herkenbare regel worden gecalculeerd zonder kunstmatige opsplitsing.

### Regeltypen

`Regeltype` beschrijft het **gedrag van de regel**, niet de kostensoort. De kernset blijft daarom klein:

- `normaal` — financiële calculatieregel; kan alle kostendragers combineren;
- `stelpost` — financieel bedrag met expliciete stelpoststatus en toelichting/verrekening;
- `PM` — pro memorie; zichtbaar en signaleerbaar, maar zonder financieel bedrag in de normale totalisatie;
- `tekst` — toelichting/kop/notitie zonder financiële werking;
- `verrekenbaar` — financiële regel waarbij contracthoeveelheid en werkelijke/verrekenbare hoeveelheid afzonderlijk gevolgd kunnen worden.

Arbeid, materiaal, materieel, OA en overig zijn dus **kostendragers/kolommen**, geen regeltypen.

Het regeltype bepaalt welke aanvullende velden, validaties en signaleringen nodig zijn, maar een financiële regel behoudt waar relevant dezelfde kostendragers.

### Primaire regelweergave

De primaire spreadsheetweergave bevat:

- omschrijving;
- locatie (compact, geërfd of expliciet);
- hoeveelheid;
- eenheid;
- arbeid;
- materiaal;
- materieel;
- OA;
- overig;
- directe kostprijs;
- regeltype/status/signaal.

Specialistische gegevens zoals normuren, uurtarief, afval, prijsbron, leverancier, materiaalcode, btw, memo, RFQ/inkoop, bewijs en volledige locatiecontext verschijnen in regel-detail en niet standaard als losse hoofdkolommen.

## Totalisering

Totalisering volgt bottom-up de classificatieboom:

```text
Kostendragers -> directe kostprijs regel
Calculatieregels -> totaal eindparagraaf
Eindparagrafen -> totaal parent-paragraaf
Parent-paragrafen -> totaal hoofdgroep
Hoofdgroepen -> directe kosten calculatie
```

Parenttotalen en hoofdgroeptotalen zijn altijd afgeleid en nooit vrij handmatig overschrijfbaar. PM- en tekstregels hebben geen bedrag in de normale financiële totalisatie. Stelposten worden financieel meegenomen maar afzonderlijk herkenbaar gehouden. Verrekenbare regels volgen hun ingestelde hoeveelheid-/verrekeningslogica.

Daarboven wordt de commerciële opbouw afzonderlijk getoond:

```text
Directe kosten
+ Algemene kosten
+ Risico
= Kostprijs na risico
+ Winst / marge
+ Commerciële correctie
= Verkoopprijs excl. btw
```

Percentages worden altijd tevens als eurobedrag zichtbaar gemaakt.

## Kostprijs versus offerte

De calculatie en offerte zijn verschillende objecten. Calculatie is technische/financiële onderbouwing; verkoopprijs is commerciële uitkomst; offerte is klantgerichte aanbieding. Een offerte verwijst naar een vastgezette calculatieversie/snapshot en wijzigt nooit stilzwijgend door latere calculatiewijzigingen.

## Versies

Iedere materiële calculatiewijziging is herleidbaar. Vastgestelde versies blijven beschikbaar voor vergelijking en audit, inclusief verschillen in directe kosten, opslagen/marge en verkoopprijs en de reden van revisie.

## Relatie met gebouw en project

Een calculatie blijft gekoppeld aan project en waar relevant werkpakket/scope. Regels en paragrafen kunnen verwijzen naar technische zone, gebouwdeel, woning, productpositie of ander canoniek scopeobject. Classificatie ordent kosten; gebouw/projectscope bepaalt waar het werk plaatsvindt.

## UI-principe

De standaard calculatiewerkplek is een spreadsheetachtige hiërarchische tabel met inklapbare hoofdgroepen/paragrafen, inspringing, live subtotalen, direct bewerkbare regels, compacte locatiekolom, filter/groepering op locatie, regel-detail op aanvraag en een live kostprijsopbouw. Alleen eindparagrafen laten calculatieregels toe. Aparte werkruimten/tabs ondersteunen inkoop/RFQ, risico, varianten, versies en offerte.

## Bestaande data

Bestaande `brebo_calc_line`-velden worden niet verwijderd voordat per veld is vastgesteld of zij behouden, afgeleid, naar regel-detail verplaatst, gemigreerd of verwijderd worden. Bestaande koppelingen met RFQ/inkoop worden bij voorkeur behouden.

Vastgesteld: 15 augustus 2026.
