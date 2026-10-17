# Inzet: Drupal-exit-audit (broncodecontrole)

Datum: 2026-10-17. Scope: repository `JohnBN1970/brebo-office`, geïsoleerde Office-branch en bestaande `web/modules/custom/brebo_inzet`. **Dit is broncodecontrole, geen productie- of runtimeproef.** Bestaande SBOFFICE blijft ongewijzigd.

## Vastgestelde afhankelijkheden

| Onderdeel | Bestaande bron | Drupal-afhankelijkheid | Zelfstandige Office-status | Exit-criterium |
| --- | --- | --- | --- | --- |
| Navigatie, personeelsdashboard, projectinzet | `brebo_inzet.routing.yml`, `InzetHomeController`, `ProjectInzetHubController` | Drupal-routes, node-permissies | Inzet-tegel en read-only projectenlijst; niet gelijkwaardig | Zelfstandige projectgebonden werkbank met Office ACL |
| Team, beschikbaarheid, planning, weekplanning | `ProjectTeamForm`, `WorkforceAvailabilityForm`, `ProjectPlanningWorkbenchForm`, `ProjectWeekPlanningController` | Drupal Form/Controller en entity repositories | Basis Inzet-schema en read-only projectteam; bewerken ontbreekt | CRUD, validatie, rechten en planning parity-tests |
| Klokzones en sessies | `ProjectClockZoneForm`, `ClockSessionManager`, `DrupalProjectClockZoneRepository`, `DrupalClockRegistrationRepository` | Drupal entities + services | Pure klokzone-/tijdlogica en OnSite event-store; geen end-to-end sessie | Migratie zones, registratie, project-/gebouwkoppeling, dubbele events |
| Urencontrole en goedkeuring | `ProjectHoursApprovalForm`, `ProjectHoursControlController`, `PersonnelActualHoursManager` | Drupal formulieren, `@brebo_finance.labour_productivity_manager` | Supplier Excel vergelijking en reviewklassen; geen live approval | Controle per persoon/dag/project en supplier-week, audit, Finance-grens |
| OnSite identity, device, OTP en bootstrap | `OnSiteLinkController`, `OnSiteBootstrapController`, `OnSiteActivationController`, `OnSiteDeviceRegistry`, `DrupalOnSiteDeviceStore`, `DrupalOnSiteOtpStore` | Drupal keyvalue, flood, entity services, routes | Losse event-validator/autorisatie, maar geen complete mobile auth/API | Nieuwe veilige device-auth, rate-limit, tokenrotatie, API-contracttests |
| OnSite presence | `OnSitePresenceController` | Drupal Controller en device registry | Losse event-store, geen mobile endpoint | Authenticated endpoint en expliciete privacy-governance |
| OnSite iOS app | `ios/CrewPOC/project.yml` | `OnSiteAPIBaseURL=https://sboffice.brebobv.nl` | Nog gekoppeld aan oude backend | Configureerbare test-API en succesvolle contracttest vóór omschakeling |
| Leveranciersweekstaten | `office-independent/src/Inzet/OnSiteSupplierWeek*` | Nieuwe klassen zonder Drupal; geen actieve oude UI | Read-only Inzet-panel onder testflags, import-/reviewlogica in code | DB-migratie, echte workbook-proef, veilige supplier/project-scope, review POST |

## Belangrijke functionele afwijking

De bestaande `OnSitePresenceController::record()` accepteert uitsluitend `trigger=clock_action` en weigert payloads met latitude/longitude; commentaar verbiedt achtergrondgeofence-events. Dit **wijkt af** van de beoogde automatische IN/OUT-registratie. Geen stilzwijgende wijziging: ontwerp eerst rechtsgrond, transparantie, minimale gegevensverwerking, toestemming-/arbeidsrechtelijke toets waar relevant, uitzonderingen en betrouwbare detectie.

## Vastgestelde servicegrenzen

`brebo_inzet.services.yml` bindt onder meer `@entity_type.manager`, `@config.factory`, `@keyvalue`, `@keyvalue.expirable`, `@flood`, `@private_key` en `@brebo_finance.labour_productivity_manager`. Die bindings mogen **niet** mee naar de zelfstandige Office-kern. Pure rekenlogica kan na test en afhankelijkheidscontrole worden hergebruikt; opslag, ACL, sessies, API en Finance vragen zelfstandige adapters.

## Migratievolgorde met controlepoorten

1. **Contracten en parity**: inventariseer data-velden, rechten, routes, klok- en urenregels; leg synthetische regressietests vast. Geen aanpassing oude module.
2. **Zelfstandige opslag en ACL**: projectteam, zones, shift, event, supplier-week; strikt project- en leveranciersgebonden. Schema uitsluitend na test-DB preflight en expliciete bevestiging.
3. **Planning en urenwerkbank**: overzetten van workflows, niet van Drupal-formulieren; bevestig rol- en gebouwtoegang.
4. **OnSite API**: eigen device-auth, idempotentie, logging en contracttests; bestaande iOS endpoint blijft tot cutover ongemoeid.
5. **Supplier Excel en review**: echte XLSX-varianten, opmerkingen, onzekere projecttoewijzing, audit en stale-review-beveiliging. Factuurvrijgave pas na Finance-integratie.
6. **Cutover**: vergelijk testuitkomsten, data en bevoegdheden; afzonderlijke goedkeuring nodig voor app-omschakeling en productie.

## Stopcondities

Geen claim '100% Drupal-loos' zolang planning, mobiele API, auth, goedkeuring en gegevensopslag niet end-to-end op Office draaien. Geen productiedeploy, migratie, oude-moduleverwijdering of wijziging van iOS API-base-url tijdens deze audit.
