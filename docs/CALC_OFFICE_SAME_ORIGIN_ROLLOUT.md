# BREBO Calc binnen Office — gecontroleerde activering

Status: **niet live geactiveerd**. Calc PR #290 en #291 zijn gemerged. Dit document beschrijft de resterende Office- en hostingkoppeling.

## Bestaande contracten

- Office is leidend voor identiteit, project, calculatie en rechten. Een Calc-sessie is geen vervanging voor Office-autorisatie.
- De bestaande `CalculationWorkbenchLaunchController` maakt na rechtencontrole een HMAC-launch-token met een vervaltijd van 90 seconden.
- Calc accepteert `POST /api/launch/consume` en geeft daarna een HttpOnly-sessiecookie uit.
- Calc kan nu optioneel onder een prefix draaien: `CALC_BASE_PATH` (Express) en `VITE_CALC_BASE_PATH` (Vite build). Beide moeten hetzelfde pad representeren.
- Zonder deze variabelen blijft de huidige Calc-rootdeployment intact.

## Nog vast te stellen op de werkelijke Office-host

1. Bepaal welke **actieve Office-webserver/router** de gebruikerslogin, tegels en routes afhandelt. De Drupal-code in deze repository is niet automatisch de router van de zelfstandige Office-host.
2. Reserveer één niet-conflicterend pad voor de Calc-werkbank. Gebruik **niet** zonder routecontrole `/calculaties`: Office kan dat pad zelf al gebruiken voor het overzicht.
3. Configureer een same-origin reverse proxy voor het gekozen pad naar de Calc Node-app. Behoud `Host`, HTTPS-indicatie, requestmethoden, querystrings, cookies en body. Sta geen openbare toegang toe tot een onbeveiligde alternatieve werkbankroute.
4. Build de Vite-frontend met `VITE_CALC_BASE_PATH=<gekozen pad>` en start de Calc API met `CALC_BASE_PATH=<gekozen pad>`. Een bestaande root-build kan niet ongewijzigd onder de prefix worden geplaatst.
5. Configureer de bestaande Office-launchbasis op de **publieke Office-origin plus gekozen pad**, zonder token of secret in logging. Het HMAC-secret blijft server-side.
6. Controleer dat Office-authenticatie en calculatierechten ook bij verlopen/ingetrokken rechten fail-closed werken; de bestaande Calc-cookie is niet automatisch een live Office-sessiecontrole.

## Verplichte acceptatie vóór omschakeling

- Anonieme bezoeker wordt naar de **Office-login** geleid, zonder calculatiegegevens te zien.
- Bevoegde gebruiker opent een calculatie vanuit Office en ziet geen apart Calc-subdomein.
- Onbevoegde gebruiker krijgt geen werkbanktoegang; verlopen/gewijzigde rechten worden opnieuw afgedwongen.
- Launch-token verdwijnt direct uit de adresbalk, is kort geldig en kan niet opnieuw worden gebruikt.
- GET/POST API, uploads, preview, assets en directe paginaverversing werken onder de prefix, ook met querystrings.
- Aanmaken, wijzigen, opslaan, heropenen, deelcalculatie en publiceren blijven correct; Office blijft leidend.
- Uitloggen verwijdert de cookie op exact dezelfde prefix; Office-uitloggen mag geen bruikbare Calc-sessie achterlaten.
- Healthcheck, serverlogs, CSP/CSRF, proxyheaders en rollback zijn gecontroleerd.

## Uitrol en rollback

1. Staging: Office-proxy en Calc-prefix samen configureren; geen productievariabelen wijzigen.
2. Smoke en rechten-/securitytests uitvoeren; bewijs van geslaagde runs bewaren.
3. Productie alleen na expliciete go/no-go, met back-up en herstelpad naar bestaande Calc-rootroute.
4. Na omschakeling Office-tegels en calculatielinks naar het interne pad laten wijzen; oude routes gecontroleerd afbouwen.

**Niet doen:** de Office-URL in documentatie gelijkstellen aan een al gerealiseerde productieproxy, of Calc als ingelogd beschouwen uitsluitend op basis van een oude Calc-cookie.

## Route-inventarisatie in Office-code (13 oktober 2026)

In `web/modules/custom/brebo_office_core/brebo_office_core.routing.yml` zijn al vastgelegd:

- `/calculaties` — Office-calculatieoverzicht;
- `/calculaties/{node}` — bestaande calculatieroute;
- `/calculaties/{calculation}/open-in-calc` — Office-launch naar Calc.

Daarom is `/calculaties` **niet** beschikbaar als volledige Calc-proxyprefix. Voor de werkbank is `/calc-werkbank` een **voorstel**, geen vastgestelde live-route. Eerst op de werkelijke Office-router controleren of dit pad vrij is. Als het vrij is, moeten `CALC_BASE_PATH=/calc-werkbank`, `VITE_CALC_BASE_PATH=/calc-werkbank` en de Office-launchbasis naar hetzelfde pad verwijzen.

De route-inventarisatie bewijst niet dat de zelfstandige Office-host deze Drupal-router gebruikt. De feitelijke Hostinger-router, TLS/proxyconfiguratie en actieve login moeten vóór implementatie apart worden vastgesteld.

## Hostinger-preflight (uitvoeren vóór de eerste proxywijziging)

De actieve Office-router is nog niet uit deze repository vastgesteld. Vraag uitsluitend **niet-geheime** output op van de live Office-host:

```sh
pwd
ls -la
find . -maxdepth 2 -type f \( -name '.htaccess' -o -name 'startup.js' -o -name 'package.json' -o -name 'index.php' -o -name 'composer.json' \) -print
```

Beoordeel op basis daarvan of Office via Apache/PHP, Passenger/Node of een andere router draait. **Geen** tokens, `.env`-inhoud, wachtwoorden, cookieheaders of privéconfiguratie delen.

### Go/no-go

- **Go voor implementatie:** actieve Office-router bekend; `/calc-werkbank` vrij; reverse-proxycapaciteit van het hostingpakket bevestigd; Node-app bereikbaar via interne of toegestane upstream; Office-login en rechtencontrole aantoonbaar.
- **No-go:** alleen repositoryroutes bekend, onbekende hosting-router, geen proxyrechten, onduidelijk sessiegedrag of onvoldoende rollbackmogelijkheid.

Op gedeelde Hostinger Cloud Hosting is een configureerbare reverse proxy **niet vanzelfsprekend beschikbaar**. Als het pakket de vereiste proxy niet toestaat, ontwerp dan eerst een ondersteunde hostingsoplossing; voeg geen onbeveiligde PHP-doorstuurproxy toe als noodgreep.


## Besluit 14 oktober 2026 — eerste integratie via twee origins

De werkelijke Hostinger-inventarisatie bevestigt dat zelfstandige Office via Apache/PHP werkt (publieke router onder `domains/brebobv.nl/public_html/office/index.php`, private handlers onder `office_private`) en dat Calc via Node/Passenger op de bestaande Hostinger-app bereikbaar is (`/api/health` geeft HTTP 200). Er is geen Passenger-koppeling onder de Office-webroot aangetroffen; configureerbare same-origin reverse proxy is niet bewezen.

**Besluit:** maak de eerste werkende integratie via de bestaande afzonderlijke HTTPS-origins, zonder proxy, extra hosting, of nieuwe login. Het eerdere same-origin ontwerp hierboven blijft een mogelijke latere fase en is **geen** blocker voor deze eerste koppeling. Dit besluit is een ontwerpkeuze; geen bewijs dat de koppeling al live is.

### Implementatiecontract (zelfstandige PHP Office → Node Calc)

1. Voeg aan de zelfstandige Office-router een expliciet toegestane, beveiligde launchroute toe; hergebruik de bestaande Office-databasesessie en controleer opnieuw de operator, `can_calculate`, projecttoegang en de geselecteerde calculatie. Geen toegang op basis van alleen een zichtbare tegel.
2. Laat Office server-side een kort geldige, eenmalig te consumeren, ondertekende launch-assertie uitgeven met expliciete issuer, audience, operator, project, calculatie, nonce/jti, iat en exp. Behoud de bestaande 90-secondenlimiet waar het bestaande Calc-contract dit voorschrijft. Deel geen HMAC-secret met de browser.
3. Gebruik voor overdracht bij voorkeur een browser-POST naar de Calc-consume-ingang met een kort geldige opaque code die Calc server-to-server bij Office inwisselt, of een aantoonbaar passend bestaand POST-contract. **Geen launch-token in URL, querystring, referer of logs**. Bevestig eerst de daadwerkelijke requestvorm van `POST /api/launch/consume`; een HMAC-assertie is niet automatisch compatibel met de bestaande Calc-API.
4. Calc valideert handtekening/audience/tijdvenster en consumeert nonce/jti atomair (replay = geweigerd). Daarna maakt Calc uitsluitend een eigen `Secure`, `HttpOnly`, passend `SameSite` sessiecookie op zijn eigen origin. Office-cookie wordt niet over domeinen gedeeld.
5. Hercontroleer autorisatie voor gevoelige Calc-acties; leg een expliciet intrekkings- en logoutcontract vast zodat een oude Calc-sessie niet geldig blijft na Office-uitloggen of ingetrokken projectrechten. CORS mag geen generieke wildcard voor credentialed requests zijn; CSRF-controles blijven vereist.
6. Maak de Office-dashboardtegel en de route pas zichtbaar/bruikbaar na autorisatie. De gebruiker ervaart één login, maar de adresbalk kan naar de Calc-origin wisselen; communiceer dit als bewuste tussenfase.

### Veilig opleveren

- **Eerst codeonderzoek:** vergelijk de zelfstandige Office-authenticator en sessieprovider met de huidige Calc `/api/launch/consume`-implementatie. De Drupal-`CalculationWorkbenchLaunchController` is niet automatisch bruikbaar in de zelfstandige PHP-router.
- **Dan een geïsoleerde PR:** contract, Office-handler, Calc-consume-aanpassing indien nodig en negatieve tests voor anoniem, ontbrekende rechten, verlopen/replay-token, onjuist project, logout/intrekking, CSRF en niet-beschikbare database.
- **Dan sandbox:** test de volledige browseroverdracht op beide HTTPS-origins. Bestaande Office-login, CRM en Calc-rootdeployment blijven ongemoeid tot groen bewijs en een afzonderlijke productie-go/no-go.
- **Rollback:** schakel de nieuwe Office-launchroute/featureflag uit; wijzig geen DNS, Passenger-app-root of bestaande Calc-healthroute.

### Nog open

De daadwerkelijke inhoud van de zelfstandige Office-release en de Calc-consume-handler moet tegen de actuele broncode worden geverifieerd voordat implementatie kan worden geclaimd. De live-host heeft wel een `office_calc_test`-sandbox, maar de aanwezigheid van die map is geen geslaagde launchtest.
