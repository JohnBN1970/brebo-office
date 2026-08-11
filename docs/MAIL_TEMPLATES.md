# BREBO Office – AI-mailsjablonen

Status: vastgesteld ontwikkelprincipe

## Verdeling van verantwoordelijkheden

```text
BREBO-dossier/context
  -> gecontroleerd BREBO-sjabloon
  -> AI schrijft variabele conceptinhoud
  -> ontbrekende informatie / aannames zichtbaar
  -> menselijke controle en formele vrijgave
  -> Mime Mail rendert HTML/MIME
  -> SMTP transporteert
  -> verzending blijft brebo_communication
```

AI is schrijver/redacteur, niet de eigenaar van vaste organisatie- of juridische tekst. Het sjabloon bepaalt doelgroep, verplichte inhoudsblokken, vaste regels en footer. Mime Mail bepaalt de presentatie. SMTP is alleen transport.

## Eerste sjablooncatalogus

- bewoner – start werkzaamheden;
- bewoner – planningwijziging;
- bewoner – afspraakverzoek;
- reactie op klacht;
- oplevering;
- garantie/nazorg;
- leverancier – offerte/inkoop;
- leverancier – planning;
- algemeen organisatiebericht.

## AI-contract

De AI-laag ontvangt alleen expliciet geselecteerde context en een gecontroleerd sjabloon. De output blijft een concept en bevat minimaal onderwerp, berichttekst, ontbrekende informatie en aannames. `requires_human_review` is altijd waar.

AI mag niet zelfstandig:

- onbewezen feiten toevoegen;
- financiële, contractuele, technische of planningstoezeggingen doen zonder bron of mandaat;
- juridische of garantievoorwaarden vrij herschrijven;
- onzekerheid verbergen;
- een bericht vrijgeven of verzenden.

## Organisatiemail

Een organisatiemail is geen apart waarheidssysteem. BREBO Office maakt per geselecteerde ontvanger een herleidbaar `brebo_communication`-concept. Doelgroepselectie, toestemming en ontvangerbeheer blijven afzonderlijke gecontroleerde stappen. Zo blijft ook groepscommunicatie volledig in het centrale dossier traceerbaar.

## Vaste footer

De eerste catalogus gebruikt een gecontroleerde BREBO-footer met `info@brebobv.nl`. Huisstijl, logo, HTML-layout en eventuele juridische standaardblokken worden in de volgende presentatielaag verder uitgewerkt zonder de AI vrij spel te geven over beschermde tekst.
