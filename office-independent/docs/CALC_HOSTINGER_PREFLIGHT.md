# Office ↔ Calc — Hostinger preflight en acceptatie

Status: **NIET LIVE ACTIVEREN**. Dit document hoort bij de zelfstandige Office-ontwikkelbranch.

## Bewezen in de code

- Office PR #1360: route `/calc-werkbank` in de zelfstandige PHP-router.
- Office PR #1361: anonieme gebruiker naar `/office/inloggen`.
- Office PR #1362: ontbrekende/ongeldige startidentificaties geven HTTP 400.
- Office PR #1363: formulier voert een Office-rechtencontrole uit via `POST /calc/start`.
- Office PR #1364: optionele project-/calculatie-ID's vullen het formulier vooraf in.
- Office PR #1365: framebescherming voor het Office-startscherm.
- Calc PR #293: no-store/referrer-headers op `POST /api/launch/consume`.

Dit bewijst **niet** dat een Calc-sessie vanuit Office geopend kan worden. De huidige Office-pagina verbruikt het token niet en navigeert niet naar Calc.

## Open blokkades

1. Bepaal de **werkelijk actieve** Office-router, loginroute en projectdetailnavigatie op Hostinger. De zelfstandige ontwikkelrouter is niet automatisch de live router.
2. Controleer of `/calc-werkbank` op de werkelijke host vrij is. Een PHP-startscherm en een volledige Node-proxyprefix mogen niet onbedoeld dezelfde route claimen.
3. Controleer of het hostingpakket een ondersteunde same-origin reverse proxy naar de Calc Node-runtime toelaat. Niet improviseren met een publiek toegankelijke PHP-doorstuurproxy.
4. `CALC_BASE_PATH` (API) en `VITE_CALC_BASE_PATH` (frontendbuild) moeten overeenkomen met het **gekozen en bewezen** proxypad.
5. De bestaande Calc-frontend accepteert `/launch?token=...`. Hoewel zij het token meteen uit de browsergeschiedenis verwijdert, kan een URL-token eerder in logs terechtkomen. Ontwerp een POST-handoff zonder token in URL, browseropslag of logs vóór activering.
6. Een Calc HttpOnly-cookie is geen live Office-rechtencontrole. Test uitloggen en intrekken van Office-rechten bij een reeds uitgegeven Calc-sessie; ontwerp zo nodig revocatie.

## Read-only Hostinger-preflight

Voer uitsluitend in de betreffende Office-hostingdirectory uit:

```sh
pwd
ls -la
find . -maxdepth 2 -type f \( -name '.htaccess' -o -name 'startup.js' -o -name 'package.json' -o -name 'index.php' \) -print
```

Deel geen `.env`-inhoud, cookies, HMAC-secrets, databasegegevens of private configuratie.

## Staging acceptatie vóór go/no-go

- Anonieme toegang leidt naar de werkelijke Office-login.
- Geen rechten of verkeerde project-calculatiekoppeling: weigeren.
- Eenmalige tokenconsumptie, replay en expiry werken fail-closed.
- Geen token in URL, referer, serverlogs of browseropslag.
- Proxy: GET, POST, assets, refresh, uploads, previews, querystrings en cookies werken.
- Calc-sessie wordt ongeldig na Office-uitloggen of rechtenintrekking volgens vastgelegd beleid.
- Projectcontext komt uit Office; geen tweede projectwaarheid.
- Rollback en configuratieherstel zijn getest.

**Go:** alleen na bewezen hostingrouter, proxy, sessiebeleid en volledige stagingacceptatie. Tot dan geen productieflag, deploy of routeomschakeling.
