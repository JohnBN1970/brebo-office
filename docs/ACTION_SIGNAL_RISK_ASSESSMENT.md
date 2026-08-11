# BREBO Office — beoordeling centrale actie-, signaal- en risicolaag

## Status

Peildatum: 11 augustus 2026. Deze beoordeling volgt op de succesvolle live CIM-objectconsistentieaudit en op `docs/BMS_CIM_DRUPAL_ALIGNMENT.md`.

## Uitkomst live CIM-audit

De live audit op `develop` is succesvol uitgevoerd tegen de actieve Drupal-runtime. De gecontroleerde dataset bevatte 1 project, 1 projectscope, 1 communicatieobject, 1 cluster, 1 woning, 24 productposities en 1 gebouwzone. Er zijn 0 relatieproblemen gevonden. De gecontroleerde gebouw-, project-, scope- en communicatieketen is daarmee geschikt als basis voor de centrale opvolglaag.

## Bestaande opvolging

BREBO Office bevat al domeinspecifieke opvolging, onder andere in communicatie, vereisten, routes, verificaties en afwijkingen. `brebo_communication` bevat bovendien vrije AI-extractievelden voor acties/termijnen en risico's/onzekerheden.

Die velden zijn nuttig als extractie- en intake-output, maar zijn geen zelfstandig beheerde objecten: zij hebben niet uit zichzelf een eigen eigenaar, lifecycle, bronrelatie, revisiegeschiedenis, gebouw-/projectcontext en overdraagbare status.

## Controle op bestaande canonieke objecten

In de actieve `config/sync` bestaan geen node bundles `brebo_action`, `brebo_signal` of `brebo_risk`. Daarmee ontbreekt de centrale objectlaag die in het CIM en de aansluitcontrole expliciet als nog te toetsen onderdeel was aangemerkt.

## Besluit

Voeg drie kleine canonieke, revisieerbare objecttypen toe en laat bestaande domeinobjecten bestaan:

- **Actie**: concrete opvolging met eigenaar, deadline, prioriteit, status, bron en resultaat/bewijs.
- **Signaal**: feitelijke waarneming of waarschuwing die eerst beoordeeld wordt en daarna eventueel leidt tot actie, risico, controle of afwijking.
- **Risico**: oorzaak, onzekere gebeurtenis, gevolg, kans, impact, beheersmaatregel, eigenaar, termijn, restrisico en status.

Alle drie kunnen verwijzen naar permanent gebouw, tijdelijk project, exact contextobject en broncommunicatie. Daarmee blijven ze bruikbaar voor zowel Gebouwendossier als tijdelijke Projectdossier-weergaven.

## Niet doen

De nieuwe objecten vervangen geen `brebo_deviation`, `brebo_verification`, `brebo_scope_requirement`, `brebo_route_item` of communicatiestatussen. Zij vormen alleen de generieke centrale opvolglaag wanneer opvolging meerdere procesdomeinen overstijgt of als duurzame dossierinformatie zelfstandig beheerd moet worden.

AI-extractievelden op communicatie blijven bronvoorstellen. Zij mogen later door de Mail Intake worden omgezet in concept-signalen/-acties/-risico's, maar formele status, eigenaar en besluiten blijven onder de geldende menselijke mandaten.
