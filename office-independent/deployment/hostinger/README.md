# Hostinger Business — releasebestanden zonder symlinks

**Nog niet op Hostinger geplaatst.** Deze map bevat het publieke entrypoint, geen volledige release.

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
8. De CRM-route heeft momenteel naast Office-sessie nog een trusted-proxy-token nodig. Op shared hosting is niet aangetoond dat die veilig kan worden geïnjecteerd. **CRM niet activeren voordat dit contract veilig is opgelost en getest.**
9. Controleer dat `office.brebobv.nl` werkelijk deze documentroot gebruikt en dat geen `index.html` de PHP-router overschrijft.

## Vrijgave

Eerst pakketcontrole en test op sandbox; daarna expliciet productie-akkoord voor de concrete release, herstelpunt, plaatsing en HTTP-/browsertest. Bij falen geen vrijgave. Dit document geeft geen opdracht om bestanden op de server te verwijderen.
