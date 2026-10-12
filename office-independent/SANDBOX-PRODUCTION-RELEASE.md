# BREBO Office — sandbox naar productie

Status: releasecontract in ontwikkelbranch; nog niet uitgevoerd op Hostinger.

## Omgevingen
| | Sandbox | Productie |
|---|---|---|
| Domein | https://sboffice.brebobv.nl | https://office.brebobv.nl |
| Hosting | Bestaand Hostinger Business-pakket | Hetzelfde pakket |
| Doel | Bouwen, experimenteren, acceptatietesten | Dagelijks stabiel gebruik |
| Database | Eigen sandboxdatabase | Eigen productiedatabase |
| Geheimen | Eigen sandboxwaarden | Eigen productiewaarden |
| Publicatie | Ontwikkelrelease | Alleen expliciet goedgekeurde release |

De publieke Drupal-website op brebobv.nl blijft ongewijzigd. De verouderde map sboffice_app is geen bron voor een nieuwe release.

## Verplichte releasevolgorde
1. Ontwikkel wijzigingen in de gedeelde GitHub-codebasis en test ze op sandbox.
2. Controleer PHP, MySQL, routes, cookies, toegangsrechten, en alle relevante automatische tests.
3. Maak een onveranderlijk releasepakket van één geteste Git-revisie; leg revisie en bestandscontrole vast.
4. Controleer de verschillen met productie en beoordeel databasewijzigingen vooraf. Geen sandboxgegevens of sandboxwachtwoorden naar productie kopiëren.
5. Vraag expliciet akkoord voor deze concrete productiepublicatie. Een algemeen akkoord op de architectuur is niet hetzelfde als akkoord op een ongeteste release.
6. Maak vóór publicatie een herstelpunt van de huidige productiecode en database. Leg vast hoe terug te schakelen.
7. Publiceer alleen het releasepakket, noodzakelijke geteste migraties en de productieconfiguratie. Houd private broncode, CLI-tools en secrets buiten de publieke documentroot.
8. Test op office.brebobv.nl HTTPS, inloggen, CRM-toegang, uitloggen, foutieve login, sessie-intrekking en foutmeldingen. Publiceer pas voor eindgebruikers na geslaagde acceptatie.
9. Bij fouten: verkeer niet vrijgeven, code terugzetten en eventuele databasemigraties afzonderlijk herstellen volgens vooraf beoordeeld herstelplan.

## Harde veiligheidsvoorwaarden
- Nooit bestanden rechtstreeks uit de sandbox-webroot naar productie synchroniseren.
- Geen automatische databasekopie tussen sandbox en productie.
- Geen wijzigingen aan brebobv.nl of bestaande Drupal-intake.
- Geen secret in Git, publiek toegankelijke bestanden of browsercode.
- De huidige CRM-route vereist een vertrouwde server-side proxy-tokeninjectie én een geldige Office-sessie. Als Hostinger Business dat niet veilig ondersteunt, de route niet activeren: eerst het authenticatiecontract aanpassen en testen.
- Een groene CI bewijst niet dat de Hostinger-documentroot, DNS, HTTPS, rewrite-regels of MySQL-permissies correct zijn.

## Nog te verifiëren op Hostinger
- Welke documentroot is daadwerkelijk gekoppeld aan elk subdomein?
- PHP-versie en PDO MySQL beschikbaar?
- Kunnen de privébestanden buiten de publieke documentroot staan?
- Welke .htaccess/rewrite- en HTTPS-instellingen gelden voor office.brebobv.nl?
- Zijn afzonderlijke sandbox- en productiedatabases aangemaakt?
- Hoe worden releases geplaatst en teruggedraaid zonder SSH/root-afhankelijkheid?
- Is de bestaande office/default.php alleen een Hostinger-placeholder? Niet verwijderen zonder verificatie.

Dit document beschrijft het afgesproken proces; het is geen bewijs dat een serverdeployment heeft plaatsgevonden.

## Hostinger-documentroots en verbod op symlinks (bevestigd)
- Productie: `office.brebobv.nl` wijst naar `public_html/office`.
- Sandbox: `sboffice.brebobv.nl` wijst naar `public_html/sboffice/web`.
- **Geen symbolische links**, ook niet tijdelijk, en geen bestaande subdomeinen verwijderen of opnieuw aanmaken.
- Laat de productie-documentroot ongewijzigd. Plaats uitsluitend publieke entrypoints/assets in `public_html/office`; plaats PHP-broncode, CLI-scripts en secrets buiten deze map, bijvoorbeeld in een afzonderlijke niet-publieke directory binnen het hostingaccount.
- Gebruik expliciete absolute paden/configuratie om publieke entrypoints met private broncode te verbinden, zonder symlinks. Test deze paden op Hostinger vóór activering.
- Verifieer dat de gekozen private directory niet via een ander domein of bestaande documentroot bereikbaar is.
