# BREBO Office — Generieke Outputgenerator Architectuur

## Doel

De BREBO Outputgenerator is een generieke document- en rapportengine voor heel BREBO Office. Hij is nadrukkelijk niet beperkt tot calculaties of offertes.

De generator haalt gevalideerde brondata uit bestaande BREBO-objecten en presenteert die via herbruikbare, versioneerbare outputmodellen. De generator wordt daarmee één centrale voorziening voor klantdocumenten, interne documenten, rapportages, bevestigingen, brieven en managementoutput.

## Kernscheiding

```text
Bronobject(en)       = inhoudelijke waarheid
Outputmodel          = presentatierecept
Outputgeneratie      = toepassen model op brondata
Outputsnapshot       = vastgelegd resultaat
Distributie          = verzenden/publiceren/opslaan
```

De Outputgenerator verandert nooit de inhoudelijke brondata. Hij selecteert, ordent, formatteert en presenteert uitsluitend.

## Ondersteunde bronnen

Een outputmodel kan één of meerdere canonieke BREBO-objecten als bron gebruiken, bijvoorbeeld:

- relatie/contactpersoon;
- kans/offerte;
- calculatie + calculatieversie;
- inkoop/RFQ/leverancier;
- project;
- werkpakket;
- gebouw;
- technische zone;
- woning/gebruiksobject;
- taak/workflow;
- inspectie/bevinding;
- foto/bewijs;
- oplevering/kwaliteit/KAM;
- MJOP/onderhoudsmaatregel;
- kosten/reserveringen/forecast;
- communicatie/dossierhistorie.

Een model mag samengestelde brondata gebruiken zolang alle velden herleidbaar blijven naar de canonieke bronobjecten.

## Documentfamilies

De Outputgenerator ondersteunt minimaal de volgende families:

### Commercieel

- offerte;
- prijsopgave;
- open begroting;
- gesloten begroting;
- calculatiesamenvatting;
- opties-/stelpostenbijlage;
- hoeveelhedenstaat.

### Inkoop

- inkoopbrief;
- prijsaanvraag/RFQ;
- offerte-uitvraag;
- inkoopopdracht;
- opdrachtbevestiging aan onderaannemer/leverancier;
- inkoopvergelijking/spiegel;
- leveranciersbevestiging;
- afwijkings-/meerwerkbevestiging.

### Project en uitvoering

- opdrachtbevestiging opdrachtgever;
- projectstartbrief;
- werkpakketbrief;
- bewoners-/gebruikersbrief;
- toegangsbrief/afspraakbevestiging;
- weekrapport;
- statusrapport;
- voortgangsrapport;
- look-ahead rapport;
- kwaliteitsrapport;
- opleverrapport;
- restpunten-/punchlist-rapport;
- fotologboek/bewijsrapport;
- revisie-/overdrachtsdocument.

### Vastgoed en onderhoud

- inspectierapport;
- conditierapport;
- gebouwoverzicht;
- MJOP;
- MJOP-managementsamenvatting;
- jaarplan onderhoud;
- meerjarenbegroting;
- kosten-/reserveringsoverzicht;
- onderhoudsadvies;
- scenariovergelijking;
- exploitatie-/TCO-overzicht.

### Management en intern

- managementrapportage;
- projectportfolio-status;
- commerciële funnelrapportage;
- risico-overzicht;
- cash-/forecast-overzicht;
- nacalculatie;
- evaluatierapport;
- audit-/kwaliteitsrapport.

Deze lijst is uitbreidbaar zonder de engine zelf te herschrijven.

## Outputmodel

Een Outputmodel is een herbruikbaar, benoemd en versioneerbaar recept. Het model bevat minimaal:

- modelnaam;
- documentfamilie;
- bronobjecttype(n);
- doelgroep/ontvangerstype;
- taal;
- huisstijlprofiel;
- hoofdstukken/secties;
- databronnen per sectie;
- zichtbare velden;
- sortering/groepering;
- filters/selectieregels;
- totalisatie-/samenvattingsregels;
- vaste teksten en conditionele teksten;
- tabel-/grafiek-/beeldblokken;
- paginering en documentmetadata;
- outputformaat;
- modelstatus en versie.

## Blokkenmodel

Outputmodellen worden opgebouwd uit herbruikbare blokken, bijvoorbeeld:

- titelblok;
- adres-/ontvangerblok;
- project-/gebouwidentiteit;
- managementsamenvatting;
- vrije/vaste tekst;
- dynamische tekst op basis van condities;
- tabel;
- gegroepeerde tabel;
- totalenblok;
- KPI-blok;
- grafiek;
- foto/fotogalerij;
- fotomarkering/bewijs;
- planning/tijdlijn;
- risico-/statusblok;
- handtekening/goedkeuringsblok;
- bijlagenlijst;
- voorwaarden/footer.

Zo kan dezelfde engine een formele brief, een statusrapport en een MJOP opbouwen uit verschillende combinaties van dezelfde bouwstenen.

## Modelvarianten

Een basisoutputmodel kan varianten kennen zonder duplicatie van het volledige model, bijvoorbeeld:

- intern / extern;
- compact / uitgebreid;
- opdrachtgever / bewoner / leverancier;
- open / gesloten;
- Nederlands / Engels;
- management / technisch;
- concept / definitief.

Een variant erft van het basismodel en overschrijft alleen afwijkende blokken, velden of zichtbaarheid.

## Conditionele inhoud

De generator ondersteunt voorwaarden zoals:

```text
ALS stelposten aanwezig -> toon sectie Stelposten
ALS risico rood -> toon risico-waarschuwing
ALS project bewoners heeft -> voeg bewonerscommunicatieblok toe
ALS MJOP maatregel onzekerheidsklasse D/E -> toon onzekerheid en reserveringsmarge
ALS opdrachtbedrag > grens -> voeg goedkeuringsblok toe
```

Conditionele presentatie verandert de brondata niet.

## Outputformaten

De architectuur moet meerdere uitvoerformaten ondersteunen vanuit hetzelfde model, minimaal:

- PDF;
- DOCX/bewerkbaar document waar functioneel gewenst;
- HTML/webweergave;
- e-mailbody/brieftekst;
- spreadsheet/export voor tabulaire output waar passend;
- printweergave.

Niet ieder outputmodel hoeft ieder formaat te ondersteunen.

## Snapshot en audit

Een gegenereerde definitieve output wordt als snapshot vastgelegd met minimaal:

- output-id/documentnummer;
- documentfamilie;
- gebruikte bronobject-id's;
- relevante bronversies/snapshots;
- outputmodel-id + modelversie;
- gebruikte variant;
- filters/selecties;
- gegenereerd door;
- datum/tijd;
- outputformaat;
- bestandsreferentie/hash;
- status: concept / vastgesteld / verzonden / vervallen;
- eventueel ontvanger/distributiecontext.

Een wijziging van brondata of model wijzigt nooit stilzwijgend een bestaande vastgestelde output.

## Distributie

Generatie en distributie blijven gescheiden. Na generatie kan een output, afhankelijk van mandaat en workflow:

- in dossier worden opgeslagen;
- als concept worden aangeboden;
- ter controle/goedkeuring worden voorgelegd;
- via e-mail worden verzonden;
- beschikbaar worden gesteld in portaal;
- als bijlage aan communicatie worden gekoppeld;
- worden vervangen door een nieuwe versie zonder de oude te verwijderen.

## Relatie met workflows

De Outputgenerator kan door processen worden aangeroepen, maar beslist niet zelfstandig over materiële goedkeuring. Voorbeelden:

```text
RFQ gereed -> genereer concept inkoopbrief
Opdracht gewonnen -> genereer concept opdrachtbevestiging
Weekafsluiting -> genereer statusrapport
MJOP vastgesteld -> genereer managementsamenvatting + volledig MJOP
Oplevering gereed -> genereer opleverrapport
```

Een workflow mag automatische conceptgeneratie starten. Definitief vaststellen/verzenden volgt bestaande mandaten en goedkeuringsregels.

## Huisstijl

Huisstijl is centraal en los van inhoudelijke modellen beheerd. Denk aan:

- BREBO-logo/merkprofiel;
- typografie;
- kleurgebruik;
- voorblad;
- kop-/voettekst;
- tabellen;
- grafieken;
- paginanummering;
- juridische/footerteksten.

Daardoor kan een huisstijlwijziging gecontroleerd worden uitgerold zonder alle inhoudelijke outputmodellen opnieuw te ontwerpen.

## Hergebruik in BREBO Office

De Outputgenerator is een platformvoorziening. Functionele modules leveren brondata en eventueel domeinspecifieke blokken, maar bouwen geen eigen losstaande documentgenerator tenzij technisch aantoonbaar noodzakelijk.

Voorbeelden:

```text
Calculatie -> Outputgenerator -> begroting/offertebijlage
Inkoop -> Outputgenerator -> inkoopbrief/RFQ/opdracht
Project -> Outputgenerator -> status-/voortgangsrapport
Gebouw/MJOP -> Outputgenerator -> MJOP/managementsamenvatting
KAM/Oplevering -> Outputgenerator -> kwaliteits-/opleverrapport
```

## Eerstvolgende ontwerpstap

Voor implementatie moeten minimaal worden ontworpen:

1. outputmodel-entiteit;
2. outputmodelversie;
3. blokdefinitie en blokconfiguratie;
4. bronmapping/selectieregels;
5. conditionele zichtbaarheid;
6. outputgeneratie-job;
7. outputsnapshot/documentregister;
8. format renderers (PDF/DOCX/HTML/etc.);
9. goedkeurings-/distributiekoppeling;
10. modelbeheer-UI met preview.

Vastgesteld: 15 augustus 2026.
