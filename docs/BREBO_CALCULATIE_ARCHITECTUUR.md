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

## Deelcalculaties

Een **deelcalculatie** is een herbruikbare, afgebakende calculatiescope binnen dezelfde financiële waarheid. Een deelcalculatie kopieert calculatieregels niet onnodig, maar verwijst naar een gecontroleerde set regels, paragrafen of structurele selectie uit de broncalculatie.

Voorbeelden:

- woningtype A / B / C;
- gevel noord;
- kozijnpakket;
- steigerwerk;
- bouwdeel of technische zone;
- fase 1 / fase 2;
- inkooppakket schilderwerk;
- meerwerkpakket.

Een deelcalculatie heeft minimaal:

- naam en code;
- type/doel;
- status;
- broncalculatie en bronversie;
- geselecteerde structuur/regels;
- hoeveelheid/vermenigvuldigingsfactor;
- optionele koppeling aan gebouwobject, woningtype, technische zone, werkpakket of projectfase;
- eigen subtotalen per kostendrager en verkoopprijs;
- auditbare herkomst en versie.

### Woningtype als herbruikbare toepassing

Voor woningtypen geldt een specifiek vast patroon:

```text
Woningtype
  -> concrete woningen die onder dit type vallen
  -> gekoppelde deelcalculatie per 1 woningtype-eenheid
  -> aantal woningen
  -> vermenigvuldigd projecttotaal
  -> expliciete uitzonderingen per individuele woning
```

Voorbeeld:

```text
Woningtype A
  24 woningen
  deelcalculatie per woning: € 42.850
  projecttotaal type A: 24 x € 42.850 = € 1.028.400
```

De concrete woningen achter de factor blijven altijd aantoonbaar. Het systeem bewaart dus niet alleen `aantal = 24`, maar ook welke woningen tot woningtype A behoren. Hierdoor kunnen afwijkingen per woning afzonderlijk worden gemodelleerd zonder 24 calculaties te dupliceren.

Standaard geldt:

- de deelcalculatie bevat de standaard technische en financiële scope per type-eenheid;
- projecttoepassing bepaalt het aantal concrete exemplaren;
- individuele afwijkingen worden als expliciete correctie/variant op de betreffende woning vastgelegd;
- één wijziging aan de onderliggende typecalculatie kan gecontroleerd doorwerken naar alle nog niet vastgezette toepassingen;
- vastgestelde project-/offerteversies veranderen nooit stilzwijgend mee;
- woningtype en woningidentiteit komen uit het canonieke gebouw-/projectmodel en worden niet opnieuw in calculatie gemodelleerd.

### Geen dubbele waarheid

Een deelcalculatie is geen losstaand duplicaat van dezelfde calculatieregels. Waar hergebruik mogelijk is, blijft de bronregel één financiële waarheid. Pas wanneer een projecttoepassing bewust wordt losgemaakt of vastgezet ontstaat een versie/snapshot met eigen auditspoor.

### Commerciële behandeling

Een deelcalculatie erft standaard de commerciële parameters van de broncalculatie. Een afwijkende commerciële behandeling is alleen toegestaan als expliciete, auditbare override. Zo blijft duidelijk of een verkoopprijs is ontstaan uit:

- dezelfde AK/risico/winst-parameters als de hoofdcalculatie;
- een specifieke deelcalculatie-override;
- een project-/offertescenario.

## Hoeveelheidswerelden: begroot, voorbereid en gerealiseerd

BREBO Office behandelt hoeveelheden niet als één veld met steeds de nieuwste waarheid. Er zijn drie afzonderlijke, auditbare werelden:

```text
Begroot      = calculatiehoeveelheid / aanbieding
Voorbereid   = technisch uitgewerkte bestel- en productiehoeveelheid
Gerealiseerd = werkelijk geleverd, verwerkt, gemonteerd of uitgevoerd
```

Vast principe:

**Begroot ≠ Voorbereid ≠ Gerealiseerd.**

Geen van deze werelden overschrijft een andere stilzwijgend. Een verschil is informatie en moet juist zichtbaar blijven.

### Begroot

De begrote hoeveelheid is de hoeveelheid waarop calculatie, kostprijs en offerte zijn gebaseerd. De bron kan bijvoorbeeld zijn:

- opname;
- tekening;
- woningtype;
- kengetal;
- meetstaat;
- handmatige calculatie-inschatting;
- afgeleide hoeveelheid uit het gebouwmodel.

De bron, datum en eventuele onzekerheid van de calculatiehoeveelheid blijven herleidbaar.

### Voorbereid

De voorbereide hoeveelheid ontstaat tijdens werkvoorbereiding uit definitieve technische uitwerking. Voorbeelden:

- definitieve maatvoering;
- stuklijst;
- zaaglijst;
- legplan;
- plaatoptimalisatie;
- handelslengte-optimalisatie;
- bestelstaat;
- productielijst.

Een voorbereide hoeveelheid mag als **controle of wijzigingsvoorstel** naast de begrote hoeveelheid worden gelegd, maar verandert een vastgestelde calculatie nooit automatisch.

### Gerealiseerd

De gerealiseerde hoeveelheid volgt uit de uitvoering en kan meerdere werkelijkheden bevatten, bijvoorbeeld:

- besteld;
- geleverd;
- verwerkt/gemonteerd;
- retour;
- restmateriaal;
- uitval/afval;
- meer-/minderwerk.

Hierdoor kan BREBO Office niet alleen nacalculeren, maar ook verklaren waar afwijkingen zijn ontstaan.

### Afwijkingsanalyse

BREBO Office moet per relevante regel of scope de keten kunnen tonen:

```text
Begroot      850,0 m¹
Voorbereid   947,7 m¹
Geleverd     972,0 m¹
Verwerkt     931,0 m¹
Rest/uitval   41,0 m¹
```

Afwijkingen worden niet alleen als euroverschil gepresenteerd, maar waar mogelijk voorzien van oorzaak, bijvoorbeeld maatwijziging, opnameverschil, snijverlies, uitvoeringsverlies, extra scope of bewonersafwijking.

## Stuk-, zaag- en productielijsten

Zaaglijsten zijn geen los einddocument van de calculatie. Zij zijn een output van een generieke **hoeveelheden-/productiemotor** die technisch gekoppeld is aan gebouwobjecten, woningtypen, deelcalculaties en werkvoorbereidingsdata.

Dezelfde motor kan onder andere leveren:

- stuklijsten;
- zaaglijsten;
- materiaalstaten;
- bestelstaten;
- handelslengte-optimalisatie;
- plaatoptimalisatie;
- productielijsten;
- bewerkingslijsten;
- hoeveelhedenstaten voor calculatiecontrole.

Een zaaglijst kan minimaal bevatten:

- object-/onderdeelreferentie;
- woningtype/woning/zone;
- materiaal en profiel;
- netto maat;
- bruto maat;
- aantal;
- zaaghoek/bewerking waar relevant;
- zaagsnede/kerf;
- handelslengte of plaatformaat;
- geoptimaliseerde indeling;
- restlengte/restplaat;
- berekend afvalpercentage;
- totale bestel-/productiehoeveelheid.

### Relatie met calculatie

De hoeveelheden-/productiemotor mag calculatie voeden met een onderbouwd voorstel voor materiaalhoeveelheid, materiaalverlies en waar beschikbaar bewerkings-/montagenormen. Dit is echter altijd broninformatie voor de **begrote** wereld en geen automatische vervanging van een bestaande, laat staan vastgestelde, calculatiehoeveelheid.

Bij woningtypen kan één technische stuk-/zaaglijst per type worden vermenigvuldigd over de concrete woningen, waarna afwijkende woningen expliciet als afwijking worden verwerkt.

### Lerende gegevens

Vergelijking tussen begroot, voorbereid en gerealiseerd vormt de basis voor latere kengetallen en normverbetering. Werkelijke projectdata mag toekomstige calculaties ondersteunen, maar wordt nooit blind als nieuw calculatiekengetal aangenomen zonder context en beoordeling.

## Totalisering

```text
Kostendragers -> directe kostprijs regel
Calculatieregels -> totaal eindparagraaf
Eindparagrafen -> totaal parent-paragraaf
Parent-paragrafen -> totaal hoofdgroep
Hoofdgroepen -> directe kosten calculatie
Deelcalculatie-eenheid x toepassingsaantal -> deelcalculatietotaal project
Deelcalculaties + overige projectregels -> projectcalculatie
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

Prijsbron en kostendrager zijn afzonderlijke begrippen. Een externe prijs kan na controle naar arbeid, materiaal, materieel, OA of overig worden geboekt. Een steigerprijs kan bijvoorbeeld materieel zijn, terwijl uitbesteed schilderwerk OA is.

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
- woningtype-/deelcalculatieoverzicht;
- stelposten- en optiebijlage;
- hoeveelhedenstaat;
- zaag-/stuklijst;
- inkoop-/RFQ-spiegel;
- projectbegroting;
- nacalculatievergelijking.

BREBO kan eigen modellen aanmaken, kopiëren, wijzigen, vastleggen en versiebeheer geven. Een model kan organisatiebreed beschikbaar zijn of projectspecifiek worden gemaakt.

### Per model instelbaar

Een outputmodel bepaalt onder andere:

- welke hoofdgroepen/paragraafniveaus worden getoond;
- welke deelcalculaties/woningtypen worden getoond;
- concrete woningen per woningtype wel/niet tonen;
- aantallen en vermenigvuldigingsfactoren wel/niet tonen;
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

Voor woningtype-output moet één document bijvoorbeeld automatisch kunnen tonen: typeomschrijving, concrete woningen, aantal, technische scope, calculatie per woningtype-eenheid, totaal per woningtype, afwijkende woningen, prijsbronnen waar relevant en het totaal van het project.

### Model versus snapshot

Het outputmodel is het herbruikbare recept. Een gegenereerde output is een **vastgelegde snapshot** van:

- gebruikte calculatieversie;
- gebruikte deelcalculatie-/toepassingsversies;
- gebruikte outputmodelversie;
- datum/tijd;
- gebruiker;
- toegepaste selectie/filters;
- gegenereerde documentversie.

Een later gewijzigd outputmodel of gewijzigde deelcalculatie verandert dus nooit stilzwijgend een eerder uitgegeven document.

### Scheiding van verantwoordelijkheden

```text
Calculatie + Parameters = financiële waarheid en rekenmethodiek
Deelcalculatie          = herbruikbare geselecteerde financiële scope
Projecttoepassing       = concrete objecten + aantal/vermenigvuldiging
Hoeveelhedenmotor       = technische hoeveelheden/productiegegevens per wereld
Outputmodel             = presentatierecept
Outputsnapshot          = vastgelegd document/resultaat
Offerte                 = commercieel klantdocument dat een outputmodel kan gebruiken
```

Hierdoor kan dezelfde brondata zonder duplicatie betrouwbare calculatie-, werkvoorbereidings-, productie- en documentoutputs opleveren.

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

Iedere materiële calculatiewijziging is herleidbaar. Vastgestelde versies blijven beschikbaar voor vergelijking en audit, inclusief wijzigingen in parameters. Deelcalculaties en projecttoepassingen kennen eveneens versie-/snapshotgedrag zodra zij materieel in een vastgestelde calculatie of output worden gebruikt. Outputmodellen kennen zelfstandig versiebeheer.

## Relatie met gebouw en project

Een calculatie blijft gekoppeld aan project en waar relevant werkpakket/scope. Classificatie ordent kosten; gebouw/projectscope bepaalt waar het werk plaatsvindt.

Woningtypen en concrete woningen worden niet opnieuw als calculatieobject uitgevonden. Calculatie koppelt aan de canonieke gebouw-/projectobjecten. De deelcalculatie beschrijft de financiële scope per type-eenheid; de projecttoepassing legt vast welke concrete woningen/gebouweenheden die scope gebruiken en met welke factor.

De hoeveelheden-/productiemotor gebruikt dezelfde canonieke objectreferenties. Daarmee kunnen begrote, voorbereide en gerealiseerde hoeveelheden naast elkaar worden gelegd zonder een parallel gebouwmodel te maken.

## UI-principe

De calculatiewerkplek is nadrukkelijk spreadsheetachtig en niet formulierachtig. Hoofdgroepen/paragrafen zijn inklapbaar, regels inline bewerkbaar, kolomkoppen sticky en normale regels gebruiken subtiele zebra-striping. Structurele subtotalen doorbreken deze striping en zijn duidelijk maar rustig herkenbaar.

De hoofdwerkruimte krijgt minimaal tabs voor:

- `Calculatie` — spreadsheet;
- `Deelcalculaties` — herbruikbare scopes, woningtypen en projecttoepassingen;
- `Hoeveelheden` — vergelijking begroot/voorbereid/gerealiseerd en toegang tot stuk-/zaag-/productielijsten;
- `Parameters` — reken- en commerciële instellingen;
- `Inkoop/RFQ`;
- `Risico`;
- `Varianten`;
- `Versies`;
- `Output` — opent/gebruikt de generieke Outputgenerator;
- `Offerte` — commerciële aanbieding, desgewenst gevoed door een outputmodel.

## Bestaande data

Bestaande `brebo_calc_line`-velden worden niet verwijderd voordat per veld is vastgesteld of zij behouden, afgeleid, naar regel-detail verplaatst, gemigreerd of verwijderd worden. Bestaande koppelingen met RFQ/inkoop worden bij voorkeur behouden.

Vastgesteld: 16 augustus 2026.
