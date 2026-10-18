# BREBO Office — Inzet v1: operationele stroom

Status: ontwerpcontract, geen productie-activatie. Historische bronimport en review blijven gescheiden van dagelijkse inzet.

## Domeingrenzen
- Planning: project, gebouw (optioneel), inzetdatum, tijdvenster, toegewezen medewerker of externe vakman, uitvoerder en geplande uren. Wijzigingen krijgen revisie en audit.
- Registratie: medewerker, project, werkdatum, begin/einde, pauze, werkzaamheden, bron (web/app/import), registrant en tijdstip. Correcties zijn nieuwe revisies, nooit stil overschrijven.
- Controle: deterministische regels voor onbekende medewerker/project, overlap, dubbele registratie, buiten planning, negatieve/ongeldige duur, ontbrekende autorisatie en afwijking geplande versus geregistreerde uren. Onbekende koppelingen worden niet automatisch geraden.
- Fiattering: concept -> ingediend -> te beoordelen -> goedgekeurd / afgewezen / correctie gevraagd. Alleen bevoegde projectrol kan goedkeuren; eigen registratie niet automatisch zelf fiatteren.
- Verwerking: uitsluitend goedgekeurde uren naar projectkosten en financiële voorbereiding; geen directe facturatie. Idempotente verwerking met unieke registratieversie.

## Beoordelingsregels
- Bronafwijkingen blijven onveranderlijk en tellen apart van openstaande beoordelingen.
- 'Onderzoeken' is geen definitieve oplossing.
- Elke statusovergang heeft actor, tijd, reden, vorige/nieuwe status en revisie.
- Werkvloer ziet eigen inzet; uitvoerder ziet toegewezen projecten; bedrijfsleiding ziet geaggregeerde KPI's.
- Gebouwkoppeling is optioneel en wordt alleen gebruikt als deze betrouwbaar is bevestigd.
- Geolocatie is niet vereist voor de eerste versie; latere geofencing alleen na AVG-toets en expliciete configuratie.

## Eerste implementatieslice
1. Repositoryinterfaces en tests voor planning, registratie en fiattering, onafhankelijk van Drupal.
2. Privé-opslag met migraties, optimistic locking en audittrail.
3. Read-only dashboard met geplande/geregistreerde/goedgekeurde uren, afwijkingen en open fiatteringen.
4. POST-routes met Office-autorisatie, CSRF, herkomstcontrole en sessie-isolatie.
5. End-to-end test op /office-test; productie pas na expliciete vrijgave.

## Acceptatie
- Invoer van 8 geplande uren en 7 geregistreerde uren toont 1 uur afwijking.
- Twee overlappende registraties worden ter beoordeling aangeboden.
- Afgewezen uren worden nooit verwerkt.
- Dubbele fiattering/verwerking produceert geen dubbele projectkosten.
- Rechten worden per rol en project afgedwongen.
- Historische import van 1.940 regels en 85 bronafwijkingen blijft ongewijzigd.
