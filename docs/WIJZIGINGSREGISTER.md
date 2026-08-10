# BREBO Office-wijzigingsregister

Dit register bevat wijzigingen die relevant zijn voor werking, beheersing, architectuur of procesvoering van BREBO Office.

## 2026-08-10 — Veilige Integration API-testvoorziening

| Onderdeel | Vastlegging |
| --- | --- |
| Status | Gerealiseerd en gepusht naar `origin/develop` |
| Commit | `3a405d5` — `Add secure Integration API test form` |
| Omvang | 6 bestanden; 484 toevoegingen en 28 verwijderingen |
| Aanleiding | De technische verbinding tussen BREBO Office en de externe AI-laag veilig en aantoonbaar beproeven |
| Resultaat | Beveiligde fictieve communicatietest werkt end-to-end en retourneert een gecontroleerde AI-respons |
| Beheersing | Menselijke beoordeling verplicht; AI-uitvoer is geen automatisch besluit of formele vaststelling |
| Testbewijs | PHP- en Drupal-controles, cache-rebuild, diff-controles en geslaagde HTTP `200`-test |
| Vervolg | Eén echte, afgebakende BREBO-workflow selecteren en onder aanvullende beheersmaatregelen aansluiten |

### Gewijzigde onderdelen

- routing en serviceconfiguratie van `brebo_office_core`;
- `IntegrationApiStatusController`;
- nieuw `IntegrationApiTestForm`;
- `IntegrationApiClient` en bijbehorende interface.

### Buiten de wijziging gehouden

Lokale `.htaccess`-bestanden, testopnamen en `web/error_log` zijn niet opgenomen in commit `3a405d5`.
