# BREBO Office — BMS/CIM/Drupal aansluitcontrole

## Status

Werkdocument voor de gecontroleerde vertaling van het vastgestelde BREBO Proceshandboek en het CIM naar de huidige Drupal-implementatie. Dit document verandert geen normatieve bedrijfsregel. Bij strijdigheid zijn het beheerde Proceshandboek, bevoegde besluiten en vastgestelde CIM-/BMS-bronnen leidend.

Peildatum: 11 augustus 2026.

## 1. Hoofdconclusie

De huidige Drupal-code is **niet meer uitsluitend projectcentrisch**. De eerste implementatie begon wel projectcentrisch (`brebo_project` als eerste object en `brebo_cluster` verplicht onder een project), maar latere updates hebben dit aantoonbaar gecorrigeerd.

Update `11018` introduceert `brebo_building` expliciet als permanente bron van waarheid voor locatie, objectstructuur, historie en projecten. Het project wordt daarbij omschreven als tijdelijke opdracht rond een of meer gebouwen. Update `11019` voegt per gebouw een auditeerbare projectscope toe en update `11020` introduceert permanente technische gebouwzones.

Daarmee is de hoofdarchitectuur in de actuele code in belangrijke mate in lijn gebracht met het vaste BREBO-principe: **het gebouw staat centraal**.

## 2. Normatieve/functionele basis

Voor deze controle gelden minimaal de volgende uitgangspunten:

1. Het gebouw staat centraal.
2. Objecten en relaties zijn primair; documenten zijn bewijs en afgeleiden.
3. Informatie wordt eenmaal vastgelegd en meervoudig gebruikt.
4. Persoonlijke mailboxen, telefoons en losse berichten zijn aanvoerkanalen, geen definitieve dossierlocatie.
5. Projectinformatie moet herleidbaar zijn naar project, gebouw, bouwdeel/locatie, bron, status, eigenaar en bewijs.
6. Digitale ondersteuning mag registreren, analyseren, signaleren, routeren en voorbereiden; materiële formele besluiten blijven binnen bevoegd mandaat en vereiste menselijke controle.
7. Klachten, garantie, evaluaties, faalkosten en projectlessen moeten terugvloeien naar verbetering en kennis.

## 3. Aansluiting huidige Drupal-code

### A. Gebouw als permanente kern — GROEN

Aanwezig:

- `brebo_building` als permanent gebouwobject;
- permanente gebouwcode, adres, plaats, land, coördinaten, status en omschrijving;
- project ↔ gebouw als many-to-many relatie via `field_brebo_building_refs`;
- expliciete migratie van bestaande projecten naar gebouwobjecten;
- bestaande clusters worden bij migratie onder een permanent gebouw geplaatst.

Beoordeling: functioneel correct als basis.

### B. Tijdelijke projectscope binnen een gebouw — GROEN

Aanwezig:

- `brebo_project_scope` koppelt exact één project aan één gebouw;
- scope kan gebouwen/clusters, woningen en productposities selecteren;
- scope heeft eigen status, afbakening, uitsluitingen en revisies;
- formulier communiceert expliciet dat het gebouw permanent is en de opdracht tijdelijk.

Beoordeling: sluit goed aan op het onderscheid tussen permanent gebouw en tijdelijke opdracht.

### C. Permanente technische objectstructuur — GROEN / ORANJE

Aanwezig:

- `brebo_building_zone` voor permanente technische zones;
- `brebo_cluster`, `brebo_dwelling` en `brebo_product_position` bestaan al;
- productposities dragen maatvoering, type, locatie, bewijsfoto's en technische gegevens.

Aandachtspunt:

- de oorspronkelijke `brebo_cluster`-structuur bevat nog een verplichte `field_brebo_project_ref`; later is daarnaast `field_brebo_building_ref` toegevoegd. Daardoor bestaat historische dubbele semantiek: cluster als projectobject én cluster als permanent gebouwobject.

Vereiste correctie: bepaal één canonieke eigendomsrelatie. Voor permanente fysieke clusters moet **Gebouw** eigenaar zijn. Projectbetrokkenheid hoort via `brebo_project_scope` te lopen, niet via een verplichte permanente projectparent.

### D. Communicatie als dossierinformatie — GROEN

Aanwezig:

- `brebo_communication` koppelt communicatie aan project én gebouw;
- optioneel exact scopeobject;
- kanaal, richting, type, datum/tijd, organisatie/contact, onderwerp, samenvatting, besluit/instructie, reactietermijn, status en eigenaar;
- financiële/planningimpact;
- mandaatbevestiging en vereiste schriftelijke bevestiging;
- bijlagen/bewijs en formele status;
- communicatieformulier benoemt expliciet het controleerbare dossier en koppeling aan project, gebouw en scopeobject.

Beoordeling: sterke basis voor de later afgesproken Migrerende Mail Intake. De intake moet dit object voeden en geen parallel mailboxdossier creëren.

### E. Kwaliteit, afwijkingen en bewijs — GROEN

Aanwezig:

- `brebo_verification` met bron, resultaat, bevinding, vervolgactie, bewijs en blokkering van vrijgave;
- `brebo_deviation` met verantwoordelijke, deadline, herstelactie, herstelbewijs en hercontrole;
- revisies en rolrechten.

Beoordeling: functioneel in lijn met het Proceshandboek, waarin bewijs, hold points, afwijkingen, hercontrole en onafhankelijke kwaliteitsbeoordeling centraal staan.

### F. Projectbesturing, calculatie en inkoop — GROEN

Aanwezig in de huidige code:

- projectsoort, BREBO-rol, verrekeningswijze, inkooppositie en bevoegdheidsvelden;
- dynamische projectroutes en vereiste producten;
- calculatieobjecten, calculatieregels, werkbegroting;
- prijsaanvragen, leveranciersoffertes en vergelijkbare prijsregels;
- expliciete budget- en selectiebesluiten.

Beoordeling: brede functionele dekking aanwezig; verdere toetsing moet per procespoort gebeuren in plaats van alleen per contenttype.

## 4. Belangrijkste nog openstaande afwijkingen

### O-001 — Dubbele parent van Cluster

**Prioriteit: hoog.**

De oude verplichte projectreferentie op `brebo_cluster` conflicteert conceptueel met het latere permanente gebouwmodel. Niet verwijderen zonder migratie-analyse; eerst bepalen welke runtime-data en code er nog van afhankelijk zijn.

Doelmodel:

`Gebouw -> Cluster/gebouwzone -> Woning/Ruimte -> Productpositie`

en afzonderlijk:

`Project -> Projectscope -> geselecteerde permanente gebouwobjecten`.

### O-002 — Projectlocatie als vrije tekst is legacy

`brebo_project` bevat uit de eerste implementatie nog `field_brebo_location`. Dat veld is gebruikt om update 11018 te backfillen naar gebouwen.

Doel: vrije projectlocatie niet langer als primaire locatiebron gebruiken. Gebouwreferenties zijn canoniek; vrije tekst mag hoogstens historische/importcontext blijven.

### O-003 — Generieke actie-/termijnenlaag nog expliciet toetsen

Het handboek kent een centrale actie- en termijnenregistratie. De code bevat veel taak-, vereiste-, communicatie- en afwijkingsopvolging, maar voor de volgende bouwstap moet worden vastgesteld of één canoniek generiek actieobject bestaat of dat acties nu versnipperd over domeinobjecten zitten.

Geen nieuw actiecontenttype maken voordat deze controle is afgerond.

### O-004 — Centraal risicoobject en risicoketen toetsen

Het handboek vereist risico's met oorzaak, gebeurtenis/gevolg, kans, impact, maatregel, eigenaar, termijn en restrisico. De project- en calculatielaag bevatten risicovelden en route-/vereiste-logica, maar de canonieke organisatiebrede/projectbrede risico-entiteit moet nog tegen RG-004/PR-130 worden getoetst.

### O-005 — Feedback/tevredenheid als leerobject

Het handboek ondersteunt klantterugkoppeling, klachten, evaluatie, lessons learned en organisatiebreed leren. De ontwikkelbesluiten voegen daar expliciete gestructureerde tevredenheidsfeedback aan toe.

Voor implementatie moet feedback worden gekoppeld aan minimaal gebouw, eventueel project, contactmoment/categorie, score/kwalitatieve toelichting, opvolging en leeractie. Het mag geen los enquête-eiland worden.

### O-006 — Migrerende Mail Intake nog niet als ingestroomde keten gerealiseerd

De communicatiestructuur is aanwezig en geschikt als doelobject, maar de feitelijke migrerende intake moet nog als gecontroleerde keten worden gebouwd:

bronbericht -> authenticiteit/herkomst -> project/gebouwherkenning -> classificatie -> extractie -> communicatieobject -> acties/signalen/controle -> menselijke verificatie bij onzekerheid.

Bronbestand en externe bron-ID moeten behouden blijven voor deduplicatie en bewijs.

### O-007 — Volledige rollen- en mandatenmatrix

De vroegste Drupal-update creëerde Projectleider, Werkvoorbereider en Kwaliteitsmanager. Het Proceshandboek kent inmiddels zestien normatieve rollen en de Virtuele Staf. Nieuwe Drupal-permissies mogen niet simpelweg door contenttype worden uitgebreid; zij moeten worden afgeleid van de formele mandaten en onafhankelijkheidsregels.

### O-008 — Objectconsistentie en cross-building bescherming

Nu gebouw, projectscope, communicatie, clusters, woningen en productposities naast elkaar bestaan, moet technisch worden afgedwongen dat relaties niet per ongeluk objecten uit verschillende gebouwen/projectscopes combineren.

Dit is een kernvereiste voor betrouwbare CIM-relaties.

## 5. Wat we niet opnieuw moeten bouwen

Niet opnieuw ontwerpen of dupliceren:

- Gebouw;
- Project;
- Projectscope per gebouw;
- Technische gebouwzones;
- Cluster;
- Woning;
- Productpositie;
- Communicatie;
- Controle/verificatie;
- Afwijking;
- bestaande calculatie- en inkoopobjecten.

Nieuwe functionaliteit moet deze objecten verbinden of ontbrekende canonieke objecten aanvullen.

## 6. Besloten ontwikkelvolgorde vanaf deze controle

1. Legacy-relaties rond Cluster/Project/Building technisch opschonen zonder dataverlies.
2. CIM-consistentievalidatie voor gebouw/project/scope/objectrelaties.
3. Centrale actie-, signaal- en risicolaag toetsen en alleen ontbrekende kernobjecten toevoegen.
4. Feedback/tevredenheid en leerkring aansluiten.
5. Migrerende Mail Intake bovenop `brebo_communication` bouwen.
6. Digitale rollen en mandaten op deze gedeelde objectlaag aansluiten.
7. Daarna dashboards en verdere automatisering.

## 7. Stopregel

Geen nieuw contenttype wordt toegevoegd alleen omdat een procesbegrip in het handboek voorkomt. Eerst wordt gecontroleerd of het begrip al bestaat als object, veld, relatie, status, register of afgeleide workflow. Alleen aantoonbaar ontbrekende canonieke informatie krijgt een nieuwe duurzame technische representatie.
