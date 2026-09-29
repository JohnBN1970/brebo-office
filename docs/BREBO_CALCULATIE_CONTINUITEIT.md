# BREBO Office Calculatie — Continuiteit

Laatst bijgewerkt: 2026-09-29

## Doel

Dit document is het actieve hervatpunt voor BREBO Calculatie. Een nieuwe chat of ontwikkelsessie begint hier, samen met:

1. docs/BREBO_CONTINUITEIT.md;
2. docs/BREBO_CALCULATIE_ARCHITECTUUR.md;
3. de actuele GitHub-stand van brebo-office en calc;
4. open PR's en de laatste groene acceptance-runs.

Ga niet terug naar de situatie waarin Drupal, een Drupal-formulier of de Calc-interface bepaalt hoe een calculatie werkt.

## Vastgestelde hoofdarchitectuur

Calculatie is de eerste referentie-implementatie van de BREBO-softwarekern.

~~~text
BREBO calculatiedomein
  -> businessregels
  -> validatie
  -> versie/lock/audit
  -> rekenen/totaliseren
  -> recepten/deelcalculaties
  -> prijsbronnen/readiness
  -> canonieke resultaten
        |
        v
contracten
        |
        v
infrastructuuradapters
  -> Drupal Database
  -> Drupal entities waar nog nodig
  -> externe bronnen/providers

BREBO calculatiedomein
        |
        v
Workspace/API-contracten
        |
        +-> Calc-interface
        +-> Office-dashboard
        +-> Output/offerte
        +-> AI
        +-> latere apps
~~~

### Harde grens

- Domeinservices bevatten geen directe Drupal Database API.
- Domeinservices bevatten geen NodeInterface, AccountInterface, EntityTypeManagerInterface of UI-frameworkafhankelijkheid.
- Drupal/database/providerlogica hoort in Infrastructure-adapters.
- Calc is een vervangbare interface en bevat geen tweede calculatiemotor of lokale calculatiewaarheid.
- De BREBO-kern is authoritative voor state, regels, resultaten en locks.
- API-contracten zijn expliciete grenzen; terugval naar oude v1-runtimecontracten is ongewenst en wordt door CI bewaakt.
- Een framework of interface moet vervangen kunnen worden zonder de calculatiekern opnieuw te bouwen.

## Architectuurmijlpaal 29 september 2026 — PR #953

Op branch architecture/calculation-recipes-subcalculations-id-routes is de actieve calculatiekern gecontroleerd losgetrokken van directe Drupal/databasekennis.

Op de actuele branch zijn onder meer achter contracten/adapters geplaatst:

- workspace read state;
- parameters;
- rows;
- structure;
- recipes en recipe instances;
- materiaal-/prijsselectie;
- deelcalculaties;
- objectuitzonderingen;
- canonieke calculation results/KPI-input;
- normen, norm feedback en norm versioning;
- price sources;
- readiness;
- calculation context;
- draft initialization;
- version establishment/snapshot/lock;
- object-derived row writes;
- block ordering en paragraph moves;
- row/structure identity generation;
- access checks;
- workspace resource ownership;
- kozijnprijsobservatie-opslag;
- legacy migration.

GuardedLegacyMigrator kent zelf geen Drupal Database meer; transactionele verificatie zit in een Infrastructure-repository.

Bewezen gates op deze architectuurslag:

- BREBO Project Render Smoke;
- Calculation domain acceptance.

Beide waren groen vóór de continuïteitsupdate. PR #953 is draft/mergeable totdat de architectuurslag formeel wordt gemerged.

## Calc-interface — vaste scheiding

De aparte calc repo is de gebruikersinterface en niet de rekenmotor.

Actieve keten:

~~~text
Office/BREBO calculatiekern
-> Workspace v2 API
-> Calc-interface
-> command
-> Office/BREBO calculatiekern
~~~

Niet meer toegestaan:

~~~text
Drupal
-> lokale Calc calculations/calculation_versions/calculation_lines
-> React-berekeningen
~~~

Calc bewaart lokaal uitsluitend interface-/sessie-/replayinformatie waar nodig. Calculatie-ID, project-ID en actor-ID zijn verwijzingen naar de kern; calculatieresultaten komen uit Office.

De /api/workbench/v1/... routefamilie is uit de actieve Calc-runtime verwijderd. CI moet terugval naar v1 blokkeren.

## Calculatiestructuur

Vast model:

~~~text
Calculatie
-> hoofdgroep
-> paragraaf
-> gemengde blokken
   -> losse regel
   -> recept
-> onderliggende regels
-> prijs-/brontrace
-> directe kosten
-> commerciële opbouw
-> canoniek resultaat
~~~

Losse regels en recepten delen één sorteerstroom binnen paragrafen.

Regeltypen blijven onder meer:

- normaal;
- stelpost;
- optie;
- notitie;
- verdisconterend;
- verrekenbaar.

Kostendragers blijven gescheiden van regeltypen:

- arbeid;
- materiaal;
- materieel;
- onderaanneming;
- overig.

## Recepten

Recepten blijven kernfunctionaliteit.

Een geplaatst recept:

- heeft een versievaste snapshot;
- bevat parametrische regels;
- kan custom regels bevatten;
- behoudt custom regels bij herberekening;
- gebruikt gecontroleerde formule-evaluatie zonder eval();
- kan artikel-/prijsreferenties vastzetten;
- wijzigt bestaande calculaties niet stilzwijgend wanneer de centrale bibliotheek verandert.

## Deelcalculaties

Deelcalculaties zijn herbruikbare scopes binnen dezelfde financiële waarheid. Zij mogen geen parallelle calculatiewereld worden.

Ondersteund model omvat onder meer:

- selectie van regels/paragrafen;
- vermenigvuldigingsfactor;
- toepassing op concrete objecten/woningen;
- objectuitzonderingen;
- eigen totalen afgeleid uit dezelfde bron;
- versie-/lockgedrag via de centrale calculatiekern.

Voor woningtypen geldt: typecalculatie x concrete woningen, met individuele uitzonderingen waar nodig.

## Hoeveelheden

Vaste scheiding:

~~~text
Begroot
Voorbereid
Gerealiseerd
~~~

Geen van deze werelden overschrijft een andere stilzwijgend. Verschillen zijn stuurinformatie voor faalkosten, inkoop, uitvoering en normverbetering.

## Prijsbronnen en leveranciersoffertes

Prijsbronnen blijven herleidbaar naar bron en menselijke beoordeling.

Vaste principes:

- extractie is voorstel, geen automatische financiële waarheid;
- leverancier/offerte/document blijft traceerbaar;
- prijsbron en kostendrager zijn afzonderlijke begrippen;
- goedkeuring schrijft pas daarna naar de relevante kostendrager;
- leveranciersofferteherkenning, preview en visuele bewijspositie lopen via Workspace v2;
- documentherkenningslogica mag functioneel verder verbeteren zonder opnieuw een lokale Calc-waarheid te introduceren.

## Normen en leren

Normen, normfeedback en normversies zijn nu achter repositorycontracten geplaatst.

Werkelijke projectdata kan toekomstige calculaties voeden, maar:

- nooit blind;
- altijd met context;
- altijd traceerbaar;
- vervangende normversies zijn expliciet en versioneerbaar.

## Readiness en vaststellen

Een calculatieversie kan alleen worden vastgesteld wanneer de readiness-gates dit toestaan.

Vaststellen omvat:

- canonical result;
- structure;
- relevante row-data;
- content hash;
- immutable snapshot;
- lockstatus;
- actor/audit.

Een vastgestelde versie verandert nooit stilzwijgend.

## Werkbank/UI

De spreadsheetachtige werkbank blijft de primaire bediening.

UI-polijsting wordt bewust los gehouden van de kernarchitectuur. Een andere chat/ontwikkellijn mag de Calc-interface verder verbeteren zolang:

- geen businesslogica naar React verhuist;
- geen lokale calculatiewaarheid terugkomt;
- Workspace/API-contracten gerespecteerd blijven.

## Actuele functionele stand — 29 september 2026 ochtend

Sinds de architectuurslag zijn ook de volgende functionele lijnen aantoonbaar verder gesloten:

- PR #973 herstelt de historische Office-workbench-URL als compatibiliteitsroute naar de signed Calc-launch. Bestaande bookmarks en referentiecalculaties, waaronder node 41 / testcalculatie 001, blijven bereikbaar zonder calculatiewaarheid terug naar Drupal te brengen.
- PR #974 maakt de geometrische uittrekstaat semantisch zuiver: breedte, hoogte, zijden, oppervlak en omtrek zijn per element; `quantity` / `element_quantity` blijven afzonderlijke vermenigvuldigingsvariabelen.
- Bestaande take-off-rijen worden via update hook herberekend vanuit de canonieke BxH-afmetingen, zodat oude reeds met aantal vermenigvuldigde geometrie geen dubbele verbruiken veroorzaakt.
- `element_quantity` volgt bij herberekening altijd de actuele recepthoeveelheid en kan daardoor niet stilzwijgend afwijken van `quantity`.
- In de aparte Calc-repo zijn de productielijnen verder doorgetrokken van uittrekstaat/recept naar traceerbare calculatieregels, netto/bruto/verlies, praktische zaagoptimalisatie, materiaalconversie, plaatnesting, materiaal-kostprijs en reproduceerbare Calc -> Office execution handoff.
- De eerstvolgende Calc-prijsstap is deterministische artikelprijsselectie op calculatiedatum, geldigheid, leverancier en bron; bij gelijkwaardige geldige kandidaten wordt niet gegokt maar expliciete keuze vereist.

Vaste hoeveelhedenregel:

~~~text
bronmaat / geometrie per element
x aantal elementen
x aantal arbeidsgangen / toepassingsfactor
+ expliciet verlies
-> bruto materiaal-/arbeidsbehoefte
-> verpakkings-/zaag-/plaatoptimalisatie
-> herleidbare kostprijs
~~~

## Nog open — functioneel

De architectuurscheiding betekent niet dat Calculatie functioneel klaar is.

Belangrijke open lijnen:

1. werkbank verder afmaken zonder kernlogica naar de UI te verplaatsen;
2. prijsbronnen/offertes/artikelkeuze verder operatorvriendelijk maken;
3. deelcalculaties/woningtypen volledig door de gebruikersflow sluiten;
4. parameters/commerciële opbouw en regelafhankelijke norm/urenbediening verder verfijnen;
5. kolommen vrij instelbaar maken waar functioneel nodig;
6. documentherkenning en bewijspositie verder verbeteren;
7. offerte/output via generieke Outputgenerator sluiten;
8. hoeveelheden-/productiemotor voor begroot/voorbereid/gerealiseerd uitbouwen;
9. AI-assistentie bovenop de kern toevoegen, nooit als tweede waarheid;
10. legacy-tabellen en compatibilitylagen pas fysiek verwijderen nadat rollback/migratiebewijs dit veilig maakt.

## Organisatiebrede vervolgroute

Calculatie is nu het patroon voor de rest van BREBO Office.

Volgorde:

~~~text
Calculatie-architectuur formeel afronden
-> kern-/adaptergrens permanent borgen
-> Finance volgens hetzelfde patroon auditen en ontkoppelen
-> Projecten volgens hetzelfde patroon auditen en ontkoppelen
-> Inzet/overige vakmodules laten volgen waar nodig
~~~

Niet opnieuw per module een andere architectuur uitvinden.

## Hervatregel

Bij hervatten van Calculatie:

1. controleer eerst de actuele head van PR #953 en de laatste acceptance-runs;
2. indien #953 nog niet gemerged is: eerst architectuurslag schoon afronden;
3. indien gemerged: behoud de contract/adaptergrens en pak functionele werkbank-/prijsbron-/deelcalculatie-/outputtaken;
4. raak UI-werk uit andere ontwikkelchats niet onnodig aan;
5. introduceer geen nieuwe directe Drupal Database- of frameworkafhankelijkheid in domeinservices;
6. introduceer geen lokale Calc-calculatiewaarheid.

Kwaliteit, reproduceerbaarheid en faalkostenpreventie gaan voor snelheid.
