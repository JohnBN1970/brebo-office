# BREBO Office — Continuïteit Finance — 2026-09-29

Dit document is per 29 september 2026 het actuele Finance-hervatpunt en vervangt voor nieuwe ontwikkelsessies de Finance-continuïteit van 5 september 2026. Lees dit samen met `docs/BREBO_CONTINUITEIT.md`, `docs/ROADMAP.md`, de actuele branch en open PR's. Ga niet terug naar oudere Finance-architectuur of een Drupal-/providergestuurde businesslogica.

## Update 29 september 2026 — architectuurcontinuiteit

De functionele Finance-continuiteit hieronder blijft leidend voor betaalcentrum, Moneybird, bank, controle en reconciliatie. De technische architectuurvolgorde is echter aangescherpt door de bewezen calculatie-ontkoppeling.

Finance volgt als volgende grote vakmodule het patroon:

~~~text
BREBO Finance-domein
-> contracten
-> infrastructuuradapters
   -> Drupal Database
   -> Moneybird
   -> ABN AMRO
   -> overige providers

BREBO Finance-domein
-> API/UI-contracten
-> Finance-cockpit / betaalcentrum / management / AI
~~~

Nieuwe en bestaande Finance-businesslogica hoort niet afhankelijk te zijn van Drupal-entities, controllers of provider-SDK's wanneer dit via een contract/adapter kan worden begrensd.


### Ontkoppelslag gestart — projectidentiteit

De eerste Finance-ontkoppelslag volgt dit patroon nu concreet voor de werkbegrotingsimport:

```text
WorkingBudgetImporter
-> ProjectReferenceGatewayInterface
   -> DrupalProjectReferenceGateway (tijdelijke adapter)
-> WorkingBudgetImportRepositoryInterface
   -> DatabaseWorkingBudgetImportRepository
```

Daarmee kent de Finance-domeinservice geen Drupal node/entity meer. De database-repository is weer uitsluitend verantwoordelijk voor Finance/calculatie-data. Zodra projectopslag buiten Drupal canoniek is, wordt alleen de projectadapter vervangen; de Finance-businesslogica blijft staan.


Dezelfde grens wordt nu ook gebruikt door de Finance operating layer voor projecttoegang. Controllers hoeven daardoor geen Drupal node meer te laden om projectbestaan of view-toegang vast te stellen; die kennis blijft tijdelijk opgesloten in de adapter.

Ook de projectpagina, cockpit-API en financiële ledger gebruiken nu dezelfde projectgrens. Projectlabel, bestaan en toegang zijn daarmee uit deze Finance-controllers gehaald; alleen de tijdelijke adapter kent de Drupal-node.

De eerdere programmaformulering "Finance afronden -> module-audit -> Calculatie later opnieuw oppakken" is technisch achterhaald: Calculatie heeft inmiddels de referentiearchitectuur geleverd. Finance wordt nu functioneel verder gesloten én gecontroleerd naar hetzelfde kern-/adapterpatroon gebracht. Bestaande betalings-, four-eyes-, reconciliatie- en fail-closedregels blijven daarbij onaangetast.

## Huidige prioriteit

De vastgestelde programma-volgorde is:

```text
Finance afronden
-> module-audit
-> Documents
-> generieke rapport-/outputgenerator
-> toolbox (o.a. MJOP, ventilatie, windbelasting, glas)
```

Finance wordt dus eerst operationeel afgerond. De calculatiemodule is nadrukkelijk nog niet gereed, maar wordt pas na Finance in de module-audit opnieuw opgepakt.

## Leidende Finance-architectuur

BREBO Office is de operationele controle- en beslislaag. Moneybird blijft de boekhoudkundige bronadministratie. ABN AMRO is de uitvoerende bank.

```text
BREBO Office
  -> controle / match / controller / autorisatie
  -> Moneybird boeking en readback
  -> betaalvoorstel / betaalrun
  -> ABN AMRO autorisatie en uitvoering
  -> bankstatus en bankmutatie terug
  -> Moneybird betalingskoppeling
  -> BREBO reconciliatie
```

Een gebruiker hoort dezelfde inhoud niet nogmaals handmatig in Moneybird te controleren. BREBO moet na verzending automatisch teruglezen wat Moneybird heeft opgeslagen en verschillen als uitzondering tonen.

## Finance A-H — gerealiseerde kern

De Finance completion-roadmap A-H is technisch grotendeels gebouwd:

- A: werkbegroting en commitments;
- B: inkoopfactuur capture/codering;
- C: read-only inkoopfactuurcontrole;
- D: performance, three-way match en betaalvrijgave;
- E: payables work queues;
- F: facturering en debiteurenwerkbank;
- G: terugkerende Moneybird receivables reconciliation en monitoring;
- H: gecontroleerde projectresultaatafsluiting.

Daarna zijn onder meer toegevoegd:

- Finance command center/dashboard;
- Nederlandse statussen en stoplichten;
- bedrijfsgezondheid, bank, vaste kosten en break-even;
- Moneybird sync health;
- digitale controllerfundering;
- payment batches;
- vier-ogen vrijgave;
- SEPA fallbackfundering;
- ABN AMRO payment/insight-adapters;
- banktransactie-reconciliatie;
- Moneybird betalingsbewijs/readback;
- zichtbaar Betaalcentrum;
- selecteerbare betaalbatchregels via Drupal tableselect.

## Productiestand 5 september 2026

De Finance command center-UI is visueel door de gebruiker positief beoordeeld. Ook het Betaalcentrum ziet er qua vorm en indeling goed uit.

Het Betaalcentrum bevat momenteel:

- `Klaar voor betaalrun`;
- uitvoerdatum;
- selecteerbare regels zodra goedgekeurde betaalvrijgaven bestaan;
- `Betaalruns`;
- controllercontrole;
- vier-ogen vrijgave;
- `ABN / Moneybird reconciliatie`;
- directe link naar ABN AMRO Internet Bankieren Zakelijk.

De huidige production branch bevat de fixes tot en met PR #576 / mergecommit `7948ae4c2eafd4b0cf354db61474183822813874` voor de Payment Center form-builder fatal. De productie-deploy na die hotfix is groen afgerond.

PR #577 is op het moment van deze continuïteitsupdate nog open. Die maakt de bestaande `LIVE CONTROL`-badge op de oude payables-werkvoorraad klikbaar. Dit is slechts een navigatiefix en niet de uiteindelijke UX-oplossing.

## Belangrijkste actuele UX-bevinding

De gebruiker heeft in productie vastgesteld dat de Finance-bediening nog onlogisch is doordat twee operationele werelden naast elkaar bestaan:

1. `Te doen · Inkoop & betaling` met facturen per processtatus;
2. `Betaalcentrum` met de daadwerkelijke betaalbatch, betaalruns en reconciliatie.

De oude `Te doen`-pagina toont bijvoorbeeld veel facturen onder `Te coderen`, terwijl `Klaar voor betaalrun` in het Betaalcentrum leeg blijft zolang nog geen goedgekeurde betaalvrijgaven bestaan. Daardoor lijkt het alsof de gebruiker facturen zou moeten kunnen aanvinken voor betaling terwijl zij inhoudelijk nog niet door de vereiste controleketen zijn gegaan.

Dit is geen reden om de controles te omzeilen. Het is een UX-/operating-layerprobleem.

## Vastgesteld nieuw UX-principe — één Betaalcentrum

**Betaalcentrum wordt de enige operationele werkplek voor inkoop en betaling.**

De gebruiker heeft deze richting expliciet bevestigd op 5 september 2026.

Bovenin het Betaalcentrum komt één werkvoorraad met procesfilters/tabs:

```text
Alle
Te coderen
Geblokkeerd
Te matchen
Vrijgave
Goedkeuren
Klaar voor betaling
```

Per factuur zijn vanuit diezelfde werkplek de relevante vervolgstappen bedienbaar. De bestaande backendcontroles blijven authoritative.

Gewenste gebruikersketen:

```text
factuur
-> project/codering
-> prestatie/controle
-> three-way match
-> betaalvrijgave aanvragen
-> onafhankelijke goedkeuring
-> Klaar voor betaling
-> checkbox
-> betaalbatch
-> controllercontrole
-> vier-ogen batchvrijgave
-> ABN AMRO
-> uitvoering/status
-> Moneybird readback
-> bank/Moneybird/BREBO reconciliatie
```

Alleen een factuur die werkelijk `Klaar voor betaling` is krijgt de batch-checkbox. Er komen dus niet simpelweg betaal-checkboxes op ongecodeerde of ongecontroleerde facturen.

De losse `Te doen · Inkoop & betaling`-wereld moet functioneel in het Betaalcentrum opgaan. Geen dubbele operationele werkplek en geen extra navigatielaag voor hetzelfde proces.

## Eerstvolgende bouwslag

Bouw nu de **Finance Operating Cockpit / geïntegreerde Betaalcentrum-werkvoorraad**.

Minimaal:

1. Integreer de bestaande payables queues bovenin het Betaalcentrum.
2. Voeg statusfilters/tabs toe: Alle, Te coderen, Geblokkeerd, Te matchen, Vrijgave, Goedkeuren, Klaar voor betaling.
3. Toon per factuur de relevante operationele actie zonder de authoritative managers te dupliceren.
4. Houd batchselectie uitsluitend beschikbaar voor goedgekeurde betaalvrijgaven.
5. Laat `Klaar voor betaling` automatisch de selecteerbare batchlijst voeden.
6. Houd betaalruns, controllercontrole, vier-ogen en reconciliatie op dezelfde werkplek.
7. Verwijder/neutraliseer dubbele navigatie zodra de geïntegreerde werkplek bewezen werkt.
8. Voeg operator-acceptancetests en permissietests toe.
9. Merge/deploy pas na groene Finance acceptance en PHP quality gate.
10. Laat de gebruiker de productieflow visueel en functioneel accepteren.

## Nog niet gereed verklaren

Finance is nog niet 100% klaar. Voor definitieve afronding blijven naast de geïntegreerde operating cockpit onder meer belangrijk:

- vaste-kostenclassificatie en de nog niet geclassificeerde Moneybird-grootboekrekeningen bedienbaar maken;
- bankrollen expliciet vastleggen, inclusief G-rekening;
- 30/60/90 liquiditeitsbeeld;
- verouderde forecasts actiegericht kunnen herberekenen en onderscheid maken tussen ontbrekende baseline en stale forecast;
- automatische Moneybird readback/driftcontrole waar nog niet gesloten;
- gewone/directe ABN-bankmutaties als gecontroleerde inbox verwerken;
- ABN productiebetaling pas activeren nadat OAuth2 client credentials, mTLS, endpoints, requestprofiel, statuspolling en uncertain-submit/idempotency aantoonbaar veilig zijn;
- payment storage/schema lifecycle hardenen;
- SEPA exact-decimal/XML-profiel hardenen;
- reconciliatie sign semantics en duplicate-race hardenen;
- Moneybird invoice readback efficiënter/direct per factuur maken;
- ontvanger-IBAN tegen het gedocumenteerde Moneybird `bank_account`-veld blijven verifiëren.

## Vaste veiligheidsregels

- Geen leveranciersbetaling zonder goedgekeurde betaalvrijgave.
- Four-eyes blijft verplicht op batchniveau.
- AI mag harde controles nooit overrulen.
- Wijziging van ontvanger/IBAN maakt een eerdere betaalcontrole ongeldig.
- G-rekening is een aparte gecontroleerde betaalinstructie.
- Een vrijgegeven batch is immutable.
- `submitted` betekent niet `executed`.
- Iedere bankmutatie krijgt een aantoonbare controle-/reconciliatiestatus.
- Groen is pas groen wanneer de relevante BREBO-, bank- en Moneybird-bewijzen sluiten.

## Hervatregel

Bij hervatten van Finance: **niet opnieuw het Betaalcentrum ontwerpen en niet terug naar een losse `Te doen`-werkplek. Bouw de bestaande payables-statussen en acties in het Betaalcentrum tot één doorlopende operationele werkplek.**

Pas wanneer deze flow in productie door een normale geautoriseerde gebruiker van factuur tot betaalbatch en reconciliatie bedienbaar is, volgt de Finance-eindaudit. Daarna pas de organisatiebrede module-audit.

De financiële afsluiting en euro-trace gebruiken eveneens de gedeelde projectgrens; ook daar worden Drupal-projectnodes niet meer rechtstreeks door Finance-controllers geladen.

Ook muterende projectfase-overgangen zijn achter een `ProjectLifecycleGatewayInterface` geplaatst. Finance bewaakt gates, closeout en audit; de tijdelijke adapter is als enige verantwoordelijk voor het lezen/schrijven van de huidige Drupal-projectstatus.

De payables-werkvoorraad en het geïntegreerde Betaalcentrum gebruiken projecttoegang en projectlabels nu via dezelfde projectgrens. De queue-builder kent geen Drupal entities of `AccountInterface` meer; controllers geven alleen de actor-UID door.

Het organisatiebrede FinancialCommandCenter haalt de voor een actor zichtbare projecten en projectlabels nu via `ProjectReferenceGatewayInterface`. De service bevat daardoor geen Drupal entity manager of NodeInterface meer; dashboardaggregaties en Finance-beslislogica blijven ongewijzigd.

De bronneutrale inkoopfactuur-intake valideert projectkoppelingen nu via `ProjectReferenceGatewayInterface`; de intake-service zelf kent geen Drupal projectnodes of entity manager meer. Daarmee blijft ook document/intake-routing bruikbaar wanneer projectopslag later buiten Drupal komt te liggen.
