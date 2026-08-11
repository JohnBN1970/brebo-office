# BREBO Office Mail Platform

Status: vastgesteld ontwikkeltraject

## Doel

BREBO Office ontvangt en verstuurt de centrale bedrijfsmail `info@brebobv.nl` zelf. E-mail is geen los kanaal maar een gecontroleerde bron en uitvoer van het centrale BREBO-dossier.

## Doelarchitectuur

```text
Zoho historie
   -> gecontroleerde migratie
Hostinger mailbox info@brebobv.nl
   -> IMAP read-only intake
BREBO Mail Intake
   -> bronregistratie in brebo_communication
   -> classificatie
   -> gebouw-/projectvoorstel
   -> actie/signaal/risico/feedbackvoorstellen
   -> uitzonderingenwerkbak
BREBO AI-laag
   -> samenvatting / conceptantwoord / controles
Drupal mail-laag
   -> Mime Mail voor HTML/opmaak/bijlagen
   -> SMTP voor verzending als info@brebobv.nl
   -> verzonden bericht terug naar brebo_communication
```

## Vaste principes

- `info@brebobv.nl` is de centrale algemene mailbox.
- De permanente mailbox wordt bij Hostinger op het eigen domein gehost.
- De bestaande Zoho-mail wordt gecontroleerd gemigreerd en daarna als historische brondata verwerkt.
- IMAP-intake leest bronmail en muteert de mailbox niet.
- Iedere mail blijft herleidbaar via Message-ID waar aanwezig, bronhash, afzender, ontvangers, datum, onderwerp en inhoud.
- Duplicaten worden idempotent afgehandeld.
- Onzekere gebouw-/projectrelaties blijven voorstellen tot controle.
- AI mag analyseren, classificeren, samenvatten en concepten voorbereiden, maar geen onbewezen dossierwaarheid creëren.
- Mime Mail is de presentatielaag voor nette HTML-mail en bijlagen; SMTP is de transportlaag voor uitgaande mail.
- Uitgaande mail wordt na verzending opnieuw als `brebo_communication` vastgelegd zodat het dossier volledig blijft.
- Mailwachtwoorden, SMTP-credentials en andere secrets staan nooit in GitHub of Drupal-config-export.
- Geen `drush cim` in de deployketen.

## Runtime configuratie inkomend

De provider-neutrale IMAP-adapter gebruikt runtime secrets/variabelen:

- `BREBO_MAIL_SOURCE=imap`
- `BREBO_MAIL_ADDRESS=info@brebobv.nl`
- `BREBO_IMAP_HOST`
- `BREBO_IMAP_PORT` (standaard 993)
- `BREBO_IMAP_USER`
- `BREBO_IMAP_PASSWORD`
- `BREBO_IMAP_FOLDER` (standaard INBOX)
- `BREBO_IMAP_FLAGS` (standaard `/imap/ssl`)
- `BREBO_IMAP_BATCH_LIMIT`
- `BREBO_MAIL_INTAKE_UID`

De adapter wordt alleen actief als alle vereiste runtimewaarden bestaan en de PHP IMAP-extensie beschikbaar is.

## Migratie Zoho

De migratie wordt in twee sporen uitgevoerd:

1. mailboxinhoud naar de nieuwe Hostinger-mailbox migreren met behoud van oorspronkelijke mailmetadata waar mogelijk;
2. BREBO Office de historische mailbox gecontroleerd in batches laten verwerken via dezelfde intakepipeline als nieuwe mail.

De MX-omschakeling naar Hostinger gebeurt pas nadat de nieuwe mailbox operationeel is en de migratieroute getest is.

## Volgende bouwstappen

1. provider-neutrale IMAP intake live zetten;
2. Hostinger `info@brebobv.nl` runtime-secrets aansluiten;
3. Mime Mail + SMTP transport toevoegen;
4. verzonden mail automatisch terugschrijven naar `brebo_communication`;
5. AI conceptantwoord en inhoudscontrole toevoegen;
6. Zoho-historie in beheersbare batches migreren en verwerken;
7. bijlagen en thread/conversation-relaties verder verdiepen.
