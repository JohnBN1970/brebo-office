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
7. `docs/BREBO_CALCULATIE_ARCHITECTUUR.md` voor calculatie-, parameter- en outputgeneratorwerk;
8. dit continuïteitsdocument;
9. de actuele GitHub-stand van `develop` en open pull requests.

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
- Calculatie gebruikt een spreadsheetachtige hiërarchische werkplek met inklapbare hoofdgroepen/paragrafen, inline regels, sticky headers en subtiele zebra-striping.
- Presentatie-only migraties van grote controllers worden bij voorkeur via theme/template/preprocess/behavior uitgevoerd; volledige bronbestanden worden niet vervangen tenzij functionele logica daadwerkelijk moet wijzigen en de volledige inhoud gecontroleerd beschikbaar is.

## Actuele objectstructuur

```text
Relatie = met wie BREBO zaken doet
Gebouw = permanente gebouwkennis
Project = tijdelijke operationele sturing
Technische zone/WBS = waar en waaraan uitvoering plaatsvindt
Taak/workflow = wie wanneer wat moet doen
```

Gebouw levert kennis aan het project. Het project bestuurt de uitvoering. Na oplevering vloeit gerealiseerde blijvende kennis terug naar het gebouw.

## Calculatiearchitectuur

`docs/BREBO_CALCULATIE_ARCHITECTUUR.md` is leidend.

Vastgelegd:

- calculatieboom: Calculatie -> Hoofdgroep -> maximaal drie paragraafniveaus -> calculatieregels;
- hoofdindeling NL-SfB, STABU of Eigen; paragrafen kunnen NL-SfB-detail of Eigen zijn;
- alleen eindparagrafen bevatten regels; hogere niveaus zijn subtotalen;
- locatie is een tweede dimensie naast classificatie en verwijst naar canonieke gebouw-/projectscope;
- normale regel combineert arbeid, materiaal, materieel, OA en overig;
- regeltypen: normaal, stelpost, optie, notitie, verdisconterend en verrekenbaar;
- aparte tab `Parameters` voor rekenmethodiek en commerciële opbouw, waaronder open/gesloten, staartkosten/enkele marge, grondslagen, tarieven, prijspeil/indexatie, BTW/afronding, bijzondere posten, verdiscontering, prijsbronbeleid, risico en status/lock;
- outputpresentatie hoort niet in Parameters;
- generieke `Outputgenerator` gebruikt versioneerbare outputmodellen waarmee dezelfde calculatie in verschillende vormen kan worden gegenereerd;
- outputmodel is het presentatierecept; gegenereerde output is een onveranderlijke/auditbare snapshot van calculatieversie + outputmodelversie + selectie;
- offerte blijft een afzonderlijk commercieel object en kan een outputmodel gebruiken.

Scheiding:

```text
Calculatie + Parameters = financiële waarheid en rekenmethodiek
Outputmodel             = presentatierecept
Outputsnapshot           = vastgelegd document/resultaat
Offerte                  = commercieel klantdocument
```

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
- standaard look-ahead is 42 dagen: groen = gereed/n.v.t., oranje = niet gereed en >7 dagen, rood = niet gereed en <=7 dagen;
- look-ahead signaleert alleen en wijzigt planning of formele vrijgave niet zelfstandig.

## Huidige ontwikkelfase

BREBO Office consolideert het canonieke gebouw- en projectmodel en bouwt centrale dossier- en operationele lagen daarop door. Nieuwe functionaliteit gebruikt deze ruggengraat en introduceert geen parallelle objectstructuren.

De calculatiearchitectuur is functioneel opnieuw vastgesteld en wordt als afzonderlijke spreadsheetachtige werkplek ontworpen. De Outputgenerator wordt generiek opgezet zodat outputkeuzes niet in de financiële calculatieparameters worden opgesloten.

De bewoners/service-bouwslag bevindt zich op `agent/resident-service-module` / PR #286 en geldt niet als productie-deployment zolang deze niet via de bestaande route is beoordeeld, gemerged en gedeployd.

## Eerstvolgende technische punten

1. Calculatiegegevensmodel en UI implementeren volgens `BREBO_CALCULATIE_ARCHITECTUUR.md`, zonder bestaande bruikbare reken-/RFQ-data voortijdig te verwijderen.
2. Generiek model voor Outputgenerator ontwerpen: outputmodel, modelversie, selectie/presentatieregels en outputsnapshot.
3. Commerciële funnel verder migreren naar de centrale presentatielaag.
4. Automatische readiness-evidence auditbaar aan release-gate-historie vastleggen.
5. Look-ahead verbreden naar generiek readinessmodel voor materiaal, tekeningen, vergunningen, steiger en KAM.
6. Directe canonieke relatie realiseren tussen technische woningscope (`brebo_dwelling`) en BAG-backed residence.
7. Foto-editor voor niet-destructieve markeringen mobiel uitwerken conform het centrale design system.

## Integration API en deployment

- Worker: `brebo-integration-api`.
- Testendpoint: `https://brebo-integration-api.john-boon.workers.dev`.
- HMAC v1-beveiliging blijft leidend.
- Productie/deploymentwijzigingen verlopen via de bestaande GitHub Actions-route; geen ad-hoc handmatige Git-merge op de server.

## Ontwikkelregel bij nieuwe chats

Een nieuwe chat is een voortzetting van dezelfde BREBO Office-ontwikkeling. Begin niet opnieuw met architectuurverkenning. Herstel eerst de actuele stand uit de hierboven genoemde bronnen en ga verder vanaf de eerstvolgende technische stap.

Bij iedere betekenisvolle bouwstap moet dit bestand daadwerkelijk worden bijgewerkt wanneer architectuur, implementatiestatus, open technische punten of eerstvolgende stap verandert. Alleen in de chat melden dat continuïteit is bijgewerkt is niet voldoende.

Bij iedere nieuwe of gewijzigde UI moet tevens worden getoetst aan `docs/BREBO_OFFICE_UI_DESIGN_SYSTEM.md`.

## Beheer

Werk dit document bij bij een belangrijke mijlpaal, architectuurbesluit, merge, deploymentwijziging of wijziging van de eerstvolgende ontwikkelstap.

Laatst bijgewerkt: 15 augustus 2026.
