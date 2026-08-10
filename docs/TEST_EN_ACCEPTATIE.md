# BREBO Office — test- en acceptatiedossier

## Testobject

De beveiligde testvoorziening voor communicatie tussen Drupal (`brebo_office_core`) en de externe Integration API / AI-laag.

## Uitgevoerde controles

| Controle | Resultaat |
| --- | --- |
| PHP-syntaxis van gewijzigde PHP-bestanden | Geslaagd |
| Drupal-configuratie, routes en service-injectie | Gecontroleerd en werkend |
| Drupal-cache-rebuild | Geslaagd |
| Witruimte- en diff-controles | Geen fouten aangetroffen |
| Fictieve end-to-end communicatietest | Geslaagd |
| HTTP-respons | `200` |
| Menselijke beoordeling | Verplicht gemarkeerd |
| Commitomvang | Uitsluitend 6 bedoelde Integration API-bestanden |

## Functioneel aangetoond

De testvoorziening kan een fictieve melding verzenden en een analyse ontvangen met elementen zoals categorie, samenvatting, urgentie, risico's, vervolgacties en een conceptantwoord. De uitkomst blijft adviserend en wordt niet automatisch als besluit verwerkt.

## Acceptatiestatus

**Geaccepteerd als technische proefvoorziening voor fictieve gegevens.**

Dit is geen vrijgave voor ongecontroleerd gebruik van echte bewoners-, project- of persoonsgegevens en geen acceptatie van een volledig geautomatiseerd bedrijfsproces.

## Voorwaarden voor de eerste echte workflow

- proceseigenaar en bevoegde beoordelaar zijn benoemd;
- doel en gegevenscategorieën zijn vastgesteld;
- autorisatie, logging en audittrail zijn getest;
- privacy- en bewaarbeleid zijn vastgesteld;
- fout-, incident- en terugvalscenario's zijn beproefd;
- acceptatiecriteria zijn vooraf vastgelegd;
- productiegebruik is expliciet vrijgegeven.

## Referentie

- Datum: 10 augustus 2026
- Branch: `develop`
- Commit: `3a405d5` — `Add secure Integration API test form`
