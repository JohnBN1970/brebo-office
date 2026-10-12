# Zelfstandige BREBO Office-login — uitrolblokkades

Status: ontwikkelbranch. Geen productie-uitrol zonder expliciete goedkeuring.

## Beschikbaar in de branch
- Office-eigen operatoraccounts met PHP password_hash/password_verify.
- Eigen sessies, intrekken van sessies en beveiligde sessiecookie.
- Inlogformulier, HTTPS-only loginroute en POST-only uitlogroute.
- CRM-route met afzonderlijke reverse-proxy- en Office-sessiegate.

## Verplicht voor inschakeling
1. Laat alle GitHub-CI-jobs groen worden en test echte HTTP GET/POST/cookies.
2. Installeer account- en sessieschema expliciet met beheerde migraties.
3. Maak eerste beheerder aan via een beveiligde beheerprocedure buiten de webroot; nooit een standaardwachtwoord in code.
4. Implementeer server-side login throttling en account-/IP-limieten, ook bij meerdere webservers. Zonder throttling geen productie.
5. Gebruik een betrouwbare HTTPS reverse proxy die client-supplied X-Office-Crm-Workbench-Token verwijdert en alleen na autorisatie injecteert.
6. Publiceer expliciete routes /office/inloggen en /office/uitloggen; voorkom toegang tot PHP-bestanden buiten de public-root.
7. Configureer OFFICE_LOGIN_ENABLED=enabled en OFFICE_CRM_SESSION_MODE=enabled pas na acceptatietest.
8. Beperk session lifetime, beveilig cookies, controleer Origin/Host achter de proxy en test logout met echte browser.
9. Verifieer dat accountblokkering en rechtenwijzigingen ook bestaande sessies ongeldig maken.
10. Bevestig dat de bestaande website-intake en Drupal-website ongewijzigd blijven.

## Nog niet af
- Centrale login-throttling/rate limiting.
- Veilig beheerpad voor eerste beheerder en accountwijzigingen.
- Productie-routebinding en volledige browseracceptatietest.
- Session revocation bij accountdisable/rechtenwijziging.
