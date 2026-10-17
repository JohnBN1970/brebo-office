# Office-test: gecontroleerde Hostinger-acceptatie

Dit is een **handmatige acceptatieprocedure**, geen productie-deploy. PR #1406 blijft draft. De bronbestanden zijn nog niet op Hostinger gepubliceerd.

## Uitrolgrenzen
- Werk uitsluitend in `/home/u213420663/domains/brebobv.nl/office_calc_test/` en de bestaande lege `public_html/office-test/` map.
- Raak `public_html/office/`, `office_private/`, `public_html/sboffice/`, `develop` en bestaande lokale wijzigingen niet aan.
- Bouw een nieuwe immutable release uit de gecontroleerde PR-commit, zonder bestaande release- of worktree-mappen te overschrijven. Controleer PHP-lint, release-hashes en de test-only entrypoint.
- Installeer `office-test-index.php` uitsluitend als `public_html/office-test/index.php`; installeer `office-test.htaccess` als `.htaccess` **pas na** het instellen van een aparte host-level Basic Auth/IP-toegangsbeveiliging.
- De meegeleverde .htaccess is bewust standaard `Require all denied`. Haal deze blokkade nooit zonder vervangende toegangscontrole weg.

## Testconfiguratie
- `OFFICE_BASE_PATH=/office-test`
- `OFFICE_PRIVATE_ROOT` verwijst uitsluitend naar de nieuwe testrelease/private; de router moet dezelfde gecontroleerde release-root verwachten.
- `OFFICE_INTAKE_PDO_DSN=mysql:host=localhost;dbname=u213420663_office_test;charset=utf8mb4`
- `OFFICE_INTAKE_DB_USER=u213420663_office_test`
- `OFFICE_PUBLIC_HOST` moet de exact gekozen, TLS-beveiligde testhost zijn, nooit `office.brebobv.nl`.
- `OFFICE_LOGIN_ENABLED=enabled`, `OFFICE_CRM_SESSION_MODE=enabled`.
- Native projecten, Calc handoff en andere nog niet gevalideerde modules blijven uit.
- Sla wachtwoorden uitsluitend in de private testconfig op; nooit in repo, screenshots of chat.

## Browserproef
1. Zonder Basic Auth: toegang geweigerd. Zonder Office-sessie: na Basic Auth door naar testlogin.
2. Testlogin `tester_office` werkt, productiesessie wordt niet hergebruikt.
3. Mijn werkplek toont permanent `TESTOMGEVING`.
4. Logout maakt uitsluitend de testcookie ongeldig; de productiecookie blijft onaangeraakt.
5. Niet-gemapte routes en verkeerde host/DSN/config geven 404 of 503; geen data uit productie.
6. Controleer PHP-errors zonder geheimen te loggen; documenteer commit, datum, resultaat en herstelactie.

## Acceptatiegrens
CI-groen is noodzakelijk maar niet voldoende. Vrijgave pas na geslaagde browserproef, review van toegang en expliciet akkoord. Een testrelease is nooit een productierelease.
