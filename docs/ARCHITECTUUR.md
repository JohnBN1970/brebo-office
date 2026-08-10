# BREBO Office — technische architectuur

## Reikwijdte

Dit document beschrijft de hoofdlijnen van de Integration API-koppeling zoals aantoonbaar gerealiseerd op 10 augustus 2026. Het is geen volledige infrastructuur- of beveiligingsaudit.

## Componenten

| Component | Functie |
| --- | --- |
| Drupal / `brebo_office_core` | Beheerinterface, invoercontrole en presentatie van het testresultaat |
| `IntegrationApiClient` | Gecontroleerde communicatie met de externe Integration API |
| Externe Integration API / AI-laag | Analyse van de aangeboden fictieve communicatie en levering van een gestructureerde conceptrespons |
| Bevoegde menselijke beoordelaar | Beoordeelt de AI-uitvoer en besluit over eventuele vervolgacties |

## Verwerkingslijn

1. Een bevoegde gebruiker voert fictieve testcommunicatie in via het beheerformulier.
2. BREBO Office valideert en begrenst de invoer.
3. De client verstuurt de aanvraag via de geconfigureerde Integration API-koppeling.
4. De externe laag retourneert een analyse en conceptvoorstellen.
5. BREBO Office controleert en presenteert de respons.
6. Een mens beoordeelt het resultaat; er volgt geen automatisch formeel besluit.

## Gerealiseerde beheersmaatregelen

- toegang via een beheerroute;
- gecontroleerde invoer en fictief testgebruik;
- veilige behandeling van verbindings- en responsfouten;
- begrenzing van onjuiste, onveilige of te lange antwoorden;
- expliciete markering dat menselijke beoordeling verplicht is;
- configuratie en gevoelige waarden worden niet in deze documentatie vastgelegd.

## Nog uit te werken vóór echte productiegegevens

- definitieve rollen- en autorisatiematrix;
- logging, audittrail en monitoring;
- classificatie van persoonsgegevens en gegevensminimalisatie;
- bewaartermijnen en verwijderprocedure;
- incident-, terugval- en herstelprocedure;
- formele acceptatiecriteria per aangesloten workflow;
- controle van geheimbeheer, transportbeveiliging en operationele configuratie.

## Technisch referentiepunt

Commit `3a405d5` op branch `develop`: `Add secure Integration API test form`.
