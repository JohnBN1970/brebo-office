# BREBO Office — Continuïteit Finance — 2026-08-24

Dit document is een actuele continuïteitsaanvulling op `docs/BREBO_CONTINUITEIT.md` voor de Finance/Moneybird-bouwslag van 24 augustus 2026. Bij hervatten van Finance-ontwikkeling moet deze stand worden meegenomen en mag de hieronder vastgelegde architectuur niet opnieuw worden uitgevonden.

## Mijlpaal — Moneybird inkoopfacturen operationeel

Status: **gerealiseerd in productie**.

De beveiligde Moneybird-inkoopfactuursync werkt end-to-end:

```text
Moneybird
  -> BREBO Integration API
  -> HMAC-beveiligde verbinding
  -> Drupal / BREBO Finance
  -> Inkoopfacturen
```

Productieresultaat op 2026-08-24:

- 380 echte inkoopfacturen zijn in BREBO Office beschikbaar;
- Moneybird Purchase Sync eindigt groen;
- importer is herhaalbaar/idempotent gemaakt;
- duplicaatbeveiliging gebruikt de leveranciersfactuur-identiteit `supplier_ref + invoice_number` als natuurlijke sleutel naast `moneybird_id`;
- facturen van verschillende leveranciers mogen hetzelfde factuurnummer hebben;
- bestaande lokale facturen worden bij een Moneybird-match geadopteerd/geüpdatet in plaats van dubbel ingevoegd;
- BREBO-owned project-/commitment-/matchgegevens blijven bij Moneybird-refresh behouden.

Relevante PR's:

- PR #405 — adopt existing purchase invoices during Moneybird sync;
- PR #406 — supplier invoice key authoritative Moneybird fallback;
- mergecommit PR #406: `df89e6e734d02e5c82d6381550fd6a9dae4fad87`.

## Leidende architectuur — inkoopfactuurroute

De normale toekomstige route voor nieuwe inkoopfacturen is **niet Moneybird -> Office als primaire intake**. De reeds gebouwde Moneybird-import blijft nodig voor historische import, synchronisatie, status-terugmelding en reconciliatie.

De leidende hoofdroute is:

```text
E-mail / bijlage
  -> BREBO Office mail-intake
  -> factuurherkenning / extractie
  -> leverancier herkennen of gecontroleerd aanmaken
  -> project herkennen/koppelen waar betrouwbaar
  -> inhoud, bedragen en BTW controleren
  -> factuur + originele PDF naar Moneybird
  -> Moneybird bewaart de boekhoudkundige factuur/PDF
  -> BREBO Office bewaart operationele metadata, koppelingen, controles en Moneybird-ID
  -> PDF vanuit Office on-demand via de Integration API uit Moneybird tonen
  -> Moneybird-status/betaling terug synchroniseren naar Office
```

### Opslagprincipe

- BREBO Office wordt niet de permanente dubbele opslagplaats voor iedere inkoopfactuur-PDF.
- Moneybird bewaart de boekhoudkundige factuur inclusief PDF.
- Office bewaart de operationele/intelligente laag: identiteit, leverancier, project, controles, workflow, matchstatus, Moneybird-ID en auditinformatie.
- Vanuit Office moet `Bekijk factuur` de originele PDF veilig on-demand uit Moneybird kunnen tonen/streamen.
- Alleen wanneer een document tevens zelfstandig onderdeel moet zijn van een juridisch/projectmatig BREBO-dossier kan bewust een aparte dossierkopie worden bewaard.

## Leveranciers — eerstvolgende bouwstap

Gebruik **geen tweede leveranciersregister in Finance**.

Het bestaande Drupal-objecttype `brebo_organization` / **Organisatie** is de centrale relatiekaart en is bedoeld voor onder meer opdrachtgever, hoofdaannemer, leverancier, adviseur en overige ketenpartij.

Gewenste keten:

```text
Moneybird contact-ID
  -> bestaande BREBO Organisatie zoeken
  -> betrouwbare match: koppelen
  -> geen match: gecontroleerd Organisatie-record met rol leverancier aanmaken
  -> inkoopfacturen aan centrale Organisatie koppelen
```

Kernregels:

- Moneybird contact-ID is de harde externe identiteit waar beschikbaar;
- niet blind op alleen leveranciersnaam dedupliceren;
- naamnormalisatie mag helpen bij matching maar mag geen onzekere organisaties automatisch samenvoegen;
- voorkom varianten zoals `Shiftbase`, `SHIFTBASE B.V.` en `Shiftbase BV` als afzonderlijke relaties wanneer bewijs dezelfde organisatie aantoont;
- eerst bestaande Organisaties herkennen, daarna uitsluitend ontbrekende leveranciers aanmaken;
- rapportage van de eerste run moet aantallen tonen: unieke leveranciers, bestaand gekoppeld, nieuw aangemaakt, twijfelgevallen en duplicaatrisico's.

## Projectherkenning — direct daarna

Na leverancierskoppeling volgt projectherkenning voor de 380 historische facturen.

Uitgangspunt:

```text
Inkoopfactuur
  -> beschikbare Moneybird-/factuurmetadata en omschrijving
  -> bestaande BREBO-projecten / gebouwen / opdrachten doorzoeken
  -> hoge betrouwbaarheid: automatisch koppelen
  -> onvoldoende bewijs: voorstel/wachtrij voor controle
```

Kernregel: **Office mag niet blind nieuwe projecten creëren uit factuurtekst.** Een project wordt alleen automatisch gekoppeld wanneer een bestaand canoniek project betrouwbaar is herkend. Aanmaak van een werkelijk nieuw project vereist voldoende bewijs en de daarvoor vastgestelde projectworkflow.

## PDF-weergave

Gewenste UX in Finance/Inkoopfacturen:

- factuur openen vanuit de lijst;
- factuurgegevens, leverancier, project, status en controles zichtbaar;
- originele Moneybird-PDF direct kunnen bekijken;
- PDF niet permanent dupliceren in de Office-database wanneer dat niet nodig is;
- veilige retrieval/streaming via de BREBO Integration API.

## Hervatpunt volgende ontwikkelsessie

1. Bestaande velden en rollen van `brebo_organization` exact inventariseren.
2. Moneybird contact-ID duurzaam aan Organisatie-identiteit koppelen.
3. Veilige leverancier-matcher bouwen.
4. Gecontroleerde automatische leverancier-aanmaak bouwen.
5. De 380 historische inkoopfacturen aan Organisaties koppelen en integriteitsrapport tonen.
6. Projectherkenning bouwen met confidence/controlepad en zonder blind projectaanmaakgedrag.
7. `Bekijk factuur` / PDF-retrieval vanuit Moneybird via Integration API bouwen.
8. Daarna Moneybird-rubrieken en organisatiebrede Finance-KPI's verder uitbouwen, waaronder quick ratio.

## Continuïteitsregel

Bij hervatten: **ga niet terug naar het ontwerpen of opnieuw bouwen van de Moneybird-inkoopfactuursync; die werkt en heeft 380 facturen ingelezen. Hervat bij centrale Organisatie/leverancierskoppeling, daarna projectherkenning en PDF-weergave.**
