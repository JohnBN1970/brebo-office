# BREBO Office BMS-backlog

## Uitgangspunt

Het BMS beschrijft en beheerst het bedrijfsproces. Software ondersteunt dit proces, maar vervangt geen bevoegdheid, beoordeling of formeel besluit.

## Prioriteiten

| Prioriteit | Onderwerp | Status | Acceptatie op hoofdlijnen |
| --- | --- | --- | --- |
| 1 | Eerste echte communicatie-workflow selecteren | Te besluiten | Scope, eigenaar, invoer, uitvoer en bevoegdheden zijn vastgesteld |
| 2 | Logging en herleidbaarheid | Te realiseren | Invoer, AI-uitvoer, beoordeling, actie, actor en tijdstip zijn controleerbaar vastgelegd |
| 3 | Rollen en autorisaties | Te realiseren | Alleen bevoegde rollen kunnen testen, beoordelen, goedkeuren of uitvoeren |
| 4 | Menselijke goedkeuring | Technisch principe gerealiseerd; procesmatig uitwerken | Geen AI-resultaat wordt zonder expliciete beoordeling een formele actie of vaststelling |
| 5 | Privacy en bewaarbeleid | Te besluiten | Gegevensminimalisatie, bewaartermijnen, verwijdering en toegang zijn vastgelegd |
| 6 | Acceptatie- en terugvalprocedure | Te realiseren | Testscenario's, stopcriteria, foutafhandeling en handmatige werkwijze zijn aantoonbaar beschikbaar |
| 7 | Beheer en monitoring | Te realiseren | Beschikbaarheid, fouten en afwijkingen worden gesignaleerd en opgevolgd |
| 8 | Gefaseerde uitbreiding | Later | Alleen goedgekeurde processen worden afzonderlijk aangesloten en geëvalueerd |

## Afgerond

- Veilige technische Integration API-koppeling gerealiseerd.
- Afgeschermd beheerformulier voor fictieve communicatietests gerealiseerd.
- End-to-endtest met verplichte menselijke beoordeling geslaagd.
- Technische wijziging vastgelegd in commit `3a405d5`.

## Beslispunt

Voor de eerste echte workflow moeten proceseigenaar, doel, gegevenscategorieën, beoordelaar en toegestane vervolgacties expliciet worden vastgesteld voordat productiegegevens worden gebruikt.
