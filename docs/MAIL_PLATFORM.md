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
   -> Mail System routeert formatter en sender
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
- Uitgaande mail wordt eerst als `brebo_communication`-concept vastgelegd en pas na expliciete vrijgave verzonden.
- AI of achtergrondprocessen mogen nooit zelfstandig de formele status `Verzenden goedgekeurd` zetten.
- Een verzendactie vereist zowel het BREBO-verzendmandaat als expliciete runtime-activering van SMTP.
- Uitgaande mail wordt na succesvolle verzending in hetzelfde `brebo_communication`-object als verzonden vastgelegd.
- Mail kent vier primaire bestemmingen: `Project`, `Administratie`, `Persoonlijk` (zichtbaar als Privé) en `Junk`; Junk blijft bewaard maar wordt naar Spam verplaatst.
- Antwoorden en doorgestuurde berichten bewaren een expliciete bronrelatie en erven uitsluitend de canonieke primaire bestemming van de bron.
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

## Runtime configuratie uitgaand

De codebase bevat Mail System, Mime Mail en SMTP. Installatie van deze modules activeert verzending niet automatisch. SMTP blijft uit totdat de Hostinger-mailbox klaar is en de serverconfiguratie expliciet wordt aangezet.

Vereiste runtimegrens:

- `BREBO_MAIL_ADDRESS=info@brebobv.nl`
- `BREBO_SMTP_ENABLED=1` pas na succesvolle verbindings- en verzendtest;
- SMTP host, poort, protocol, gebruikersnaam en wachtwoord worden als server-only Drupal config overrides/runtime-secrets gezet en niet naar `config/sync` geschreven.

BREBO Office weigert een goedgekeurd bericht te verzenden zolang zowel de runtimeflag als `smtp.settings:smtp_on` niet actief zijn. Hiermee kan installatie en deployment veilig plaatsvinden voordat de echte mailboxcredentials beschikbaar zijn.

## Migratie Zoho

De migratie wordt in twee sporen uitgevoerd:

1. mailboxinhoud naar de nieuwe Hostinger-mailbox migreren met behoud van oorspronkelijke mailmetadata waar mogelijk;
2. BREBO Office de historische mailbox gecontroleerd in batches laten verwerken via dezelfde intakepipeline als nieuwe mail.

De MX-omschakeling naar Hostinger gebeurt pas nadat de nieuwe mailbox operationeel is en de migratieroute getest is.

## Bouwstatus

1. provider-neutrale IMAP intake: gebouwd;
2. aparte Zoho-migratiebron: gebouwd;
3. auditable uitgaand mailconcept + verzendmandaat: gebouwd;
4. Mail System + Mime Mail + SMTP dependencies: gebouwd;
5. veilige SMTP-activeringsgrens: gebouwd;
6. Hostinger `info@brebobv.nl` runtime-secrets: wacht op mailboxaanmaak;
7. gebruikersketen concept -> expliciet goedkeuren -> verzenden -> Verzonden: gebouwd; echte transportacceptatie blijft runtime-afhankelijk;
8. Zoho-historie in beheersbare batches migreren en verwerken: na broncredentials;
9. directe bronrelatie voor antwoorden/doorsturen: gebouwd; volledige conversation-threadweergave: vervolg.
