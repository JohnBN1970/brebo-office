# Finance verkoopfactuurconcepten — Office-test vrijgavecontrole

Deze controle hoort bij PR #1463 en is **geen** toestemming voor een deploy, migratie of featureflagwijziging.

## Vereiste volgorde
1. Controleer dat PR #1462 en PR #1463 zijn gereviewd en dat de relevante CI-runs groen zijn.
2. Verifieer de actieve Office-test codeversie, databaseverbinding en de bestaande tabellen met een uitsluitend-lezen account.
3. Vergelijk de daadwerkelijke kolommen en typen van `office_finance_sales_drafts`, `office_vat_regimes`, `office_module_permissions`, `office_project_memberships`, `office_operator_accounts` en `office_projects` met de repository en de bestaande Office-test structuur.
4. Controleer expliciet dat de goedgekeurde btw-regimes uit Office-instellingen komen; voeg geen standaardtarieven toe.
5. Controleer een actieve Finance-operator met projectbeheerrechten en een operator met uitsluitend leesrechten; gebruik uitsluitend geautoriseerde testaccounts.
6. Voer de read-only schema-smoketest uit op Office-test met `OFFICE_BASE_PATH=/office-test`, een MySQL DSN voor Office-test en `OFFICE_VAT_REGIMES_SCHEMA_VERIFIED=enabled` **alleen wanneer het schema vooraf aantoonbaar geverifieerd is**.
7. Laat de schrijvende `OfficeFinanceSalesDraftMySqlRoundTripTest.php` **uitsluitend** in de tijdelijke GitHub Actions database `office_finance_ci` draaien. Niet uitvoeren op Office-test of productie.
8. Vraag afzonderlijk akkoord voor migraties, activeren van featureflags en daadwerkelijke functionele acceptatie in Office-test.

## Featureflags (standaard uit laten)
- `OFFICE_VAT_REGIMES_SCHEMA_VERIFIED`
- `OFFICE_FINANCE_SALES_DRAFTS_SCHEMA_VERIFIED`
- `OFFICE_FINANCE_SALES_DRAFTS_CREATE_ENABLED`

## Stopcriteria
- Ontbrekende of afwijkende tabellen/kolommen
- Onvoldoende onderbouwde Finance- of projectrechten
- Geen expliciet goedgekeurde actieve btw-regimes
- Een mislukte CI-test of ontbrekende handmatige review
- Onbedoelde verwijzing naar productiegegevens

## CI-bewijs en beperkingen (29 oktober 2026)
- PR #1463, commit `4343754`: GitHub Actions run #16 geslaagd (MySQL 8 fixture, Finance PHP-syntax, unit-, ACL-, rollback- en CSRF-broncodecontrole).
- De CSRF-test inspecteert broncode en simuleert **geen** echte browser, cookie of sessie; browseracceptatie blijft verplicht.
- De MySQL-fixture bevat vereenvoudigde tabellen voor gebruikers en rechten; een groene CI-run bewijst **niet** dat de bestaande Office-test database hetzelfde schema heeft.
- PR #1463 is gestapeld op #1462; de integratiebranch bevat overgenomen formulierwijzigingen. Controleer bij review de actuele basis en voorkom dubbele of verloren wijzigingen.
- Geen migratie, live schrijfactie, featureflagwijziging of deploy uitgevoerd.

## Code-review aandachtspunten (29 oktober 2026)
- `OfficeOutgoingInvoiceRepository::create()` staat alleen toe op `/office-test` met beide conceptflags ingeschakeld; dit is geen algemene productieschrijfroutine.
- De repository controleert Finance-leesrecht via `OfficeFinanceAccess::allows()` en daarnaast afzonderlijk `office_project_memberships.permission='manage'`. Bevestig in de functionele review dat deze combinatie het bedoelde **factuur-aanmaakrecht** vertegenwoordigt; er bestaat in dit pad geen afzonderlijk `finance.create`-recht.
- De rechtencontrole binnen de transactie verkleint het tijdvenster bij rechtenwijzigingen, maar garandeert zonder databasevergrendeling niet dat gelijktijdige intrekking onmogelijk is.
- De formulier-CSRF-token wordt pas na geslaagde opslag verwijderd; controleer de sessie- en herhaal-POST-afhandeling met een echte browser.
- Het formulier neemt een vrij ingevoerde klantnaam aan; de koppeling met een formele debiteur en definitieve facturatie valt buiten deze conceptfase.

## Nog niet afgedekt
- Live Office-test migratie en functionele browseracceptatie
- Formulier-CSRF en sessiegedrag in echte hostingomgeving
- Factuurnummering, definitief maken, verzenden en boekhoudkundige verwerking
