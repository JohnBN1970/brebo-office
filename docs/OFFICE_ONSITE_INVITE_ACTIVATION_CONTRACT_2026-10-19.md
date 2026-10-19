# OnSite uitnodiging → activatie → iOS: contractcontrole

Peildatum: 19 oktober 2026. Integratiebranch; geen productieacceptatie.

## Bevestigd uit code

| Stap | Bron | Feitelijk contract |
| --- | --- | --- |
| Uitnodigen | `web/modules/custom/brebo_inzet/src/Service/OnSiteInvitationManager.php` | Bestaande Drupal-gebruiker (`uid`), mobiel vereist; geeft `mobile`, `install_url`, `language` terug. Geen automatische WhatsApp-verzending. |
| Token uitgeven | `web/modules/custom/brebo_inzet/src/Service/OnSiteActivationManager.php` | Random 32 bytes, SHA-256-hash in Drupal activation store, TTL 604800 seconden; token eenmalig. |
| Token consumeren | Zelfde Drupal-manager | Leest en verwijdert Drupal-token, geeft device-token via Drupal-device-registry. |
| Native Office activeren | `office-independent/public/onsite-activate.php` | POST JSON `activation_token`; gebruikt eigen `OnSiteActivationService` en `OnSiteDeviceTokenRepository` met Office-MySQL. |
| iOS activeren | `ios/CrewPOC/Sources/OnSiteAPIClient.swift` | POST `/api/onsite/v1/activate` met `activation_token`; verwacht `device_token`. |
| iOS bootstrap | Zelfde Swift-client | GET `/api/onsite/v1/bootstrap` met Bearer device-token; verwacht medewerker en projecten met zones. |
| iOS presence | Zelfde Swift-client | POST `/api/onsite/v1/presence` met project_id, zone_id, kind, occurred_at. |
| Drupal presence | `web/modules/custom/brebo_inzet/src/Controller/OnSitePresenceController.php` | Eist `trigger=clock_action` en verbiedt coördinaten; iOS verstuurt die trigger nu niet. |

## Blockers en beslissingen

1. **Activatietoken-bron onverenigbaar totdat bewezen:** Drupal schrijft tokens in Drupal-store, Office-test valideert via eigen MySQL-service. Een uitnodiging van Drupal is dus niet aantoonbaar geldig op Office-test. Kies één issuer en één verifier; voeg geen tweede tokenadministratie toe.
2. **Personeelsidentiteit:** Drupal `uid`, Office `employee_id` en `office_inzet_people.id` zijn nog niet aantoonbaar dezelfde canonieke identiteit. Verifieer een bronmapping vóór Office-medewerker aanmaken, uitnodigen of migreren.
3. **iOS presence-contract:** Swift mist `trigger=clock_action` dat Drupal verplicht stelt. Eerst besluiten over expliciete klokactie versus automatische geofence, inclusief privacy, dan één versieerbaar contract.
4. **Hostinger-routing:** Office-test kent `/office-test/api/onsite/v1/activate` en bootstrap, maar de iOS-base-URL is standaard `https://sboffice.brebobv.nl`; route, host en test/production-scheiding zijn niet aangetoond.
5. **Bewijs vereist:** test met dezelfde (test)medewerker: uitnodiging, eenmalige activatie, bootstrap met juiste projecten, tweede activatie geweigerd, verlopen token geweigerd, ongeldig device-token geweigerd. Geen echte persoonsgegevens of productie-token gebruiken.

## Implementatiegrens

**Niet** zelfstandig nieuwe uitnodiging-, activatie- of medewerkers-write-routes maken zolang bovenstaande punten openstaan. Bestaande code hergebruiken na bron- en contractbesluit. Geen merge of deployment zonder aparte goedkeuring.

## Productbesluit: automatische aanwezigheidsregistratie (19 oktober 2026)

De opdrachtgever bevestigt expliciet: **automatisch in- en uitklokken op basis van projectzones** is de gewenste werking. De bestaande Drupal-route met verplichte `trigger=clock_action` is daarmee **legacy-gedrag, niet het doelcontract**. De eerder aangepaste uitnodigingstekst over expliciet inklokken is tijdelijk en moet vóór uitrol worden vervangen door juiste uitleg over automatische geofencing.

Vóór implementatie: vastleggen van toestemming/informatieplicht en passende AVG-grondslag, achtergrondlocatiebeleid voor iOS en Android, project- en gebouwzones, GPS-hysterese/dwell-time, offline buffering, idempotente events, correcties met audittrail en de autorisatiegrenzen. Geen continue locatiehistorie opslaan als enkel in-/uit-event volstaat. Geen geautomatiseerde uren-goedkeuring zonder controles.
