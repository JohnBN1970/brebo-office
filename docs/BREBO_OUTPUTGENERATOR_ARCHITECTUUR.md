# BREBO Office — Generieke Outputgenerator Architectuur

## Doel

De BREBO Outputgenerator is een generieke document- en rapportengine voor heel BREBO Office. Hij is nadrukkelijk niet beperkt tot calculaties of offertes.

De generator haalt gevalideerde brondata uit bestaande BREBO-objecten en presenteert die via herbruikbare, versioneerbare outputmodellen. De generator wordt daarmee één centrale voorziening voor klantdocumenten, interne documenten, rapportages, bevestigingen, brieven en managementoutput.

## Kernscheiding

```text
Bronobject(en)       = inhoudelijke waarheid
Outputmodel          = inhoudelijk presentatierecept
Lay-outprofiel       = visuele compositie en huisstijl
Bijlagenpakket       = mee te leveren documenten/bijlagen
Outputgeneratie      = toepassen model + lay-out + bijlagen op brondata
Outputsnapshot       = vastgelegd resultaat
Distributie          = verzenden/publiceren/opslaan
```

De Outputgenerator verandert nooit de inhoudelijke brondata. Hij selecteert, ordent, formatteert en presenteert uitsluitend.

## Ondersteunde bronnen

Een outputmodel kan één of meerdere canonieke BREBO-objecten als bron gebruiken, bijvoorbeeld relatie/contactpersoon, kans/offerte, calculatie + calculatieversie, inkoop/RFQ/leverancier, project, werkpakket, gebouw, technische zone, woning/gebruiksobject, taak/workflow, inspectie/bevinding, foto/bewijs, oplevering/kwaliteit/KAM, MJOP/onderhoudsmaatregel, kosten/reserveringen/forecast en communicatie/dossierhistorie.

## Documentfamilies

De Outputgenerator ondersteunt onder andere offertes, prijsopgaven, begrotingen, inkoopbrieven, RFQ's, inkoopopdrachten, opdrachtbevestigingen, projectstartbrieven, status-/voortgangsrapporten, bewonersbrieven, kwaliteits- en opleverrapporten, inspectierapporten, MJOP's, managementsamenvattingen, jaarplannen, nacalculaties en portfolio-rapportages.

## Outputmodel

Een Outputmodel is een herbruikbaar, benoemd en versioneerbaar inhoudelijk recept. Het model bevat minimaal modelnaam, documentfamilie, bronobjecttype(n), doelgroep, taal, gekoppeld lay-outprofiel, hoofdstukken/secties, databronnen, zichtbare velden, sortering/groepering, filters, totalisaties, vaste en conditionele teksten, tabellen/grafieken/beelden, documentmetadata, outputformaat, modelstatus en versie.

## Blokkenmodel

Outputmodellen worden opgebouwd uit herbruikbare blokken, bijvoorbeeld titelblok, adres-/ontvangerblok, project-/gebouwidentiteit, managementsamenvatting, vrije/dynamische tekst, tabel, gegroepeerde tabel, totalenblok, KPI-blok, grafiek, foto/fotogalerij, fotomarkering/bewijs, planning/tijdlijn, risico-/statusblok, handtekening/goedkeuringsblok, bijlagenlijst en voorwaarden/footer.

## Lay-outprofiel

Lay-out is een first-class onderdeel van de Outputgenerator en wordt centraal beheerd. Een lay-outprofiel bepaalt minimaal paginaformaat/orientatie, marges en grids, typografie, witruimte, BREBO-kleurgebruik, voorblad/tussenbladen, kop- en voettekst, logo- en merkpositionering, tabelstijl en zebra-rijen, KPI-/managementblokken, grafiekstijl, fotokaders en onderschriften, call-outs, paginanummering, documentnummer/datum/versie, handtekeningblokken, footerteksten en page-breakregels.

Centrale lay-outfamilies zijn bijvoorbeeld Brief/correspondentie, Commercieel document, Technisch rapport, Managementrapport/magazine, MJOP/meerjarenrapport, Tabel-/begrotingsdocument en Bewonerscommunicatie.

De modelbeheer-UI krijgt live pagina-preview zodat voor publicatie zichtbaar is hoe voorblad, tabellen, foto's, pagina-overgangen, kop/voet en conditionele secties worden gerenderd.

## Bijlagen en documentpakketten

Bijlagen zijn een volwaardig onderdeel van de Outputgenerator. Vooral offertes, inkoopopdrachten, opdrachtbevestigingen en technische rapportages bestaan in de praktijk uit een hoofddocument plus één of meer bijlagen.

Een outputmodel kan daarom een standaard `Bijlagenpakket` definiëren. Dit pakket bepaalt welke bijlagen verplicht, conditioneel of optioneel zijn.

Voorbeelden bij een offerte zijn een prijsbijlage, hoeveelhedenstaat, technische omschrijving, planning, tekeningen, foto's, productbladen, stelposten-/optielijst, voorwaarden, uitsluitingen/uitgangspunten, veiligheidsdocumenten en overige projectdocumenten.

### Bronnen van bijlagen

Een bijlage kan:

- door de Outputgenerator zelf worden gegenereerd via een ander outputmodel;
- een bestaand vastgesteld document uit BREBO Office zijn;
- een extern ontvangen bestand zijn dat aan een canoniek object is gekoppeld;
- op basis van brondata worden omgezet naar een nieuwe BREBO-bijlage.

Die laatste variant is belangrijk. Een leveranciersdocument kan bijvoorbeeld als bron dienen voor een door BREBO opgebouwde kozijnstaat, hoeveelhedenstaat, productspecificatie of andere afgeleide bijlage. De nieuwe bijlage houdt altijd een herleidbare bronreferentie naar het oorspronkelijke leveranciersdocument.

### Bijlagenregels

Per bijlage wordt minimaal vastgelegd:

- bijlagetype;
- volgorde;
- verplicht / conditioneel / optioneel;
- selectieregel;
- bronobject/documentbron;
- minimale status/versie;
- extern mee te sturen ja/nee;
- opnemen in integraal document ja/nee;
- apart bestand meegeven ja/nee;
- benaming richting ontvanger;
- versie/datum tonen ja/nee.

### Integraal document en doorlopende paginanummering

De voorkeursuitvoer voor een formeel documentpakket is één **integraal document** waarin hoofddocument en geselecteerde bijlagen als één geheel worden samengesteld.

Paginanummering loopt daarbij standaard **door over het volledige pakket**. Een offerte van 6 pagina's met daarna een kozijnstaat van 8 pagina's krijgt dus pagina 1 t/m 14, niet opnieuw pagina 1 bij de bijlage.

Hetzelfde geldt voor:

- kop-/voettekst waar passend;
- documentnummer;
- pakketversie;
- inhoudsopgave/bijlagenlijst;
- PDF-bookmarks/navigatie;
- totaal aantal pagina's, bijvoorbeeld `Pagina 9 van 14`.

Een bijlage mag visueel een eigen sectiestijl of titelblad krijgen, maar blijft onderdeel van dezelfde documentpaginering zolang zij als `integraal` is gemarkeerd.

Voor externe bron-PDF's ondersteunt de generator twee modi:

1. `Ongewijzigd invoegen` — originele pagina-inhoud blijft intact; BREBO voegt alleen gecontroleerd pakketmetadata/paginanummering toe waar technisch verantwoord.
2. `BREBO-heruitvoer` — broninformatie wordt via een outputmodel opnieuw opgebouwd in BREBO-lay-out, bijvoorbeeld een leveranciersofferte die als bron dient voor een uniforme kozijnstaat.

Het originele bronbestand blijft altijd bewaard en herleidbaar, ook wanneer BREBO een nieuwe afgeleide bijlage genereert.

### Bijlagenlijst en inhoudsopgave

Het hoofddocument kan automatisch een bijlagenlijst opnemen die exact overeenkomt met het werkelijk samengestelde pakket. Bij lange pakketten kan dezelfde pakketstructuur tevens een inhoudsopgave met beginnende paginanummers genereren.

Voorbeeld:

```text
Offerte                                             pagina 1
Bijlage 1 — Prijsblad                              pagina 7
Bijlage 2 — Kozijnstaat                            pagina 10
Bijlage 3 — Planning                               pagina 18
Bijlage 4 — Algemene voorwaarden                   pagina 20
```

### Pre-flight controle

Voor definitief maken of verzenden controleert BREBO Office minimaal of alle verplichte bijlagen aanwezig zijn, de juiste versies zijn gekoppeld, vereiste statussen zijn behaald en het samengestelde pakket technisch compleet kan worden gegenereerd.

Ontbrekende verplichte bijlagen geven een blokkade of expliciete waarschuwing conform workflow en mandaat.

## Modelvarianten en conditionele inhoud

Een basisoutputmodel kan varianten kennen zoals intern/extern, compact/uitgebreid, doelgroep, taal, open/gesloten en concept/definitief. Conditionele inhoud kan secties en bijlagen activeren, bijvoorbeeld `ALS stelposten aanwezig -> voeg stelpostenbijlage toe`.

## Outputformaten

De architectuur ondersteunt minimaal PDF, DOCX waar gewenst, HTML/webweergave, e-mailbody/brieftekst, spreadsheet/export voor tabulaire output en printweergave. Niet ieder model hoeft ieder formaat te ondersteunen.

## Snapshot en audit

Een definitieve output legt minimaal vast: output-id/documentnummer, documentfamilie, bronobject-id's/versies, outputmodel + versie, lay-outprofiel + versie, gebruikte variant, selectie/filters, gegenereerd door, datum/tijd, outputformaat, bestandsreferentie/hash, status en ontvanger/distributiecontext.

Een documentpakket legt aanvullend de exacte bijlagenlijst vast inclusief volgorde, bronreferentie, bijlageversie, integratiemodus, startpagina/eindpagina en hash/referentie. Een wijziging van brondata, model, lay-out of bijlage wijzigt nooit stilzwijgend een eerder vastgesteld/verzonden pakket.

## Distributie

Generatie en distributie blijven gescheiden. Na generatie kan een outputpakket worden opgeslagen, ter controle worden aangeboden, goedgekeurd, via e-mail verzonden, in een portaal gepubliceerd of aan communicatie gekoppeld. Definitief verzenden volgt bestaande mandaten.

## Hergebruik in BREBO Office

```text
Calculatie -> Outputgenerator -> begroting/offertebijlage
Offerte -> Outputgenerator -> offerte + integraal bijlagenpakket
Inkoop -> Outputgenerator -> inkoopbrief/RFQ/opdracht + bijlagen
Project -> Outputgenerator -> status-/voortgangsrapport + bewijsbijlagen
Gebouw/MJOP -> Outputgenerator -> MJOP/managementsamenvatting + bijlagen
KAM/Oplevering -> Outputgenerator -> kwaliteits-/opleverrapport + bewijs
```

## Eerstvolgende ontwerpstap

Voor implementatie moeten minimaal worden ontworpen:

1. outputmodel-entiteit en versie;
2. lay-outprofiel en versie;
3. blokdefinitie/configuratie;
4. bronmapping/selectieregels;
5. bijlagenpakket + bijlagenregels;
6. integrale documentcomposer met doorlopende paginanummering;
7. documentpakket + pre-flight controle;
8. outputgeneratie-job;
9. outputsnapshot/documentregister;
10. format renderers;
11. goedkeurings-/distributiekoppeling;
12. modelbeheer-UI met live preview en pakketpreview.

Vastgesteld: 15 augustus 2026.
