# BREBO Office — Continuïteitsdocument

## Doel

Dit document voorkomt dat de BREBO Office-ontwikkeling bij een volle of nieuwe chat opnieuw vanaf nul moet worden opgebouwd. Het bevat de compacte actuele werkstand en verwijst naar de leidende bronnen.

Het is geen vervanging van het Proceshandboek, CIM, Appendix A, roadmap, UI Design System of wijzigingsregister.

## Startvolgorde voor iedere nieuwe ontwikkelsessie

Lees eerst, in deze volgorde:

1. het vastgestelde BREBO Proceshandboek;
2. `docs/APPENDIX_A.md`;
3. `docs/CIM.md`;
4. `docs/ROADMAP.md`;
5. `docs/BMS_CIM_DRUPAL_ALIGNMENT.md`;
6. `docs/BREBO_OFFICE_UI_DESIGN_SYSTEM.md` voor alle presentatie/UI-werkzaamheden;
7. dit continuïteitsdocument;
8. de actuele GitHub-stand van `develop` en open pull requests.

Verzin geen nieuwe architectuur of module-eigen presentatietaal wanneer een onderwerp al in deze bronnen is vastgesteld.

## Vaste functionele uitgangspunten

- Het gebouw staat centraal als permanente projectoverstijgende kaartenbak.
- Het project is het tijdelijke stuurmechanisme voor scope, tijd, geld, mensen, toegang, uitvoering en kwaliteit.
- Projectscope selecteert tijdelijk permanente gebouwobjecten.
- Technische zones bepalen welk detailniveau nodig is; woningniveau wordt alleen verlangd wanneer de technische scope dat vereist.
- BMS en CIM zijn leidend; Drupal is de technische vertaling.
- Eén keer vastleggen, overal hergebruiken.
- Communicatiekanalen zijn aanvoerkanalen, geen blijvende primaire waarheid.
- AI en digitale rollen signaleren en bereiden voor; formele materiële besluiten blijven binnen aantoonbaar mandaat.
- Geen aannames wanneer bewijs nodig is.
- Belangrijke implementatie geldt pas als duurzaam wanneer zij in GitHub staat.

## Vaste presentatiearchitectuur

- `web/themes/custom/brebo_office` is het centrale applicatietheme.
- `docs/BREBO_OFFICE_UI_DESIGN_SYSTEM.md` is leidend voor UI/UX en presentatiepatronen.
- Functionele modules leveren inhoud en gedrag; zij introduceren geen eigen parallelle visuele taal.
- Vaste schermpatronen: cockpit/dashboard, detaildossier, lijst/tabel, kanban, formulier, look-ahead/risico en foto/bewijs.
- Gebouwpagina presenteert permanente gebouwkennis; projectpagina presenteert operationele sturing.
- Status, navigatie, KPI-kaarten, acties, informatiedichtheid en mobiel gedrag worden centraal gestandaardiseerd.
- Nieuwe schermen moeten direct conform het design system worden gebouwd; bestaande schermen worden gefaseerd gemigreerd.
- `css/design-system.css` bevat centrale herbruikbare componenten voor cockpit/KPI/status/secties/tabellen, werkpakketbeslisinformatie en gebouwdossier.
- Project-look-ahead, projecttabs/BREBO Lens en projecttoegang gebruiken de centrale presentatietaal.
- Werkpakket/release-gate gebruikt dezelfde cockpit/KPI/status-hiërarchie en houdt formele vrijgave menselijk.
- Gebouwdossier presenteert permanente identiteit en structuur vóór projecthistorie; de bestaande luchtfoto blijft een prominente gebouwcontext.
- Gebouwbewonersoverzicht en individueel woningdossier zijn gemigreerd naar dezelfde centrale cockpit/sectie/tabelcomponenten.
- Woningdossier presenteert eerst bewoning, toegang, bewoners/contacten en open dossiers; BAG-identiteit, service, foto's/markeringen en historie volgen als dossiersecties.
- Presentatie-only migraties van grote controllers worden bij voorkeur via theme/template/preprocess/behavior uitgevoerd; volledige bronbestanden worden niet vervangen tenzij functionele logica daadwerkelijk moet wijzigen en de volledige inhoud gecontroleerd beschikbaar is.
- Prioriteit verdere presentatie-migratie: commerciële funnel -> overige modules.

## Actuele objectstructuur

```text
Relatie = met wie BREBO zaken doet
Gebouw = permanente gebouwkennis
Project = tijdelijke operationele sturing
Technische zone/WBS = waar en waaraan uitvoering plaatsvindt
Taak/workflow = wie wanneer wat moet doen
```

Gebouw levert kennis aan het project. Het project bestuurt de uitvoering. Na oplevering vloeit gerealiseerde blijvende kennis terug naar het gebouw.

## Bewoners, woningen, toegang en service

Branch `agent/resident-service-module` / draft PR #286 bevat `brebo_resident_service`.

Vastgelegd en/of gebouwd:

- koppeling aan canoniek `brebo_building`; geen tweede gebouwmodel;
- BAG/PDOK-gevalideerde adressen/gebruiksobjecten;
- technisch woningdetail alleen wanneer technische zone dit vereist;
- bewoners/servicecontext, meldingen, klachten, schade en nazorg;
- onveranderlijke foto's met niet-destructieve annotatielagen;
- toegang/contact op project-, gebouw-, zone- en woningniveau;
- effectieve toegang erft woning -> zone -> gebouw -> project;
- bewoning, contact, toegang en startgereedheid zijn afzonderlijk;
- leegstand geeft nooit automatisch toegang;
- `ZoneAccessReadiness` -> `WorkPackageAccessReadiness` -> formele `brebo_release_gate`;
- release gate toont automatische beslisinformatie maar blijft een menselijke formele beoordeling;
- `LookAheadAccessReadiness` gebruikt de bestaande geplande start van werkpakketten;
- standaard look-ahead is 42 dagen: groen = gereed/n.v.t., oranje = niet gereed en >7 dagen, rood = niet gereed en <=7 dagen;
- project heeft de tab `Look-ahead startgereed`; deze gebruikt het centrale cockpit/KPI/status/tabel-patroon;
- projecttoegang gebruikt dezelfde visuele hiërarchie;
- werkpakketdashboard presenteert de bestaande release-gate-samenvatting als expliciet startbesluit zonder de formele poortlogica te wijzigen;
- gebouwdossier presenteert permanente identiteit/structuur vóór projecthistorie en gebruikt dezelfde centrale KPI/tabellenstijl;
- gebouwbewonersoverzicht toont KPI's voor woningen/gebruiksobjecten, toegangsproblemen, open bewonersdossiers en leegstand;
- individueel woningdossier toont KPI's voor bewoning, toegang, bewoners/contacten en open dossiers en groepeert BAG-identiteit, service, foto's/markeringen en historie in vaste dossiersecties;
- look-ahead signaleert alleen en wijzigt planning of formele vrijgave niet zelfstandig;
- legacy `brebo_dwelling` -> BAG-residence adres-bridge blijft tijdelijk en moet een directe canonieke referentie worden.

## Operationele keten toegang

```text
PDOK/BAG + gebouwkennis
  -> technische scope
  -> effectieve toegangsregel
  -> ZoneAccessReadiness
  -> WorkPackageAccessReadiness
  -> Project Look-ahead (42 dagen)
       groen / oranje / rood
  -> release-gate beslisinformatie
  -> formele menselijke vrijgave
  -> uitvoering
```

Dit is tevens het herbruikbare modulepatroon voor andere readiness-soorten: data -> automatische beoordeling -> vroegsignalering -> formeel bestaand besluitobject -> uitvoering.

## Huidige ontwikkelfase

BREBO Office consolideert het canonieke gebouw- en projectmodel en bouwt centrale dossier- en operationele lagen daarop door. Nieuwe functionaliteit gebruikt deze ruggengraat en introduceert geen parallelle objectstructuren.

De centrale UI/UX-specificatie staat in `docs/BREBO_OFFICE_UI_DESIGN_SYSTEM.md`. Project-look-ahead, projecttabs/BREBO Lens, projecttoegang, werkpakket/release-gate, gebouwdossier, gebouwbewonersoverzicht en woningdossier zijn concrete migraties naar de centrale presentatielaag. De functionele architectuur blijft behouden; presentatie wordt centraal en progressief verbeterd.

Een foutieve volledige vervanging van `OfficeController.php` op 15 augustus 2026 is direct teruggedraaid naar de directe parentcommit. Daarna is gecontroleerd dat de controller volledig hersteld was. Presentatie-only wijzigingen worden sindsdien veilig en gecontroleerd uitgevoerd. De foutcommit is niet gedeployd.

De bewoners/service-bouwslag bevindt zich op `agent/resident-service-module` / PR #286 en geldt niet als productie-deployment zolang deze niet via de bestaande route is beoordeeld, gemerged en gedeployd.

## Eerstvolgende technische punten

1. Commerciële funnel (marketing lead -> kans -> offerte/calculatie -> project) naar dezelfde presentatielaag migreren, met lijst én kanban als gelijkwaardige weergaven.
2. Automatische readiness-evidence auditbaar aan release-gate-historie vastleggen zonder de menselijke poortbeslissing te overschrijven.
3. Look-ahead verbreden van alleen toegang naar een generiek readinessmodel voor o.a. materiaal, tekeningen, vergunningen, steiger en KAM, steeds via bestaande objecten/modules.
4. Directe canonieke relatie realiseren tussen technische woningscope (`brebo_dwelling`) en BAG-backed residence.
5. Oude directe `access_status` op `brebo_residence` uitfaseren als primaire waarheid; `brebo_access_contact` + resolver wordt leidend.
6. Foto-editor voor niet-destructieve markeringen mobiel uitwerken conform het centrale design system.
7. Bewoners/service-objecten aansluiten op centrale taken, workflow, communicatie en oplever-/kwaliteitsprocessen zonder duplicatie.
8. Historische verplichte `Cluster -> Project`-relatie en legacy `field_brebo_location` binnen canonieke consolidatie beoordelen/migreren zonder dataverlies.

## Integration API en deployment

- Worker: `brebo-integration-api`.
- Testendpoint: `https://brebo-integration-api.john-boon.workers.dev`.
- HMAC v1-beveiliging blijft leidend.
- Worker healthcheck is als Drush-script gedeployd.
- Echte end-to-end healthcheck met `BREBO_SHARED_SECRET` staat geparkeerd totdat het secret veilig beschikbaar is.
- Productie/deploymentwijzigingen verlopen via de bestaande GitHub Actions-route; geen ad-hoc handmatige Git-merge op de server.

## Ontwikkelregel bij nieuwe chats

Een nieuwe chat is een voortzetting van dezelfde BREBO Office-ontwikkeling. Begin niet opnieuw met architectuurverkenning. Herstel eerst de actuele stand uit de hierboven genoemde bronnen en ga verder vanaf de eerstvolgende technische stap.

Bij iedere betekenisvolle bouwstap moet dit bestand daadwerkelijk worden bijgewerkt wanneer architectuur, implementatiestatus, open technische punten of eerstvolgende stap verandert. Alleen in de chat melden dat continuïteit is bijgewerkt is niet voldoende.

Bij iedere nieuwe of gewijzigde UI moet tevens worden getoetst aan `docs/BREBO_OFFICE_UI_DESIGN_SYSTEM.md`. Een lokale afwijking wordt alleen duurzaam wanneer het centrale design system die afwijking expliciet toestaat of zelf wordt bijgewerkt.

## Beheer

Werk dit document bij bij een belangrijke mijlpaal, architectuurbesluit, merge, deploymentwijziging of wijziging van de eerstvolgende ontwikkelstap.

Laatst bijgewerkt: 15 augustus 2026.
