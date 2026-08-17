# BREBO Office Calculatie – vaste architectuur voor recepten en calculatieblokken

Status: vastgesteld uitgangspunt voor verdere ontwikkeling van de calculatiemodule.

## Kernprincipe

Een calculatie is geen verzameling losse regels, maar een hiërarchische werkbank waarin losse calculatieregels en recepten vrij door elkaar gebruikt kunnen worden.

Vaste structuur:

Calculatie → hoofdgroep → paragraaf → gemengde blokken → recept of losse regel → onderliggende calculatieregels → materiaal-/prijsbron → kostprijs.

## Recepten

Een recept is een herbruikbaar bouwkundig calculatieblok dat uit meerdere calculatieregels bestaat.

Voorbeeld recept `Kozijn vervangen`:

- kozijn
- stelkozijn
- bevestigingsmateriaal
- klein materiaal
- flexpur
- arbeid montage
- materieel
- kitwerk

Voorbeeld recept `Afwerking`:

- hoeklijn
- lijm
- plaat-/afwerkmateriaal
- arbeid
- kitten

Recepten zijn nooit een afgesloten of star object. Binnen een calculatie mogen recepten en losse regels volledig door elkaar staan. In een recept moeten bovendien regels kunnen worden toegevoegd, verwijderd, aangepast, gedupliceerd en verplaatst.

## Parametrische hoeveelheden

Een recept heeft één of meer invoerparameters. De hoofdhoeveelheid op receptniveau voedt de onderliggende regels parametrisch.

Voorbeeld:

- aantal kozijnen = 12
- breedte = 1,20 m
- hoogte = 1,50 m
- omtrek = parametrisch berekend

Onderliggende regels kunnen vervolgens formules gebruiken, bijvoorbeeld:

- stelkozijnen = 2 × aantal
- arbeid montage = uren_per_stuk × aantal
- flexpur = omtrek × aantal × verbruik_per_meter
- kit = omtrek × aantal
- hoeklijn = omtrek × aantal

Niet iedere regel hoeft dezelfde eenheid of hoeveelheidslogica te gebruiken. Een recept is daarmee een parametrische calculatiemotor en niet alleen een sjabloon.

## Materiaal- en prijslijsten

Materiaalregels in een recept of losse calculatieregel moeten kunnen worden gekoppeld aan artikelen uit ingeladen materialen- en prijslijsten.

De scheiding is bewust:

- het recept bepaalt wat nodig is en hoeveel;
- de materialenbibliotheek bepaalt welk concreet artikel wordt gekozen;
- de actuele prijslijst levert leverancier, artikelnummer, eenheid, prijs, prijsdatum en waar beschikbaar verpakking/staffel.

Hetzelfde recept moet dus bruikbaar blijven met verschillende leveranciers, artikelen en prijsniveaus.

Prijsvergelijking tussen gelijkwaardige artikelen moet later mogelijk zijn zonder de receptlogica te wijzigen.

## Receptbibliotheek

Er komen minimaal drie niveaus:

- BREBO-standaardrecepten;
- bedrijfseigen/aangepaste recepten;
- project- of calculatiespecifieke recepten.

Recepten krijgen versiebeheer.

Wanneer een recept vanuit de bibliotheek in een calculatie wordt geplaatst, wordt een snapshot van de gebruikte receptversie in de calculatie vastgelegd. Latere wijzigingen aan het standaardrecept mogen bestaande calculaties en offertes niet stilzwijgend wijzigen.

BREBO Office mag wel signaleren dat een nieuwere receptversie beschikbaar is en de gebruiker bewust laten kiezen om bij te werken.

## Werkbankgedrag

De gewenste eindinterface is een BREBO Office-werkbank en geen klassiek Drupal-formulier.

In een paragraaf moet de gebruiker vrij kunnen werken met bijvoorbeeld:

1. losse calculatieregel;
2. recept Kozijn vervangen;
3. extra losse regel;
4. recept Afwerking;
5. aanvullende losse regel.

`+ Regel` blijft dus beschikbaar, maar recepten vormen de snelle standaardroute voor repeterende bouwkundige werkzaamheden.

Recepten moeten in de werkbank als één herkenbaar blok kunnen worden toegevoegd of gesleept, opengeklapt en op regelniveau worden aangepast.

## Nieuwe calculatie

De normale gebruiker hoeft niet meer door het volledige Drupal-aanmaakformulier.

Gewenste flow:

`+ Nieuwe calculatie` → compacte popup → naam calculatie → `Calculatie starten` → direct naar calculatiewerkbank.

BREBO Office vult op de achtergrond onder andere automatisch:

- calculatiecode;
- eerste versie;
- conceptstatus;
- prijspeildatum;
- auteur/eigenaar;
- technische systeemvelden.

Werkpakket is geen noodzakelijke tussenlaag voor het aanmaken of calculeren en hoort niet in deze primaire gebruikersflow.

Op termijn mag AI op basis van beschikbare context een passende calculatienaam voorstellen. De gebruiker blijft de naam kunnen accepteren of wijzigen.

## AI-richting

AI is een assisterende laag bovenop dit model, niet de bron van waarheid.

Toekomstige mogelijkheden:

- calculatienaam voorstellen uit relatie-, object-, project- en aanvraagcontext;
- recepten voorstellen uit bestek, opname, aanvraag of foto's;
- parameters voorstellen of afleiden;
- materiaalalternatieven signaleren;
- prijsverschillen tussen beschikbare leverancierslijsten signaleren.

De gebruiker behoudt controle over receptkeuze, parameters, materiaalkeuze en commerciële uitkomst.

## Ontwikkelregel

Nieuwe calculatiefuncties moeten dit model ondersteunen en mogen geen tweede parallel calculatiemodel introduceren.

De eerstvolgende ontwikkellijn is daarom:

1. receptdatamodel;
2. receptversies en snapshots;
3. receptparameters en formule-evaluatie;
4. koppeling receptregels aan materiaal-/prijslijsten;
5. gemengde blokken in de calculatiewerkbank;
6. compacte nieuwe-calculatie-popup;
7. verdere BREBO Office UX en AI-assistentie.

Bestaande versie-, locking-, audit- en financiële invarianten van de calculatiekern blijven leidend.