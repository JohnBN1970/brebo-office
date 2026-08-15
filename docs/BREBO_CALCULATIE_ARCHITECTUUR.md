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

Een hoofdgroep bevat minimaal:

- classificatiesysteem;
- code;
- omschrijving;
- sorteervolgorde;
- actieve/inactieve status;
- optionele eigen BREBO-weergavenaam.

Bij NL-SfB en STABU wordt de code uit de betreffende classificatie gekozen. Bij `Eigen` wordt de code en omschrijving door BREBO beheerd.

Hoofdgroepen zijn structurele containers. Hun financiële waarde wordt altijd berekend als de som van alle direct onderliggende paragrafen en daarmee indirect alle onderliggende regels.

## Paragraaf

Onder iedere hoofdgroep kunnen één of meer paragrafen worden aangemaakt. Een paragraaf kan vervolgens zelf weer één of meer subparagrafen bevatten.

Een paragraaf heeft twee mogelijke bronnen:

1. `NL-SfB detailcode` — een verdere NL-SfB-code, waaronder het gewenste vierde codeniveau wanneer dat voor de gekozen NL-SfB-structuur van toepassing is;
2. `Eigen` — vrije, door BREBO beheerde paragraafcode en omschrijving.

De paragraaf bevat minimaal:

- parent-hoofdgroep;
- optionele parent-paragraaf;
- niveau: 1, 2 of 3;
- bron: `nlsfb_detail` of `eigen`;
- paragraafcode;
- paragraafomschrijving;
- sorteervolgorde;
- optionele interne notitie;
- actieve/inactieve status.

Een calculatie mag dus bijvoorbeeld een NL-SfB-hoofdstructuur hebben met daaronder deels NL-SfB-detailparagrafen en deels praktische eigen paragrafen, ook gemengd over meerdere niveaus.

## Regels voor de boomstructuur

- Een paragraaf niveau 1 hangt rechtstreeks onder een hoofdgroep.
- Een paragraaf niveau 2 hangt onder een paragraaf niveau 1.
- Een paragraaf niveau 3 hangt onder een paragraaf niveau 2.
- Alleen een eindparagraaf (leaf) bevat calculatieregels.
- Een paragraaf die subparagrafen bevat, is uitsluitend een subtotalisatie-/structuurniveau en bevat zelf geen calculatieregels.
- Wanneer een paragraaf later subparagrafen krijgt, moeten bestaande regels eerst gecontroleerd naar een eindparagraaf worden verplaatst; BREBO Office mag niet stilzwijgend een gemengde structuur creëren.

Hierdoor blijft iedere euro exact herleidbaar en is er nooit twijfel of een bedrag uit regels of uit onderliggende paragrafen bestaat.

## Calculatieregel

Een calculatieregel hangt altijd onder de laatste/eindparagraaf in een tak.

De primaire regelweergave bevat alleen de gegevens die nodig zijn om snel te calculeren:

- omschrijving;
- hoeveelheid;
- eenheid;
- arbeid;
- materiaal;
- materieel;
- onderaanneming;
- overig;
- directe kostprijs;
- status/signaal.

Specialistische velden zoals normuren, uurtarief, afval, prijsbron, leverancier, materiaalcode, btw, memo, RFQ/inkoop en bewijs worden in regel-detail getoond en niet standaard als losse hoofdkolommen.

## Totalisering

Totalisering volgt altijd bottom-up de volledige boom.

Voorbeeld:

```text
Hoofdgroep 31                         € 125.000
  Paragraaf 31.1                     € 80.000
    Paragraaf 31.1.1                 € 50.000
      Regels                         € 50.000
    Paragraaf 31.1.2                 € 30.000
      Regels                         € 30.000
  Paragraaf 31.2                     € 45.000
    Regels                           € 45.000
```

De regels zijn dus:

```text
Calculatieregels
  -> totaal eindparagraaf
  -> totaal parent-paragraaf
  -> totaal eventueel hogere parent-paragraaf
  -> totaal hoofdgroep
  -> directe kosten calculatie
```

Een parent-paragraaf is altijd exact de som van zijn directe child-paragrafen. Een eindparagraaf is altijd exact de som van zijn onderliggende calculatieregels. De hoofdgroep is altijd exact de som van zijn directe paragrafen.

Totalen worden afgeleid en zijn nooit vrij handmatig overschrijfbaar.

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

De calculatie en offerte zijn verschillende objecten.

- calculatie = technische en financiële kostprijsonderbouwing;
- verkoopprijs = commerciële uitkomst van de calculatie;
- offerte = klantgerichte aanbieding met scope, voorwaarden, geldigheid, uitsluitingen en aangeboden prijs.

Een offerte verwijst naar een vastgezette calculatieversie/snapshot. Een latere calculatiewijziging wijzigt een reeds uitgegeven offerte nooit stilzwijgend.

## Versies

Iedere materiële calculatiewijziging moet herleidbaar zijn. Vastgestelde versies blijven beschikbaar voor vergelijking en audit.

De gebruikerswerkplek moet minimaal kunnen tonen:

- actieve versie;
- eerdere versies;
- verschil in directe kosten;
- verschil in opslagen/marge;
- verschil in verkoopprijs;
- reden van revisie.

## Relatie met gebouw en project

Een calculatie blijft gekoppeld aan project en waar relevant werkpakket/scope. Regels kunnen aanvullend verwijzen naar technische zone, gebouwdeel, woning, productpositie of ander canoniek scopeobject wanneer dit nodig is voor hoeveelheden, uitvoering of nacalculatie.

Classificatie vervangt dus niet de gebouwstructuur. Beide dimensies blijven naast elkaar bestaan:

- classificatie = hoe kosten worden geordend;
- gebouw/projectscope = waar het werk plaatsvindt.

## UI-principe

De standaard calculatiewerkplek is een spreadsheetachtige hiërarchische tabel:

- hoofdgroepen inklapbaar;
- paragraafniveau 1, 2 en 3 afzonderlijk inklapbaar;
- inspringing maakt de hiërarchie direct zichtbaar;
- parent-paragrafen tonen hun live subtotalen;
- alleen eindparagrafen tonen/toestaan dat calculatieregels worden toegevoegd;
- regels direct bewerkbaar;
- totalen per eindparagraaf, parent-paragraaf en hoofdgroep live zichtbaar;
- detailvelden alleen op aanvraag;
- rechter- of onderpaneel met live kostprijsopbouw;
- aparte werkruimten/tabs voor inkoop/RFQ, risico, varianten, versies en offerte.

## Bestaande data

De bestaande `brebo_calc_line`-velden worden niet verwijderd voordat per veld is vastgesteld of zij:

- behouden worden;
- een afgeleide waarde worden;
- naar regel-detail verhuizen;
- worden gemigreerd;
- of vervallen.

Bestaande koppelingen met RFQ/inkoop worden bij voorkeur behouden.

Vastgesteld: 15 augustus 2026.
