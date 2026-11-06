# Calculatie opnieuw aansluiten op Office-test — integratiecontract

Status: **NIET GEACTIVEERD**. Deze notitie beschrijft gecontroleerde broncodebevindingen, geen bewezen staginggedrag.

## Bestaande Calc API (repo JohnBN1970/calc, main)

- `apps/api/src/index.ts`: `POST /api/launch/consume` verwacht JSON `{"token":"<signed-token>"}`.
- Het token heeft `v=1`, `calculation_id`, `project_id`, `actor_id`, `exp`, `nonce`, optioneel `grant_ref`. Handtekening: HMAC-SHA256 over base64url JSON.
- `launch_nonces` voorkomt hergebruik; Calc maakt pas daarna een lokale sessiecookie aan.
- Bij `CALC_OFFICE_GRANT_ENFORCEMENT=enabled` moet `grant_ref` vóór creatie en op beschermde API-verzoeken gevalideerd worden via `POST /api/workbench/v1/calculation-grants/validate`.
- `docs/OFFICE_CALC_GRANT_ROLLOUT_PREFLIGHT.md` eist gecontroleerde Office-routering, grantschema, autorisatie, cookie- en logouttests.

## Gecontroleerde Office-test bron (repo JohnBN1970/brebo-office, develop)

- `office-independent/deployment/hostinger/office-test-index.php` bevat geen `/office-test/calc-werkbank` of `/office-test/calc/start` route en blokkeert `OFFICE_CALC_LAUNCH_ENABLED=enabled` op router-niveau.
- `office-independent/public/office-home.php` vereist onder andere `OFFICE_CALC_SAME_ORIGIN_PROXY_VERIFIED=enabled` om de Calculatie-tegel te tonen.
- De Drupal `CalculationWorkbenchLaunchController` is geen geschikte vervanging: die zet een signed token in de URL en gebruikt Drupal-gebruikersrechten.

## Implementatievolgorde

1. Controleer de werkelijk actieve Office-test release/router en het bijbehorende databaseschema. De repository-router heeft een statische `release-next` root; neem niet aan dat deze de actieve release is.
2. Maak een server-side autorisatiepoort voor Office-operator, projectlidmaatschap en calculatierechten. Geen impliciete rechten uit een menu-item of featureflag.
3. Implementeer persistente, intrekbare grants en een signed validation endpoint met exact het door Calc gebruikte HMAC-transportcontract. Bind grants aan actor/project/calculation en Office-sessie; logout en rechtenintrekking moeten direct doorwerken.
4. Voeg een Office-test launchroute toe die pas **na** alle rechtenchecks een kortlevend token uitgeeft via `OfficeCalcLaunchToken`.
5. Bouw een browserhandoff die de token **niet** in URL, referer, localStorage of logs zet. De huidige Calc consume-route accepteert JSON, dus een gewone HTML-form POST is niet direct compatibel; ontwerp en test de browseroverdracht op beide origins en cookiebeleid expliciet.
6. Test anonieme toegang, ontbrekende rechten, verkeerd project, verlopen token, replay, dubbele submit, logout, intrekking, Office-uitval en Calc-uitval.
7. Schakel flags pas in na aantoonbaar groene staging end-to-end tests en expliciete go/no-go. Productie blijft ongemoeid.

## Reikwijdte van PR #1635

Deze PR voegt een Calc-v1 tokenencoder, unitcontract en fail-closed regressieguard toe. Hij implementeert **geen** Office-grantschema, grantvalidatie, projectautorisatie, router of browserhandoff. De PR is daarom bewust draft; CI-groen betekent niet dat de koppeling live werkt.
