# BREBO Integration API

Cloudflare Worker voor de stateless analyse van communicatie uit BREBO Office.
De Worker kan niets verzenden of in BREBO Office wijzigen. Resultaten hebben
altijd `stored: false`, `sent: false` en verplichte menselijke controle.

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
```

Voor Cloudflare worden deze waarden uitsluitend interactief ingesteld:

```bash
npx wrangler secret put OPENAI_API_KEY
npx wrangler secret put BREBO_SHARED_SECRET
```

Zet secrets nooit in `wrangler.jsonc`, Git, logs of Drupal-config-export.

## Geen deployment vanuit deze bouwstap

`npm run deploy:dry-run` bouwt en valideert lokaal. Een echte deployment,
Cloudflare-accountconfiguratie, productie-secrets en Drupal-activatie vereisen
afzonderlijke menselijke goedkeuring.
