# Moneybird bank read: gecontroleerde uitrol

## Eigenaarschap en bereik

BREBO Office blijft eigenaar van facturen en financiële administratie. Deze route
leest uitsluitend bankbewijs uit Moneybird en importeert geen facturen. Bestaande
factuurverzending, factuurimport en reconciliatie worden niet aangepast of aangeroepen.

`GET /v1/accounting/bank-data` vereist dezelfde HMAC-canonicalisatie als de Office
bankclient: methode, pad, SHA-256 van de lege body, Unix-tijd en UUID-v4 request-id,
gescheiden door newlines. Queryparameters worden geweigerd. Succes en fouten zijn
`Cache-Control: no-store, private`. POST, PATCH en DELETE worden geweigerd.

De respons is `{status:"ok",request_id,provider:"moneybird",bank:{...}}` met:

- `balances_available: false`; nooit berekende of overgenomen saldi.
- `period: "this_month"`; uitsluitend de huidige maand volgens Moneybird.
- `accounts`: actieve `BankAccount`-rekeningen met id, naam, identificatie en valuta.
- `transactions`: id, financial_account_id, bedrag als decimale string, datum, bericht.

Alleen twee GET-aanvragen naar de geconfigureerde Moneybird-administratie:
`financial_accounts.json` en
`financial_mutations.json?filter=period%3Athis_month%2Cstate%3Aall`.
Factuur-/betalingslinks, providerstatussen en extra velden worden niet doorgegeven.
Onbekende/inactieve rekeningen worden uitgesloten. Ongeldige responses worden als
fout afgehandeld; er wordt geen gedeeltelijk succes teruggemeld.

Moneybird weigert lijsten met meer dan 100 mutaties met HTTP 400. Deze implementatie
geeft dan een generieke 502 terug: geen stille afkapping, paginering of claim op een
volledige historie. Een grotere periode/historie vraagt een afzonderlijke uitbreiding.

Bronnen: https://developer.moneybird.com/api/financial-accounts/ en
https://developer.moneybird.com/api/financial-mutations/ (gecontroleerd 6 november 2026).

## Vereiste configuratie

| Omgeving | Instelling | Vereiste |
| --- | --- | --- |
| Worker | `MONEYBIRD_BANK_READ_ENABLED` | Exact `enabled`; standaard ontbrekend en dus uitgeschakeld |
| Worker | `BREBO_SHARED_SECRET` | Minimaal 24 tekens, gelijk aan het Office-integratiegeheim |
| Worker | `MONEYBIRD_ADMINISTRATION_ID` | Expliciete numerieke administratie-id |
| Worker | `MONEYBIRD_ACCESS_TOKEN` | Serversecret met scopes `settings` en `bank` |
| Office-test | `OFFICE_MONEYBIRD_BANK_READ_ENABLED` | Exact `enabled`, uitsluitend na acceptatie |
| Office-test | `OFFICE_MONEYBIRD_BANK_API_URL` | `https://brebo-integration-api.john-boon.workers.dev/v1/accounting/bank-data` |
| Office-test | `OFFICE_INTEGRATION_SHARED_SECRET` | Hetzelfde geheim als `BREBO_SHARED_SECRET` |
| Office-test | PHP-cURL | Beschikbaar, naast bestaande Finance-configuratie/autorisatie |
| Office-test ACL | `finance/read` én `finance/bank_read` | Beide expliciet toegekend aan een actieve Office-operator |

Geen geheimen opnemen in Git, screenshots, logs of responsebodies. Deze wijziging
zet geen configuratie aan. De bestaande deploy gebruikt `--keep-vars`.

## Volgende stappen en grenzen

1. PR-check: typecheck, volledige Vitest-set en Wrangler dry-run. Tests gebruiken
   fictieve bankdata; remote bindings zijn in Vitest uitgeschakeld.
2. Review vóór merge: `integration-api/**` activeert bij merge naar `develop` de
   bestaande productie-Worker-deployment. Dat is meer dan een stagingkopie.
   Geen merge of deployment als onderdeel van uitsluitend broncodeacceptatie.
3. Na afzonderlijke deploymentbeslissing: voer op de server een gesigneerde GET uit
   en controleer uitsluitend status, schema, periode en aantallen. Log geen bankdata.
   Verifieer ook 401 bij ongeldige HMAC en de expliciete configuratiestatus.
4. Snapshot en paneel eisen nu `period:this_month` en tonen expliciet dat de
   mutaties van deze maand zijn, zonder volledige historie te claimen. Een
   ontbrekende of andere periode wordt geweigerd. Bankgegevens worden alleen op
   het test-Betaalcentrum getoond aan actieve operators met beide expliciete grants
   `finance/read` en `finance/bank_read`. De bankaanvraag vindt pas na die controle
   plaats. Gewone Finance-, CRM- of projecttoegang is onvoldoende. Er worden geen
   grants automatisch toegekend en er is geen schemawijziging nodig.
   Tests controleren grantcombinaties, intrekken van rechten, ongeldige periodes
   en de volledige Finance-weergave voor toegestane/geweigerde paneeltoegang.
   Verifieer vóór activering ook de sessie/autorisatie op de doelserver.
5. Controleer dat factuurverzending ongewijzigd werkt. Inventariseer bestaande
   Moneybird-factuurimport apart; deze bankroute schakelt die niet uit.

GO: broncodecontrole en geïsoleerde staging. NO-GO: Office-paneelactivering zolang
de echte bankaanvraag en sessie/autorisatie op de doelserver niet zijn geaccepteerd.

Terugval: zet alleen `MONEYBIRD_BANK_READ_ENABLED` en/of de Office-bankflag uit.
Er zijn geen datamigraties of herstelacties op facturen nodig voor deze bankroute.
