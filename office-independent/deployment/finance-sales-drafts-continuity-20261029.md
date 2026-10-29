# BREBO Office Finance — continuïteit en overdracht (29 oktober 2026)

## Actuele status
- Repo: `JohnBN1970/brebo-office`.
- PR #1462: `feat/office-finance-unified-invoices-20261027`, basis `develop`. Functionaliteit: uniform factuurregister, inkomende factuuradapter, uitgaande concepten, native btw-regimes.
- PR #1463: `test/office-finance-sales-drafts-integration-20261028`, basis #1462. Tests, MySQL-fixture, CSRF-broncodecontrole en releasechecklist. PR staat open en is klaar voor review. Niet gemerged of gedeployed.
- GitHub Actions run #20 op commit `56103ab` geslaagd. Latere wijzigingen aan documentatie kunnen een nieuwe run vereisen.
- De vier achterliggende commits van #1462 betreffen inhoudelijk hetzelfde formulierbestand als op #1463; blob SHA `ee779d3871e3231853728310a901fcbe97b64195`.
- Er zijn geen geregistreerde reviews vastgesteld bij de laatste controle.

## Bevestigd op Hostinger Office-test (29 oktober 2026)
- Serverpad: `/home/u213420663/domains/brebobv.nl`.
- `office_calc_test` en `office_private` bestaan.
- Testconfiguratie: `office_calc_test/config/config.php`. Deze bevat `OFFICE_BASE_PATH`, `OFFICE_INTAKE_PDO_DSN`, `OFFICE_INTAKE_DB_USER`, `OFFICE_INTAKE_DB_PASSWORD` en bestaande Finance-flags. Geen secrets afdrukken of delen.
- Een read-only PHP/PDO-controle via deze configuratie, met expliciete check `OFFICE_BASE_PATH === '/office-test'`, heeft de volgende tabelstatus op de **echte Office-testdatabase** opgeleverd:
  - `office_finance_sales_drafts`: **ONTBREEKT**
  - `office_vat_regimes`: **ONTBREEKT**
  - `office_module_permissions`: AANWEZIG
  - `office_project_memberships`: AANWEZIG
  - `office_operator_accounts`: AANWEZIG
  - `office_projects`: AANWEZIG
- Dit was uitsluitend een bestaancontrole; kolomtypes, indexen, data, actieve btw-regimes en daadwerkelijke autorisatie zijn nog niet op de echte database geverifieerd.

## Migratiebestanden in PR #1462
- `office-independent/deployment/office-finance-sales-drafts.sql`: tabel voor concepten met JSON-regels en project/operator-indexen.
- `office-independent/deployment/office-vat-regimes.sql`: native btw-catalogus; **geen** standaardtarieven, regimes alleen op basis van goedgekeurde Office-instellingen.
- De migraties zijn **niet uitgevoerd** op Office-test.

## Volgende veilige stappen
1. Verifieer opnieuw de doelomgeving, database-identiteit, huidige tabellen, relevante kolommen en eventuele bestaande objecten. Maak vooraf een herstelpunt/backup en bepaal wie akkoord geeft voor schemawijzigingen.
2. Controleer dat de SQL-migraties exact passen bij de echte Office-teststructuur; beoordeel DDL en eventuele conflicten. Voer alleen na expliciete toestemming de twee benodigde migraties op **Office-test** uit. Geen productie-DSN gebruiken.
3. Draai daarna de read-only schemacontrole `office-independent/tests/OfficeFinanceSalesDraftMySqlSmoke.php` tegen Office-test met de vereiste testomgevingvariabelen; schrijf- en integratietests uitsluitend in tijdelijke GitHub Actions MySQL-fixture.
4. Richt goedgekeurde btw-regimes in via Office-instellingen; geen stilzwijgende btw-tarieven toevoegen.
5. Controleer Finance- en projectrechten. Het aanmaken vereist Finance-toegang plus `office_project_memberships.permission='manage'`; afzonderlijk `finance.create`-recht bestaat nog niet in dit pad.
6. Pas na schema-, rechten- en reviewakkoord de benodigde featureflags voor Office-test aan; voer browseracceptatie uit (login, CSRF, replay, projectisolatie, btw-keuze, 303 redirect). Zie `finance-sales-drafts-office-test-release-checklist.md`.
7. Vraag afzonderlijk akkoord voor merge en deploy. Productie ongemoeid laten.

## Belangrijke grenzen
- Alleen conceptopslag, zonder factuurnummer, verzenden, boeken of definitief maken.
- Geen automatische schemawijzigingen, geen live schrijvende roundtriptest, geen secrets in output.
- CSRF-regressietest is broncodecontrole, geen echte browsertest.
- Bevestigde status: **Office-test database nog NIET gereed voor activering van verkoopfactuurconcepten**.
