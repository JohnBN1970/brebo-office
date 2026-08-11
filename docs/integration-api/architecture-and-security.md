# BREBO Integration API — technisch ontwerp en beveiligingsgrenzen

Status: concept  
Doel: testbare specificatie voor de serverloze Integration API die aansluit op PR #78.  
Niet in scope: deployment, productie-secrets, automatische verzending, formele vaststelling en uitvoering van vervolgacties.

## 1. Besluit

De Integration API wordt een zelfstandige Cloudflare Worker tussen BREBO Office en de OpenAI Responses API.

- BREBO Office blijft de enige bron en eigenaar van project- en communicatiegegevens.
- De Worker heeft geen bedrijfsdatabase en bewaart geen communicatie of modeluitvoer.
- OpenAI wordt aangeroepen met `store: false`.
- Modeluitvoer wordt met Structured Outputs tegen een strikt JSON-schema afgedwongen.
- Alleen BREBO Office mag een geldig ondertekend verzoek doen.
- Het API-resultaat is uitsluitend analyse. BREBO Office slaat dit uitsluitend op als `AI-concept`.
- Menselijke controle en formele vaststelling blijven verplicht.

## 2. Componenten en gegevensstroom

1. BREBO Office valideert maximaal 12.000 tekens en bouwt het contractuele JSON-verzoek.
2. BREBO Office maakt een unieke request-ID en UTC-timestamp.
3. BREBO Office berekent SHA-256 over de exacte request-body en ondertekent de canonieke request met HMAC-SHA256.
4. De Worker controleert content-type, grootte, timestamp, request-ID, handtekening en schema.
5. Een replayregister bewaart alleen een hash van request-ID gedurende tien minuten; geen inhoud of persoonsgegevens.
6. De Worker roept OpenAI aan met `store: false`, zonder tools, bestanden, webtoegang of conversation state.
7. De Worker valideert de analyse opnieuw en retourneert het contractuele antwoord.
8. BREBO Office accepteert alleen `stored: false`, `sent: false` en `human_review_required: true`.

## 3. Authenticatie v1

Verplichte headers:

- `Content-Type: application/json`
- `X-BREBO-Timestamp`: Unix-seconden in UTC
- `X-BREBO-Request-Id`: UUID v4
- `X-BREBO-Signature`: `v1=<hex-hmac-sha256>`

Canonieke tekenreeks:

```text
<METHOD>
<PATH>
<SHA256_HEX_BODY>
<TIMESTAMP>
<REQUEST_ID>
```

Regels:

- HMAC-SHA256 met het omgevingsspecifieke `BREBO_SHARED_SECRET`.
- Constant-time vergelijking.
- Maximale klokafwijking: vijf minuten.
- Request-ID mag binnen tien minuten niet opnieuw voorkomen.
- Test en productie gebruiken verschillende secrets.
- Bearer-authenticatie uit PR #78 is alleen een tijdelijke compatibiliteitsfase en geen productie-eindtoestand.
- Geen secret, volledige handtekening of request-body in logs.

## 4. Endpoints

### `GET /health/status`

Geeft alleen operationele status, contractversie en Worker-versie terug. Geen verbindingstest naar OpenAI en geen bedrijfsgegevens.

### `POST /v1/communications/analyze`

Accepteert exact het schema uit `openapi.yaml`. Limieten:

- body maximaal 32 KiB;
- kanaal maximaal 50 tekens;
- onderwerp maximaal 200 tekens;
- bericht maximaal 12.000 tekens;
- onbekende velden worden geweigerd;
- `human_review_required` moet `true` zijn;
- testmodus vereist `contains_real_data: false`.

## 5. AI-contract

De Worker vraagt uitsluitend om:

- classificatie;
- samenvatting;
- besluiten/toezeggingen;
- risico's;
- voorgestelde vervolgacties;
- confidence van 0 tot en met 100;
- `human_review_required: true`.

Vaste AI-grenzen:

- geen tool calls;
- geen web search;
- geen file search;
- geen verzending;
- geen side effects;
- geen ketengesprekken of `previous_response_id`;
- geen opslag van response-objecten;
- model configureerbaar via `OPENAI_MODEL`;
- time-out en tokenlimiet worden expliciet begrensd.

## 6. Foutcontract

| HTTP | Code | Betekenis |
|---:|---|---|
| 400 | `invalid_request` | JSON of schema ongeldig |
| 401 | `invalid_signature` | Handtekening ontbreekt of klopt niet |
| 409 | `replayed_request` | Request-ID al gebruikt |
| 413 | `payload_too_large` | Body groter dan toegestaan |
| 415 | `unsupported_media_type` | Geen JSON |
| 422 | `unsafe_or_unprocessable` | Verzoek kan niet veilig worden verwerkt |
| 429 | `rate_limited` | Verbruikslimiet bereikt |
| 502 | `provider_error` | Ongeldige of mislukte AI-respons |
| 504 | `provider_timeout` | AI-aanroep overschrijdt tijdslimiet |

Foutresponsen bevatten uitsluitend `status`, `error.code`, `error.message` en `request_id`. Geen input, AI-uitvoer, stacktrace of providerdetails.

## 7. Logging en privacy

Toegestane logvelden:

- request-ID als eenrichtingshash;
- endpoint;
- HTTP-status;
- duur;
- Worker-versie;
- modelnaam;
- foutcategorie;
- testmodus.

Verboden logvelden:

- onderwerp of bericht;
- project- of communicatie-inhoud;
- namen, e-mailadressen, telefoonnummers of adressen;
- secrets, autorisatieheaders of handtekeningen;
- volledige OpenAI-request of -response.

Observability gebruikt gestructureerde JSON-logs met beperkte retentie. De Worker logt geen request-bodies, ook niet bij fouten.

## 8. Configuratie

Niet-geheime configuratie:

- `API_VERSION`
- `WORKER_VERSION`
- `OPENAI_MODEL`
- `MAX_INPUT_CHARS=12000`
- `MAX_BODY_BYTES=32768`
- `MAX_CLOCK_SKEW_SECONDS=300`
- `REPLAY_TTL_SECONDS=600`

Secrets:

- `OPENAI_API_KEY`
- `BREBO_SHARED_SECRET`

Secrets worden uitsluitend via Cloudflare secrets gezet en nooit in Git, Wrangler-vars, logs of Drupal-config-export opgenomen.

## 9. Testmatrix

| ID | Scenario | Verwacht |
|---|---|---|
| A01 | Geldig gesigneerd fictief verzoek | 200, schema geldig, niets opgeslagen of verzonden |
| A02 | Ontbrekende handtekening | 401 |
| A03 | Verkeerde handtekening | 401 |
| A04 | Timestamp ouder dan vijf minuten | 401 |
| A05 | Zelfde request-ID opnieuw | 409 |
| A06 | Body na ondertekening gewijzigd | 401 |
| V01 | Leeg bericht | 400 |
| V02 | Bericht van 12.001 tekens | 400 |
| V03 | Body groter dan 32 KiB | 413 |
| V04 | Onbekend veld | 400 |
| V05 | Testmodus met echte data | 422 |
| O01 | AI-respons mist vereist veld | 502, geen analyse terug |
| O02 | AI-respons heeft extra veld | 502, geen analyse terug |
| O03 | Confidence buiten 0–100 | 502 |
| O04 | Provider weigert | 422 of 502, generieke fout |
| R01 | Rate limit overschreden | 429 |
| T01 | Provider-time-out | 504 |
| P01 | Logs na succes | Geen inhoud of persoonsgegevens |
| P02 | Logs na fout | Geen inhoud, secrets of providerrespons |
| D01 | Drupal ontvangt veilige 200-respons | Alleen AI-concept + revisie |
| D02 | Drupal ontvangt foutrespons | Dossier ongewijzigd |
| D03 | Formele vaststelling zonder mens | Technisch geblokkeerd |

## 10. Vrijgavepoort

Productie is pas toegestaan wanneer:

1. alle contract-, authenticatie-, replay-, privacy- en fouttests groen zijn;
2. Drupal HMAC v1 gebruikt en Bearer-auth is verwijderd;
3. fictieve end-to-endtest alleen een AI-concept en revisie oplevert;
4. formele vaststelling aantoonbaar menselijke invoer vereist;
5. fout- en time-outtests het dossier ongewijzigd laten;
6. secrets in afzonderlijke test- en productieomgevingen staan;
7. rate- en budgetlimieten actief zijn;
8. een aparte menselijke goedkeuring voor deployment is gegeven.

## 11. Nog uit te voeren in volgende bouw-PR

- Worker-project en testharnas aanmaken.
- Contracttypes uit OpenAPI genereren of aantoonbaar ermee valideren.
- HMAC- en replaycontrole implementeren.
- OpenAI Responses API met `store: false` en Structured Outputs implementeren.
- Drupal-client van Bearer naar HMAC v1 ombouwen.
- CI voor typecheck, lint, unit-, contract- en Worker-integratietests toevoegen.

Deze concept-PR bouwt en deployt niets.
