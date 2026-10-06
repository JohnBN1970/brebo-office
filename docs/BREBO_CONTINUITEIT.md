# BREBO Office — Continuïteitsdocument

## Doel

Dit document voorkomt dat de BREBO Office-ontwikkeling bij een volle of nieuwe chat opnieuw vanaf nul wordt opgebouwd. Het bevat de compacte actuele werkstand en verwijst naar de leidende bronnen.

Het is geen vervanging van het Proceshandboek, CIM, Appendix A, roadmap, UI Design System of wijzigingsregister.

**Actuele peildatum: 5 oktober 2026.**

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

De Project Cockpit is een persistente operationele stuurlaag en bevat projectcontext voor onder meer project, planning, geld/cash, inzet, kwaliteit, risico en projectgebonden dossier-/financetabs.

Directie-/portfoliosturing, prognoses, faalkosten, organisatiebrede KPI's en leerpatronen blijven de volgende managementlaag.

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

## Ontwikkelregel bij nieuwe chats

Een nieuwe chat is een voortzetting van dezelfde BREBO Office-ontwikkeling. Begin niet opnieuw met architectuurverkenning. Herstel eerst de actuele stand uit de genoemde bronnen en de actuele GitHub-stand en ga verder vanaf de eerstvolgende technische stap.

Voor centrale intake geldt expliciet: bronnen leveren uitsluitend via de centrale intake; geen bronadapter schrijft rechtstreeks naar Finance, Projecten of een andere vakmodule. De eerstvolgende slice is de menselijke reviewbeslissing, niet opnieuw de intakefundering ontwerpen.

Voor calculatie geldt: ga niet terug naar het ontwerpen van de spreadsheetbasis; die staat.

Voor Mail geldt: behoud de bewezen baseline en gebruik de bron-neutrale intake voor nieuwe routing.

Voor website/klantportaal geldt: BREBO Office blijft bron en externe zichtbaarheid ontstaat uitsluitend via expliciete veilige publicatie/projectie.

Bij iedere betekenisvolle bouwstap moet dit bestand daadwerkelijk worden bijgewerkt wanneer architectuur, implementatiestatus, open technische punten of eerstvolgende stap verandert.
