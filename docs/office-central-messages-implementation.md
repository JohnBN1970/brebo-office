# BREBO Office — centrale berichten, fase 1

Bron: veiliggestelde Office-test snapshot op `review/finance-office-test-20261029`, commit `218c404`.

## Besluit
Een centraal berichtenstelsel voor Finance, Projecten, CRM, Calculatie en Documenten. Elk gesprek hoort bij een bronobject via `module`, `entity_type`, `entity_id`. Het object blijft eigenaar van de toegangscontrole. Geen automatische productie-deploy.

## Vastgestelde huidige situatie
- Office-test heeft `OfficeQuestionService::create`, `listForEntity`, `reply` en `hasBlockingQuestions`.
- Finance heeft een dossierweergave met onderwerp, tekst, ontvanger en antwoorden.
- Het huidige ontvangersveld toont numerieke operator-ID's in plaats van medewerkersnamen.
- `listForEntity` en `reply` voeren geen eigen actor-/dossierrechtencontrole uit; de Finance-controller controleert nu toegang vóór aanroep. Hergebruik buiten Finance zonder expliciete guard zou onveilig zijn.
- Er is nog geen afhandelen/oplossen, notificatie-outbox, Mijn Werkplek-inbox, bijlagekoppeling of pushverzending.
- In `office-finance.php` staat dubbele upload-deduplicatiecode; dit hoort in een afzonderlijke Finance-opschoningswijziging.

## Contract voor implementatie
1. **Toegang verplicht:** alle read/create/reply/resolve/inbox-acties krijgen de ingelogde operator en verifiëren toegang tot het bronobject. De controller is niet de enige beveiligingslaag. Alleen ontvangers, gespreksdeelnemers en bevoegde bronobjectgebruikers mogen lezen/antwoorden, afhankelijk van vastgelegde rolregels.
2. **Bericht aanmaken:** onderwerp, multiline tekst, één of meer actieve medewerkers op naam, type `remark` of `blocking_question`. Ontvangers en eerste bericht atomair opslaan.
3. **Antwoorden:** alleen op een toegankelijk open gesprek; transacties en audittrail. Gesloten gesprekken zijn read-only.
4. **Afhandelen:** expliciete actie door bevoegde medewerker; vastleggen `resolved_by` en `resolved_at`. Open blokkerende vragen verhinderen uitsluitend de relevante goedkeur-/vrijgaveactie, niet concept opslaan.
5. **Notificaties:** transactionele outbox bij aanmaken/antwoorden; inbox in Mijn Werkplek, gelezen/ongelezen per ontvanger; idempotente levering en geen push met vertrouwelijke berichtinhoud.
6. **Bijlagen:** alleen via bestaande private documentopslag met objectautorisatie, MIME-/groottecontrole en audit; geen publieke directe bestands-URL's.
7. **Presentatie:** één herbruikbare compacte component in Office, geen afzonderlijke modulevarianten. Naam van medewerker, tijd, status, onderwerp, berichten, antwoord, bijlage en verzenden.
8. **Uitrol:** tests en draft-PR; geen merge/deploy zonder expliciete acceptatie.

## Eerste tests
- Gebruiker zonder toegang tot factuurdossier kan geen gesprek lezen, beantwoorden of via inbox ontdekken.
- Ongeldige/inactieve ontvanger wordt geweigerd; meerdere geldige ontvangers worden eenmaal opgeslagen.
- Gelijktijdige antwoorden behouden berichten; afhandelen verhindert nieuwe antwoorden.
- Notificatie-outbox wordt atomair met bericht aangemaakt; retry veroorzaakt geen dubbele push.
- Open blokkerende vraag blokkeert vrijgave; opmerking niet; concept opslaan blijft mogelijk.
- Bestaande Finance-dossierweergave en factuurregels blijven functioneren.

## Integratievolgorde
1. Bronobjectautorisatie en tests.
2. Centrale service + berichtencomponent.
3. Finance koppelen met bestaande dossierfunctionaliteit.
4. Mijn Werkplek inbox en notificatie-outbox.
5. Projecten/CRM/Calculatie/Documenten per module.
6. Mobiele push pas na controle van device-registratie en toestemming.
