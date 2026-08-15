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

Bestaand en waardevol. De repository bevat onder andere bestaande velden voor:

- eenheid;
- regeltype;
- memo;
- arbeidstarief;
- normuren;
- eenheidsprijs;
- regelstatus;
- directe kostprijs;
- tariefgroep;
- prijsbron;
- budgeturen;
- regelvolgorde;
- afvalpercentage.

Daarnaast bestaan verwijzingen vanuit RFQ-regels naar `brebo_calc_line`. Dit maakt behoud van stabiele regelidentiteit tijdens de migratie belangrijk.

### Bestaande calculatie-UI/logica

Bekende afhankelijkheden in `brebo_office_core`:

- `src/Form/CalculationGridForm.php`;
- `src/Form/GenerateWorkBudgetForm.php`;
- `src/Form/OfferVersionForm.php`;
- `src/Controller/OfficeController.php`;
- `brebo_office_core.install`;
- calculatie seed/proof tooling.

Deze onderdelen worden niet verwijderd voordat hun verantwoordelijkheid is overgenomen of expliciet vervallen verklaard.

## Doelmodule

Werknaam: `brebo_calculation`.

De module wordt eigenaar van:

- calculatie-object / calculatieversie;
- hoofdgroepen;
- paragrafen niveau 1-3;
- calculatieregels en regelgedrag;
- calculatieparameters;
- rekenmotor en totalisering;
- spreadsheetwerkplek;
- varianten;
- calculatieversies/snapshots;
- calculatiespecifieke risico-/prijsstatus;
- domein-API/services voor offerte, inkoop/RFQ, project en Outputgenerator.

De module wordt nadrukkelijk niet eigenaar van:

- gebouwdata;
- projectmasterdata;
- relatie/contact;
- generieke inkoopobjecten;
- generieke Outputgenerator;
- generieke documentlay-out;
- formele projectvrijgave/KAM-gates.

## Migratieclassificatie

Iedere bestaande calculatie-eigenschap krijgt exact één primaire behandeling:

- `BEHOUDEN` — semantiek en data blijven bruikbaar;
- `VERPLAATSEN` — verantwoordelijkheid verhuist naar `brebo_calculation`, data-identiteit blijft zoveel mogelijk behouden;
- `AFLEIDEN` — niet langer handmatig bronveld; nieuwe waarde wordt berekend uit canonieke brondata;
- `MIGREREN` — inhoud wordt naar een nieuwe structuur/veldset omgezet;
- `DETAIL` — blijft beschikbaar maar verdwijnt uit de primaire spreadsheetkolommen;
- `VERVALLEN` — alleen na bewezen redundantie en gecontroleerde datamigratie.

## Eerste veldmapping `brebo_calc_line`

| Bestaand | Doel | Behandeling | Opmerking |
|---|---|---|---|
| eenheid | regel.eenheid | BEHOUDEN | Primaire spreadsheetkolom |
| regeltype | regel.regeltype | MIGREREN | Normaliseren naar normaal/stelpost/optie/notitie/verdisconterend/verrekenbaar |
| memo | regel.detail.memo | DETAIL | Niet standaard als hoofdkolom |
| arbeidstarief | regel.detail.arbeidstarief | BEHOUDEN/DETAIL | Regel mag parameter/default overschrijven |
| normuren | regel.detail.normuren | BEHOUDEN/DETAIL | Voedt arbeidscomponent |
| eenheidsprijs | berekende/ingevoerde prijsgrondslag | HERBEOORDELEN | Niet blind behouden als enige kostprijsbron |
| regelstatus | regel.status | BEHOUDEN | Voor signaal/controle |
| directe kostprijs | regel.directe_kostprijs | AFLEIDEN | Som van kostendragers; legacy waarde gebruiken voor migratiecontrole |
| tariefgroep | regel.detail.tariefgroep | BEHOUDEN/DETAIL | Verwijst naar parameter/tariefset waar mogelijk |
| prijsbron | regel.detail.prijsbron | BEHOUDEN/DETAIL | Belangrijk voor actualiteit/audit |
| budgeturen | regel.detail/begrotingsuren | BEHOUDEN | Nog toetsen tegen normuren x hoeveelheid |
| regelvolgorde | regel.sortering | BEHOUDEN | Nodig voor spreadsheetvolgorde |
| afvalpercentage | regel.detail.afvalpercentage | BEHOUDEN/DETAIL | Materiaalberekening |

`HERBEOORDELEN` is tijdelijk en moet voor implementatie worden vervangen door een definitieve classificatie na inspectie van de huidige rekenlogica.

## Nieuwe structuur die legacy niet kent

De nieuwe module voegt expliciet toe:

- classificatiesysteem per calculatie: NL-SfB / STABU / Eigen;
- hoofdgroep;
- recursieve paragraafstructuur met maximaal drie UI-niveaus;
- parent/subtotaalregels als afgeleide structuur;
- locatie als afzonderlijke dimensie met overerving;
- samengestelde kostendragers op één regel: arbeid, materiaal, materieel, OA, overig;
- regelgedrag voor stelpost, optie, notitie, verdisconterend en verrekenbaar;
- Parameters-tab;
- open/gesloten calculatiemethodiek;
- staartkosten of enkele marge;
- expliciete opslaggrondslagen;
- prijspeil/indexatie;
- risico-/onzekerheidsregels;
- lock/status/controle;
- versie- en revisiehistorie.

## Identiteit en externe koppelingen

### RFQ / inkoop

Bestaande `brebo_rfq_line -> brebo_calc_line` verwijzingen zijn een harde migratieconstraint. Voorkeur:

1. bestaande `brebo_calc_line` node-identiteit behouden en semantiek uitbreiden; of
2. bij nieuw technisch object een expliciete legacy->nieuw ID-mapping plus migratie van alle references uitvoeren.

Optie 1 heeft voorlopig de voorkeur omdat deze referentiele integriteit en historie het beste beschermt.

### Offerte

Offerteversies mogen na migratie niet naar een stilzwijgend veranderende live calculatie wijzen. Nieuwe koppeling wordt calculatieversie/snapshot -> offerteversie.

### Werkbegroting

`GenerateWorkBudgetForm` wordt onderzocht op welke calculatiegegevens naar project-/werkbegroting worden overgenomen. De nieuwe calculatiemodule levert hiervoor een expliciete service/DTO in plaats van directe kennis van interne velden.

### Gebouw/project

Calculatie verwijst naar project en canonieke gebouw-/scopeobjecten. De calculatiemodule dupliceert geen gebouwstructuur. Locatieverwijzingen worden waar mogelijk objectreferences, met vrije locatie alleen als gecontroleerde uitzondering.

## Extractiestrategie

### Fase A — inventarisatie

- alle calc bundles/velden/config vinden;
- alle PHP-referenties vinden;
- alle routes/menu-links/permissies vinden;
- alle RFQ/inkoop/offerte/werkbegroting-afhankelijkheden vinden;
- huidige rekenformules documenteren;
- bestaande data-aantallen en referenties voorafgaand aan deployment controleren.

### Fase B — zelfstandige module-shell

Maak `brebo_calculation` met eigen:

- `.info.yml`;
- routing;
- permissions;
- services;
- libraries;
- domeininterfaces;
- update/migratiepad.

In deze fase blijft legacy functioneel.

### Fase C — datamodel uitbreiden

Voeg nieuwe structuurvelden/objecten additief toe. Verwijder niets. Maak migratie herhaalbaar/idempotent waar mogelijk.

### Fase D — rekenmotor

Centraliseer berekeningen in services. UI, offerte en werkbegroting mogen daarna niet meer ieder hun eigen calculatieformules bezitten.

### Fase E — spreadsheetwerkplek

Nieuwe werkplek conform UI Design System:

- boomstructuur inklapbaar;
- inline editing;
- zebra-striping;
- sticky headers/structuurkolom;
- locked afgeleide velden;
- detailpaneel voor specialistische velden;
- live totalen;
- toetsenbordbediening;
- Parameters als aparte tab.

### Fase F — adapters

Laat bestaande consumers tijdelijk via adapters/services op de nieuwe calculatiemodule aansluiten:

- RFQ/inkoop;
- offerte;
- werkbegroting;
- Outputgenerator;
- projectcockpit.

### Fase G — omschakeling en opruiming

Pas nadat data-, formule- en referentiecontroles slagen:

- routes omschakelen;
- legacy forms/controllers uit core verwijderen of reduceren;
- legacy velden alleen verwijderen als zij aantoonbaar redundant zijn;
- config export controleren;
- regressietests uitvoeren;
- migratierapport vastleggen.

## Validatiecriteria

Migratie is pas geslaagd wanneer minimaal geldt:

- geen bestaande calculatieregels verloren;
- RFQ-references intact;
- historische offerte-/calculatiecontext herleidbaar;
- totalen voor gemigreerde legacy calculaties verklaarbaar aansluiten;
- verschillen door nieuwe rekenmethodiek expliciet gerapporteerd, nooit verborgen;
- gebouw-/projectscope blijft canoniek;
- nieuwe spreadsheet toont geen overbodige technische velden;
- afgeleide totalen zijn locked;
- versie-/audittrail werkt;
- rollback/backuproute is aantoonbaar beschikbaar voor productiemigratie.

## Eerstvolgende inspectie

1. volledige veldset van `brebo_calc_line`, `brebo_calc_element`, `brebo_calc_component` en eventuele calculatiecontainer vaststellen;
2. huidige formule voor arbeid, materiaal, componenten, receptprijs, contract/prognose en directe kostprijs uitschrijven;
3. alle inkomende en uitgaande entity references vaststellen;
4. daarna definitief logisch datamodel voor `brebo_calculation` vastleggen.

Vastgesteld als migratie-aanpak: 15 augustus 2026.
