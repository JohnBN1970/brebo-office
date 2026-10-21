# Hervatting PR #1407 op de basis van #1408 — 20 oktober 2026

## Vastgestelde GitHub-stand

- #1408 is gemerged naar develop als d62eda59cbd3f763945e88902cd55d1f2a5dc867.
- #1407 staat nog als draft tegen feat/office-isolated-test-mount; head 15fcbf5b5c2db2fc95d3ae3b7d149e54c77e7d85.
- Een lokale samenvoegproef van #1407 met deze develop leverde 24 conflicten op. De proef is afgebroken; er is geen onafgemaakte merge.
- De botsing is inhoudelijk: de oudere OnSite-keten gebruikt onsite_*-projecties en een vlak eventbericht; develop gebruikt Office-native identiteit, apparaatregistratie, gebouwzones en een event-envelop.

## Deze afgebakende integratie

De bestaande bronreview uit #1407 is selectief overgenomen op develop. De test-only route /office-test/inzet/importcontrole gebruikt de bestaande Office-sessie en een afzonderlijk, exact geconfigureerd beoordelaar-ID. Geen omzetting naar integer: bijvoorbeeld 1 en 1-other mogen nooit dezelfde bevoegdheid krijgen. Het formulier is gebonden aan zowel de operator als de huidige Office-login. Een andere login maakt het oude formulier ongeldig.

De oorspronkelijke SQLite-bron wordt read-only geopend. Onderzoeken of uitsluiten wordt apart opgeslagen met revisiecontrole en transactionele audit. Directe koppeling van personen/projecten blijft geblokkeerd zolang de canonieke doelvalidatie ontbreekt. Het scherm verleent geen uren-, factuur- of betalingsgoedkeuring.

De native OnSite-klassen, aanwezigheid, uitnodiging/intrekking, gebouwzones, projectpagina en iOS-bestanden zijn niet meegenomen uit de oude branch. Dit is geen volledige consolidatie of vervanging van #1407; overige CRM-, Calc-, planning- en legacy-OnSite-delen blijven afzonderlijk te beoordelen.

## Verificatie

Lokaal PHP 8.3.35 met pdo_sqlite: syntaxcontrole en zes tests geslaagd. Vier bestaande review-smokes controleren bronlabels, filters, beslissingen en reviewcyclus. Nieuwe tests controleren exacte tekstuele identiteit, loginbinding, audit-rollback en de werkelijke PHP-route in afzonderlijke processen met tijdelijke SQLite-fixtures. Die routecontrole dekt onder meer actieve/inactieve accounts, ingetrokken sessies, verkeerde beoordelaar, CSRF, andere login, vreemde Origin, payloadgrens, onbekend issue, ongeldige doelkoppeling, herhaling en ongewijzigde bronhash.

Dit is applicatiebewijs met een gesimuleerde HTTPS-servercontext, geen browser-, Hostinger-, MySQL- of productieacceptatie. CI draait dezelfde slice op PHP 8.3 en 8.5 naast de bestaande workflows.

## Voorwaarden voor een afzonderlijke testdeploy

- Bestaande HTTP-authenticatie en Office-testconfig behouden; niet blind de serverrouter vervangen.
- OFFICE_TEST_INZET_IMPORT_REVIEW_ENABLED=enabled en OFFICE_TEST_INZET_IMPORT_REVIEWER_ID moeten bewust ingesteld zijn. De laatste is een exacte string uit de Office-sessie, geen personeels-ID.
- OFFICE_TEST_INZET_SQLITE_PATH verwijst naar de bestaande private, onveranderlijke bron. OFFICE_TEST_INZET_REVIEW_DECISIONS_SQLITE_PATH verwijst naar een afzonderlijke private beslissingenopslag buiten public_html.
- De nieuwe reviewer_id-kolommen gebruiken TEXT. Oude INTEGER-affinity stores kunnen tekst-ID's zoals 01 stilzwijgend veranderen. Schrijven naar zo'n store wordt daarom geweigerd. Controleer het bestaande schema en behoud alle eerdere besluiten/audit; deze PR bevat geen automatische conversie. Nieuwe lege teststores kunnen expliciet met bin/init-inzet-review-store.php worden ingericht. Dat CLI-script is geen migratieprogramma voor bestaande stores.
- Na CI en review: afzonderlijke goedgekeurde testdeploy, daarna browseracceptatie met werkelijk Office-account, bron- en auditcontrole en herstelplan.

Geen merge naar develop, test-/productiedeploy, bestaande databasewijziging of iPhone-build uitgevoerd.

## Vervolg: uitleescontrole voor de bestaande testopslag

`office-independent/bin/preflight-inzet-review.php` is een zelfstandig CLI-bestand. Het leest de bestaande private testconfig en opent uitsluitend bestaande SQLite-bestanden read-only. Geen database-initialisatie, migratie, chmod, router-/configwijziging of activatie. De private bron en beslissingenopslag moeten binnen de expliciet opgegeven private root en buiten de documentroot liggen. Bron en beslissingen mogen ook niet via een hardlink hetzelfde bestand zijn.

De uitvoer bevat uitsluitend gecontroleerde statuscodes, kolomtypen en aantallen: bronregels, issues, besluiten, auditregels, ontbrekende bronverwijzingen, afwijkend auditspoor, dubbele revisies en onverwachte triggers/views. Geen namen, notities, actor-ID's, credentials of absolute bestandspaden. WAL of een open journal vereist een afzonderlijke consistente snapshot; de controle probeert zelf niets te herstellen of te checkpointen.

- `READY_FOR_REVIEW`: gelezen schema en gecontroleerde auditvoorwaarden passen; dit bewijst nog geen Office-login, serverrechten of browseracceptatie.
- `REVIEW_REQUIRED`: bijvoorbeeld geen beslissingenpad, oude INTEGER-kolommen, afwijkend auditspoor of onbekende triggers. Eerst het rapport beoordelen; niet automatisch converteren.
- `BLOCKED`: onveilige paden, ontbrekende bestanden/kolommen of geen bruikbare snapshot. Bestaande bestanden blijven ongewijzigd.

De reviewpagina toont bestaande beoordelingen read-only bij oude reviewer-kolommen en biedt dan geen opslagformulieren aan; POST wordt met 503 geweigerd. Dit voorkomt een werkend ogend formulier dat pas bij opslaan vastloopt.

Lokaal zijn nu zeven tests geslaagd, inclusief de zelfstandige CLI, behoud van bestandshashes en directory-inhoud, ontbrekende bestanden zonder aanmaak, bronalias/webrootweigering, oud schema zonder conversie, auditverschillen en WAL-weigering. De CI-matrix is uitgebreid met deze test. Werkelijke Hostinger-schemastatus blijft onbekend totdat deze uitleescontrole daar is uitgevoerd. Er is geen ingediende onafhankelijke GitHub-review op #1410; geen reviewthreads is geen bewijs van afgeronde review.

## Hostinger-uitleesbewijs — 21 oktober 2026

De gebruiker heeft het hash-gecontroleerde preflightbestand van commit 06f4d974f723a4eff58fb297f0dc9fe316d8fcbc uitgevoerd. Bestandscontrole: OK. Resultaat: read_only=true, status=BLOCKED, reason=SOURCE_IDENTITY_KEY_REVIEW_REQUIRED; source en decisions stonden beide op NOT_CHECKED. Daarmee is uitsluitend bewezen dat minimaal één primaire sleutelvoorwaarde niet past. Welke tabel afwijkt, of er een view/unieke index bestaat en of er werkelijk dubbele bron-ID's zijn, is nog onbekend. Dit is geen bewijs van corrupte brondata en geen reden om tabellen of imports opnieuw aan te maken.

De controle is uitgebreid met source_identity per verwacht bronobject: table/view, verwachte sleutelkolom, type, primaire-sleutelpositie/-kolomtelling, enkelvoudige unieke index en aantallen lege/dubbele sleutels. Zij geeft geen rijwaarden, indexnamen of viewdefinities vrij. Een samengestelde primaire sleutel certificeert niet langer onterecht de afzonderlijke bronkolom. De bronblokkade blijft gehandhaafd totdat de werkelijke schema-informatie is beoordeeld; er is geen automatische acceptatie van een view of unieke index toegevoegd. De afzonderlijke beslissingenopslag wordt nu ook uitgelezen wanneer alleen deze bronidentiteitsvoorwaarde afwijkt.

Zeven lokale tests slagen, met extra gevallen voor views, unieke indexen, dubbele/lege sleutelwaarden, samengestelde sleutels, aparte inspectie van de beslissingenopslag en ongewijzigde bronhash. De volgende runtime-stap is uitsluitend deze uitgebreidere uitleescontrole uitvoeren. Geen migratie, herimport, merge of deploy.

## Hostinger-schema bevestigd — vervolg op 21 oktober 2026

De uitgebreidere gebruikersuitvoer bevestigt: review_issues is een tabel met enkelvoudige INTEGER primary key, 85 issues, geen lege/dubbele sleutels. Worksheets is een tabel met dezelfde sleutelgarantie en 90 regels. Inzet_source_workbench is een view met 1.940 regels en nul lege/dubbele source_row_id's. De primaire-sleuteleis op die read-only projectie was te streng en is gecorrigeerd: alleen voor deze specifieke lookup-view wordt de gemeten unieke/niet-lege join binnen de uitleessnapshot geaccepteerd. Issue-identiteit en de andere primaire sleutels blijven vereist. Duplicaten/lege keys blijven blokkeren. Dit verandert de bron niet.

Twee issues hebben geen gekoppelde bronregel; de nieuwe controle splitst deze telling in een lege bronverwijzing versus een niet-lege maar onopgeloste verwijzing. Zij worden niet weggefilterd, hersteld of automatisch goedgekeurd.

De bestaande beslissingenopslag bevat één besluit en één auditregel. Reviewer-kolommen zijn beide INTEGER. Auditverschillen, dubbele revisies, verweesde audit, auditgaten, onbekende triggers/views en besluiten zonder bronissue zijn alle nul. Beoordelaar is geconfigureerd. Dit onderbouwt voorbereiding van een aparte TEXT-schema kopie; er is nog geen kopie op de server aangemaakt of geactiveerd.

`bin/prepare-inzet-review-copy.php` maakt alleen met expliciete --create-copy een nieuw, private 0600-bestand naast de bestaande beslissingenopslag. De doelnaam is een beperkte bestandsnaam, geen pad; bestaande bestanden/symlinks worden atomair geweigerd. De oude opslag en bron worden met read-only transacties geopend. Onbekende tabellen, kolommen, triggers/views en aangepaste indexes worden geweigerd voordat data wordt weggelaten. Alle opgeslagen waarden worden exact vergeleken; uitsluitend reviewer_id wordt bewust naar de tekstrepresentatie van de historische opgeslagen waarde omgezet. Eerder verloren voorloopnullen worden niet geraden. Audit-ID's en de oorspronkelijke autoincrement-stand blijven behouden. Na het kopiëren volgen preflight, bron-/origineelhashcontrole en terugkoppeling COPY_CREATED_NOT_ACTIVATED. Configuratie wordt nooit omgezet; omschakelen is een volgende, afzonderlijke stap. Een mislukte conversie ruimt alleen het zelf aangemaakte doelbestand op.

Acht lokale tests slagen. De nieuwe test reproduceert een bronview en twee issues zonder broncontext, één legacy-besluit met audit, Unicode/notities, een hogere audit-sequence, echte CLI-uitvoering, behouden bestandshashes/config, weigering van overschrijven/onbekende tabellen en terugdraaien bij verlies van een waarde/type. De bestaande kopie wordt niet vervangen. CI omvat deze tests op PHP 8.3 en 8.5. Geen productieaanpassing, actieve configuratiewijziging, herimport, merge of deploy uitgevoerd.
