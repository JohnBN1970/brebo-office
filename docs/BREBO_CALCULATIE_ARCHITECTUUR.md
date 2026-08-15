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

Iedere calculatie krijgt een aparte tab **Parameters**. Hier wordt niet het werk zelf gecalculeerd, maar wordt vastgelegd **hoe deze calculatie rekent, presenteert en commercieel wordt opgebouwd**.

Parameters zijn onderdeel van de calculatieversie. Een wijziging van een materiële parameter veroorzaakt herberekening en moet auditbaar zijn.

### 1. Calculatiemodus

- `Open` — transparante calculatie waarbij de relevante opbouw richting opdrachtgever kan worden getoond;
- `Gesloten` — interne calculatie; opdrachtgever ontvangt uitsluitend de afgesproken commerciële prijs/opbouw;
- optioneel `Intern open / extern gesloten` als praktische presentatievariant: intern volledige openheid, externe output beperkt.

Open/gesloten bepaalt **presentatie en output**, niet of BREBO intern minder detail vastlegt. Intern blijft de volledige kostprijs altijd beschikbaar.

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

Iedere component toont minimaal percentage, eurobedrag, berekeningsgrondslag en volgorde. Hierdoor is zichtbaar of een percentage bijvoorbeeld over directe kosten, kostprijs na AK of een andere vastgelegde basis wordt berekend.

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

Regels kunnen aangeven dat een bepaalde opslag niet van toepassing is. Het systeem moet afwijkingen expliciet zichtbaar houden.

### 4. Arbeid en tarieven

- standaard arbeidsuurtarief of tariefset;
- eventuele tariefgroepen;
- standaard norm-/urenmethodiek;
- loon-/prijspeildatum;
- eventuele toeslagregels voor bijzondere werktijden of omstandigheden.

Een regel mag hiervan afwijken, maar een afwijking moet herkenbaar zijn en de gebruikte bron/tariefdatum bewaren.

### 5. Prijspeil en indexatie

- prijspeildatum calculatie;
- gewenste geldigheidsdatum;
- indexatie wel/niet toepassen;
- indexatiemethode/bron wanneer toegepast;
- indexatie tot uitvoeringsmoment of offertepeil waar relevant.

Prijspeil en indexatie blijven gescheiden: prijspeil beschrijft de basis van de gebruikte prijzen; indexatie beschrijft de correctie naar een ander moment.

### 6. BTW en afronding

- standaard BTW-regime/tarief voor output waar relevant;
- bedragen intern standaard excl. BTW;
- afronding per regel, subtotaal en eindprijs;
- commerciële eindafronding optioneel afzonderlijk vastleggen;
- afrondingsverschil altijd controleerbaar en nooit verborgen in de rekenregels.

### 7. Opties, stelposten en verrekenbare posten

Parameters bepalen de standaard behandeling:

- opties standaard opgenomen of niet opgenomen;
- stelposten wel/niet in basisprijs;
- aparte totaalweergave van stelposten;
- standaard verrekeningsgrondslag voor verrekenbare regels;
- standaard presentatie in offerte/output.

Individuele regels mogen hiervan gecontroleerd afwijken.

### 8. Verdiscontering

Voor verdisconterende regels wordt de standaard verdeelmethode ingesteld, bijvoorbeeld:

- naar rato directe kostprijs;
- naar rato hoeveelheid;
- naar rato arbeid;
- naar rato verkoopwaarde;
- handmatige gewichten;
- geselecteerde doelregels/paragrafen.

Het verdeelde bedrag moet altijd volledig aansluiten op de bronregel. Verschil door afronding wordt expliciet gecorrigeerd en auditbaar vastgelegd.

### 9. Inkoop- en prijsbronbeleid

- voorkeursvolgorde prijsbronnen;
- maximale ouderdom van prijzen voordat een signaal ontstaat;
- leverancier/offerte verplicht vanaf een instelbare drempel of risicoklasse;
- prijs zonder actuele bron wel/niet toegestaan;
- RFQ/inkoopstatus meenemen als calculatiewaarschuwing.

Dit is een kwaliteitsparameter en geen automatische blokkade tenzij BREBO dat expliciet instelt.

### 10. Risico en onzekerheid

Naast een algemeen risicopercentage kan de calculatie werken met expliciete risico-items. Parameters bepalen:

- centraal risicopercentage wel/niet actief;
- risico op regelniveau wel/niet toegestaan;
- dubbel tellen van centraal en lokaal risico voorkomen;
- onzekerheids-/prijsstatus zichtbaar maken;
- drempel voor waarschuwing op onbeprijsde of onzekere scope.

### 11. Output en commerciële zichtbaarheid

Voor open/gesloten calculaties wordt vastgelegd welke gegevens in externe output mogen verschijnen:

- hoeveelheden;
- eenheidsprijzen;
- kostendragers;
- subtotalen;
- staartkosten;
- marge/winst;
- stelposten;
- opties;
- notities;
- locatie;
- classificatiecodes.

Externe zichtbaarheid verandert nooit de interne waarheid; het is uitsluitend een presentatiefilter.

### 12. Calculatiestatus en lock

De parameter-tab toont tevens:

- concept / in calculatie / ter controle / vastgesteld / vervallen;
- calculator/eigenaar;
- controledatum en controleur;
- actieve versie;
- reden revisie;
- lockstatus.

Een vastgestelde calculatieversie wordt niet stilzwijgend aangepast. Materiële wijzigingen leiden tot een nieuwe versie of expliciete heropening volgens mandaat.

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

De calculatie en offerte zijn verschillende objecten. Calculatie is technische/financiële onderbouwing; verkoopprijs is commerciële uitkomst; offerte is klantgerichte aanbieding. Een offerte verwijst naar een vastgezette calculatieversie/snapshot en wijzigt nooit stilzwijgend door latere calculatiewijzigingen.

## Versies

Iedere materiële calculatiewijziging is herleidbaar. Vastgestelde versies blijven beschikbaar voor vergelijking en audit, inclusief wijzigingen in parameters. Een versieverschil moet dus niet alleen gewijzigde regels tonen, maar ook gewijzigde marge-, risico-, prijspeil- en outputparameters.

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
- `Offerte`.

## Bestaande data

Bestaande `brebo_calc_line`-velden worden niet verwijderd voordat per veld is vastgesteld of zij behouden, afgeleid, naar regel-detail verplaatst, gemigreerd of verwijderd worden. Bestaande koppelingen met RFQ/inkoop worden bij voorkeur behouden.

Vastgesteld: 15 augustus 2026.
