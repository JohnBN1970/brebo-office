# BREBO Zoho-mailmigratie runbook

## Doel

Historische Zoho-mail gecontroleerd, herleidbaar en zonder bronwijzigingen opnemen in BREBO Office.

## Veiligheidsgrens

De migratiebron gebruikt dezelfde provider-neutrale `ImapSourceAdapter` als de permanente Hostinger-inbox. De verbinding is read-only (`OP_READONLY`) en de adapter verwijdert, verplaatst, expunged of markeert bronmail niet als gelezen.

`BREBO_ZOHO_MIGRATION_ENABLED` blijft standaard `0`. Alleen het expliciet activeren van deze runtime-vrijgave laat Drupal-cron de Zoho-migratiebron verwerken.

## Runtimevariabelen

Niet in Git of exporteerbare Drupal-config opslaan:

- `BREBO_ZOHO_IMAP_HOST`
- `BREBO_ZOHO_IMAP_PORT` (standaard 993)
- `BREBO_ZOHO_IMAP_FLAGS` (standaard `/imap/ssl`)
- `BREBO_ZOHO_IMAP_FOLDER` (start met `INBOX`)
- `BREBO_ZOHO_IMAP_USER`
- `BREBO_ZOHO_IMAP_PASSWORD`
- `BREBO_ZOHO_IMAP_BATCH_LIMIT`
- `BREBO_ZOHO_MIGRATION_ENABLED`

De eigenaar van geïmporteerde communicatie blijft de bestaande `BREBO_MAIL_INTAKE_UID`.

## Volgorde

1. Controleer in Zoho zelf welke IMAP-server bij het account hoort en of IMAP-toegang is ingeschakeld.
2. Zet uitsluitend de Zoho IMAP-runtimevariabelen, met `BREBO_ZOHO_MIGRATION_ENABLED=0`.
3. Voer de workflow **BREBO Zoho mail inventory** uit. Deze opent alleen read-only en rapporteert zonder mailinhoud of secrets het aantal berichten en UID-bereik.
4. Beoordeel omvang en kies een kleine eerste `BREBO_ZOHO_IMAP_BATCH_LIMIT` (bijvoorbeeld 5).
5. Activeer de migratie pas na expliciete vrijgave door `BREBO_ZOHO_MIGRATION_ENABLED=1` te zetten.
6. Verwerk batches via de bestaande Drupal-pipeline en bewaak `brebo_mail_intake.zoho_migration_last_uid`.
7. Verhoog batchgrootte pas nadat classificatie, deduplicatie, koppelsuggesties en reviewwerkbak zich aantoonbaar correct gedragen.
8. Behandel extra Zoho-mappen pas als aparte gecontroleerde migratiestappen; verander niet stilzwijgend de actieve folder tijdens een lopende UID-reeks.

## Zoho-serverkeuze

Zoho documenteert dat de precieze serverconfiguratie account- en datacenterafhankelijk kan zijn. Voor betaalde organisatieaccounts met een eigen domein noemt Zoho doorgaans `imappro.zoho.com` op poort 993 met SSL; voor andere accounttypen kan `imap.zoho.com` gelden. De accountpagina **Server Configurations Details** in Zoho is leidend.

Bij ingeschakelde tweestapsverificatie kan een applicatiespecifiek wachtwoord nodig zijn.

## Niet doen

- Geen Zoho-wachtwoord in GitHub-code, issues, PR-tekst of Drupal-config exporteren.
- Geen SMTP activeren als onderdeel van de migratie.
- Geen bulkimport starten voordat de read-only inventarisatie slaagt.
- Geen UID-state handmatig terugzetten zonder afzonderlijk herstelbesluit; deduplicatie helpt, maar state-reset verandert het migratiepad.
