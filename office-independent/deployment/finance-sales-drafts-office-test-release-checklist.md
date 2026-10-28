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

## Nog niet afgedekt
- Live Office-test migratie en functionele browseracceptatie
- Formulier-CSRF en sessiegedrag in echte hostingomgeving
- Factuurnummering, definitief maken, verzenden en boekhoudkundige verwerking
