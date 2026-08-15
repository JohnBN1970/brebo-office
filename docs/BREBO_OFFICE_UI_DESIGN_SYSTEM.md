# BREBO Office UI Design System

## Status

Dit document is leidend voor de presentatie van BREBO Office. Functionele modules bepalen inhoud en gedrag; het centrale `brebo_office` theme en dit design system bepalen hoe informatie wordt gepresenteerd.

Doel: rust, voorspelbaarheid, lage cognitieve belasting en consistente bediening over alle modules heen.

## Bestaande basis

Het bestaande custom theme `web/themes/custom/brebo_office` blijft de centrale presentatielaag.

Vaste basis:

- UI-lettertype: Roboto; Arial als tijdelijke fallback.
- Documentlettertype: Verdana.
- Geen module-eigen visuele taal tenzij dit design system expliciet een uitzondering toestaat.
- Geen parallelle themes per functionele module.

## Ontwerpprincipes

1. Informatiehiërarchie gaat vóór volledigheid op één scherm.
2. Toon eerst wat nodig is om te sturen of besluiten; secundaire details pas op aanvraag.
3. Eén object = één herkenbare pagina-opbouw.
4. Eén status = overal dezelfde semantiek en visuele behandeling.
5. Primaire acties zijn beperkt en voorspelbaar geplaatst.
6. Tabellen zijn alleen voor vergelijken/scannen; dossiers zijn voor begrijpen; cockpits zijn voor sturen.
7. Mobiel gebruik op de bouw is een eersteklas scenario.
8. Presentatie mag nooit de canonieke datamodellen dupliceren.
9. Toegankelijkheid, toetsenbordbediening en duidelijke focusstates zijn verplicht.
10. Presentatiegereed betekent: begrijpelijk zonder uitleg door de bouwer van het scherm.

## Vaste schermtypes

### 1. Cockpit / dashboard

Gebruik voor sturing en uitzonderingen.

Vaste opbouw:

- Objectheader met naam, code, status en context.
- Maximaal 4–6 primaire KPI-kaarten.
- Uitzonderingen en blokkades boven reguliere voortgang.
- Actielijst direct onder KPI's.
- Detailtabellen pas daarna.
- Geen volledige datadumps in de cockpit.

Voorbeelden: projectcockpit, toegangscockpit, look-ahead, financieel overzicht.

### 2. Detaildossier

Gebruik voor één object: gebouw, project, woning, relatie, werkpakket.

Vaste opbouw:

- Titel + objectcode + status.
- Contextregel: bovenliggend object / project / gebouw.
- Primaire acties rechtsboven.
- Tabnavigatie voor hoofdonderwerpen.
- Samenvatting bovenaan elke tab.
- Historie/audit als aparte sectie of tab.

### 3. Lijst / tabel

Gebruik voor scannen, filteren, sorteren en bulkselectie.

Regels:

- Sticky kolomkoppen waar zinvol.
- Maximaal 7–9 zichtbare kernkolommen; overige via kolomkeuze/detail.
- Filters boven de lijst, niet verspreid door het scherm.
- Status als badge, niet als losse tekstkolom waar mogelijk.
- Acties niet vermenigvuldigen per rij; gebruik contextmenu voor secundaire acties.

### 4. Kanban

Gebruik alleen wanneer statusflow of werkvoorraad centraal staat.

- Kolommen corresponderen met echte workflowstatussen.
- Geen kanban voor objecten zonder duidelijke statusflow.
- Kaarten tonen alleen kerninformatie en eerstvolgende actie.

### 5. Formulier

- Groepeer velden per logisch onderwerp.
- Toon conditionele velden alleen wanneer relevant.
- Maximaal één primaire submitactie.
- Leg uit waarom informatie nodig is wanneer dat niet vanzelfsprekend is.
- Technische-zone-logica bepaalt welke woning/ruimte/elementvelden zichtbaar of verplicht zijn.

### 6. Look-ahead / risico-overzicht

- Tijd tot gebeurtenis prominent.
- Blokkadeoorzaak naast signaal tonen.
- Rood/oranje/groen nooit als enige informatiedrager; altijd tekst/icoon erbij.
- Doorklik naar bron en oplossing.

### 7. Foto / bewijsweergave

- Origineel bewijsobject blijft onveranderd.
- Annotaties zijn afzonderlijke lagen.
- Voor/na, datum, maker, locatie/context en gekoppeld dossierobject zichtbaar.
- Mobiel: grote aanraakdoelen, eenvoudige markeringstools, zo min mogelijk chrome.

## Navigatie

### Globaal

Hoofdnavigatie bevat alleen primaire domeinen, bijvoorbeeld:

- Relaties
- Gebouwen
- Projecten
- Commercie
- Taken
- Documenten
- Rapportages

Modulefuncties verschijnen binnen de context van het object, niet allemaal als losse hoofdnavigatie-items.

### Objectnavigatie

Gebouw en project krijgen vaste tabstructuren. Nieuwe modules voegen alleen een tab toe als de informatie op dat objectniveau werkelijk thuishoort.

Tablabels zijn kort, zelfstandig begrijpelijk en consistent.

## Statussemantiek

Statussen hebben één functionele betekenis over BREBO Office heen.

Basisset:

- Gereed / akkoord: positief afgerond.
- Open / actief: werk loopt of wacht op uitvoering.
- Aandacht: actie vereist maar niet direct blokkerend.
- Geblokkeerd: uitvoering kan niet verantwoord verder.
- Afgekeurd: formeel negatief beoordeeld.
- N.V.T.: bewust niet van toepassing.
- Onbekend: informatie ontbreekt; nooit verwarren met N.V.T.

Rood/oranje/groen zijn signalen, geen vervanging van de onderliggende status.

## Kleurgebruik

Kleur ondersteunt betekenis, maar tekst en iconografie blijven leidend.

- Groen: gereed/positief.
- Oranje: aandacht/risico/opvolging.
- Rood: blokkade/afkeur/kritiek risico.
- Grijs: neutraal, onbekend of N.V.T. afhankelijk van label.

Gebruik kleur spaarzaam. Grote gekleurde vlakken alleen voor uitzonderingen die directe aandacht vereisen.

## Typografie en informatiedichtheid

- Roboto voor applicatie-UI.
- Duidelijke hiërarchie: paginatitel, sectietitel, kaarttitel, body, metadata.
- Geen overmatig vet of hoofdletters.
- Lange technische toelichtingen inklapbaar of in detailweergave.
- Metadata compact en visueel ondergeschikt.

## Acties

Per scherm:

- Eén primaire actie.
- Maximaal 2–3 zichtbare secundaire acties.
- Overige acties in een contextmenu.
- Destructieve acties visueel en tekstueel onderscheiden.
- Formele besluiten zoals release-gate-goedkeuring nooit verbergen tussen algemene acties.

## KPI-kaarten

Een KPI-kaart bevat:

- kerngetal;
- korte titel;
- optioneel trend/signaal;
- doorklik naar onderliggende lijst.

Geen KPI-kaart wanneer het getal geen stuurwaarde heeft.

## Tabellen

- Uitlijning en kolomvolgorde zijn per objecttype vast.
- Datums consequent in Nederlands formaat voor gebruikersweergave.
- Codes/ID's alleen prominent wanneer operationeel nodig.
- Lange teksten niet volledig in tabelcellen; gebruik samenvatting + detail.
- Sorteren en groeperen waar dit de workflow ondersteunt.

## Formulierregels voor technische scope

Niet alle technische informatie is standaard zichtbaar.

De technische zone bepaalt registratieniveau:

`gebouw -> technische zone -> woning -> ruimte -> element`

Toegang en aanspreekpunt blijven een zelfstandige operationele laag en mogen zichtbaar zijn ook als technisch woningdetail niet nodig is.

## Gebouwpagina

Gebouw = permanente kaartenbak.

De gebouwpagina toont primair:

- identiteit / BAG-PDOK;
- technische zones;
- adressen/gebruiksobjecten;
- permanente documentatie;
- historie;
- actieve en historische projecten;
- relevante bewoners/servicecontext;
- garanties/onderhoud zodra modules dat leveren.

Projecttijdelijke stuurinformatie mag de gebouwpagina niet domineren.

## Projectpagina

Project = stuurmechanisme.

De projectpagina toont primair:

- scope;
- planning/look-ahead;
- werkpakketten;
- toegang/startgereedheid;
- taken/risico's;
- inkoop/financiën;
- uitvoering/kwaliteit;
- oplevering.

Permanente gebouwkennis wordt gebruikt, niet gedupliceerd.

## Responsief en mobiel

- Kritieke workflows bruikbaar op telefoon.
- Geen horizontaal scrollen voor primaire mobiele taken waar dit te voorkomen is.
- Tabellen mogen op mobiel overschakelen naar kaarten/lijstregels.
- Foto, inspectie, toegang en gereedmelding krijgen prioriteit voor mobiele optimalisatie.

## Presentatiegereed-check

Een scherm is pas presentatiegereed als:

- doel van het scherm binnen enkele seconden duidelijk is;
- primaire actie direct herkenbaar is;
- uitzonderingen vóór normale informatie staan;
- informatie niet dubbel wordt getoond;
- statussemantiek klopt;
- mobiel gedrag acceptabel is;
- er geen module-eigen afwijkende stijlen zonder reden zijn;
- lege states, foutstates en N.V.T.-situaties zijn ontworpen;
- audit/herkomst bereikbaar is zonder het hoofdscherm te vervuilen.

## Governance

Nieuwe modules moeten bij UI-werk expliciet toetsen aan dit document.

Afwijkingen worden alleen toegestaan wanneer:

1. het bestaande patroon aantoonbaar niet past;
2. de afwijking herbruikbaar is voor meerdere modules; en
3. het design system zelf wordt bijgewerkt.

Dus: geen lokale CSS-oplossing als eerste reflex.

## Migratie van bestaande schermen

Bestaande functionele schermen hoeven niet direct allemaal te worden herschreven. Migratie gebeurt gefaseerd:

1. ontwerpregels vastleggen;
2. centrale theme-componenten bouwen;
3. nieuwe schermen conform standaard;
4. bestaande cockpits en dossiers per module migreren;
5. laatste presentatieslag vóór brede ingebruikname.

Prioriteit bij migratie:

1. projectcockpit en look-ahead;
2. gebouwcockpit;
3. werkpakket/release-gate;
4. bewoners/toegang/woningdossier;
5. commerciële funnel;
6. overige modules.

## Relatie met andere documenten

- BMS/CIM bepalen semantiek en canonieke objecten.
- Proceshandboek bepaalt processen en bevoegdheden.
- `BREBO_CONTINUITEIT.md` bepaalt actuele ontwikkelstand.
- Dit document bepaalt applicatiepresentatie en interactiepatronen.
- `web/themes/custom/brebo_office` implementeert deze regels technisch.

Laatst vastgesteld: 15 augustus 2026.
