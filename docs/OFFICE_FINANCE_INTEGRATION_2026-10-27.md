# Office-native Finance — inventarisatie en integratie, 27 oktober 2026

## Bestaande stand op develop

Onderzocht vertrekpunt: `76dd891efbdc4dff2f481ee36c59a9583481870a`.

De Finance-module is een uitgebreide operationele kern, geen lege tegel. `web/modules/custom/brebo_finance/src/Service` bevat werkbegroting/import/goedkeuring, commitments, prestaties en bewijs, three-way matching, betaalvrijgaven, SEPA-batches, G-rekening, bankreconciliatie, verkoop/debiteuren, fasegates, euro-trace en controllerfuncties. Deze businessregels opnieuw bouwen zou bestaande controles dupliceren.

De eerdere ontkoppeling heeft al 85 contractbestanden onder `src/Contract` opgeleverd. Projectidentiteit en toegang zitten achter `ProjectReferenceGatewayInterface`; projectstatuswijzigingen achter `ProjectLifecycleGatewayInterface`; actor/rol/permissies achter `FinancialActorGatewayInterface`; relaties achter `OrganizationReferenceGatewayInterface`. Werkbegrotingimport en de operating layer gebruiken repositorycontracten. `FinancialCommandCenter` heeft een dashboardmethode voor zichtbare projecten, maar de volledige constructiegraaf bevat onder meer cockpit, beslisinbox, toewijzing en reconciliatiemonitor. Die hele graaf aansluiten zonder alle adapters te leveren zou een onvolledige of onveilige aansluiting opleveren.

Ook `PayablesWorkQueueBuilder`, `PurchaseInvoiceControlViewBuilder` en `InvoicePerformanceBlockerResolver` waren al repositorygestuurd. Ze gebruikten nog de module-namespace; hun concrete adapters gebruikten de CMS-database. De onafhankelijke Office-home, sessierepository en projectlidmaatschappen bestonden al. De Finance-navigatie was uitgeschakeld.

## Gerealiseerde aansluiting

- Eén gedeelde `packages/brebo-finance-core`: bovenstaande drie services en vier contracten zijn verplaatst met behoud van de bestaande businessregels. Oude namen zijn compatibility aliases; de bestaande services.yml en controllers blijven dezelfde kern uitvoeren.
- Office-home toont een Finance-tegel en navigatie bij geactiveerde module én actueel Finance-leesrecht.
- `/finance`: nieuw dashboard met open facturen, geblokkeerde werkvoorraad en ter goedkeuring, inclusief btw-definitie, bron en peildatum.
- `/finance/betaalcentrum`: alle zes bestaande werkvoorraden, zoeken, paginering en doorklik naar factuurcontrole.
- `/finance/factuur?id=…`: factuurregels, regelblokkades, laatste betaalvrijgave en aanwezigheid van G-rekeninginstructie.
- Alle routes gebruiken de bestaande centrale Office-cookie en sessievalidatie. Geen eigen Finance-login of overname van CRM-rechten.
- `office_module_permissions` bevat expliciete grants `module=finance, permission=read`. `unassigned_read` is een aanvullend recht voor projectloze facturen, nooit impliciet onderdeel van lezen of projectbeheer. Het account moet actief zijn.
- Projectgegevens worden vóór aggregatie beperkt via de bestaande `office_project_memberships` (`read`/`manage`) én een geverifieerde `office_finance_project_mapping`. Geen impliciete gelijkstelling van bronproject- en Office-ID. Rechten worden bij elk verzoek opnieuw gelezen.
- De Office-PDO-adapter leest uitsluitend bestaande Finance-tabellen. Geen CMS-bootstrap, entity load, provider-SDK of HTTP-fallback naar SBOFFICE. Performance-bewijs uit een vreemd project wordt ook bij een foutief gekoppelde commitment niet getoond.

De nieuwe dashboard-KPI's zijn bewust beperkt tot de aangesloten werkvoorraad. Het is geen complete vervanging van de bestaande managementcockpit, quick ratio, omzet, cashflow of debiteurenmonitor. Een goedgekeurde vrijgave is geen bewijs van bankbetaling. Bestaande classificatievolgorde (vrijgavestatus vóór regelblokkades) is behouden; er worden geen betaalacties aan ontleend.

## Runtimecontract en activatie

De code wordt in deze PR gebouwd en getest; er is geen runtime geactiveerd of database gewijzigd. De Hostinger-test-router bevat de drie Finance-routes. De productie-router wordt buiten deze repository beheerd en moet vóór een latere activatie dezelfde drie paden naar de drie `office-finance*.php` handlers sturen.

1. Lever de Office-bestanden en `packages/brebo-finance-core` samen uit. Standaard staan `office-independent` en `packages` naast elkaar. Bij een private deployment zet de beheerder `OFFICE_FINANCE_CORE_ROOT` op het private pad van het pakket, bijvoorbeeld `…/packages/brebo-finance-core`. Ook de legacy module vereist het pakket op repositoryniveau.
2. Gebruik de bestaande `OFFICE_INTAKE_PDO_DSN` en Office-databasecredentials. Finance leest de bestaande platte `brebo_finance_*` tabellen in die database; er is geen automatische bronkopie, CMS-connectie of fallback. De brondata moeten eerst langs een afzonderlijk gecontroleerde migratie/projection worden aangesloten als ze nog alleen in SBOFFICE staan.
3. `OfficeFinanceSchema::statements()` levert uitsluitend expliciete installatiestatements voor grants en projectmapping. Routes voeren geen DDL uit. Beoordeel deze statements en de bronprojectmapping vóór toepassing op een testomgeving. Geef alleen bedoelde Office-accounts Finance-rechten; geen automatische rolconversie.
4. De leesbron vereist purchase_invoice, purchase_invoice_line, payment_release, g_account_instruction, commitment, commitment_line en performance_receipt conform het bestaande Finance-schema. Ontbrekende benodigde bronnen leveren 503 op, geen schijnbaar lege/goedgekeurde administratie.
5. Bevestig schema, grants, bronvolledigheid en mappings; stel pas daarna `OFFICE_FINANCE_SCHEMA_VERIFIED=enabled` en `OFFICE_FINANCE_ENABLED=enabled` in. Centrale `OFFICE_CRM_SESSION_MODE=enabled`, `OFFICE_PUBLIC_HOST` en HTTPS blijven vereist. Uitzetten van de Finance-vlag sluit alle Finance-routes en verwijdert de tegel.
6. Test met een normale bevoegde gebruiker, een gebruiker zonder Finance-grant, een gebruiker met slechts een ander project, een ingetrokken sessie en een ingetrokken projectlidmaatschap. Controleer desktop en mobiel in de beoogde test-runtime.

Geen merge, productie-deploy, bronmigratie, productiedatabasewijziging, Moneybird-mutatie of bankbetaling maakt deel uit van deze PR-uitvoering.

## Bewijs en vervolg

`OfficeFinanceRegressionTest.php` toetst de zes werkvoorraden, totalen, laatste vrijgave, factuurcontrole, bron-ID-botsingen, onbekende/onbevestigde mapping, projectloze toegang, ingetrokken grants/lidmaatschappen, inactieve accounts, vreemde performancegegevens, escaping, CMS-onafhankelijkheid en de oude class/interface aliases. Dezelfde suite draait op SQLite en een afzonderlijke MySQL 8 CI-database.

`OfficeFinanceHttpTest.php` voert de daadwerkelijke PHP-handlers uit met centrale sessiefixtures: dashboard → werkvoorraad → detail, toegestane/verborgen tegel, verlopen/ingetrokken sessies, inactieve accounts, IDOR/ongeldige IDs, host/HTTPS/methode, vlaggen en ontbrekende bron. Dit is route-integratie; het is geen bewijs van een live Hostinger-browseracceptatie.

CI: `Office Finance acceptance` voert lint en regressies uit op PHP 8.3/8.4 en de MySQL-suite. De bestaande PHP quality gate, Finance/building acceptance en Office shell/Inzet-controles blijven van toepassing. CI gebruikt synthetische data en geen productiecredentials.

Vervolgvolgorde:

1. Gecontroleerde Finance-bronaansluiting, geverifieerde projectmapping en grants in de zelfstandige test-runtime; browseracceptatie door normale Finance-gebruiker.
2. Resterende managementdashboard/receivables-adapters aansluiten op de bestaande command-center/monitor-kern; onbekende of verouderde data expliciet markeren.
3. Bestaande codering/matching/vrijgave/batch/reconciliatie-commands aansluiten met Office-actoradapter, CSRF, audit en behoud van vierogen- en fasegatecontroles. Geen alternatieve betaalmotor bouwen.
4. Operationele Finance-eindaudit pas na de volledige geautoriseerde factuur → betaalbatch → bank/reconciliatieketen. Deze leesintegratie bewijst die keten nog niet.


## Test-runtime preflight en vrijgavevolgorde

De CLI-controle `office-independent/bin/office-finance-preflight.php` is toegevoegd op de Finance-featurebranch. Draai deze uitsluitend op de geïsoleerde Office-testomgeving, met de echte Office-testconfiguratie in de CLI-omgeving:

```sh
php office-independent/bin/office-finance-preflight.php
```

De preflight schrijft niet naar databases, toont geen DSN, credentials of factuurinhoud en geeft een foutcode bij ontbrekende voorwaarden. Hij vereist dat `OFFICE_FINANCE_ENABLED` nog niet `enabled` is. De controles zijn: Office-sessieconfiguratie, publieke host, Office-identiteitsdatabase, modulegrants, Office-projecten en lidmaatschappen, geverifieerde bronprojectmapping, minimaal één daadwerkelijk geautoriseerde combinatie van actieve Finance-gebruiker en projectlidmaatschap, Finance-leesbron en zeven benodigde brontabellen. De financiële leesbron kan via `OFFICE_FINANCE_READ_PDO_DSN` apart worden aangesloten. Gebruik daarvoor een databaseaccount met uitsluitend SELECT-rechten; de applicatie opent bovendien een read-only transactie. De preflight alleen bewijst niet dat de brondata actueel, compleet of financieel juist zijn.

Bij `FAIL`: niet activeren, ontbrekende randvoorwaarde herstellen en opnieuw controleren. Bij `PASS`: brondata en projectmapping inhoudelijk beoordelen, een bevoegde en een onbevoegde gebruiker via HTTPS op `/office-test/finance`, `/office-test/finance/betaalcentrum` en factuurdetail testen; controleer ook ingetrokken sessies en projectlidmaatschappen. Pas na die acceptatie mogen de flags `OFFICE_FINANCE_SCHEMA_VERIFIED` en `OFFICE_FINANCE_ENABLED` op `enabled` worden gezet. Deze PR voert die activering niet uit.

Let op: de preflight is een voorbereidende test en mag niet worden gebruikt als bewijs dat de betaalopdrachten, Moneybird-synchronisatie of bankreconciliatie in de zelfstandige Office werken.
