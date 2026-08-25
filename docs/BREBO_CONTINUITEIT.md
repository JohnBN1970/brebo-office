# BREBO Office — Continuïteitsdocument

## Doel

Dit document voorkomt dat de BREBO Office-ontwikkeling bij een volle of nieuwe chat opnieuw vanaf nul wordt opgebouwd. Het bevat de compacte actuele werkstand en verwijst naar de leidende bronnen.

Het is geen vervanging van het Proceshandboek, CIM, Appendix A, roadmap, UI Design System of wijzigingsregister.

**Actuele peildatum: 25 augustus 2026.**

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
- Communicatiekanalen zijn aanvoerkanalen, geen tweede dossierwaarheid.
- AI en digitale rollen signaleren en bereiden voor; formele materiële besluiten blijven binnen aantoonbaar mandaat.
- Geen aannames wanneer bewijs nodig is.
- Belangrijke implementatie geldt pas als duurzaam wanneer zij in GitHub staat.
- Externe toegang gebruikt uitsluitend expliciet vrijgegeven projecties; BREBO Office blijft de bron.

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

De actuele roadmap schat BREBO Office op circa **70% richting breed dagelijks bruikbaar** en **55-60% richting de volledige eindvisie**. Het systeem is de prototypefase voorbij. Communicatie, calculatie, projectsturing, inzet en Finance bevatten inmiddels echte operationele ketens.

De hoofdbeweging is nu:

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

**Eerstvolgende calculatiestap:** prijsbronbediening rechtstreeks vanuit calculatieregels, daarna document-/e-mailextractie, review, OA-boeking, bronvergelijking en leveranciersanalyse.

## Finance en Moneybird

Finance is inmiddels een kernlaag en niet meer alleen een gepland domein.

Aanwezig/gebouwd zijn onder meer:

- projectbegroting en financiële projectsturing;
- inkoop;
- contracten;
- facturen;
- termijnschema's;
- gemengde btw-/btw-verlegdlogica;
- stelpostbewaking;
- verkoopfactuurconcepten en gecontroleerde vrijgave;
- immutable outbox en queue-afhandeling;
- HMAC-beveiligde Integration API-keten;
- Moneybird-providervertaling en terugschrijving naar de BREBO-spiegel;
- read-only verwerking/spiegeling van Moneybird-inkoopinformatie en verdere leveranciers/masterdatafundering.

De beveiligde verkoopfactuurketen is technisch gesloten. Fail-closed configuratie, idempotency en reconciliation bij onzekere provideruitkomsten blijven vaste veiligheidsprincipes.

**Eerstvolgende Finance-opgave:** leveranciersidentiteit/masterdata, inkoopfacturen, projectkoppeling en financiële control verder tot één gesloten keten brengen en productie-evidence blijven bewaken.

## Mail en communicatie

De centrale Mail Intake-kernketen is productiegeaccepteerd. De bewezen mailbox-, reader-, compose-, tabs- en linkingbaseline moet behouden blijven.

Sinds de oorspronkelijke acceptatie zijn mailboxprojectie en HTML-weergave verder uitgebouwd. Inkomende communicatie blijft gecontroleerde broninformatie; classificatie en relaties worden niet stilzwijgend formele dossierwaarheid.

**Belangrijk open punt:** de historische Zoho-backfill van circa 19.000 berichten blijft bewust geblokkeerd totdat de canonieke `sboffice`-runtime alle vereiste Mail-runtimeconfiguratie/readiness aantoonbaar groen heeft. Geen bulkimport forceren om een configuratieprobleem heen.

Daarna: gecontroleerde historie-import, threads/bijlagen, contextverrijking, AI-concepten, retentie en operationele kwaliteitsmetingen.

## Project Cockpit en managementsturing

De Project Cockpit is een persistente operationele stuurlaag en bevat projectcontext voor onder meer:

- project;
- planning;
- geld/cash;
- inzet;
- kwaliteit;
- risico;
- projectgebonden dossier-/financetabs.

Hiermee is roadmapfase 8 niet meer uitsluitend toekomstig. Directie-/portfoliosturing, prognoses, faalkosten, organisatiebrede KPI's en leerpatronen blijven de volgende managementlaag.

## Inzet en personeelssturing

BREBO Office bevat mobiele/PWA-bouwstenen voor personeelsinzet en de eigen Shiftbase-richting, waaronder projectgebonden klokregistratie, GPS/klokzones, aanwezigheid/vertrek en afwijkingsafhandeling.

De personeelslaag moet verder worden verbonden met planning, werkbegroting, projectcontrol en managementinformatie.

## Actie-, signaal- en controlemotor

Roadmapfase 5 is niet meer alleen gepland. Er bestaan meerdere control-services, cockpit-signalen, readiness-/release-gates, contract-/financiële controles en leveranciers-/scorecardbouwstenen.

De hoofdopgave is nu één centrale motor te vormen die bron, eigenaar, termijn, status, risico en afsluitbewijs uniform bewaakt. Nieuwe module-eigen controlelijstjes zijn ongewenst wanneer dezelfde betekenis centraal kan worden gemodelleerd.

## Bewoners, woningen, toegang en service

Resident service gebruikt het canonieke gebouwmodel en omvat bewoners/servicecontext, meldingen, klachten, schade, nazorg, foto's/annotaties en toegang/readiness.

Vaste keten:

```text
ZoneAccessReadiness
-> WorkPackageAccessReadiness
-> formele brebo_release_gate
```

Look-ahead signaleert en wijzigt planning of formele vrijgave niet zelfstandig.

## Klantportaal

`brebo_client_portal` vormt de veilige foundation voor externe projecttoegang.

Vaste grens:

```text
BREBO Office interne waarheid
-> expliciete vrijgave/publicatie
-> veilige externe projectie
-> klantportaal
```

Per project wordt toegang bestuurbaar gemaakt. Alleen expliciet vrijgegeven informatie mag extern zichtbaar worden. Interne projectbesturing en projectvoortgangprojectie zijn gebouwd; publieke routes/login worden pas geopend nadat toegang, blokkering, publicatiegrenzen, concurrency en security aantoonbaar gesloten zijn.

Geen interne objecten rechtstreeks extern renderen en geen tweede klantendossier naast Office bouwen.

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

Implementatie moet generiek bruikbaar zijn voor calculatie/offerte, inkoop, project, bewoners/service, KAM/oplevering, gebouw/MJOP en management.

## Integration API en deployment

- Worker: `brebo-integration-api`.
- HMAC v1-beveiliging blijft leidend.
- Deploymentwijzigingen verlopen via GitHub Actions.
- Externe providercredentials horen niet in Drupal of broncode.
- `sboffice` moet de enige canonieke productieruntime zijn.
- Legacy-runtime/resten worden gecontroleerd uitgefaseerd, niet parallel in leven gehouden.

## Eerstvolgende technische punten — organisatiebreed

1. `sboffice` runtime/readiness volledig canoniek maken en legacyconfiguratie opruimen.
2. Mail/Zoho-readiness groen bewijzen en daarna historische migratie gecontroleerd uitvoeren.
3. Finance/Moneybird leveranciers- en inkoopfactuurketen verder sluiten.
4. Bestaande acties, signalen, readiness en controls verbinden tot één centrale controlemotor.
5. Digitale rollen operationaliseren op betrouwbare dossier- en controldata.
6. Calculatieprijsbronnen rechtstreeks in de werkbank bedienbaar maken.
7. Klantportaal access/publication/security-hardening afronden voordat publieke toegang wordt geopend.
8. Generieke Outputgenerator implementeren.
9. Management-/portfoliosturing verder uitbouwen.
10. Canonieke gebouw-/projectconsolidatie en legacy-afbouw blijven bewaken.

## Ontwikkelregel bij nieuwe chats

Een nieuwe chat is een voortzetting van dezelfde BREBO Office-ontwikkeling. Begin niet opnieuw met architectuurverkenning. Herstel eerst de actuele stand uit de genoemde bronnen en de actuele GitHub-stand en ga verder vanaf de eerstvolgende technische stap.

Voor calculatie geldt expliciet: ga niet terug naar het ontwerpen van de spreadsheetbasis; die staat. Hervat bij prijsbronbediening tenzij GitHub inmiddels een latere stand toont.

Voor Mail geldt: behoud de bewezen baseline en forceer geen Zoho-backfill zolang runtime-readiness niet aantoonbaar groen is.

Voor het klantportaal geldt: BREBO Office blijft bron en externe zichtbaarheid ontstaat uitsluitend via expliciete veilige publicatie/projectie.

Bij iedere betekenisvolle bouwstap moet dit bestand daadwerkelijk worden bijgewerkt wanneer architectuur, implementatiestatus, open technische punten of eerstvolgende stap verandert.
