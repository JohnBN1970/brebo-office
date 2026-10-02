# BREBO Intake API v1

## Status van dit document

Dit document is het canonieke integratiecontract tussen `brebo-platform` en `brebo-office` voor website-/portaalintake. Wijzigingen die de grens tussen beide systemen raken, worden eerst hier vastgelegd en daarna in beide repositories tegen dezelfde contractversie gebouwd en getest.

Gescheiden ontwikkelen betekent nadrukkelijk niet los van elkaar werken: Platform en Office delen dit contract, maar blijven ieder eigenaar van hun eigen domein.

## Doel en eigenaarschap

De website begeleidt een bezoeker bij het aanleveren van projectinformatie en documenten en toont een begrijpelijke terugkoppeling. Office voert de inhoudelijke verwerking uit.

- `brebo-platform` bezit website-UX, formulieren, uploads, klantreis, SEO, bezoekersvalidatie en presentatie van het afgesproken publieke resultaat.
- `brebo-office` bezit centrale intake, documentextractie, classificatie, scope-inference, review, waarheidslogica en CRM-/gebouwverwerking.
- Platform bouwt geen tweede herkenningsmotor.
- Office neemt geen websitepresentatie of websiteklantreis over.
- Website-specifieke namen zijn geen onderdeel van de Office-kern; verschillen worden via `source` en contextmetadata doorgegeven.

## Contractversie

Alle projectaanvragen gebruiken `schema_version: "1.0"`.

Nieuwe contractwijzigingen zijn alleen compatibel binnen v1 zolang bestaande verplichte velden en betekenissen niet worden gebroken. Een breaking change krijgt een nieuwe hoofdversie.

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

De aanvraag bevat minimaal:

```json
{
  "schema_version": "1.0",
  "request_id": "00000000-0000-0000-0000-000000000000",
  "source": "brebo-platform.europakozijn",
  "journey": {
    "name": "projectaanvraag",
    "step": "initial",
    "submission_type": "initial"
  },
  "service": {
    "selected_service": "optioneel",
    "help_request": "optioneel"
  },
  "previous_intake_id": null,
  "observed": {},
  "detected": {},
  "calculated": {},
  "selected": {}
}
```

### Betekenis van de contextvelden

- `source`: vaste bron-ID. Nieuwe website- of portaalstromen krijgen een nieuwe source-ID, geen nieuwe Office-controller. De bron-ID is configuratie en geen bewijs van documentinhoud.
- `journey.name`: klantreis of formulier waar de bezoeker zich bevindt.
- `journey.step`: concrete stap binnen die klantreis.
- `journey.submission_type`: bijvoorbeeld `initial`, `supplement` of `correction`.
- `service.selected_service`: door de bezoeker gekozen dienst.
- `service.help_request`: wat de bezoeker daadwerkelijk van BREBO vraagt.
- `request_id`: unieke aanvraagreferentie voor idempotentie en correlatie. Opnieuw aanbieden van hetzelfde request mag geen dubbele intake opleveren.
- `previous_intake_id`: optionele verwijzing naar een bestaande intake als dit een aanvulling is.

De context geeft Office richting, maar is nooit automatisch inhoudelijk bewijs. Een aanvraag vanaf een kozijnenpagina betekent bijvoorbeeld niet dat alle aangeleverde stukken uitsluitend over kozijnen gaan.

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
  "journey": {
    "name": "projectaanvraag",
    "step": "documents"
  },
  "project_name": "optioneel",
  "address": "optioneel",
  "previous_intake_id": null
}
```

Office slaat het origineel privé op, voert beschikbare extractie uit, correleert documentinhoud met de overige stukken in dezelfde aanvraag, maakt waar verantwoord een voorlopige machinescope en routeert de uitkomst via de centrale source-neutral intake.

## Herkenningsregels

De herkenningsmotor blijft volledig aan Office-zijde. Bij de interpretatie gelden minimaal de volgende regels:

- één afgebeeld kozijntype is niet automatisch één te leveren kozijn;
- aantallen kunnen elders in een document, tabel, legenda of toelichting staan;
- afbeeldingen, tabellen, tekst en overige stukken moeten waar mogelijk in samenhang worden beoordeeld;
- tegenstrijdige waarden blijven als conflict zichtbaar;
- ontbrekende gegevens blijven expliciet open;
- context uit de website mag de inhoudelijke herkenning sturen, maar niet vervangen;
- voorlopige machine-uitkomsten blijven reviewbaar en worden niet stilzwijgend als geverifieerde waarheid opgeslagen.

## Ontvangstrespons

Ontvangst en inhoudelijke herkenning zijn twee afzonderlijke toestanden. Platform mag `received` of `accepted` nooit presenteren als `recognized` of `completed`.

Een succesvolle ontvangstrespons bevat minimaal:

- `status`
- `api_version`
- `request_id`
- `intake_id` of tijdelijk compatibel `record_id`
- `duplicate`
- `processing_status`

Voorkeursstatus na ontvangst: `received` of `processing`.

## Statusopvraag

BREBO Intake API v1 voorziet in een statusopvraag op basis van de Office-intake-ID en/of aanvraagreferentie. De concrete route wordt in dezelfde v1-contractslice geïmplementeerd en mag geen interne databasekennis van Platform vereisen.

Het statusantwoord bevat minimaal:

- `request_id`
- `intake_id`
- `processing_status`
- `updated_at`
- indicatie of een publiek resultaat beschikbaar is

Minimale statusset:

- `received`
- `processing`
- `review_required`
- `completed`
- `failed`

## Publiek resultaat voor Platform

Wanneer verwerking voldoende ver is, kan Platform een bezoekersgeschikt resultaat ophalen. Dit publieke resultaat bevat alleen informatie die veilig en begrijpelijk aan de bezoeker kan worden teruggegeven, zoals:

- wat Office heeft herkend;
- de voorlopige projectscope;
- welke informatie ontbreekt;
- waar verduidelijking nodig is;
- zichtbare onzekerheden of conflicten die relevant zijn voor de vervolgstap.

Het publieke resultaat bevat niet automatisch:

- interne notities;
- kostprijzen of marges;
- interne risicoscores;
- interne beoordelingscommentaren;
- gegevens uit andere klanten/dossiers;
- overige Office-informatie die niet expliciet onderdeel is van het publieke contract.

## Fouten

Fouten worden machineleesbaar teruggegeven als:

```json
{
  "status": "error",
  "error": {
    "code": "machine_readable_code"
  }
}
```

Het contract onderscheidt ten minste validatiefouten, authenticatiefouten, duplicate/idempotency-situaties, tijdelijke Office-onbeschikbaarheid en verwerkingsfouten.

## Migratie

De bestaande Europakozijn-routes blijven tijdelijk beschikbaar zodat Platform en Office onafhankelijk gedeployed kunnen worden. Zodra `brebo-platform` aantoonbaar via v1 aanlevert én status/resultaat via v1 gebruikt, kunnen de legacy-routes in een aparte cleanup-PR worden verwijderd.

## Architectuurregel

Nieuwe websitefunctionaliteit mag niet als website-specifieke controller, herkenningsmotor of destination aan `brebo-office` worden toegevoegd. Nieuwe bronnen integreren via dit contract en de generieke source-neutral intake.

Voor wijzigingen die beide repositories raken geldt:

1. contract eerst aanpassen;
2. Office-implementatie tegen het contract bouwen;
3. Platform-implementatie tegen hetzelfde contract bouwen;
4. contracttests in beide repositories;
5. Office eerst deployen wanneer Platform nieuwe Office-functionaliteit nodig heeft;
6. daarna Platform deployen;
7. pas na aantoonbare end-to-end acceptatie legacy-koppelingen verwijderen.

## Scope van deze integratieslice

Binnen scope:

- aanlevering;
- ontvangstbevestiging;
- idempotentie/correlatie;
- statusopvraag;
- publiek resultaat;
- foutcontract;
- websitecontext;
- documentherkenning en voorlopige scope voor deze intake.

Buiten scope:

- brede wijzigingen aan verkoop;
- calculatie;
- Finance;
- overige Office-modules die niet nodig zijn voor dit contract.

## Definition of Done

Deze contractversie is operationeel gereed wanneer:

1. een websiteaanvraag met documenten en websitecontext exact één traceerbare Office-intake oplevert;
2. opnieuw aanbieden van dezelfde aanvraag geen dubbele intake veroorzaakt;
3. aanvullingen aan een bestaande intake kunnen worden gekoppeld;
4. Office de inhoudelijke verwerking uitvoert met behoud van onzekerheden en conflicten;
5. Platform ontvangst, voortgang en het afgesproken publieke resultaat correct kan tonen;
6. beide repositories dezelfde v1-contracttests doorstaan;
7. interne Office-data buiten het publieke websiteantwoord blijft.
