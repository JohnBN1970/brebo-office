# BREBO Office — Continuïteitsdocument

## Doel

Dit document voorkomt dat de BREBO Office-ontwikkeling bij een volle of nieuwe chat opnieuw vanaf nul wordt opgebouwd. Het bevat de compacte actuele werkstand en verwijst naar de leidende bronnen.

Het is geen vervanging van het Proceshandboek, CIM, Appendix A, roadmap, UI Design System of wijzigingsregister.

**Actuele peildatum: 6 september 2026.**

## Startvolgorde voor iedere nieuwe ontwikkelsessie

Lees eerst, in deze volgorde:

1. het vastgestelde BREBO Proceshandboek;
2. `docs/APPENDIX_A.md`;
3. `docs/CIM.md`;
4. `docs/ROADMAP.md`;
5. `docs/BMS_CIM_DRUPAL_ALIGNMENT.md`;
6. `docs/BREBO_OFFICE_UI_DESIGN_SYSTEM.md` voor presentatie/UI;
7. `docs/BREBO_CALCULATIE_ARCHITECTUUR.md` voor calculatie;
8. `docs/BREBO_OUTPUTGENERATOR_ARCHITECTUUR.md` voor document-/rapportoutput;
9. dit continuïteitsdocument;
10. de actuele GitHub-stand van `develop` en open pull requests.

Verzin geen nieuwe architectuur of module-eigen presentatietaal wanneer een onderwerp al in deze bronnen is vastgesteld.

## Vaste functionele uitgangspunten

- Het gebouw staat centraal als permanente projectoverstijgende kaartenbak.
- Het project is het tijdelijke stuurmechanisme voor scope, tijd, geld, mensen, toegang, uitvoering en kwaliteit.
- Projectscope selecteert tijdelijk permanente gebouwobjecten.
- BMS en CIM zijn leidend; Drupal is de technische vertaling.
- Eén keer vastleggen, overal hergebruiken.
- Communicatiekanalen en andere bronnen zijn aanvoerkanalen, geen tweede dossierwaarheid.
- AI en digitale rollen signaleren en bereiden voor; formele materiële besluiten blijven binnen aantoonbaar mandaat.
- Geen aannames wanneer bewijs nodig is.
- Belangrijke implementatie geldt pas als duurzaam wanneer zij in GitHub staat.
- Externe toegang gebruikt uitsluitend expliciet vrijgegeven projecties; BREBO Office blijft de bron.

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

`docs/BREBO_CALCULATIE_ARCHITECTUUR.md` blijft leidend.

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

1. Centrale intake-reviewbesluiten bouwen: accepteren, afwijzen, herclassificeren en opnieuw koppelen, met audit en concurrency.
2. Destination-contracten tussen centrale intake en vakmodules expliciet maken zonder directe adapter-writes.
3. Finance/Moneybird leveranciers- en inkoopfactuurketen verder sluiten op dezelfde intake/masterdatafundering.
4. Bestaande acties, signalen, readiness en controls verbinden tot één centrale controlemotor.
5. Digitale rollen operationaliseren op betrouwbare dossier- en controldata.
6. Calculatieprijsbronnen rechtstreeks in de werkbank bedienbaar maken.
7. Klantportaal access/publication/security-hardening afronden voordat publieke toegang wordt geopend.
8. Generieke Outputgenerator implementeren.
9. Management-/portfoliosturing verder uitbouwen.
10. Canonieke gebouw-/projectconsolidatie en legacy-afbouw blijven bewaken.

## Ontwikkelregel bij nieuwe chats

Een nieuwe chat is een voortzetting van dezelfde BREBO Office-ontwikkeling. Begin niet opnieuw met architectuurverkenning. Herstel eerst de actuele stand uit de genoemde bronnen en de actuele GitHub-stand en ga verder vanaf de eerstvolgende technische stap.

Voor centrale intake geldt expliciet: bronnen leveren uitsluitend via de centrale intake; geen bronadapter schrijft rechtstreeks naar Finance, Projecten of een andere vakmodule. De eerstvolgende slice is de menselijke reviewbeslissing, niet opnieuw de intakefundering ontwerpen.

Voor calculatie geldt: ga niet terug naar het ontwerpen van de spreadsheetbasis; die staat.

Voor Mail geldt: behoud de bewezen baseline en gebruik de bron-neutrale intake voor nieuwe routing.

Voor website/klantportaal geldt: BREBO Office blijft bron en externe zichtbaarheid ontstaat uitsluitend via expliciete veilige publicatie/projectie.

Bij iedere betekenisvolle bouwstap moet dit bestand daadwerkelijk worden bijgewerkt wanneer architectuur, implementatiestatus, open technische punten of eerstvolgende stap verandert.
