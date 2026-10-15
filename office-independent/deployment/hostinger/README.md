# Hostinger Business — releasebestanden zonder symlinks

**Bestaande Hostinger-installatie aanwezig; PR #1394 nog niet geplaatst.** Deze map bevat het publieke entrypoint, geen volledige release.

## Bestaande live installatie - veilige testrelease (oktober 2026)

De bovenstaande stappen beschrijven een nieuwe installatie en zijn niet rechtstreeks toepasbaar op office.brebobv.nl. Login en CRM werken al op de bestaande Hostinger-installatie. PR #1394 is niet gedeployed.

1. Laat de afzonderlijke databaseproef ongemoeid; geen migraties of schemaveranderingen.
2. Maak een herstelbare backup van het huidige publieke index.php, private public-bestanden en private configuratie, buiten de webroot.
3. Vergelijk de live router en CRM-handler met de PR-versies. De huidige live router herschrijft REQUEST_URI voor CRM; de PR-router doet dat niet. Bewijs dat CRM zonder die herschrijving blijft werken.
4. Test op een afgeschermde aparte testlocatie met testgegevens, zonder de productie- of lopende testdatabase te gebruiken.
5. Test: ongeautoriseerd dashboard naar login; aanmelden naar dashboard; CRM-overzicht en detail; POST-logout; opnieuw aanmelden vereist; onbekende office_route geeft 404; GET-logout wijzigt de sessie niet.
6. Controleer de werkelijke URL-mapping en Location-headers. Live gebruikt /index.php?office_route=...; de oudere /office/...-instructies zijn hiervoor geen bewijs.
7. Geen productieplaatsing of merge van de conceptbasis zonder geslaagde browsertest, terugrolplan en expliciete vrijgave.

De PR-router bevat een installatiegebonden absoluut pad naar office_private/config.php. Gebruik dat pad niet op een andere testhost. Geen geheimen in GitHub.

## Definitieve paden

- Bestaande publieke documentroot: `/home/u213420663/domains/brebobv.nl/public_html/office`.
- Voorgestelde private applicatiemap: `/home/u213420663/domains/brebobv.nl/office_private` **alleen als dit pad buiten alle webroots valt en door PHP gelezen kan worden**.
- Bestaande sandbox: `/home/u213420663/domains/brebobv.nl/public_html/sboffice/web` (niet wijzigen).
- Geen symlinks en geen subdomeinwijziging.

## Bestandsindeling van één release

| Bron in repository | Doel op Hostinger |
|---|---|
| `office-independent/deployment/hostinger/index.php` | `public_html/office/index.php` |
| `office-independent/public/*.php` | `office_private/public/*.php` |
| `office-independent/src/**` | `office_private/src/**` |
| `office-independent/bin/**` | `office_private/bin/**` (CLI-only) |

Kopieer geen `tests`, Git-metadata, credentials, sandboxdatabase of bestaande Drupal-mappen. Het `default.php`-bestand in `public_html/office` niet verwijderen zonder controle.

## Hostinger-instellingen vóór activering

1. Controleer dat `office_private` niet via enig domein op te vragen is.
2. Controleer dat PHP het pad kan lezen en dat `realpath()` werkt.
3. Stel de server-side omgevingsvariabele `OFFICE_PRIVATE_ROOT` in op het private pad. **Niet** in een publiek PHP-bestand, URL, browser of repository opnemen.
4. Controleer PHP 8.1+ (voor `readonly`, `str_starts_with`) en PDO MySQL.
5. Stel voor productie afzonderlijke MySQL-inloggegevens en een eigen database in; sandboxgegevens blijven gescheiden.
6. Leg HTTPS, `REQUEST_URI`, `HTTPS` en `HTTP_ORIGIN` gedrag van de hosting vast.
7. Configureer een geteste rewrite zodat de vier toegestane paden via `index.php` lopen. Zonder rewrite zijn `/office/inloggen` en `/office/crm/aanvragen` niet bereikbaar. Publiceer geen ongeteste `.htaccess`.
8. De CRM-route ondersteunt nu expliciet `OFFICE_CRM_AUTH_MODE=session_only` op shared hosting: vereist HTTPS, exacte `OFFICE_PUBLIC_HOST=office.brebobv.nl`, `OFFICE_CRM_SESSION_MODE=enabled` en een geldige geautoriseerde database-sessie. De oude proxy-plus-sessie-modus blijft standaard. **Niet activeren voordat de Hostinger-inlogflow en negatieve toegangstests geslaagd zijn.**
9. Controleer dat `office.brebobv.nl` werkelijk deze documentroot gebruikt en dat geen `index.html` de PHP-router overschrijft.

## Vrijgave

Eerst pakketcontrole en test op sandbox; daarna expliciet productie-akkoord voor de concrete release, herstelpunt, plaatsing en HTTP-/browsertest. Bij falen geen vrijgave. Dit document geeft geen opdracht om bestanden op de server te verwijderen.

## Eerste inlogscherm — uitvoerbare volgorde

1. **Hostinger File Manager:** verifieer dat `office.brebobv.nl` nog steeds `public_html/office` gebruikt. Maak buiten `public_html` de private map `office_private` aan; gebruik geen symlink.
2. **Release opbouwen en controleren:** voer het versiegebonden buildscript uit in een vertrouwde shell met de uitgecheckte revisie. Upload `public/` naar `public_html/office` en `private/` naar `office_private`; maak vooraf een backup van bestaande bestanden. Geen hele sandbox kopiëren.
3. **Configuratie veilig instellen:** zet `OFFICE_PRIVATE_ROOT`, `OFFICE_PUBLIC_HOST=office.brebobv.nl`, aparte productiedatabasevariabelen, `OFFICE_LOGIN_ENABLED=enabled`, `OFFICE_CRM_SESSION_MODE=enabled` en pas na geteste authenticatie `OFFICE_CRM_AUTH_MODE=session_only`. Geheimen uitsluitend server-side en buiten alle documentroots; als Hostinger deze omgeving niet veilig kan aanbieden, installatie niet activeren.
4. **CLI-controle:** draai `php office-independent/deployment/hostinger/preflight.php` via SSH/terminal, vanaf een private locatie; dit bestand niet publiek uploaden. Zonder SSH eerst een veilige alternatieve provisioningroute ontwerpen, niet een publiek bootstrapformulier toevoegen.
5. **Operatoraccount:** maak de eerste beheerder aan met `bin/create-office-operator.php` vanuit de private map via een vertrouwde CLI met TTY. Deel het wachtwoord nooit in de chat, GitHub of webroot.
6. **Routecontrole:** bevestig de Apache-rewrites en dat `https://office.brebobv.nl/office/inloggen` een aanmeldformulier toont; controleer dat niet-geautoriseerde CRM-verzoeken geweigerd worden en dat login/logout correct werken.
7. **Vrijgave:** voer pas na test en expliciet akkoord de daadwerkelijke productievrijgave uit. Tot die tijd blijft de Hostinger-placeholder ongewijzigd.

**Let op:** de URL bevat voorlopig `/office/inloggen` achter het Office-subdomein. Dit is de huidige routeconventie; verkorten kan na werkende eerste login.
