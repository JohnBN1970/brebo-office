# BREBO Office — Calculatie migratiekaart

## Status

Werkdocument voor gecontroleerde extractie van calculatiefunctionaliteit uit `brebo_office_core` naar een zelfstandig calculatiedomein/module.

Leidend functioneel model: `docs/BREBO_CALCULATIE_ARCHITECTUUR.md`.

## Hoofdregel

Geen big-bang migratie. Bestaande data en koppelingen blijven intact totdat de nieuwe module aantoonbaar dezelfde of betere functionele dekking heeft en de migratie gecontroleerd is uitgevoerd.

```text
Inventariseren -> mappen -> nieuwe module naast bestaand -> migreren -> valideren -> omschakelen -> legacy opruimen
```

## Huidige kernobjecten

### `brebo_calc_line`

Bestaand en waardevol. Reeds aangetroffen velden/gedrag omvatten onder andere eenheid, regeltype, memo, arbeidstarief, normuren, eenheidsprijs, regelstatus, directe kostprijs, tariefgroep, prijsbron, budgeturen, regelvolgorde, afvalpercentage, contracthoeveelheid, werkelijke hoeveelheid, urenmodus, posttype en kostencategorie.

Daarnaast bestaan verwijzingen vanuit RFQ-regels en werkbegrotingsregels naar `brebo_calc_line`. Behoud van stabiele regelidentiteit heeft daarom de voorkeur.

### `brebo_calc_element`

Wordt in de huidige werkbank als `recept` gebruikt. Het element heeft onder andere calculatiereferentie, NL-SfB-componentreferentie, technische zone, recepthoeveelheid, recepteenheid en volgorde. In het nieuwe model wordt deze semantiek niet één-op-één overgenomen; bruikbare classificatie- en locatiegegevens worden gemigreerd naar hoofdgroep/paragraaf/location-context.

### `brebo_calc_component`

Wordt gebruikt als NL-SfB-hoofdcomponent/groepering. Moet worden beoordeeld als bron voor de nieuwe hoofdgroep/classificatiestructuur, niet automatisch als definitief doelobject.

## Bestaande calculatie-UI/logica in core

Bekende afhankelijkheden:

- `src/Form/CalculationGridForm.php`;
- `src/Form/CalculationChatForm.php`;
- `src/Form/GenerateWorkBudgetForm.php`;
- `src/Form/OfferVersionForm.php`;
- `src/Controller/OfficeController.php`;
- `brebo_office_core.install`;
- `brebo_office_core.routing.yml`;
- calculatie seed/proof tooling;
- artikelprijs-snapshot `brebo_calculation_article_snapshot`.

Deze onderdelen worden niet verwijderd voordat hun verantwoordelijkheid is overgenomen of expliciet vervallen verklaard.

## Huidige rekenlogica — vastgesteld uit `CalculationGridForm`

De legacy werkbank rekent nu op hoofdlijnen als volgt:

```text
contractbedrag regel = contracthoeveelheid x eenheidsprijs
prognosehoeveelheid =
  werkelijke hoeveelheid, indien posttype = Verrekenpost en werkelijk is ingevuld
  anders contracthoeveelheid
prognosebedrag regel = prognosehoeveelheid x eenheidsprijs
receptcontract = som contractbedragen van niet-notitieregels
receptprognose = som prognosebedragen van niet-notitieregels
recepturen = som budgeturen van niet-notitieregels
recept-eenheidsprijs = receptcontract / recepthoeveelheid, indien recepthoeveelheid > 0
```

Belangrijk: de huidige primaire regel heeft één `kostencategorie` uit Arbeid/Materiaal/Materieel/Onderaanneming/Overig. Dit wijkt af van het nieuwe ontwerp waarin één normale regel meerdere kostendragers tegelijk kan bevatten.

De huidige `post_type` kent minimaal `Vaste post`, `Stelpost`, `Verrekenpost`, `Optie`, `Alternatief`, `Meer-/minderwerk`. De huidige `line_type` kent minimaal `Calculatieregel`, `Verdisconteerd`, `Notitie`. Deze twee legacy dimensies worden samengebracht in de nieuwe compacte regelgedragsset, zonder kostendragers tot regeltypen te maken.

### Concurrentiecontrole

De legacy grid heeft al nuttige optimistic concurrency: een hash over changed-times van calculatie, componenten, elementen en regels blokkeert opslaan als iemand anders tussentijds heeft gewijzigd. Dit principe moet worden behouden in de nieuwe spreadsheetwerkplek.

### Artikel-/prijsbron snapshot

Bij selectie uit `brebo_article` wordt op calculatieregelniveau een immutable snapshot vastgelegd met artikel, leverancier, leveranciersartikelnummer, nettoprijs, prijsdatum en catalogusimport. Dit is waardevolle auditdata en moet behouden blijven, bij voorkeur achter een calculatie-domeinservice in plaats van directe databasekennis in de UI.

## Werkbegroting-overdracht — vastgesteld

`GenerateWorkBudgetForm` maakt alleen vanuit een calculatie met status `Vastgesteld` of `Definitief budget` een bevroren werkbegroting voor exact dezelfde calculatieversie.

De overdracht:

- koppelt werkbegroting aan calculatie en werkpakket;
- bevriest versienummer en baselinedatum;
- maakt per relevante calculatieregel een werkbegrotingsregel;
- slaat een terugverwijzing `field_brebo_calc_line_ref` op;
- neemt budgeturen over;
- slaat notitieregels over;
- slaat opties en alternatieven standaard over;
- neemt bij materiaalregels materiaalomschrijving, hoeveelheid, eenheid, materiaalcode/spec, afval, verpakking en voorkeursleverancier mee;
- kopieert geen verkoopprijzen, opslagen of marge naar uitvoerdersregels.

Dit is een sterke domeingrens die behouden moet blijven: calculatie levert een gecontroleerde uitvoeringsbaseline, werkbegroting blijft een project-/uitvoeringsobject.

Nieuwe doelrichting: `brebo_calculation` levert een versiegebonden `WorkBudgetBaseline`/DTO/service; de werkbegrotingsmodule hoeft geen interne calculatievelden te kennen.

## Offerte-overdracht — vastgesteld

`OfferVersionForm` maakt nu een versiegebonden commercieel object vanuit een calculatie. Het bevat echter ook presentatielogica die volgens de nieuwe architectuur naar de generieke Outputgenerator moet verhuizen.

Legacy offerte bevat onder andere:

- offertenummer en offerteversie;
- status en geldigheid;
- layoutkeuze;
- prijsdetailniveau open/halfopen/gesloten/regie;
- gegenereerde commerciële teksten;
- indeling van calculatieregels naar Basisaanbieding/Optie/Stelpost/Verrekenpost;
- btw- en G-rekeninginstellingen.

Nieuwe grens:

- calculatie levert versie/snapshot, bedragen en regelgedrag;
- offerte blijft commercieel object met scope, voorwaarden, geldigheid en besluitstatus;
- visuele layout, prijsdetailpresentatie en documentmodel gaan naar Outputgenerator/Outputmodel;
- reeds uitgegeven offerte blijft gebonden aan calculatieversie + outputmodelversie.

## Calculatiechat

Er bestaat een eenvoudige `brebo_calculation_chat` tabel/formulier voor interne berichten bij een calculatie. Deze functionaliteit is niet financieel maar wel calculatiecontext. Bij extractie wordt beslist of dit naar een generieke dossier-/communicatielaag verhuist of tijdelijk via een adapter blijft bestaan. Geen tweede communicatie-eiland bouwen.

## Doelmodule

Werknaam: `brebo_calculation`.

De module wordt eigenaar van calculatie-object/versie, hoofdgroepen, paragrafen niveau 1-3, calculatieregels en gedrag, calculatieparameters, rekenmotor/totalisering, spreadsheetwerkplek, varianten, snapshots en calculatiespecifieke risico-/prijsstatus.

Niet van calculatie: gebouwmasterdata, projectmasterdata, relaties, generieke inkoop, generieke Outputgenerator/documentlay-out, formele projectvrijgave/KAM.

## Migratieclassificatie

- `BEHOUDEN` — semantiek en data blijven bruikbaar;
- `VERPLAATSEN` — verantwoordelijkheid verhuist, identiteit blijft zoveel mogelijk behouden;
- `AFLEIDEN` — nieuwe waarde wordt berekend;
- `MIGREREN` — inhoud wordt naar nieuwe structuur omgezet;
- `DETAIL` — blijft beschikbaar buiten primaire grid;
- `VERVALLEN` — alleen na bewezen redundantie en gecontroleerde migratie.

## Eerste veldmapping `brebo_calc_line`

| Bestaand | Doel | Behandeling | Opmerking |
|---|---|---|---|
| contracthoeveelheid | regel.hoeveelheid | BEHOUDEN | Primaire spreadsheetkolom |
| werkelijke hoeveelheid | regel.verrekenbare_hoeveelheid | BEHOUDEN/CONDITIONEEL | Relevant bij verrekenbaar |
| eenheid | regel.eenheid | BEHOUDEN | Primaire spreadsheetkolom |
| line_type | regel.regeltype | MIGREREN | Calculatieregel/Verdisconteerd/Notitie naar nieuw gedrag |
| post_type | regel.regeltype/status | MIGREREN | Stelpost/Optie/Verrekenpost etc. consolideren |
| kostencategorie | legacy migratiehint | MIGREREN | Niet behouden als exclusieve kostendrager |
| memo | regel.detail.memo | DETAIL | Niet standaard als hoofdkolom |
| arbeidstarief | regel.arbeid/detail | BEHOUDEN/DETAIL | Parameter/default mag worden overschreven |
| normuren | regel.arbeid/detail | BEHOUDEN/DETAIL | Voedt arbeidscomponent |
| budgeturen | regel.arbeid/budgeturen | BEHOUDEN | Uitvoeringsbaseline gebruikt dit al |
| eenheidsprijs | legacy prijsbasis | MIGREREN | Wordt per kostendrager/prijsgrondslag herijkt |
| regelstatus | regel.status | BEHOUDEN | Signaal/controle |
| directe kostprijs | regel.directe_kostprijs | AFLEIDEN | Nieuwe som kostendragers; legacy waarde voor reconciliatie |
| tariefgroep | regel.detail.tariefgroep | BEHOUDEN/DETAIL | Koppelen aan tariefset |
| prijsbron | regel.detail.prijsbron | BEHOUDEN/DETAIL | Actualiteit/audit |
| regelvolgorde | regel.sortering | BEHOUDEN | Spreadsheetvolgorde |
| afvalpercentage | regel.materiaal/detail | BEHOUDEN/DETAIL | Materiaalberekening |
| artikelprijs-snapshot | regel.prijsbewijs | BEHOUDEN/VERPLAATSEN | Immutable bronbewijs |

## Nieuwe structuur die legacy niet kent

Toevoegen: classificatiesysteem NL-SfB/STABU/Eigen, hoofdgroep, paragraafboom tot drie UI-niveaus, locatie met overerving, samengestelde kostendragers arbeid/materiaal/materieel/OA/overig, regelgedrag normaal/stelpost/optie/notitie/verdisconterend/verrekenbaar, Parameters-tab, staartkosten/enkele marge, grondslagen, prijspeil/indexatie, risico, lock/status/controle en echte versie-/revisiehistorie.

## Identiteit en externe koppelingen

### RFQ / inkoop

Bestaande `brebo_rfq_line -> brebo_calc_line` verwijzingen zijn harde migratieconstraint. Voorkeur blijft bestaande `brebo_calc_line` node-identiteit behouden en additief uitbreiden, tenzij een technische blocker dit onmogelijk maakt.

### Werkbegroting

Behoud terugverwijzing naar calculatieregel en introduceer versiegebonden baseline-service. Geen commerciële marge/opslagen naar uitvoeringsregels.

### Offerte

Nieuwe koppeling: calculatieversie/snapshot -> offerteversie. Presentatiekeuzes uit legacy `OfferVersionForm` verschuiven naar Outputgenerator.

### Gebouw/project

Locatie verwijst naar canonieke gebouw-/projectscope; geen duplicaat gebouwmodel.

## Extractiestrategie

A. inventarisatie en formule-/reference-map;
B. zelfstandige `brebo_calculation` module-shell naast legacy;
C. additief datamodel/migratievelden;
D. centrale rekenmotor/services;
E. nieuwe spreadsheetwerkplek;
F. adapters voor RFQ, offerte, werkbegroting, Outputgenerator en projectcockpit;
G. omschakeling, reconciliatie, regressietest en legacy opruimen.

## Validatiecriteria

- geen calculatieregels verloren;
- RFQ- en werkbegrotingsreferences intact;
- historische offerte-/calculatiecontext herleidbaar;
- legacy contract/prognose/recepttotalen reproduceerbaar voor reconciliatie;
- verschillen door nieuwe kostendragerlogica expliciet gerapporteerd;
- immutable artikelprijs-snapshots behouden;
- gebouw-/projectscope canoniek;
- afgeleide totalen locked;
- concurrency/versieconflicten veilig afgehandeld;
- rollback/backuproute aantoonbaar beschikbaar.

## Eerstvolgende inspectie

1. volledige config/veldset `brebo_calc_element`, `brebo_calc_component`, `brebo_calculation` vaststellen;
2. arbeid/normuren/budgeturen-logica buiten de grid lokaliseren;
3. alle routes/services/menu/permissies en inkomende references inventariseren;
4. daarna definitief logisch datamodel en module-shell bouwen.

Bijgewerkt: 15 augustus 2026.
