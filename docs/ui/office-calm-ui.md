# BREBO Office — rust en overzicht

Deze regels gelden Office-breed. Projecten is de eerste referentie-implementatie; daarna worden Gebouwen, Finance, Mail, CRM/Intake en Instellingen hierop gelijkgetrokken.

## Hoofdprincipe

Een scherm heeft één primaire taak. De gebruiker ziet eerst wat nodig is om te sturen of te handelen; details volgen pas daarna.

## Vaste schermhiërarchie

1. **Context** — waar ben ik? Project, gebouw, relatie, factuur of intake.
2. **Status** — wat is de actuele toestand en vraagt iets aandacht?
3. **Primaire actie** — wat is hier de logisch volgende handeling?
4. **Kerninformatie** — alleen de gegevens die voor deze taak nodig zijn.
5. **Verdieping** — detailtabellen, historie, techniek en uitzonderingen achter tabs/details.

## Office-brede regels

- Maximaal één visueel dominante primaire actie per scherm.
- Secundaire acties worden gegroepeerd en visueel terughoudend getoond.
- Geen dubbele informatie op hetzelfde scherm.
- Geen grote technische tabellen bovenaan een werkpagina.
- Statussignalen krijgen voorrang op totalen en metadata.
- Tabellen zijn scanbaar: compacte kolommen, voorspelbare uitlijning en acties rechts.
- Lange tabellen krijgen zoek/filter/sortering zonder extra dashboardruis.
- Detailinformatie staat standaard onder `details`, tab of aparte detailpagina.
- Formulieren worden logisch gegroepeerd; geavanceerde/technische velden staan niet tussen de hoofdvelden.
- Dezelfde begrippen, statuslabels en actieposities worden in heel Office hergebruikt.
- Compatibiliteitsroutes en technische internals zijn geen gebruikersnavigatie.
- Een leeg scherm geeft één duidelijke vervolgstap, geen reeks losse knoppen.
- Desktop en mobiel volgen dezelfde informatievolgorde.

## Dichtheid

BREBO Office is een operationeel systeem, geen marketingwebsite. De UI is compact maar niet druk:

- normale werkruimte: 12–16 px ritme tussen gerelateerde elementen;
- secties: duidelijke scheiding zonder zware kaders;
- maximaal 3–5 kern-KPI's boven de vouw;
- maximaal 1 hoofdstatusbalk per dossierpagina;
- maximaal 1 primaire knopgroep per taakgebied.

## Projecten als referentie

De projectdossier-volgorde is:

**Overzicht → Planning → Documenten → Begroting → Orders → Contracten → Facturen → Inzet → Tekortkomingen → Oplevering**

Het projectoverzicht is een stuurpagina, geen verzamelpagina. Bovenaan horen alleen:

- projectstatus;
- aandachtspunten;
- voortgang/planning;
- financieel resultaat/cash-signaal;
- eerstvolgende acties.

Volledige financiële tabellen horen onder Begroting/Facturen/Finance, niet dubbel op het projectoverzicht.

## Uitrolvolgorde

1. Projecten volledig afmaken en als visuele referentie vastzetten.
2. Gebouwen en Gebouwwaarheid op dezelfde hiërarchie brengen.
3. Finance: registers en editors versimpelen en dubbele stuurinformatie verwijderen.
4. CRM/Intake: resultaat eerst, technische extractiedetails daarna.
5. Mail: driepaneels werkplek behouden, lokale acties verder standaardiseren.
6. Instellingen en beheer: functioneel groeperen en technische opties uit de hoofdflow halen.

## Definition of Done per scherm

Een scherm is pas klaar wanneer:

- binnen enkele seconden duidelijk is waar de gebruiker is;
- de belangrijkste status direct zichtbaar is;
- de primaire actie ondubbelzinnig is;
- er geen dubbele of concurrerende actieknoppen zijn;
- detailinformatie de hoofdtaak niet verdringt;
- terminologie overeenkomt met de rest van Office;
- dag/nacht en mobiel dezelfde hiërarchie behouden.
