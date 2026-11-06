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

Geen geheimen opnemen in Git, screenshots, logs of responsebodies. De standaardstaging
zet geen configuratie aan. Alleen de optionele 0%-versie krijgt een bankflag.
De bestaande deploy gebruikt `--keep-vars`.

## Volgende stappen en grenzen

De stagingworkflow checkt de immutable `github.sha` van de gekozen workflowbranch
uit. Selecteer voor deze PR de branch `finance-moneybird-bank-api-20261106`, met
bevestiging `STAGE-OFFICE-BANK`. Er is geen merge nodig voor deze test.
Elke poging krijgt een eigen directory met rechten 700. Een meegekopieerd
SHA-256-manifest wordt op de doelserver vergeleken voordat de tests starten.
De map bevat alleen de benodigde bronbestanden, tests en het CLI-preflightscript;
er is geen router, sessie of actieve release aan gekoppeld.

`check_live_bank=true` voert aanvullend een ongesigneerde en, indien serverconfiguratie
aanwezig is, een gesigneerde bank-GET uit. De preflight gebruikt uitsluitend de
Office-testconfiguratie en leest de ACL binnen een read-only databasetransactie.
Er worden alleen booleans en de ongesigneerde HTTP-status gelogd. Configuratie- en
actieve Finance-bestandshashes worden voor/na vergeleken. Geen geheimen of bankdata
worden naar staging gekopieerd of in logs gezet. Ontbrekende prerequisites blijven
expliciet zichtbaar als `BANK_READ_PREFLIGHT_GO=0`, ook als staging zelf slaagt.
Een geslaagde CLI-preflight vervangt de sessie-/UI-acceptatie niet.

Met `check_worker_version=true` volgt een afzonderlijke versietest op Cloudflare.
`wrangler versions upload --keep-vars` uploadt de exacte bron als een niet-actieve
versie. Alleen voor die versie wordt `MONEYBIRD_BANK_READ_ENABLED=enabled` gezet.
Deze Worker bevat Durable Objects, waarvoor Cloudflare geen versie-URL genereert.
Dat is vastgesteld in run 42191611089; de upload slaagde, de actieve deployment
bleef gelijk en de bank-GET werd niet uitgevoerd.

De ondersteunde proef wijzigt daarom tijdelijk de API-deployment naar exact
100% verkeer op de vastgelegde huidige versie en 0% op de nieuwe testversie.
Er mag bij aanvang maar één versie met 100% verkeer actief zijn. De workflow
deelt de concurrency-lock met de productie-API-deploy en controleert de verdeling
voordat de bank-GET start. Alleen de twee expliciete bank-GETs bevatten een
`Cloudflare-Workers-Version-Overrides` header voor de testversie. Gewoon verkeer
blijft op de oorspronkelijke versie. Er worden geen factuur-, AI- of Durable
Object-routes aangeroepen en geen Office-configuratie, release of grants aangepast.

Na de proef herstelt de workflow altijd de oorspronkelijke versie als enige
versie op 100%, ook als de bankaanvraag faalt. Een onverwachte verdeling wordt
niet overschreven. De deployment-id verandert; versie en verkeersverdeling worden
hersteld en gecontroleerd. Deze proef vereist expliciete toestemming voor de
tijdelijke API-deployment, naast de stagingbevestiging.
De proef gebruikt bestaande resources/secrets en rapporteert alleen status,
periode en aantallen. Ze bewijst de bankroute, geen complete historie en geen
werkende Office-testconfiguratie of sessieacceptatie.

Bronnen: https://developers.cloudflare.com/workers/versions-and-deployments/version-urls/
en https://developers.cloudflare.com/workers/versions-and-deployments/version-overrides/

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
