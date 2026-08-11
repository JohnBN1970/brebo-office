# BREBO Office - Gmail Mail Intake

De Gmail-bron is uitsluitend-lezen en voedt de bestaande `brebo_communication`-intakepipeline. Er wordt niets in Gmail gewijzigd of als gelezen gemarkeerd.

## Vereiste runtime secrets

- `BREBO_GMAIL_CLIENT_ID`
- `BREBO_GMAIL_CLIENT_SECRET`
- `BREBO_GMAIL_REFRESH_TOKEN`
- `BREBO_MAIL_INTAKE_UID` - bestaand Drupal user-id waaronder broncommunicatie wordt geregistreerd
- optioneel `BREBO_GMAIL_INITIAL_LOOKBACK_SECONDS` - standaard 3600, begrensd op maximaal 7 dagen

Gebruik voor Google OAuth uitsluitend `https://www.googleapis.com/auth/gmail.readonly` en offline access zodat een refresh token beschikbaar is. Secrets horen in de runtime secret store en nooit in GitHub broncode of Drupal-configexport.

Drupal cron haalt maximaal 50 recente berichten per cyclus op met overlap. Idempotentie blijft geborgd via Gmail message-id en SHA-256. `SENT` wordt als uitgaand geregistreerd; overige berichten als inkomend. Gebouw- en projectrelaties blijven voorstellen totdat een mens ze formeel bevestigt.
