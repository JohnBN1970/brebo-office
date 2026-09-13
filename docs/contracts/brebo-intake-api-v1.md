# BREBO Intake API v1

## Doel

Dit contract vormt de vaste grens tussen `brebo-platform` en `brebo-office`.

- `brebo-platform` bezit website-UX, formulieren, uploads, SEO en klantreis.
- `brebo-office` bezit intake, extractie, classificatie, scope-inference, review en CRM/gebouwverwerking.
- Website-specifieke namen zijn geen onderdeel van de Office-kern; bronverschillen worden via `source` en metadata doorgegeven.

## Authenticatie

Alle requests gebruiken dezelfde HMAC-signatuur:

- `X-BREBO-Request-Id`: UUID
- `X-BREBO-Timestamp`: unix timestamp, maximaal 300 seconden verschil
- `X-BREBO-Signature`: `v1=<hex hmac sha256>`

Voor documentuploads is daarnaast verplicht:

- `X-BREBO-Content-SHA256`: sha256 van het bestand

De canonical string is:

```text
POST\n<path>\n<body-or-content-sha256>\n<timestamp>\n<request-id>
```

## Projectaanvraag

`POST /brebo-internal/intake/v1/project-request`

Minimale JSON-payload:

```json
{
  "schema_version": "1.0",
  "request_id": "00000000-0000-0000-0000-000000000000",
  "source": "brebo-platform.europakozijn",
  "observed": {},
  "detected": {},
  "calculated": {},
  "selected": {}
}
```

`source` moet beginnen met `brebo-platform.`. Nieuwe website- of portaalstromen krijgen alleen een nieuwe source-id; hiervoor is geen nieuwe Office-controller nodig.

Succesrespons bevat minimaal:

- `status`
- `api_version`
- `request_id`
- `record_id`
- `duplicate`

## Projectdocument

`POST /brebo-internal/intake/v1/project-document`

Multipart velden:

- `document`: bestand
- `metadata`: JSON-string

Minimale metadata:

```json
{
  "source": "brebo-platform.europakozijn",
  "source_label": "Website - Europakozijn",
  "project_name": "optioneel",
  "address": "optioneel"
}
```

Office slaat het origineel privé op, voert beschikbare extractie uit, maakt een voorlopige machinescope en routeert de uitkomst via de centrale source-neutral intake.

## Migratie

De bestaande Europakozijn-routes blijven tijdelijk beschikbaar zodat Platform en Office onafhankelijk gedeployed kunnen worden. Zodra `brebo-platform` aantoonbaar op v1 draait, kunnen de legacy-routes in een aparte cleanup-PR worden verwijderd.

## Architectuurregel

Nieuwe websitefunctionaliteit mag niet meer als website-specifieke controller of destination aan `brebo-office` worden toegevoegd. Nieuwe bronnen integreren via dit contract en de generieke source-neutral intake.
