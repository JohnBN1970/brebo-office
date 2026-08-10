# BREBO Office-roadmap

## Doel

BREBO Office gefaseerd ontwikkelen tot een betrouwbaar digitaal bedrijfs- en projectmanagementsysteem waarin informatie wordt vastgelegd, risico's tijdig worden gesignaleerd en bevoegde medewerkers besluiten nemen.

## Status per 10 augustus 2026

### Gerealiseerd — technische basis voor AI-integratie

- De beveiligde verbinding tussen Drupal en de externe Integration API is operationeel.
- Een beheerfunctie voor uitsluitend fictieve communicatietests is beschikbaar.
- Een fictieve bewonersmelding is end-to-end verwerkt met HTTP-status `200`.
- De respons kan onder meer classificatie, samenvatting, urgentie, risico's, vervolgacties en een conceptantwoord bevatten.
- Menselijke beoordeling blijft verplicht; AI-uitvoer wordt niet automatisch als besluit of formele vaststelling verwerkt.
- Foutafhandeling en begrenzing van onjuiste, onveilige of te lange antwoorden zijn aangebracht.
- De gerealiseerde wijziging is vastgelegd in commit `3a405d5` op `develop`.

## Actuele fase

De technische koppeling is bewezen. BREBO Office bevindt zich nu in de overgang van een afgeschermde fictieve testvoorziening naar de eerste afgebakende echte bedrijfsworkflow.

## Eerstvolgende mijlpaal

Selecteer en realiseer één echte workflow met:

1. duidelijke bron en invoergrenzen;
2. logging en herleidbaarheid;
3. rollen en autorisaties;
4. verplichte menselijke beoordeling en goedkeuring;
5. bewaartermijnen en omgang met persoonsgegevens;
6. acceptatiecriteria en terugvalprocedure.

Voorkeursrichting: communicatie registreren, classificeren en als beoordeelbare vervolgactie aanbieden. De definitieve workflowkeuze vereist een afzonderlijk besluit.

## Latere fasen

- Gecontroleerde aansluiting van aanvullende BREBO-processen.
- Rapportages en signaleringen op basis van vastgelegde procesdata.
- Verdere automatisering uitsluitend waar bevoegdheden, kwaliteit en menselijke controle aantoonbaar zijn geborgd.
