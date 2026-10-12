# Zelfstandige BREBO Office-login — uitrolblokkades

Status: ontwikkelbranch. Gebruiker heeft ingestemd met voorbereiding van ingebruikname op bestaande Hostinger Business-hosting; nog geen livegang of serverwijziging uitgevoerd.

## Beschikbaar in de branch
- Office-eigen operatoraccounts met PHP password_hash/password_verify.
- Eigen sessies, intrekken van sessies en beveiligde sessiecookie.
- Inlogformulier, HTTPS-only loginroute en POST-only uitlogroute.
- CRM-route met afzonderlijke reverse-proxy- en Office-sessiegate.

## Verplicht voor inschakeling
1. Laat alle GitHub-CI-jobs groen worden en test echte HTTP GET/POST/cookies.
2. Installeer account- en sessieschema expliciet met beheerde migraties.
3. CLI-only bootstrap staat in `office-independent/bin/create-office-operator.php`; voer deze uitsluitend handmatig uit op een vertrouwde server met een interactieve terminal. Nooit een standaardwachtwoord in code.
4. Centrale MySQL login-throttling en account-/IP-limieten zijn aangesloten en in CI getest; controleer de database-installatie, vertrouwde REMOTE_ADDR en gedrag achter de productieproxy vóór inschakeling.
5. Gebruik een betrouwbare HTTPS reverse proxy die client-supplied X-Office-Crm-Workbench-Token verwijdert en alleen na autorisatie injecteert.
6. Publiceer expliciete routes /office/inloggen en /office/uitloggen; voorkom toegang tot PHP-bestanden buiten de public-root.
7. Configureer OFFICE_LOGIN_ENABLED=enabled en OFFICE_CRM_SESSION_MODE=enabled pas na acceptatietest.
8. Beperk session lifetime, beveilig cookies, controleer Origin/Host achter de proxy en test logout met echte browser.
9. Verifieer dat accountblokkering en rechtenwijzigingen ook bestaande sessies ongeldig maken.
10. Bevestig dat de bestaande website-intake en Drupal-website ongewijzigd blijven.

## Nog niet af
- Productie-installatie van de MySQL-tabellen `office_login_attempts`, `office_operator_accounts` en `office_operator_sessions`; de webroute maakt deze niet automatisch aan.
- Accountbeheer na de eerste CLI-bootstrap (wijzigen, blokkeren en wachtwoordherstel).
- Productie-routebinding, betrouwbare reverse-proxyconfiguratie en volledige browseracceptatietest.
- Sessies controleren per verzoek actuele accountstatus en CRM-rechten; expliciete sessie-intrekking bij wijzigingen en integratietests voor het volledige beheerproces ontbreken nog.
- Controleer origin/host-bescherming van de login-POST en test gelijktijdige mislukte aanmeldingen.

## Hostingkeuze: bestaande Hostinger Business
- Geen VPS of hostingupgrade. Gebruik ondersteunde PHP/MySQL-mogelijkheden van de bestaande hosting.
- Controleer eerst PHP-versie, PDO MySQL, MySQL-toegang, HTTPS en de beschikbare webroot-/rewrite-instellingen.
- Controleer of de huidige Hostinger-configuratie een betrouwbare afgeschermde route voor de extra `X-Office-Crm-Workbench-Token`-gate kan realiseren. **Niet** omzeilen of een secret in browser-JavaScript opnemen. Zonder veilige oplossing blijft CRM dicht.
- Houd `office-independent/src`, `bin` en secrets buiten publiek bereik; alleen expliciet toegestane entrypoints publiceren.
- De huidige Drupal-website en bestaande intake blijven ongewijzigd; schakel nieuwe routes pas in na HTTP/cookie-acceptatietests.
- De bestaande Hostinger Business-omgeving is nog niet vanuit deze branch op serverinstellingen gecontroleerd.

## Bevestigde domeinindeling
- `brebobv.nl`: bestaande Drupal-website, ongewijzigd.
- `office.brebobv.nl`: gewenste HTTPS-ingang voor zelfstandige BREBO Office op **hetzelfde Hostinger Business-pakket**.
- `sboffice@brebobv.nl`: door gebruiker genoemde bestaande sandbox-identificatie; niet aannemen dat dit een URL of DNS-record is.
- Maak subdomein en eigen documentroot pas na verificatie van de bestaande Hostinger-mappenstructuur; voorkom overschrijven van de Drupal-webroot.
- Verifieer SSL-certificaat, subdomein-DNS, route-rewrites en isolatie van private PHP-bestanden; test eerst zonder productieverkeer om te leiden.
- Bestaande sandbox blijft beschikbaar totdat de nieuwe Office-ingang aantoonbaar werkt.
