# BREBO Integration API

Cloudflare Worker voor de stateless analyse van communicatie uit BREBO Office.
De Worker kan niets verzenden of in BREBO Office wijzigen. Resultaten hebben
altijd `stored: false`, `sent: false` en verplichte menselijke controle.

De Worker begrenst analyses vooraf met een atomaire limiet per minuut en een
conservatief maandelijks tokenbudget. De budgetbewaking reserveert de maximale
uitvoer plus een schatting van de invoer; hierdoor kan de werkelijke rekening
niet ongemerkt boven de ingestelde technische grens uitkomen.

## Lokaal controleren

```bash
npm install
npm run check
```

De tests gebruiken uitsluitend fictieve waarden en een lokale, nagebootste
OpenAI-respons. Ze maken geen externe AI-kosten.

## Secrets

Maak voor lokale ontwikkeling een niet-gecommit bestand `.dev.vars`:

```text
OPENAI_API_KEY=<lokale testsleutel>
BREBO_SHARED_SECRET=<lang willekeurig omgevingssecret>
DOCUMENT_EXTRACTION_TOKEN=<lang willekeurig extraction-secret>
```

Voor Cloudflare worden deze waarden uitsluitend als secrets ingesteld:

```bash
npx wrangler secret put OPENAI_API_KEY
npx wrangler secret put BREBO_SHARED_SECRET
npx wrangler secret put DOCUMENT_EXTRACTION_TOKEN
```

Zet secrets nooit in `wrangler.jsonc`, Git, logs of Drupal-config-export.

## Limieten

- `MAX_ANALYSES_PER_WINDOW`: maximaal aantal toegestane analyses per venster.
- `RATE_WINDOW_SECONDS`: lengte van het venster in seconden.
- `MONTHLY_TOKEN_BUDGET`: conservatief tokenbudget per UTC-kalendermaand.
- `MAX_EXTRACTIONS_PER_WINDOW`: maximaal aantal documentextracties per venster.
- `MONTHLY_EXTRACTION_BUDGET`: maximaal aantal gereserveerde extracties per maand.

Een overschrijding geeft een generieke `429` terug. Alleen tellers en perioden
worden bewaard; nooit communicatie-inhoud, projectgegevens of AI-uitvoer.

## Productiedeployment en managed extraction acceptance

Wijzigingen onder `integration-api/**` worden na merge naar `develop` door de
Integration API workflow gevalideerd en naar Cloudflare Workers gedeployed.
Daarna voert de workflow een productie-smoke uit op
`/v1/internal/document-extraction`. Die acceptance bewijst dat het endpoint
zonder bearer-token fail-closed is en dat een synthetische PNG via de echte
Workers AI binding naar niet-lege tekst wordt omgezet. De smoke gebruikt geen
klantdata en bewaart het testdocument niet.
