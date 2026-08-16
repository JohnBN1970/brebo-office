# BREBO Office — Continuïteitsdocument

## Doel

Dit document voorkomt dat de BREBO Office-ontwikkeling bij een volle of nieuwe chat opnieuw vanaf nul moet worden opgebouwd. Het bevat de compacte actuele werkstand en verwijst naar de leidende bronnen.

Het is geen vervanging van het Proceshandboek, CIM, Appendix A, roadmap, UI Design System of wijzigingsregister.

## Startvolgorde voor iedere nieuwe ontwikkelsessie

Lees eerst, in deze volgorde:

1. het vastgestelde BREBO Proceshandboek;
2. `docs/APPENDIX_A.md`;
3. `docs/CIM.md`;
4. `docs/ROADMAP.md`;
5. `docs/BMS_CIM_DRUPAL_ALIGNMENT.md`;
6. `docs/BREBO_OFFICE_UI_DESIGN_SYSTEM.md` voor alle presentatie/UI-werkzaamheden;
7. `docs/BREBO_CALCULATIE_ARCHITECTUUR.md` voor calculatie- en parameterwerk;
8. `docs/BREBO_OUTPUTGENERATOR_ARCHITECTUUR.md` voor alle document-/rapportoutput;
9. dit continuïteitsdocument;
10. de actuele GitHub-stand van `develop` en open pull requests.

Verzin geen nieuwe architectuur of module-eigen presentatietaal wanneer een onderwerp al in deze bronnen is vastgesteld.

## Vaste functionele uitgangspunten

- Het gebouw staat centraal als permanente projectoverstijgende kaartenbak.
- Het project is het tijdelijke stuurmechanisme voor scope, tijd, geld, mensen, toegang, uitvoering en kwaliteit.
- Projectscope selecteert tijdelijk permanente gebouwobjecten.
- Technische zones bepalen welk detailniveau nodig is; woningniveau wordt alleen verlangd wanneer de technische scope dat vereist.
- BMS en CIM zijn leidend; Drupal is de technische vertaling.
- Eén keer vastleggen, overal hergebruiken.
- Communicatiekanalen zijn aanvoerkanalen, geen blijvende primaire waarheid.
- AI en digitale rollen signaleren en bereiden voor; formele materiële besluiten blijven binnen aantoonbaar mandaat.
- Geen aannames wanneer bewijs nodig is.
- Belangrijke implementatie geldt pas als duurzaam wanneer zij in GitHub staat.

## Vaste presentatiearchitectuur

- `web/themes/custom/brebo_office` is het centrale applicatietheme.
- `docs/BREBO_OFFICE_UI_DESIGN_SYSTEM.md` is leidend voor UI/UX en presentatiepatronen.
- Functionele modules leveren inhoud en gedrag; zij introduceren geen eigen parallelle visuele taal.
- Calculatie gebruikt een spreadsheetachtige hiërarchische werkplek met inklapbare hoofdgroepen/paragrafen, inline regels, sticky headers en compacte totalisatie.
- Presentatie-only migraties van grote controllers worden bij voorkeur via theme/template/preprocess/behavior uitgevoerd.

## Actuele objectstructuur

```text
Relatie = met wie BREBO zaken doet
Gebouw = permanente gebouwkennis
Project = tijdelijke operationele sturing
Technische zone/WBS = waar en waaraan uitvoering plaatsvindt
Taak/workflow = wie wanneer wat moet doen
```

Gebouw levert kennis aan het project. Het project bestuurt de uitvoering. Na oplevering vloeit gerealiseerde blijvende kennis terug naar het gebouw.

## Calculatiearchitectuur — leidend en actuele implementatiestand

`docs/BREBO_CALCULATIE_ARCHITECTUUR.md` is functioneel leidend. De implementatie staat inmiddels substantieel verder dan alleen architectuur.

Vastgelegd en/of gebouwd:

- calculatieboom: Calculatie -> Hoofdgroep -> paragrafen -> calculatieregels;
- hoofdindeling NL-SfB, STABU of Eigen; classificatie en locatie blijven afzonderlijke dimensies;
- alleen eindparagrafen bevatten regels; hogere niveaus zijn structuur- en subtotalisatieniveaus;
- normale regel combineert arbeid, materiaal, materieel, OA en overig;
- regeltypen: normaal, stelpost, optie, notitie, verdisconterend en verrekenbaar;
- versiegebonden parameters en lockstatus;
- migratie-audit voor overgang van legacy calculaties naar het nieuwe domein;
- calculatiewerkbank met spreadsheetachtige grid, AJAX, inline celbewerking en autosave;
- live herberekening van directe kostprijs en totalen;
- toevoegen, dupliceren, verplaatsen en verwijderen van regels via bewaakte mutaties;
- hoofdgroepen en paragrafen kunnen vanuit BREBO Office worden aangemaakt met behoud van legacy-identiteit zolang de migratieperiode loopt;
- centrale calculatienavigatie: `Calculatie | Structuur | Parameters | Audit`;
- structuur kan rechtstreeks vanuit de werkbank worden toegevoegd;
- hoofdgroepen en paragrafen kunnen worden ingeklapt; de persoonlijke inklapstatus wordt per calculatie onthouden;
- subtotalen op paragraaf- en hoofdgroepniveau;
- kostenuitsplitsing op structuur: arbeid, materiaal, materieel, OA, overig en directe kostprijs;
- commerciële kolommen: AK, risico, winst/marge en verkoopprijs;
- commerciële opbouw volgt de centrale `CommercialCalculator`: staartkosten sequentieel of als alternatief één enkele marge;
- vaste commerciële correctie blijft expliciet op calculatieniveau en wordt niet stil over regels verdeeld;
- commerciële scenariovergelijking `Basis | Scherp | Doel` is als niet-destructieve rekenhulp in de werkbank opgenomen; de directe kostprijs blijft daarbij ongewijzigd;
- werkbankroutes bestaan voor calculatie, structuur, parameters en audit.

Belangrijke recente implementatiepunten op `develop` zijn onder meer de werkbank, AJAX-autosave, structuur- en rijmanagers, inline structuurcreatie, inklapbare hiërarchie, subtotalen, commerciële verkoopprijskolommen en de prijsbronnenfundering.

## Prijsbronnen / inkooponderbouwing — nieuw vast werkprincipe

Een externe prijs is in BREBO Office niet alleen een bedrag of bestand maar een **prijsbron**.

Een prijsbron kan onder meer zijn:

- leveranciersofferte als PDF/document;
- e-mail waarin een leverancier rechtstreeks een prijs noemt, ook zonder bijlage;
- later eventueel een andere traceerbare externe prijsopgave.

Vaste procesketen:

```text
Calculatieregel
  -> prijs nodig / offerteaanvraag
  -> document of e-mail ontvangen
  -> bron registreren
  -> gegevens automatisch uitlezen
  -> voorstel tonen
  -> menselijke controle/goedkeuring
  -> goedgekeurde prijs naar OA
  -> bronkoppeling aan calculatieregel
  -> interne notitie / onderbouwing
  -> bron ook in projectdossier bewaren
```

Kernregels:

- AI/extractie mag nooit zelfstandig `subcontracting_unit_cost` overschrijven;
- extractie levert een voorstel; goedkeuring is een expliciete stap;
- voorgestelde en goedgekeurde OA-prijs zijn afzonderlijke gegevens;
- de originele bron blijft altijd aantoonbaar;
- herziene offerte/e-mail vervangt de oude bron niet destructief; revisie/herkomst blijft zichtbaar;
- één prijsbron kan aan één of meerdere calculatieregels worden gekoppeld;
- één calculatieregel kan meerdere prijsbronnen hebben, waarvan er één als actieve/goedgekeurde bron kan gelden;
- bronmetadata omvat waar beschikbaar leverancier, afzender, offerte-/referentienummer, datum, geldigheid, valuta, totaal, scope, voorwaarden, extractieresultaat en interne notitie;
- vanuit de calculatie moet later zichtbaar zijn waar een OA-prijs vandaan komt, bijvoorbeeld: `OA €18.750 — bron: offerte/e-mail leverancier X`;
- prijsbronverwerking moet later ook leveranciersvergelijking ondersteunen op prijs, scope, uitsluitingen, levertijd, garantie, voorwaarden en TCO/risico.

### Gebouwde prijsbronnenfundering

In `brebo_calculation.install` zijn op `develop` twee nieuwe domeintabellen toegevoegd:

- `brebo_calculation_price_source` — hoofdrecord van document/e-mail/andere traceerbare prijsbron;
- `brebo_calculation_price_source_line` — koppeling van bron naar calculatieregel inclusief extractievoorstel, goedgekeurde OA-prijs, goedkeuringsstatus en actieve-bronstatus.

Er is tevens een update hook voor bestaande installaties toegevoegd. Recente funderingscommit: `3813c2ca`.

## Eerstvolgende calculatiestap

De eerstvolgende functionele bouwslag is **prijsbronbediening in de calculatiewerkbank**.

Gewenste bediening per calculatieregel, direct naast/gerelateerd aan OA:

- zichtbaar bron/status-icoon;
- `Document toevoegen`;
- `E-mail koppelen`;
- `Prijsbronnen bekijken`;
- status zoals geen bron / ontvangen / uitgelezen / te controleren / akkoord / vervangen;
- na akkoord de goedgekeurde bronwaarde gecontroleerd naar OA boeken;
- bron en interne notitie vanuit de regel kunnen openen;
- originele document/e-mail tevens aan projectdossier koppelen.

Daarna volgen extractieservice, reviewformulier, Gmail-selectie/koppeling, documentupload, bronvergelijking en leveranciers-/inkoopanalyse.

## Generieke Outputgenerator

`docs/BREBO_OUTPUTGENERATOR_ARCHITECTUUR.md` is leidend.

De Outputgenerator is een BREBO Office-brede platformvoorziening en niet een calculatiefunctie. Functionele modules leveren brondata; de centrale generator maakt daar via versioneerbare outputmodellen documenten en rapportages van.

Vaste scheiding:

```text
Bronobject(en)   = inhoudelijke waarheid
Outputmodel      = inhoudelijk presentatierecept
Lay-outprofiel   = visuele compositie en huisstijl
Bijlagenpakket   = geselecteerde/gegenereerde bijlagen
Outputsnapshot   = vastgelegd resultaat
Distributie      = verzenden/publiceren/opslaan
```

Vastgelegd voor de Outputgenerator:

- generiek inzetbaar voor calculatie/offerte, inkoop, project, bewoners/service, KAM/oplevering, gebouw/MJOP en management;
- blokgebaseerde, herbruikbare en versioneerbare outputmodellen;
- lay-out is first-class: centrale lay-outprofielen, documentfamilies, typografie, tabellen, grafieken, foto's, voorbladen, kop/voet, page-breakregels en live pagina-preview;
- bijlagen zijn onderdeel van een formeel documentpakket en kunnen verplicht, conditioneel of optioneel zijn;
- leveranciersofferte/e-mail kan inhoudelijke bron zijn voor calculatie en later voor gegenereerde inkoop-/offertedocumenten, terwijl de originele bron herleidbaar bewaard blijft;
- voorkeursuitvoer is waar passend één integraal document met hoofddocument + bijlagen;
- integrale documenten hebben standaard doorlopende paginanummering over hoofddocument en bijlagen (`Pagina X van Y`), automatische bijlagenlijst/inhoudsopgave en PDF-bookmarks;
- externe PDF's kunnen ongewijzigd worden ingevoegd of als BREBO-heruitvoer opnieuw worden opgebouwd;
- documentpakket bewaart exacte bijlagevolgorde, bron-/versiereferenties, integratiemodus, start-/eindpagina en auditgegevens;
- pre-flight controle voorkomt ongemerkt verzenden van een incompleet formeel pakket;
- generatie en distributie blijven gescheiden; definitief vaststellen/verzenden volgt mandaat.

## Bewoners, woningen, toegang en service

Branch `agent/resident-service-module` / draft PR #286 bevat `brebo_resident_service`.

Vastgelegd en/of gebouwd:

- koppeling aan canoniek `brebo_building`; geen tweede gebouwmodel;
- BAG/PDOK-gevalideerde adressen/gebruiksobjecten;
- technisch woningdetail alleen wanneer technische zone dit vereist;
- bewoners/servicecontext, meldingen, klachten, schade en nazorg;
- onveranderlijke foto's met niet-destructieve annotatielagen;
- toegang/contact op project-, gebouw-, zone- en woningniveau;
- leegstand geeft nooit automatisch toegang;
- `ZoneAccessReadiness` -> `WorkPackageAccessReadiness` -> formele `brebo_release_gate`;
- standaard look-ahead is 42 dagen;
- look-ahead signaleert alleen en wijzigt planning of formele vrijgave niet zelfstandig.

## Huidige ontwikkelfase

BREBO Office consolideert het canonieke gebouw- en projectmodel en bouwt centrale dossier- en operationele lagen daarop door. Nieuwe functionaliteit gebruikt deze ruggengraat en introduceert geen parallelle objectstructuren.

De calculatiemodule bevindt zich niet meer alleen in de architectuurfase: er staat op `develop` een serieuze nieuwe calculatiewerkbank met hiërarchie, AJAX, mutaties, subtotalen, commerciële opbouw en scenariovergelijking. De volgende kernlaag is auditbare prijsbron-/inkooponderbouwing vanuit documenten en e-mails rechtstreeks naar gecontroleerde OA-kostprijzen.

De Outputgenerator blijft architectonisch los van calculatie en geldt als generieke document-/rapportengine inclusief lay-out, bijlagenpakketten en integrale documentcompositie.

De bewoners/service-bouwslag bevindt zich op `agent/resident-service-module` / PR #286 en geldt niet als productie-deployment zolang deze niet via de bestaande route is beoordeeld, gemerged en gedeployd.

## Eerstvolgende technische punten

1. Prijsbronbediening per calculatieregel in de werkbank bouwen.
2. Documentprijsbron-upload + extractievoorstel + review/goedkeuring + OA-boeking bouwen.
3. Gmail/e-mail zonder bijlage als volwaardige prijsbron kunnen selecteren en koppelen.
4. Prijsbronnen aan intern calculatienotitie-/auditspoor en projectdocumentatie koppelen.
5. Meerdere prijsbronnen per regel vergelijkbaar maken voor inkoopkeuze en TCO/risicobeoordeling.
6. Calculatiewerkbank verder afronden met volgorde/drag-and-drop, zoeken, filteren, kopiëren/import en bronbibliotheken.
7. Generieke Outputgenerator implementeren vanuit `BREBO_OUTPUTGENERATOR_ARCHITECTUUR.md`.
8. Commerciële funnel verder migreren naar de centrale presentatielaag.
9. Automatische readiness-evidence auditbaar aan release-gate-historie vastleggen.
10. Look-ahead verbreden naar generiek readinessmodel.
11. Directe canonieke relatie realiseren tussen technische woningscope en BAG-backed residence.
12. Foto-editor voor niet-destructieve markeringen mobiel uitwerken.

## Integration API en deployment

- Worker: `brebo-integration-api`.
- Testendpoint: `https://brebo-integration-api.john-boon.workers.dev`.
- HMAC v1-beveiliging blijft leidend.
- Productie/deploymentwijzigingen verlopen via de bestaande GitHub Actions-route.

## Ontwikkelregel bij nieuwe chats

Een nieuwe chat is een voortzetting van dezelfde BREBO Office-ontwikkeling. Begin niet opnieuw met architectuurverkenning. Herstel eerst de actuele stand uit de hierboven genoemde bronnen en ga verder vanaf de eerstvolgende technische stap.

Voor de calculatiemodule geldt bij hervatten expliciet: **ga niet terug naar het ontwerpen van de spreadsheetbasis; die staat. Hervat bij prijsbronbediening / document- en e-mailbronverwerking tenzij GitHub inmiddels een latere stand toont.**

Bij iedere betekenisvolle bouwstap moet dit bestand daadwerkelijk worden bijgewerkt wanneer architectuur, implementatiestatus, open technische punten of eerstvolgende stap verandert.

Bij iedere nieuwe of gewijzigde UI moet tevens worden getoetst aan `docs/BREBO_OFFICE_UI_DESIGN_SYSTEM.md`.

## Beheer

Werk dit document bij bij een belangrijke mijlpaal, architectuurbesluit, merge, deploymentwijziging of wijziging van de eerstvolgende ontwikkelstap.

Laatst bijgewerkt: 16 augustus 2026.
