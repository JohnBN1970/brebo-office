# BREBO Office — Continuïteitsdocument

## Doel

Dit document voorkomt dat de BREBO Office-ontwikkeling bij een volle of nieuwe chat opnieuw vanaf nul wordt opgebouwd. Het bevat de compacte actuele werkstand en verwijst naar de leidende bronnen.

Het is geen vervanging van het Proceshandboek, CIM, Appendix A, roadmap, UI Design System of wijzigingsregister.

**Actuele peildatum: 7 oktober 2026.**

## Startvolgorde voor iedere nieuwe ontwikkelsessie

Lees eerst, in deze volgorde:

1. het vastgestelde BREBO Proceshandboek;
2. `docs/APPENDIX_A.md`;
3. `docs/CIM.md`;
4. `docs/ROADMAP.md`;
5. `docs/BMS_CIM_DRUPAL_ALIGNMENT.md`;
6. `docs/BREBO_OFFICE_UI_DESIGN_SYSTEM.md` voor presentatie/UI;
7. `docs/BREBO_CALCULATIE_ARCHITECTUUR.md` en `docs/BREBO_CALCULATIE_CONTINUITEIT.md` voor calculatie;
8. `docs/BREBO_CONTINUITEIT_FINANCE_2026-09-29.md` voor Finance;
9. `docs/BREBO_PROJECT_CONTINUITY.md` voor Projecten/publicatie;
10. `docs/BREBO_OUTPUTGENERATOR_ARCHITECTUUR.md` voor document-/rapportoutput;
11. dit continuïteitsdocument;
12. de actuele GitHub-stand van `develop` en open pull requests, met bijzondere aandacht voor architectuurbranches die nog niet gemerged zijn.

Verzin geen nieuwe architectuur of module-eigen presentatietaal wanneer een onderwerp al in deze bronnen is vastgesteld.

## Vaste functionele uitgangspunten

- Het gebouw staat centraal als permanente projectoverstijgende kaartenbak.
- Het project is het tijdelijke stuurmechanisme voor scope, tijd, geld, mensen, toegang, uitvoering en kwaliteit.
- Projectscope selecteert tijdelijk permanente gebouwobjecten.
- BMS en CIM zijn leidend voor bedrijfs- en informatiesemantiek.
- De BREBO-softwarekern bezit domeinregels en canonieke operationele waarheid; Drupal, React/Calc en andere interfaces zijn vervangbare adapters/consumers en mogen die waarheid niet bepalen.
- Eén keer vastleggen, overal hergebruiken.
- Communicatiekanalen en andere bronnen zijn aanvoerkanalen, geen tweede dossierwaarheid.
- AI en digitale rollen signaleren en bereiden voor; formele materiële besluiten blijven binnen aantoonbaar mandaat.
- Geen aannames wanneer bewijs nodig is.
- Belangrijke implementatie geldt pas als duurzaam wanneer zij in GitHub staat.
- Externe toegang gebruikt uitsluitend expliciet vrijgegeven projecties; BREBO Office blijft de bron.

## Organisatiebrede softwarearchitectuur — vastgesteld 29 september 2026

De architectuursprong die in Calculatie is bewezen geldt voortaan als standaardpatroon voor BREBO Office als geheel.

```text
BREBO-domein / softwarekern
-> contracten
-> infrastructuuradapters
-> Drupal/database/externe providers

BREBO-domein / softwarekern
-> API-contracten
-> vervangbare interfaces
   -> Office-dashboard
   -> Calc-interface
   -> Finance-interface
   -> Project-interface
   -> Inzet-interface
   -> AI / output / portaal
```

Harde grens:

- domeinservices bevatten businessregels, validatie, versie-/lockregels, totalisering en beslislogica;
- domeinservices kennen geen Drupal Database API, NodeInterface, AccountInterface, EntityTypeManager of UI-framework;
- opslag, Drupal-entities, sessies, requests en provider-SDK's horen in adapters/infrastructuur;
- een interface mag geen tweede domeinwaarheid, rekenmotor of lokale calculatiedatabase introduceren;
- API-contracten zijn de grens tussen kern en interfaces;
- een framework of interface moet vervangbaar zijn zonder de kern opnieuw te bouwen.

Calculatie is het eerste volledig uitgewerkte referentiemodel voor deze scheiding. Finance en Projecten volgen dit patroon bij hun volgende architectuur-/consolidatieslag.

### Bewezen calculatie-ontkoppeling — PR #953

Op architectuurbranch `architecture/calculation-recipes-subcalculations-id-routes` is Calculatie als referentie-implementatie losgetrokken van directe Drupal/databasekennis.

Bewezen op de actuele branch:

- Calc gebruikt Workspace v2-contracten; v1 is uit de actieve Calc-runtime verwijderd en CI blokkeert terugval;
- leveranciersofferteherkenning, projectcontext en artikelzoeken lopen via v2-contracten;
- workspace state, parameters, rows, structure, recipes, deelcalculaties, uitzonderingen, resultaten, normen, materiaal-/prijsselectie, price sources, readiness, context, draft-init, establishment, block ordering, identities, access en legacy migration werken via contracten + infrastructuuradapters;
- actieve calculatie-services en access checks bevatten geen directe Drupal Database-afhankelijkheid;
- `GuardedLegacyMigrator` is eveneens achter een infrastructuurrepository geplaatst;
- Project Render Smoke en Calculation domain acceptance zijn groen op de architectuurbranch;
- PR #953 is draft/mergeable totdat deze architectuurslag formeel wordt gemerged.

Belangrijke continuiteitsregel: na merge mag deze scheiding niet worden teruggedraaid door nieuwe directe databasecalls of Drupal-frameworktypes in domeinservices. CI/acceptance moet die grens expliciet blijven bewaken.

## Centrale bron- en intakearchitectuur

Sinds september 2026 is de bron-neutrale intake een expliciete kernlaag van BREBO Office. De vaste keten is:

```text
Bron
-> Intake
-> Herkennen
-> Classificeren
-> Koppelen
-> Canoniek object
-> Vakmodule
-> Controle
-> Actie
-> Terugkoppeling
```

Bronnen kunnen onder meer e-mail, handmatige upload, API, bank, Moneybird, portaal, website en mobiel zijn. Bronadapters schrijven niet rechtstreeks naar Finance, Projecten of andere vakmodules. Zij leveren aan de centrale `SourceNeutralIntakeManager`; vakmodules blijven eigenaar van hun businessregels.

De intake bewaart herkomst en bronreferentie, normaliseert records, voorkomt identieke dubbele verwerking en kan onzekere items als `review_required` klaarzetten voor menselijke controle. Het oorspronkelijke bronbestand blijft canoniek; de intake maakt geen tweede documentopslag.

Persistente intakefundering:

- `brebo_data_source` — geregistreerde bron;
- `brebo_data_ingest_run` — auditeerbare bronverwerking;
- `brebo_data_record` — genormaliseerd bronrecord;
- `brebo_classification_term` — versieerbare classificatie;
- `brebo_masterdata_candidate` — voorstel voor gecontroleerde koppeling aan masterdata.

Werkende adapters zijn inmiddels e-mail/factuurrouting en handmatige upload. De centrale reviewwerkbank op `/brebo-office/intake` toont `review_required`-items bron-neutraal, gepagineerd en read-only, inclusief echte brontijd, operatorvriendelijke classificatie en canonieke projectkoppeling.

**Eerstvolgende intake-opgave:** menselijke reviewbesluiten toevoegen: accepteren, afwijzen, herclassificeren en opnieuw koppelen, met audittrail, concurrencybescherming en expliciete destination-contracten. Ook deze stap mag geen directe bronadapter-write naar vakmodules introduceren.

## Recente bewezen mijlpaal — PR #592 t/m #597

De deployment- en intakeketen is op 6 september 2026 opnieuw end-to-end bewezen:

- **#592** maakte `.brebo-deployed-sha` de gezaghebbende productie-release-identiteit en maakte deployment autoritatief via `rsync --delete`; de marker wordt pas gepubliceerd nadat runtime- en configuratiecontroles slagen.
- **#593** herstelde de archive-markercontrole die door `pipefail`/SIGPIPE kon falen.
- **#594** herstelde `source_key` bij de insert-tak van bronregistratie; daarna bewezen productieacceptatie `SOURCE_NEUTRAL_RUNTIME_OK=1` en deduplicatie.
- **#595** is bewust gesloten zonder merge nadat de branch onbedoeld explodeerde naar 196 commits/193 bestanden. Niet heropenen of als basis gebruiken.
- **#596** leverde de schone centrale read-only intake-reviewwerkbank. Een timingrace in de Codex-review maakte drie P2-bevindingen pas direct na merge zichtbaar.
- **#597** herstelde die bevindingen: update 11002 verleent uitsluitend reviewrecht, `Ontvangen` gebruikt `envelope.received_at`, classificaties zijn leesbaar en de controller botst niet met `ControllerBase::$entityTypeManager`. De bestaande intake-schemahook bleef volledig behouden.

#597 is gemerged als **`9c95d8d8ae6548e1cb02fd2d713dcf502addbf28`**. Productierun **34020446877** is exact op die SHA geslaagd. Bewezen markers omvatten `CACHE_REBUILD_OK=1`, `DATA_INTAKE_RUNTIME_OK=1`, `SOURCE_NEUTRAL_RUNTIME_OK=1`, `CONFIG_EXPORT_OK=1`, `DEPLOYMENT_MARKER_OK=9c95d8d8ae6548e1cb02fd2d713dcf502addbf28`, `PRODUCTION_DEPLOY_SHA=9c95d8d8ae6548e1cb02fd2d713dcf502addbf28` en `DEPLOY_OK=1`.

Belangrijke reviewregel: merge nooit uitsluitend op het groene Codex-summary. Controleer vóór merge zowel de exacte huidige head, vereiste CI als de actuele reviewthreads; een review geldt pas als schoon wanneer er geen actuele onopgeloste findings op die head zijn.

## Vaste presentatiearchitectuur

- `web/themes/custom/brebo_office` is het centrale applicatietheme.
- `docs/BREBO_OFFICE_UI_DESIGN_SYSTEM.md` is leidend voor UI/UX.
- Functionele modules leveren inhoud en gedrag; zij introduceren geen parallelle visuele taal.
- Calculatie gebruikt een spreadsheetachtige hiërarchische werkplek met inline regels, sticky headers en compacte totalisatie.

## Actuele objectstructuur

```text
Relatie = met wie BREBO zaken doet
Gebouw = permanente gebouwkennis
Project = tijdelijke operationele sturing
Technische zone/WBS = waar en waaraan uitvoering plaatsvindt
Taak/workflow = wie wanneer wat moet doen
```

Gebouw levert kennis aan het project. Project bestuurt uitvoering. Na oplevering vloeit gerealiseerde blijvende kennis terug naar het gebouw.

## Totale systeemstand

BREBO Office is de prototypefase voorbij. Communicatie, calculatie, projectsturing, inzet, Finance, publicatie en centrale bronintake bevatten echte operationele ketens. Oude percentages uit augustus zijn niet langer betrouwbaar genoeg om als actuele waarheid te gebruiken; de roadmap en actuele GitHub-stand bepalen de voortgang.

De hoofdbeweging blijft:

```text
consolideren
-> koppelen
-> automatiseren
-> controleren
-> digitale rollen
-> managementsturing
```

## Calculatie

`docs/BREBO_CALCULATIE_ARCHITECTUUR.md` en `docs/BREBO_CALCULATIE_CONTINUITEIT.md` blijven leidend. De oude opvatting dat Drupal of de Calc-interface de calculatiewaarheid beheert is vervallen; de BREBO-calculatiekern is authoritative en interfaces zijn vervangbaar.

Gebouwd/op `develop` aanwezig:

- Calculatie -> Hoofdgroep -> paragrafen -> calculatieregels;
- NL-SfB, STABU of Eigen hoofdindeling;
- regeltypen normaal, stelpost, optie, notitie, verdisconterend en verrekenbaar;
- versiegebonden parameters en lockstatus;
- migratie-audit;
- spreadsheetachtige werkbank met AJAX/autosave;
- live herberekening, subtotalen en kostenuitsplitsing;
- bewaakte rij- en structuurmutaties;
- inklapbare hiërarchie;
- commerciële kolommen en centrale `CommercialCalculator`;
- scenariovergelijking Basis | Scherp | Doel;
- prijsbronnenfundering via `brebo_calculation_price_source` en `brebo_calculation_price_source_line`.

Vast prijsbronprincipe: externe prijzen blijven herleidbaar naar originele document/e-mailbron; extractie is voorstel; menselijke goedkeuring is vereist voordat OA wordt gewijzigd.

## Finance en Moneybird

Finance is een operationele kernlaag. Werkbegroting, commitments, inkoopfacturen, factuurbediening, projectkoppeling en controle zijn verder uitgebouwd dan de augustus-baseline. Moneybird blijft boekhoudkundige bron waar van toepassing; Office is de operationele controlelaag.

De factuurwerkbank toont het originele brondocument naast verwerking en gebruikt de centrale intake voor bronrouting. De beveiligde verkoopfactuurketen, fail-closed configuratie, idempotency en reconciliation blijven vaste veiligheidsprincipes.

## Mail en communicatie

De centrale Mail Intake-kernketen is productiegeaccepteerd. De bewezen mailbox-, reader-, compose-, tabs- en linkingbaseline moet behouden blijven. Mail is nu tevens een echte adapter op de bron-neutrale intake; nieuwe mailverwerking mag deze centrale route niet omzeilen.

Architectuurslag op 5 oktober 2026: PR #1148 (`1da6660…`) bracht `MailIntakeIngestor` achter een communicatie-repository. PR #1149 (`c5e8c3a…`) bracht `OutboundAttachmentService` achter `OutboundAttachmentPersistenceInterface`; Drupal file/node/file-usage-afhankelijkheden zitten sindsdien uitsluitend in `DrupalOutboundAttachmentPersistence`, terwijl hashcontrole, 25 MB-totalisering en documentrelaties in de service bleven. De volgende slice trekt draft-persistence en outbound field provisioning uit `OutboundMailService` achter `OutboundMailPersistenceInterface`; transport en expliciete verzendvrijgave blijven functioneel ongemoeid.

De historische Zoho-backfill blijft een afzonderlijke migratieopgave en mag niet worden geforceerd om runtime- of readinessproblemen heen.

## Project, publicatie en websitegrens

`brebo_project` is het canonieke projectobject. Website en andere externe consumers krijgen uitsluitend een begrensde publicatie/projectie; zij worden geen tweede projectwaarheid. De bounded project-publicationketen is gebouwd en productiegeaccepteerd.

Vaste grens:

```text
BREBO Office interne waarheid
-> expliciete vrijgave/publicatie
-> veilige externe projectie
-> website/portaal
```

## Project Cockpit en managementsturing

De Project Cockpit is de projectspecifieke operationele stuurlaag. De vaste hoofdstructuur is per 7 oktober 2026:

```text
Overzicht
-> Planning
-> Documenten
-> Begroting
-> Orders
-> Contracten
-> Facturen
-> Inzet
-> Tekortkomingen
-> Oplevering
```

Vaste semantiek:

- `Gebouwgegevens` is de eenvoudige gebruikersnaam voor de project-/gebouwcontext; projectkoppelingen en contactpersonen horen bij de project-/gebouwrelaties en niet in een los waarheidssilo.
- `Tekortkomingen` is de gebruikersnaam voor operationele afwijkingen, gebreken, opleverpunten, schade en herstelpunten. Onderliggende kwaliteitsborging/controlelogica mag technisch `quality`/deviation blijven heten.
- `Orders` verenigt inkomende en uitgaande orders/opdrachten. Inkomend = klantopdracht/verkooporder; uitgaand = inkooporder/opdracht aan leverancier of onderaannemer.
- `Facturen` verenigt inkomende en uitgaande projectfacturen in één register, met richting-, status- en sorteermogelijkheden. Detailprocessen voor inkoop- en verkoopfacturen blijven daarachter intact.
- `Bank` hoort niet in Projecten maar in Finance. Project toont hooguit betaalstatus en financiële context.
- Een project, order, contract of projectverplichting is nooit een kunstmatige voorwaarde om een geldige factuur te verwerken of betalen. Finance kent zowel de order/match-route als de losse-factuurroute.
- Contracten zijn juridische/contractuele vastlegging; financiële verplichting ontstaat uit de werkelijke order/opdracht of andere geldige grondslag, niet automatisch uit het bestaan van een projectcontract.
- Met order geldt waar passend de controleketen `order -> prestatie -> factuur -> betaalbaar`; zonder order volgt de losse-factuurroute met classificatie en fiattering.

PR **#748** is op 7 oktober 2026 gemerged als **`df446368a628205a19f7a21e424f60fde8c1f382`**. Deze slag bracht de canonieke opdrachtgever-/contactrelaties, Orders, het uniforme facturenregister, de handmatige uitgaande-orderroute, Tekortkomingen en compatibiliteitsroutes samen. PHP quality gate en Project Render Smoke waren groen vóór merge.

De eerstvolgende Project-slag is functionele afbouw, niet opnieuw architectuur ontwerpen:

1. Planning visueel afronden met project-Gantt, stoplicht/KPI-laag, filters en sortering bovenop de bestaande planningsdata.
2. Documenten als zelfstandige hoofdmodule houden, met projectspecifieke doorsnede binnen Project; geen tweede documentopslag maken.
3. Rapporten volgens hetzelfde patroon: centrale rapportmodule, projectspecifieke doorsnede binnen Project.
4. Orders uitbreiden met een gecontroleerde AI-conceptroute. AI mag uitsluitend een conceptorder voorbereiden; menselijke controle/fiattering blijft vereist vóór definitieve opdracht.
5. Daarna de resterende projecttabs één voor één functioneel nalopen en Projecten afsluiten voordat Calculatie weer de hoofdprioriteit wordt.

Directie-/portfoliosturing, prognoses, faalkosten, organisatiebrede KPI's en leerpatronen blijven de managementlaag boven de projectspecifieke cockpit.

## Inzet en personeelssturing

BREBO Office bevat mobiele/PWA-bouwstenen voor personeelsinzet en de eigen Shiftbase-richting, waaronder projectgebonden klokregistratie, GPS/klokzones, aanwezigheid/vertrek en afwijkingsafhandeling.

De personeelslaag moet verder worden verbonden met planning, werkbegroting, projectcontrol en managementinformatie.

## Actie-, signaal- en controlemotor

Er bestaan meerdere control-services, cockpit-signalen, readiness-/release-gates, contract-/financiële controles en leveranciers-/scorecardbouwstenen. De hoofdopgave is één centrale motor te vormen die bron, eigenaar, termijn, status, risico en afsluitbewijs uniform bewaakt.

Nieuwe module-eigen controlelijstjes zijn ongewenst wanneer dezelfde betekenis centraal kan worden gemodelleerd.

## Bewoners, woningen, toegang en service

Resident service gebruikt het canonieke gebouwmodel en omvat bewoners/servicecontext, meldingen, klachten, schade, nazorg, foto's/annotaties en toegang/readiness.

Vaste keten:

```text
ZoneAccessReadiness
-> WorkPackageAccessReadiness
-> formele brebo_release_gate
```

Look-ahead signaleert en wijzigt planning of formele vrijgave niet zelfstandig.

## Generieke Outputgenerator

`docs/BREBO_OUTPUTGENERATOR_ARCHITECTUUR.md` is leidend. De Outputgenerator is een platformvoorziening, niet een calculatiefunctie.

Vaste scheiding:

```text
Bronobject(en)
-> Outputmodel
-> Lay-outprofiel
-> Bijlagenpakket
-> Outputsnapshot
-> Distributie
```

## Integration API en deployment

- Worker: `brebo-integration-api`.
- HMAC v1-beveiliging blijft leidend.
- Deploymentwijzigingen verlopen via GitHub Actions.
- Externe providercredentials horen niet in Drupal of broncode.
- `sboffice` is de canonieke productieruntime.
- De productie-release-identiteit komt uit `.brebo-deployed-sha`; de achtergebleven `.git`-metadata op productie is niet gezaghebbend.
- De productiecode wordt autoritatief gesynchroniseerd; persistente runtimebestanden/configuratie zijn expliciet uitgesloten.

## Eerstvolgende technische punten — organisatiebreed

1. PR #953 afronden/mergen en de softwarekerngrens als permanente CI-/acceptance-invariant vastzetten.
2. Finance inventariseren op domeinlogica versus Drupal/UI/providerlogica en gecontroleerd naar hetzelfde contract/adapterpatroon brengen.
3. Projecten inventariseren op domeinlogica versus Drupal/UI/publicatielogica en gecontroleerd naar hetzelfde contract/adapterpatroon brengen.
4. Centrale intake-reviewbesluiten bouwen: accepteren, afwijzen, herclassificeren en opnieuw koppelen, met audit en concurrency.
5. Destination-contracten tussen centrale intake en vakmodules expliciet maken zonder directe adapter-writes.
6. Finance/Moneybird leveranciers- en inkoopfactuurketen verder sluiten op dezelfde intake/masterdatafundering.
7. Bestaande acties, signalen, readiness en controls verbinden tot één centrale controlemotor.
8. Digitale rollen operationaliseren op betrouwbare dossier- en controldata.
9. Calculatiefunctionele werkbank, prijsbronnen en output verder afronden zonder de kern-/interfacegrens te doorbreken.
10. Klantportaal, Outputgenerator, managementsturing en canonieke gebouw-/projectconsolidatie verder uitbouwen.

## Architectuurontkoppeling — actuele stand 5 oktober 2026

De ontkoppeling van actieve domeinlogica uit Drupal/databasekennis wordt module voor module voortgezet volgens het vaste patroon:

```text
domeinservice
-> contract
-> infrastructuuradapter
-> Drupal/database uitsluitend aan de buitenrand
```

Actuele bewezen volgorde op `develop`:

- Finance: actieve domeinservices zijn achter contracten/repositories geplaatst; Drupal Database `Connection` zit in de infrastructuurlaag;
- Projecten/Intake: meerdere persistencegrenzen zijn geïsoleerd, waaronder projectfacturen, stelposten, intakebesluiten, mailboxprojecties en outbound attachments;
- Buildings: project-gebouwrelatiepersistence is geïsoleerd;
- eerstvolgende project-slice: centrale `ProjectPlanningService` wordt ontdaan van directe database- en schemakenis via `ProjectPlanningRepositoryInterface` en `DatabaseProjectPlanningRepository`.

Nieuwe ontkoppelslices mogen functioneel gedrag niet wijzigen: eerst grens schoonmaken en CI/acceptance bewijzen, daarna pas UI/functionele verbouwing.

### Office Core — administration context persistence boundary

`AdministrationContextResolver` schrijft/leest projectadministratie niet meer rechtstreeks via Drupal KeyValue. De persistence loopt via `AdministrationContextStoreInterface` met `DrupalAdministrationContextStore` aan de buitenrand. Deze slice wijzigt bewust nog niet de Node-contextsignatures; die entitygrens volgt apart zodat Finance-/offerteconsumenten niet tegelijk functioneel hoeven te veranderen.

### Office Core — administration registry source boundary

`AdministrationRegistry` leest geen Drupal Config meer rechtstreeks. Geconfigureerde administraties, primaire administratie en legacy-organisatie-instellingen komen via `AdministrationRegistrySourceInterface`; `DrupalAdministrationRegistrySource` bezit de ConfigFactory-afhankelijkheid. Fallback- en nummeringslogica blijven in de registry-service.

### Glas — availability persistence boundary

`GlassAvailabilityService` bevat geen directe database- of schemakennis meer voor voorraadevents. Eventopslag en totalen lopen via `GlassAvailabilityRepositoryInterface` met `DatabaseGlassAvailabilityRepository`; groepering, validatie en vrije-voorraadberekening blijven in de service.

### Glas — calculation link persistence boundary

`GlassCalculationLinkGuard` bevat geen directe database- of schemakennis meer. Exportlinks en bronchecksums worden gelezen via `GlassCalculationLinkRepositoryInterface` met `DatabaseGlassCalculationLinkRepository`; duplicate-export- en stale/current-logica blijven in de service.

### Glas — price repository boundary

`GlassPriceResolver` bevat geen directe database- of Drupal-accountafhankelijkheid meer. Catalogusselectie en immutable artikelprijssnapshots lopen via `GlassPriceRepositoryInterface` met `DatabaseGlassPriceRepository`; productcode-, eenheid-, valuta- en tariefbeslislogica blijven in de resolver. De exporter geeft voor audit alleen een neutrale user-id door.

### Glas — product repository boundary

De glasproductcatalogus zit niet meer als databaseklasse in de servicelaag. Consumers gebruiken `GlassProductRepositoryInterface`; `DatabaseGlassProductRepository` onder Infrastructure bezit database- en time-afhankelijkheden. Verificatie-, selectie- en catalogusgedrag blijven functioneel gelijk.

### Glas — position persistence boundary

`GlassPositionRepository` bevat geen directe Drupal database-, entity- of time-afhankelijkheden meer. Opslag, objectreferentievalidatie en request-time lopen via `GlassPositionPersistenceInterface` met `DrupalGlassPositionPersistence`; technische approval-policy, checksum en concurrencybeslissing blijven in de servicelaag.

### Calculatie — fact/take-off storage boundary

`CalculationFactService` bevat geen directe database- of time-afhankelijkheid meer. Fact- en take-offopslag en request-time lopen via `CalculationFactStoreInterface` met `DatabaseCalculationFactStore`; leveranciersquote-normalisatie, maatdetectie en geometrieberekening blijven in de service.

### Measure — storage boundary

`MeasureRepository` bevat geen directe database- of time-afhankelijkheid meer. Opslag, lookup, capture-versiebepaling en request-time lopen via `MeasureStorageInterface` met `DatabaseMeasureStorage`; domeinvalidatie, provenance, JSON-normalisatie en workflowvoorwaarden blijven in de servicelaag.

### Calculatie — document set storage boundary

`CalculationDocumentSetService` bevat geen directe database- of time-afhankelijkheid meer. Documentsetopslag, projectdocumentprojectie en itemopslag lopen via `CalculationDocumentSetStoreInterface` met `DatabaseCalculationDocumentSetStore`; classificatie, relevantiescore en reviewstatusbeslissing blijven in de service.

### Finance — sales tax settings source boundary

`SalesTaxSettings` kent Drupal Config niet meer rechtstreeks. Btw-regimes en G-rekeningconfiguratie lopen via `SalesTaxSettingsSourceInterface` met `DrupalSalesTaxSettingsSource`; defaults, validatie, actieve opties en G-rekeningsplit blijven Finance-businesslogica.

### Finance — receivables dunning schedule boundary

`ReceivablesDunningManager` kent Drupal Config niet meer rechtstreeks. Het configureerbare herinnering/aanmaning/sommatie/incasso-schema loopt via `ReceivablesDunningScheduleSourceInterface` met `DrupalReceivablesDunningScheduleSource`; defaults, oplopend-schema-validatie, blokkades en escalatielogica blijven Finance-businesslogica.

### Data Intake — canonical attachment source boundary

Managed document extraction, local PDF text extraction en local OCR laden intakebestanden niet meer rechtstreeks via Drupal file entities en FileSystem. Alle drie gebruiken `IntakeAttachmentSourceInterface`; `DrupalIntakeAttachmentSource` valideert permanente bestanden, de private intake-URI en het leesbare pad. Extractie- en normalisatielogica blijven in de enrichers.

### Data Intake — website opportunity gateway boundary

`WebsiteProjectRequestIntakeDestination` kent Drupal Settings, user storage en node entities niet meer rechtstreeks. Lead-owner-resolutie, duplicate lookup en opportunity-persistence lopen via `WebsiteOpportunityGatewayInterface` met `DrupalWebsiteOpportunityGateway`; request-id-validatie, titelvorming en voorlopige scopesamenstelling blijven in de destination-service.

### Calculatie — clock boundary

`CalculationVersionEstablisher` kent Drupal Time niet meer rechtstreeks. Het vaststeltijdstip loopt via `CalculationClockInterface` met `DrupalCalculationClock`; readiness, hashopbouw, snapshots en lockbeslissingen blijven in de servicelaag.

### Finance — business health settings boundary

`BusinessHealthBuilder` en `PortfolioLiquidityProjection` kennen Drupal Config niet meer rechtstreeks. Vaste-kostencategorieën, liquiditeitsdrempels en bankrekeningrollen lopen via `BusinessHealthSettingsSourceInterface` met `DrupalBusinessHealthSettingsSource`. Beide services zijn via de container bedraad, zodat controllers ze niet meer handmatig met `config.factory` construeren. Normalisatie, break-evenberekening, liquiditeitsprojectie en managementsignalen blijven Finance-businesslogica.

### Project Publication — public projection read boundary

`PublicProjectProjection` bevat geen directe database- of file-URL-generatorafhankelijkheid meer. Vrijgegeven projectrecords en media lopen via `PublicProjectPublicationReadRepositoryInterface` met `DatabasePublicProjectPublicationReadRepository`; publieke veldprojectie en JSON-normalisatie blijven in de service.

### Finance — nl.legal runtime config boundary

`NlLegalCollectionProvider` kent Drupal Config niet meer rechtstreeks. API-key (met env-override) en base-URL lopen via `CollectionProviderRuntimeConfigInterface` met `DrupalNlLegalRuntimeConfig`; dossiercontrole, idempotency, payloadvorming en responsevalidatie blijven in de provider-service.

### Office Core — retired communication AI compatibility

`CommunicationAiProcessor` is alleen nog een fail-closed compatibility service voor de retired directe AI-route. De service kent geen Drupal `NodeInterface` meer; `process()` accepteert een neutrale compatibility-input en blijft altijd blokkeren ten gunste van de centrale BREBO Integration API.

### Contract Control — payment gate read boundary

`ContractPaymentGate` bevat geen directe databasekennis meer. Opdracht- en leveranciersfactuurreads lopen via `ContractPaymentGateReadRepositoryInterface` met `DatabaseContractPaymentGateReadRepository`; projectmatching, contractmonitoring en betaalvrijgavechecks blijven in de service.

### Contract Control — payment batch read boundary

`PaymentBatchControlService` bevat geen directe database- of schemakennis meer. Leveranciersfactuurcontrole loopt via `PaymentBatchControlReadRepositoryInterface` met `DatabasePaymentBatchControlReadRepository`; batch-deduplicatie, IBAN/G-rekeningchecks, vier-ogencontrole en anomalielogica blijven in de service.

### Contract Control — audit readiness read boundary

`AuditReadinessEngine` bevat geen directe databasekennis meer. Actieve beleidsregels en het laatste compliance-bewijs per beleidsversie lopen via `AuditReadinessReadRepositoryInterface` met `DatabaseAuditReadinessReadRepository`; volledigheidscontrole, readinesspercentage, ontbrekend-bewijsanalyse en statusclassificatie blijven in de service.

### Projecten/Office Core — offerteformulier

Na de planninggrens is ook `OfferVersionForm` ontdaan van directe databasekennis. Het formulier gebruikt voortaan de bestaande `CalculationAccessRepositoryInterface` voor de laatste vastgestelde calculatieversie en `ProjectContractRepositoryInterface` voor het commerciële termijnschemasnapshot. Daarmee ontstaat geen nieuw parallel contract en blijven bestaande domeingrenzen leidend.

### Project Cockpit — progress read boundary

Na de statusaggregatie is ook `ProjectProgressBuilder` losgetrokken van Drupal entity- en field-API's. De builder bevat uitsluitend voortgangs-, tijdsverloop- en afwijkingslogica; bronactiviteiten worden genormaliseerd aangeleverd via `ProjectProgressReadRepositoryInterface` met een Drupal-adapter aan de buitenrand.

### Project Cockpit — milestone read boundary

Ook `ProjectMilestoneBuilder` is losgetrokken van Drupal entity-API's. De builder bevat uitsluitend fase-, deadline-, blokkade- en eerstvolgende-mijlpaallogica; geordende route-items worden genormaliseerd aangeleverd via `ProjectMilestoneReadRepositoryInterface` met een Drupal-adapter aan de buitenrand.

### Inzet — OnSite identity boundary

Na Project Cockpit is de ontkoppeling voortgezet in `brebo_inzet`. `OnSiteIdentityResolver` kent geen Drupal entity manager of `UserInterface` meer en levert een neutrale identiteit (`uid`, mobiel, taal) via `OnSiteIdentityRepositoryInterface`. Drupal-userqueries en taalvelden zitten in `DrupalOnSiteIdentityRepository`. OTP, uitnodiging en bootstrap consumeren de neutrale identity-projectie waar relevant.

### Inzet — project clock-zone boundary

Na de OnSite identity-grens is ook `ProjectClockZoneManager` losgetrokken van Drupal entities. De manager werkt uitsluitend met een project-id en genormaliseerde clock-zonegegevens via `ProjectClockZoneRepositoryInterface`; Drupal node-query's, building-references en field-extractie zitten in `DrupalProjectClockZoneRepository`. Bestaande geofence- en clocksessiegedrag blijft ongewijzigd.

### Inzet — clock registration persistence boundary

`ClockRegistrationWriter` is losgetrokken van Drupal entities en `NodeInterface`. De writer ontvangt genormaliseerde project-/zone-/gebruikersidentiteit en klokdata en schrijft via `ClockRegistrationRepositoryInterface`; Drupal node-opslag zit in `DrupalClockRegistrationRepository`.

### Inzet — clock session boundary

`ClockSessionManager` en `ClockTransitionReconciler` zijn losgetrokken van Drupal entities en `NodeInterface`. Open sessies, assignment-tijden, registratie-updates en projectwisselreconciliatie lopen via de bestaande `ClockRegistrationRepositoryInterface`, waarvan Drupal node-opslag uitsluitend in `DrupalClockRegistrationRepository` zit. Formulieren/controllers consumeren neutrale sessieprojecties; geofence-, tijdcontrole- en afwijkingslogica blijft ongewijzigd.

### Inzet — OnSite persistence boundary

`OnSiteAssignmentProvider` en `OnSitePresenceEvidenceWriter` kennen geen Drupal entity storage of `NodeInterface` meer. Project-/assignmentselectie, gebouw- en zoneprojecties, referentievalidatie en presence-event opslag lopen via `OnSiteRepositoryInterface`; Drupal node-opslag zit in `DrupalOnSiteRepository`. De expliciete user-actionregel blijft ongewijzigd: geen achtergrondtracking en geen automatische aanwezigheidsregistratie.

### Inzet — project inzet proposal read boundary

`ProjectInzetProposalBuilder` is losgetrokken van Drupal entities. Work-packageperioden en historische werkbegrotingsuren worden via `ProjectInzetProposalReadRepositoryInterface` aangeleverd; Drupal node-query's en bronselectie zitten in `DrupalProjectInzetProposalReadRepository`. De builder houdt uitsluitend periode-, werkdagen-, ploeg- en urenberekening over.

### Inzet — personnel assignment comparison boundary

`PersonnelAssignmentComparison` is losgetrokken van Drupal entities, config en time services. Assignmentprojectie, klokregistraties en timezoneconfig worden via `PersonnelAssignmentComparisonRepositoryInterface` aangeleverd; de service houdt alleen planned-versus-actual urenvergelijking en statusclassificatie over. Consumenten roepen de vergelijking nu aan via assignment-id in plaats van een Drupal `NodeInterface`.

### Inzet — personnel actual-hours / Finance boundary

De laatste directe Drupal-entiteiten in de Inzet-servicelaag zijn verwijderd uit `PersonnelActualHoursManager`, `PersonnelFinanceSynchronizer` en `PersonnelLabourLineResolver`. Assignmentprojectie, medewerkerkostprijs, budget-linekoppeling en operationele review-opslag lopen via `PersonnelAssignmentRepositoryInterface`; Drupal node/user-opslag zit in `DrupalPersonnelAssignmentRepository`. Serviceconsumenten werken via assignment-id en neutrale kostprijsdata.

### Buildings — building truth boundary

`BuildingTruthRepository` is uit de servicelaag gehaald en als infrastructuuradapter ondergebracht in `DatabaseBuildingTruthRepository` achter `BuildingTruthRepositoryInterface`. Forms/controllers gebruiken het contract; database-, entity-, lock- en schemakennis zitten daarmee expliciet aan de buitenrand. Functionele truth-/proposal-/historylogica is ongewijzigd.

### Buildings — building object boundary

`BuildingObjectRepository` is uit de servicelaag gehaald en als `DatabaseBuildingObjectRepository` onder `Infrastructure` geplaatst achter `BuildingObjectRepositoryInterface`. Controllers en afhankelijke modules (Finance, Measure en Building Truth) gebruiken nu het contract. Database-, entity-, time- en schemakennis zitten daarmee aan de buitenrand.

### Buildings — building relation boundary

`BuildingRelationRepository` is uit de servicelaag gehaald en als `DatabaseBuildingRelationRepository` onder `Infrastructure` geplaatst achter `BuildingRelationRepositoryInterface`. PDOK/BAG, Mail Intake en de Building Truth-workbench gebruiken voortaan het contract. Database-, entity- en time-afhankelijkheden zitten daarmee aan de buitenrand; de PDOK acceptance-test is op dezelfde contractgrens aangepast.

### Control — shared action and notification boundaries

De eerste Control-ontkoppeling centraliseert `brebo_control_action` achter `ControlActionRepositoryInterface` met `DatabaseControlActionRepository`. `ControlActionManager`, `ControlInboxService`, `ControlTrendActionService`, `ControlHistoryService` (open-action count) en `PortfolioEarlyWarningService` (recurring drivers) gebruiken deze grens. Notification-deduplicatie en queue-opslag lopen daarnaast via `ControlNotificationRepositoryInterface` en `DatabaseControlNotificationRepository`. Directe action/notification-SQL is daarmee uit de betreffende services verdwenen.

### Control — history boundary

`ControlHistoryService` bevat geen directe database- of service-locatorlogica meer. Snapshot-opslag en history reads lopen via `ControlHistoryRepositoryInterface` met `DatabaseControlHistoryRepository`; project financial control wordt als dependency geïnjecteerd. De service houdt uitsluitend capturevoorwaarden en trendanalyse over.

### Control / Finance — supplier boundaries

Leveranciersfactuur-analytics blijven eigendom van Finance en worden ontsloten via `SupplierInvoiceAnalyticsRepositoryInterface` met `DatabaseSupplierInvoiceAnalyticsRepository`. Control leest daardoor niet meer rechtstreeks uit `brebo_supplier_invoice`. Supplier-performance-events zijn achter `SupplierPerformanceRepositoryInterface` geplaatst. `SupplierPerformanceService`, `SupplierScorecardService` en `PortfolioEarlyWarningService` bevatten daarmee geen directe databasecode meer.

### Mail Intake — canonical project/building context boundary

`MailRelationSuggester` en `CanonicalContextResolver` zijn losgetrokken van Drupal node/entity-API's. Actieve project- en gebouwprojecties worden via `MailContextReadRepositoryInterface` aangeleverd door `DrupalMailContextReadRepository`. Project-gebouwafleiding, naam-/adresmatching, PDOK-resolutie en reviewstatus blijven functioneel gelijk; de bestaande `BuildingRelationRepositoryInterface` wordt nu correct als contract gebruikt.

### Mail Intake — canonical CRM context boundary

`CanonicalCrmContextResolver` is losgetrokken van Drupal node/entity-API's. Exacte contactmatches, organisatie-e-mailmatches en unieke niet-generieke domeinmatches worden via `MailCrmReadRepositoryInterface` aangeleverd door `DrupalMailCrmReadRepository`. De resolver houdt alleen CRM-matchvolgorde, confidence en provisional/canonical statuslogica over.

### Resident Service — access readiness read boundary

`AccessContactResolver`, `ZoneAccessReadiness`, `WorkPackageAccessReadiness` en `LookAheadAccessReadiness` zijn losgetrokken van directe database-, node-, config- en entity-API's. Accessregels, zone-residenties, work-packageprojecties, zone-gebouwkoppeling en timezoneconfig worden via `ResidentAccessReadRepositoryInterface` aangeleverd door `DrupalResidentAccessReadRepository`. De readiness-, inherited-scope- en look-aheadlogica blijft in de services.

### Resident Service — address-scope persistence boundary

`AddressScopeIntake` bevat geen directe Drupal-databaseoperaties meer. Intake-opslag, resolutieresultaten, building-address materialization en residence-persistence lopen via `AddressScopeRepositoryInterface` met `DatabaseAddressScopeRepository` aan de buitenrand. Parser-, persistability-, PDOK-resolutie- en materializationregels blijven in de service. Daarmee is de Resident Service-servicelaag vrij van directe `Connection`, `EntityTypeManagerInterface` en `NodeInterface`-afhankelijkheden.

## Ontwikkelregel bij nieuwe chats

Een nieuwe chat is een voortzetting van dezelfde BREBO Office-ontwikkeling. Begin niet opnieuw met architectuurverkenning. Herstel eerst de actuele stand uit de genoemde bronnen en de actuele GitHub-stand en ga verder vanaf de eerstvolgende technische stap.

Voor centrale intake geldt expliciet: bronnen leveren uitsluitend via de centrale intake; geen bronadapter schrijft rechtstreeks naar Finance, Projecten of een andere vakmodule. De eerstvolgende slice is de menselijke reviewbeslissing, niet opnieuw de intakefundering ontwerpen.

Voor calculatie geldt: ga niet terug naar het ontwerpen van de spreadsheetbasis; die staat.

Voor Mail geldt: behoud de bewezen baseline en gebruik de bron-neutrale intake voor nieuwe routing.

Voor website/klantportaal geldt: BREBO Office blijft bron en externe zichtbaarheid ontstaat uitsluitend via expliciete veilige publicatie/projectie.

Bij iedere betekenisvolle bouwstap moet dit bestand daadwerkelijk worden bijgewerkt wanneer architectuur, implementatiestatus, open technische punten of eerstvolgende stap verandert.


## Drupal-ontkoppeling — 6 oktober 2026

- PR #1222 is gemerged als `bc7e008da00c2fe90f5e5a6db2372150876525e7`: `AuditReadinessEngine` leest beleidsregels en compliance-evidence via `AuditReadinessReadRepositoryInterface`; directe Drupal Database-afhankelijkheid is uit de service verwijderd.
- Volgende lineaire slice: `AuditPackageVerificationService`. De package- en evidence-reads lopen via `AuditPackageVerificationReadRepositoryInterface` en `DatabaseAuditPackageVerificationReadRepository`; hash- en integriteitslogica blijft in de service.
- Werk uitsluitend vanaf actuele `develop`; oude architectuurbranches niet hergebruiken.


### Audit-package exportisolatie

- PR #1223 is gemerged als `edbe2f4288044b7df905fd219c5685f6c8cee682`: `AuditPackageVerificationService` leest packages en evidence uitsluitend via `AuditPackageVerificationReadRepositoryInterface`.
- Volgende lineaire slice: `AuditPackageExportService`. De package-read loopt via `AuditPackageExportReadRepositoryInterface` en `DatabaseAuditPackageExportReadRepository`; verificatie, manifestopbouw, findings en render-targets blijven in de service.


### Audit-package generatorisolatie

- PR #1224 is gemerged als `ed175a6423fd1ee5327e720514443126f8e6cfdb`: `AuditPackageExportService` leest packages uitsluitend via `AuditPackageExportReadRepositoryInterface`.
- Volgende lineaire slice: `AuditPackageGenerator`. Policy-, evidence- en exception-reads plus package-opslag lopen via `AuditPackageGeneratorRepositoryInterface` en `DatabaseAuditPackageGeneratorRepository`; readiness, manifestopbouw, hashing en package-ref blijven in de service.


### Compliance-evidence isolatie

- PR #1225 is gemerged als `56e997dc181bcf443063fdef9b5a59a533fd64a4`: `AuditPackageGenerator` gebruikt uitsluitend `AuditPackageGeneratorRepositoryInterface` voor policy-, evidence- en exception-reads en package-opslag.
- Volgende lineaire slice: `ComplianceEvidenceEngine`. Evidence-opslag en audittrail-read lopen via `ComplianceEvidenceRepositoryInterface` en `DatabaseComplianceEvidenceRepository`; policy-evaluatie, bewijsvereiste en hashing blijven in de service.


### Organizational-learning isolatie

- PR #1226 is gemerged als `18153715573a0ce3dfee8ddfe6e0c1c613fd96e5`: `ComplianceEvidenceEngine` gebruikt uitsluitend `ComplianceEvidenceRepositoryInterface` voor evidence-opslag en audittrail-reads.
- Volgende lineaire slice: `OrganizationalLearningRegistry`. Opslag, reviewselectie en historie lopen via `OrganizationalLearningRepositoryInterface` en `DatabaseOrganizationalLearningRepository`; validaties, vier-ogenprincipe en reviewsemantiek blijven in de service.


### Policy-enforcement isolatie

- PR #1227 is gemerged als `50e0926131b8f7c8a4dc7e4b8ad2253483196e24`: `OrganizationalLearningRegistry` gebruikt uitsluitend `OrganizationalLearningRepositoryInterface` voor opslag, reviewselectie en historie.
- Volgende lineaire slice: `PolicyStandardEnforcementService`. Actieve policy-read, policy-by-id en exception-opslag lopen via `PolicyStandardEnforcementRepositoryInterface` en `DatabasePolicyStandardEnforcementRepository`; requirement-evaluatie, uitzonderingsvoorwaarden en vier-ogenprincipe blijven in de service.


### Control-effectiveness isolatie

- PR #1228 is gemerged als `fea7b845d956a7aef9850e99989f7eb6c5f99dd7`: `PolicyStandardEnforcementService` gebruikt uitsluitend `PolicyStandardEnforcementRepositoryInterface` voor policy-reads en exception-opslag.
- Volgende lineaire slice: `ControlEffectivenessIntelligenceService`. Managementactie-reads lopen via `ControlEffectivenessReadRepositoryInterface` en `DatabaseControlEffectivenessReadRepository`; scoring, exposure-, confidence- en effectivenesslogica blijven in de service.


### Root-cause isolatie

- PR #1229 is gemerged als `b7d323654c51aaf31106d4e686a51862db06c966`: `ControlEffectivenessIntelligenceService` gebruikt uitsluitend `ControlEffectivenessReadRepositoryInterface` voor managementactie-reads.
- Volgende lineaire slice: `RootCauseIntelligenceService`. Managementactie-reads lopen via `RootCauseReadRepositoryInterface` en `DatabaseRootCauseReadRepository`; classificatie, scoring, confidence en governance blijven in de service.


### Contract-monitoring isolatie

- PR #1230 is gemerged als `73af183c405c979014fad42b5598dfd807c2a5ba`: `RootCauseIntelligenceService` gebruikt uitsluitend `RootCauseReadRepositoryInterface` voor managementactie-reads.
- Volgende lineaire slice: `ContractMonitoringService`. Award-read, obligation-opslag/-afronding en statusreads lopen via `ContractMonitoringRepositoryInterface` en `DatabaseContractMonitoringRepository`; validaties, overdue-/blockinglogica en close-status blijven in de service.


### Management-action-source isolatie

- PR #1231 is gemerged als `988e6d2c084d1f473b52bc062dc5121bab753bfb`: `ContractMonitoringService` gebruikt uitsluitend `ContractMonitoringRepositoryInterface` voor award-, obligation- en deviation-persistence/reads.
- Volgende lineaire slice: `ManagementActionSourceResolver`. Action-, controller-case-, blocked-invoice- en overdue-obligation-reads lopen via `ManagementActionSourceReadRepositoryInterface` en `DatabaseManagementActionSourceReadRepository`; source-type routing en contextinterpretatie blijven in de resolver.


### Closed-loop isolatie

- PR #1232 is gemerged als `95b2544d42b67b0fe74b221e7be16144fabeac32`: `ManagementActionSourceResolver` gebruikt uitsluitend `ManagementActionSourceReadRepositoryInterface` voor action- en operationele bronreads.
- Volgende lineaire slice: `ClosedLoopControlService`. Reads van resolved managementacties en action-updates lopen via `ClosedLoopControlRepositoryInterface` en `DatabaseClosedLoopControlRepository`; verificatielogica, signaaldrempels en reopen-termijnen blijven in de service.


### Management-trend isolatie

- PR #1233 is gemerged als `b5f5fe0c96607f7742bb71fc06aca7700c7b5558`: `ClosedLoopControlService` gebruikt uitsluitend `ClosedLoopControlRepositoryInterface` voor resolved-action reads en updates.
- Volgende lineaire slice: `ManagementTrendIntelligenceService`. Snapshot storage/read/upsert lopen via `ManagementTrendRepositoryInterface` en `DatabaseManagementTrendRepository`; periodeberekening, JSON-interpretatie, metric-delta's en risk-direction blijven in de service.


### Management-decision-record isolatie

- PR #1234 is gemerged als `25083b7354f2b4e386e9bf81deaa30bb01dc831b`: `ManagementTrendIntelligenceService` gebruikt uitsluitend `ManagementTrendRepositoryInterface` voor snapshot-storage, reads en upserts.
- Volgende lineaire slice: `ManagementDecisionRecordService`. Tabelbeheer, decision-record insert, unmeasured reads en outcome-updates lopen via `ManagementDecisionRecordRepositoryInterface` en `DatabaseManagementDecisionRecordRepository`; payload/hash-opbouw en 30/90-dagen reviewlogica blijven in de service.


### Controller-case isolatie

- PR #1235 is gemerged als `0516267885f3819af815999d6a05edf49a680e9e`: `ManagementDecisionRecordService` gebruikt uitsluitend `ManagementDecisionRecordRepositoryInterface` voor tabelbeheer, inserts, reads en outcome-updates; de acceptance-workflow is aangepast aan deze repositorygrens.
- Volgende lineaire slice: `ControllerCaseManagementService`. Case insert/read/update lopen via `ControllerCaseRepositoryInterface` en `DatabaseControllerCaseRepository`; severity, exposure, deadlines en onafhankelijke-reviewlogica blijven in de service.


### Payment-anomaly isolatie

- PR #1236 is gemerged als `9bef1720455202f2515c8bacbcce6aa7020557e4`: `ControllerCaseManagementService` gebruikt uitsluitend `ControllerCaseRepositoryInterface` voor case insert/read/update.
- Volgende lineaire slice: `PaymentAnomalyIntelligenceService`. Databeschikbaarheid en threshold-, exception-, decision-pair- en bank-change-reads lopen via `PaymentAnomalyReadRepositoryInterface` en `DatabasePaymentAnomalyReadRepository`; scoring, risiconiveau, signalen en governance blijven in de service.


### Management-action isolatie

- PR #1237 is gemerged als `f357a325f641fd439f05bc2662e4792f1ef9a690`: `PaymentAnomalyIntelligenceService` gebruikt uitsluitend `PaymentAnomalyReadRepositoryInterface` voor databeschikbaarheid en patroonreads.
- Volgende lineaire slice: `ManagementActionEngine`. Action insert/resolve/open/overdue/exists lopen via `ManagementActionRepositoryInterface` en `DatabaseManagementActionRepository`; triggerregels, severity, deadlines en escalatielogica blijven in de service.


### Management-control-center isolatie

- PR #1238 is gemerged als `dee15d7be1c372a0dc2ed060c3ac9937a5bb3093`: `ManagementActionEngine` gebruikt uitsluitend `ManagementActionRepositoryInterface` voor action-persistence en reads.
- Laatste directe `@database`-service in Contract Control: `ManagementControlCenterService`. Blocked-payment-, overdue-obligation- en critical-controller-case-reads lopen via `ManagementControlCenterReadRepositoryInterface` en `DatabaseManagementControlCenterReadRepository`; managementstatus en aggregatielogica blijven in de service.
- Na merge volgt een repo-brede eindscan op directe Drupal-koppelingen in service/domain code.


### Repo-brede Drupal-ontkoppeling — Mail Intake failure store

- PR #1239 is gemerged als `3aa7c48186c058ced05c1129c39c59fff24be022`: Contract Control is service-side vrij van directe `@database`-injecties.
- Repo-brede eindscan gestart. Eerste resterende servicekoppeling: `MailIntakeFailureRegistry`.
- Drupal State-opslag loopt nu via `MailIntakeFailureStoreInterface` en `DrupalStateMailIntakeFailureStore`; bounded register, privacy-safe inhoud, sortering en acknowledge-logica blijven in de service.


### Repo-brede Drupal-ontkoppeling — Receivables reconciliation state

- PR #1240 is gemerged als `f431010bc5878a5bcb2a5e7c09e5f9a67ee7368f`: `MailIntakeFailureRegistry` gebruikt uitsluitend `MailIntakeFailureStoreInterface` voor statusopslag.
- Volgende servicekoppeling: `ReceivablesReconciliationMonitor`. Drupal State-opslag loopt via `ReceivablesReconciliationStateStoreInterface` en `DrupalReceivablesReconciliationStateStore`; last-success-logica en audittrail blijven in de service.


### Repo-brede Drupal-ontkoppeling — Control trend Node-grens

- PR #1241 is gemerged als `d20f54922868cdf75a855789b124b79db81b4831`: `ReceivablesReconciliationMonitor` gebruikt uitsluitend `ReceivablesReconciliationStateStoreInterface` voor statusopslag.
- Volgende servicekoppeling: `ControlTrendActionService`. De service accepteert nu alleen `projectId` en kent geen `NodeInterface` meer; trend- en actionlogica blijven ongewijzigd.
- `ControlAutomationRunner` vertaalt tijdelijk de Drupal Node naar projectId; die entity-laag wordt in een volgende slice achter een project-read abstraction geplaatst.


### Repo-brede Drupal-ontkoppeling — Portfolio project source

- PR #1242 is gemerged als `7ec575ee56204ace2b86b130f7180f81119f201e`: `ControlTrendActionService` accepteert alleen nog `projectId` en kent geen `NodeInterface` meer.
- `PortfolioControlService` gebruikt nu `PortfolioProjectSourceInterface`; actieve Drupal projectnodes en legacy early-warning/history calls zitten in `DrupalPortfolioProjectSource`.
- Exposure-, sorteer-, concentratie- en managementportfolio-logica blijven volledig in de service.


### Repo-brede Drupal-ontkoppeling — Control project boundaries

- PR #1243 is gemerged als `12d886541f19ee7f0ba8ad3b390d84be54003790`: `PortfolioControlService` gebruikt uitsluitend `PortfolioProjectSourceInterface` voor actieve projectfacts.
- `ControlAutomationRunner` gebruikt nu `ControlProjectSourceInterface` en verwerkt alleen scalar project-id's.
- `ControlActionManager` en `ControlHistoryService` gebruiken `ControlProjectAnalysisSourceInterface`; Node/entity/legacy-service calls zitten in `DrupalLegacyProjectControlAnalysisSource`.
- De control-servicelaag bevat daarmee voor runner/action/history geen directe Drupal entity- of Node-contracten meer.


### Repo-brede Drupal-ontkoppeling — OnSite device store

- PR #1244 is gemerged als `59387c389a536d556ef95a5c4c67ba27842a2e42`: runner/action/history gebruiken project-source contracts in plaats van directe Drupal entity/Node-afhankelijkheden.
- Volgende servicekoppeling: `OnSiteDeviceRegistry`. Drupal KeyValue-opslag loopt via `OnSiteDeviceStoreInterface` en `DrupalOnSiteDeviceStore`; tokenuitgifte, hashing, resolve en revoke blijven in de service.


### Repo-brede Drupal-ontkoppeling — OnSite activation store

- PR #1246 is gemerged als `6a673c01fcec7c9ab5658c1078c6d32412bb0a68`: `OnSiteDeviceRegistry` gebruikt uitsluitend `OnSiteDeviceStoreInterface` voor durable device credentials.
- Volgende servicekoppeling: `OnSiteActivationManager`. Expirable Drupal KeyValue-opslag loopt via `OnSiteActivationStoreInterface` en `DrupalOnSiteActivationStore`; tokenuitgifte en single-use activatielogica blijven in de service.


### Repo-brede Drupal-ontkoppeling — OnSite OTP boundaries

- PR #1247 is gemerged als `b0ee8a94741e56642a31733c8b71f75d590ba64a`: `OnSiteActivationManager` gebruikt uitsluitend `OnSiteActivationStoreInterface` voor expirable activation storage.
- `OnSiteOtpManager` gebruikt nu `OnSiteOtpStoreInterface`, `OnSiteOtpRateLimiterInterface` en `OnSiteOtpSecretProviderInterface`; Drupal KeyValue, Flood en PrivateKey zitten in Infrastructure.
- Securitysemantiek blijft gelijk: 5 requests per 900 seconden, challenge-TTL 600 seconden, maximaal 5 verificatiepogingen en HMAC-SHA256 over challenge-id plus code.


### Repo-brede Drupal-ontkoppeling — OnSite invitation

- PR #1248 is gemerged als `47680cf6b69cfc95f91bd225be3e1a68bfbfb5aa`: `OnSiteOtpManager` gebruikt dedicated store/rate-limit/secret contracts en kent geen Drupal KeyValue/Flood/PrivateKey meer.
- `OnSiteInvitationManager` accepteert alleen `uid`, gebruikt `OnSiteIdentityResolver` voor actieve identity/mobiel/taal en `OnSiteInstallLinkBuilderInterface` voor de installatielink.
- Drupal `UserInterface` en route-`Url` blijven alleen in controller/infrastructure.


### Repo-brede Drupal-ontkoppeling — Office Core administration/document context

- PR #1249 is gemerged als `6f357d6cb36d8606ddf6037760af46cac3361789`: `OnSiteInvitationManager` gebruikt alleen scalar uid + install-link contract.
- `AdministrationContextResolver`, `ProjectDocumentIdentityResolver` en `ProjectDocumentNumberIssuer` kennen geen `NodeInterface` meer.
- Node-traversal naar projectcontext loopt via `AdministrationNodeContextSourceInterface` en `DrupalAdministrationNodeContextSource`; service-API's gebruiken project-/context-node-id's.
- Forms, module-hooks en Infrastructure mogen Drupal-entiteiten blijven kennen; administratie- en documentnummeringslogica in de servicelaag niet.


### Repo-brede Drupal-ontkoppeling — Onboarding tour store

- PR #1250 is gemerged als `4ee085f0dd983de5c92a18e3f7907420127e5f2e`: administratie- en documentcontextservices werken met scalar ids en contracts in plaats van `NodeInterface`.
- `OnboardingTourManager` gebruikt nu `OnboardingTourStoreInterface`; Drupal KeyValue en user-entitydetails blijven in Infrastructure/controllers.
- De tourservice-API gebruikt uitsluitend `userId`, `tourId`, status en step.


### Repo-brede Drupal-ontkoppeling — Administration access

- PR #1251 is gemerged als `b8ff5763c7e4d295b275322d9ae44cc58fbda6a9`: `OnboardingTourManager` gebruikt uitsluitend `OnboardingTourStoreInterface`.
- `AdministrationAccessManager` gebruikt nu `AdministrationAccessStoreInterface`; Drupal KeyValue en user-entitydetails blijven in Infrastructure/controllers/forms.
- De access-service-API gebruikt uitsluitend scalar `userId`, administratiecode, rollen en status.
- Tijdens de callercontrole is de resterende onnodige `$user = match (...)`-toewijzing in `OnboardingTourController` gecorrigeerd.


### Repo-brede Drupal-ontkoppeling — Administration numbering

- PR #1252 is gemerged als `24bd1a8aee1a2eef42696121efd2d620b84dbb97`: `AdministrationAccessManager` gebruikt uitsluitend scalar user-id's en `AdministrationAccessStoreInterface`.
- `AdministrationNumberIssuer` gebruikt nu `AdministrationNumberStoreInterface` en `AdministrationNumberLockInterface`; Drupal KeyValue en Lock zitten in Infrastructure.
- Nummerreeksregels, idempotente owner-check, cursorberekening, immutable receipt-opbouw en nummerformattering blijven in de service.

## Projectafbouw — actuele continuïteit 7 oktober 2026

- Projecten eerst functioneel afmaken; daarna Calculatie.
- Projectlijst ondersteunt Lijst, Kanban en algemene verzamelplanning; de verzamelplanning is visueel/Gantt-georiënteerd en moet filters en sortering houden.
- Projectspecificieke Planning gebruikt dezelfde visuele taal, maar met detailactiviteiten, mijlpalen, afhankelijkheden, baseline/voortgang en stoplichtstatus.
- Documenten blijft een eigen hoofdmenu/module met classificaties, filters en sortering; binnen een project wordt slechts de projectspecifieke doorsnede getoond.
- Rapporten volgen hetzelfde centrale-module-plus-projectdoorsnedeprincipe.
- Mail/intake mag niet van ieder bericht automatisch een project maken; projectvorming vereist voldoende context/confidence of menselijke bevestiging.
- De mailpoort kan pas als operationele instroom worden beschouwd wanneer de ontvangende Office-runtime schoon is en de classificatie-/koppelketen fail-safe staat; presentatie/UX van de buitenkant blijft afzonderlijk af te werken.
- Branch `feature/project-ai-order-draft` is aangemaakt vanaf actuele `develop` voor de gecontroleerde AI-conceptorderroute. Geen definitieve order mag door AI zelfstandig worden verzonden of vrijgegeven.
