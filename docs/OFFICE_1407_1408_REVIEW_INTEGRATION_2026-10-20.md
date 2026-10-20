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
