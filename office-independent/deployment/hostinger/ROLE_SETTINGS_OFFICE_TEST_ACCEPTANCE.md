# Office-test — Rollen en rechten: migratie- en acceptatiecontrole

Status: **NIET UITGEROLD**. Dit document autoriseert geen databasewijziging.

## Vooraf controleren (alleen lezen)

1. Bevestig dat de actieve mount **/office-test** is, dat de database de afzonderlijke Office-testdatabase is en dat productie-DSN's nergens gebruikt worden.
2. Leg actieve release, Git-commit, routerpad en configuratie vast. Bewaar een herstelbare databaseback-up.
3. Controleer in `information_schema.tables` of `office_roles`, `office_user_roles`, `office_role_permissions` en `office_role_scopes` ontbreken of al bestaan. **Bij bestaande tabellen: stop; inspecteer eerst de schema's en inhoud.**
4. Controleer de bestaande `office_operator_accounts`, `office_module_permissions` en `office_project_memberships`; wijzig deze niet tijdens de migratie.
5. Controleer of er al een actieve operator is met de expliciete directe grant `settings.manage`. **Niet automatisch toekennen**.
6. Controleer de actuele PR-CI: alle workflows moeten groen zijn. Review de testdekking voor rolrechten, CSRF, projectautorisatie en gegevensfilters.

## Migratie — pas na expliciete goedkeuring

- Maak uitsluitend de vier nieuwe tabellen aan met de statements uit `src/Offer/OfficeOfferRoleSchema.php`; nooit via een webrequest.
- Voer geen `DROP`, `TRUNCATE` of automatische migratie van bestaande gebruikers/rechten uit.
- Leg de nieuw aangemaakte tabellen en aantallen vast. Start zonder standaardroltoewijzingen of automatische grants.
- Laat `OFFICE_ROLE_SETTINGS_ENABLED` uitgeschakeld totdat alle acceptatiechecks geslaagd zijn.
- Controleer dat het Office-test-routerpad `/office-test/instellingen/rollen` naar het privédoel `public/office-role-settings.php` wijst.

## Acceptatie — eerst op Office-test

1. Featureflag uit: pagina niet beschikbaar en menuknop verborgen.
2. Featureflag aan: niet-ingelogde of onbevoegde gebruiker krijgt geen toegang; menuknop verborgen.
3. Bevoegde beheerder: pagina toont actieve rollen en Offertes (ook zonder bestaande grants).
4. POST zonder geldige CSRF-token: geweigerd, geen databasewijziging.
5. Offertes-rechten inschakelen, opslaan, teruglezen en uitschakelen: database en scherm consistent.
6. Onbekende module, ongeldige rol, ongeldige scope en misvormde rechtenlijst: geweigerd.
7. Bestaande directe gebruikersrechten, projectlidmaatschappen, CRM, Inzet, Finance en productie blijven ongewijzigd.
8. Rolrecht zonder gegevensfilter en scope `own` geven **geen** nieuwe toegang. Scope `assigned`/`all` is nog steeds onderworpen aan bestaande projectautorisatie.
9. Controleer dat geen generiek CRM-offerte-endpoint of ongeautoriseerde offerteactie beschikbaar is.

## Nog niet gereed voor algemene uitrol

- `own`-scope is nog niet geïmplementeerd in de offertequeries.
- Centrale rechten voor andere modules en individuele overrides zijn nog niet aangesloten.
- Er is nog geen vastgestelde auditlog voor wijzigingen aan rolrechten.
- Live schema- en sessiecontroles op Office-test zijn nog niet uitgevoerd.

**Stopcriterium:** bij een afwijking de featureflag uit laten en de migratie niet uitvoeren. Productie nooit als testdoel gebruiken.
