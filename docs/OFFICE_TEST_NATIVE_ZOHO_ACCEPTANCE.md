# BREBO Office-test — native Zoho-mail acceptatie (PR #1471)

## Status en grens
Dit document beschrijft de **nieuwe zelfstandige Office-communicatiemodule**. Het bestaande `docs/ZOHO_MIGRATION_RUNBOOK.md` beschrijft de oudere Drupal-route en is **niet** de uitvoeringshandleiding voor deze PR.

- Doelomgeving: `https://brebobv.nl/office-test`; productie `office.brebobv.nl` blijft ongewijzigd.
- Geen Zoho MX-, distributiegroep-, SMTP- of mailboxinstellingen aanpassen.
- `facturen@brebobv.nl` is een distributiegroep, **geen** zelfstandig IMAP-account. Selecteer eerst een echte ontvangende Zoho-mailbox en controleer het groepslidmaatschap.
- Geen credentials, mailinhoud of bijlagen in GitHub, CI-logs of tickets plaatsen.
- Geen automatische betaalbaarstelling, goedkeuring, betaling of projecttoewijzing. Finance ontvangt uitsluitend voorstellen voor menselijke review.

## Acceptatievoorwaarden vóór activering
1. PR #1471 heeft groene communicatie- én algemene Office-workflows op de **laatste** commit.
2. Office-test release, router, authenticatie en autorisatie zijn gecontroleerd. Er is geen productiepad in de configuratie.
3. Een afzonderlijke Office-test databaseback-up en terugvalprocedure zijn vastgelegd.
4. Schema `OfficeCommunicationSchema` plus migraties `office_communication_attachments.sql` en `office_communication_cursors.sql` zijn **uitsluitend in Office-test** uitgevoerd en gecontroleerd.
5. Er is een echte Zoho IMAP-mailbox met toegestane read-only toegang; host/poort zijn uit de Zoho-accountinstellingen bevestigd.
6. De PHP IMAP-extensie en PDO-driver zijn beschikbaar in de **daadwerkelijke Office-test runtime**.
7. De bijlagekluis bestaat buiten iedere publieke webroot, is schrijfbaar voor de runtime en heeft een geverifieerde `DOCUMENT_ROOT`. Geen webtoegang tot opgeslagen PDF's.
8. De native sync is expliciet geconfigureerd met `OfficeImapMimeExtractor`, `OfficeAttachmentImportService` en `OfficeImapCursorRepository`; een onvolledige keten mag niet starten.
9. Een bevoegde beheerder heeft de testmailbox geregistreerd; synchronisatie staat standaard uit totdat een afzonderlijke vrijgave is gegeven.

## Gefaseerde test
1. **Read-only inventarisatie:** verbind met de echte Zoho-mailbox, lees UIDVALIDITY, UID-bereik en aantallen. Geen provider-side mutaties. Stop bij certificaat- of authenticatiefouten.
2. **Gecontroleerde eerste batch:** maximaal vijf testberichten, waaronder platte tekst, multipart/alternative, PDF-bijlage en inline afbeelding. Leg alleen technische IDs, aantallen en hashes vast.
3. **Verificatie:** bericht en PDF zijn in Office-test geregistreerd; SHA-256 klopt; PDF staat buiten webroot; reviewwerkbak is zichtbaar voor bevoegde gebruikers.
4. **Herhaling:** voer dezelfde batch opnieuw uit. Verwacht geen dubbele berichten of bijlageregistraties en geen teruglopende cursor.
5. **Foutinjectie:** test ontbrekende bijlagekluis, ongeldige metadata, onderbroken opslag en UIDVALIDITY-wijziging. Verwacht foutmelding, geen cursorvooruitgang en herstelbare herimport.
6. **Finance-review:** factuurkandidaat krijgt uitsluitend review of koppelsuggestie na verificatie van leverancier, bedragen, duplicaat en project/kostenplaats. Geen boeking of betaling.
7. **Uitrolbesluit:** pas na vastgelegde acceptatie en afzonderlijke toestemming syncfrequentie/batchlimiet verhogen. Productie vereist een apart besluit.

## Stop- en terugvalcriteria
Stop onmiddellijk bij onverwachte Zoho-mutaties, cursorverschuiving na mislukte import, publieke toegankelijkheid van een bijlage, ontbrekende autorisatie, dubbel boekingsvoorstel of onbekende UIDVALIDITY. Schakel de Office-test sync uit; behoud bronmail en auditdata; herstel uitsluitend vanuit de gecontroleerde Office-test back-up na analyse. Reset IMAP-cursors nooit automatisch.

## Bewijslijst voor akkoord
Leg vast: commit-SHA, workflow-run IDs, testdatabase/schema-versie, mailbox-ID (geen geheimen), UIDVALIDITY, start-/eindcursor, aantallen berichten/bijlagen, SHA-256 van testbijlagen, resultaten van herhaling/foutinjectie, autorisatiecontrole, naam van acceptant en datum.

**Huidige beperking:** GitHub CI bewijst niet dat de Hostinger-runtime, Zoho-account of Office-test deployment werkt. De echte mailbox- en acceptatietest is nog niet uitgevoerd.

## Offline preflight (vóór verbinding met Zoho)

Voer op de **Office-test server** uit, met de werkelijke absolute paden (geen secrets):

```sh
OFFICE_COMMUNICATION_VAULT_ROOT=/absoluut/prive/pad \\
OFFICE_COMMUNICATION_PUBLIC_ROOT=/absoluut/office-test/webroot \\
php office-independent/bin/office-communication-preflight.php
```

De controle leest alleen PHP-extensies, paden en de aanwezigheid van bron- en migratiebestanden. Bij een FAIL is de import geblokkeerd. Een PASS is **geen** bewijs van databaseconnectiviteit, Zoho-autorisatie of een geslaagde import. Leg de uitvoer vast zonder wachtwoorden.
