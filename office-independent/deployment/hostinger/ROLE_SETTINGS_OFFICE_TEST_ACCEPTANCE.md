# Office-test — Rollen en rechten: migratie- en acceptatiecontrole

## Werkelijke acceptatiestand — 31 oktober 2026

**Gedeeltelijk live en bevestigd op Office-test; niet op productie.** De oorspronkelijke instructies hieronder zijn een historische preflight en mogen niet worden gelezen als de actuele uitvoeringsstatus.

Bevestigd door de Office-test-beheerder via SSH, MariaDB en browser:
- Back-up vóór migratie: `/home/u213420663/office-test-backups/roles-before-id-migration-20261031.sql`.
- `office_user_roles.operator_id` en `office_role_settings_audit.actor_operator_id` zijn `VARCHAR(128) NOT NULL`; bestaande roltoewijzing en auditregels bleven behouden.
- Zeven relevante PHP-bestanden voor de rolleninterface zijn in de actieve private Office-test-release geïnstalleerd, met geslaagde `php -l` en bestandsvergelijking.
- `/office-test/instellingen/rollen` rendert voor de bevoegde beheerder.
- Voor rol `1`, module `offers`, is `office_role_scopes.scope_key = 'all'`.
- Auditregel `id=3`, actor `1`, 2026-10-31 14:48:17: scope `none` → `all`; zeven bestaande permissies ongewijzigd.
- Vijf GitHub Actions-workflows waren groen op commit `36102e9f0226aad8f5c2c9251286389162fa858b` vóór deze documentupdate.

**Nog niet bewezen / niet afvinken:**
- Negatieve HTTP-acceptatie op de live Office-test-runtime (niet-ingelogd, onbevoegd, CSRF, ongeldige payload).
- Effectieve server-side autorisatie van iedere offerteactie in combinatie met projectlidmaatschap; scope `all` mag projectrechten niet omzeilen.
- De vier offertedomeintabellen en volledige offerte-acceptatieketen zijn niet met live databasebewijs geaccepteerd.
- Centrale rollen voor andere modules, individuele overrides en `own`-scope zijn niet aangesloten.
- Geen bewijs van een volledige productie-uitrol; productie ongemoeid laten.

## Oorspronkelijke preflight en acceptatie-instructies (historisch)

Status: **NIET UITGEROLD**. Dit document autoriseert geen databasewijziging.

## Vooraf controleren (alleen lezen)

1. Bevestig dat de actieve mount **/office-test** is, dat de database `u213420663_office_test` is en dat productie-DSN's nergens gebruikt worden.
2. Leg actieve release, Git-commit, routerpad en configuratie vast. Bewaar een herstelbare databaseback-up.
3. Controleer in `information_schema.tables` of `office_roles`, `office_user_roles`, `office_role_permissions`, `office_role_scopes` en `office_role_settings_audit` ontbreken of al bestaan. **Bij bestaande tabellen: stop; inspecteer eerst de schema's en inhoud.**
4. Controleer de bestaande `office_operator_accounts`, `office_module_permissions` en `office_project_memberships`; wijzig deze niet tijdens de migratie.
5. Controleer of er al een actieve operator is met de expliciete directe grant `settings.manage`. **Niet automatisch toekennen**.
6. Voer de read-only queries uit `ROLE_SETTINGS_OFFICE_TEST_PREFLIGHT.sql` uit op een afzonderlijk geverifieerde Office-testverbinding. Bij `STOP_WRONG_DATABASE`: onmiddellijk stoppen. Bewaar de resultaten in het acceptatiedossier. De preflight toont ook kolomdefinities van de bestaande Office-identiteits-, module- en projectrechten-tabellen, zonder persoonsgegevens uit te lezen.\n7. Controleer de actuele PR-CI: alle workflows moeten groen zijn. Review de testdekking voor rolrechten, CSRF, projectautorisatie en gegevensfilters.

## Veiligheidsgrenzen

- De route en de navigatie blijven achter `OFFICE_ROLE_SETTINGS_ENABLED=enabled` en bestaande Office-sessieautorisatie.
- De route weigert mounts buiten `/office-test` en databases anders dan `u213420663_office_test`.
- Geen rol wordt automatisch aangemaakt of toegewezen; `settings.manage` wordt niet automatisch verstrekt.
- De vijf nieuwe tabellen zijn een afzonderlijke migratie. Niet aanmaken via een HTTP-verzoek.
- Voer vooraf `SHOW CREATE TABLE` en rijaantallen uit voor eventueel reeds bestaande tabellen; bij verschillen stoppen.
- Na succesvolle acceptatie blijft productie apart geblokkeerd; deze PR geeft geen toestemming voor productierollout.

## Migratie — pas na expliciete goedkeuring

- Maak uitsluitend de vijf nieuwe tabellen aan met de statements uit `src/Offer/OfficeOfferRoleSchema.php`; nooit via een webrequest.
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
7. Controleer dat iedere geautoriseerde wijziging een auditregel met beheerder, rol, module en voor-/na-waarden oplevert; bij ontbrekende auditregistratie mag de rechtenwijziging niet worden opgeslagen.
8. Bestaande directe gebruikersrechten, projectlidmaatschappen, CRM, Inzet, Finance en productie blijven ongewijzigd.
9. Rolrecht zonder gegevensfilter en scope `own` geven **geen** nieuwe toegang. Scope `assigned`/`all` is nog steeds onderworpen aan bestaande projectautorisatie.
10. Controleer dat geen generiek CRM-offerte-endpoint of ongeautoriseerde offerteactie beschikbaar is.

## Nog niet gereed voor algemene uitrol

- `own`-scope is nog niet geïmplementeerd in de offertequeries.
- Centrale rechten voor andere modules en individuele overrides zijn nog niet aangesloten.
- De append-only auditregistratie is in code toegevoegd, maar moet nog op Office-test worden gemigreerd en geaccepteerd.
- Live schema- en sessiecontroles op Office-test zijn nog niet uitgevoerd.

**Stopcriterium:** bij een afwijking de featureflag uit laten en de migratie niet uitvoeren. Productie nooit als testdoel gebruiken.
