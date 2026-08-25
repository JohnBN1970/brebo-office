# BREBO Office-roadmap

## Doel

BREBO Office gefaseerd ontwikkelen tot een betrouwbaar digitaal bedrijfs- en gebouwmanagementsysteem waarin het gebouw de permanente informatiekern vormt, projecten tijdelijke uitvoeringseenheden zijn en informatie, bewijs, risico's, besluiten en leerpunten beheerst en herleidbaar worden vastgelegd.

## Peildatum

25 augustus 2026.

## Leidende bronnen

De roadmap volgt de volgende bronvolgorde:

1. het vastgestelde BREBO Proceshandboek;
2. het Canonical Information Model (CIM) en het domein-/objectmodel;
3. Appendix A voor vastgestelde aanvullingen en ontwikkelbesluiten;
4. de actuele Drupal-implementatie en technische documentatie.

Bij strijdigheid wordt niet vanuit de software teruggeredeneerd naar de bedrijfsnorm. De software wordt aangepast aan de vastgestelde functionele bron.

## Totale voortgang

BREBO Office is de prototypefase voorbij. Meerdere echte bedrijfsprocessen zijn operationeel of substantieel gebouwd. De actuele indicatieve voortgang is:

- circa **70% richting een breed dagelijks bruikbaar BREBO Office**;
- circa **55-60% richting de volledige eindvisie**.

Deze percentages zijn stuurinformatie en geen formele acceptatiecriteria. De eindvisie is sinds de vorige peildatum verbreed met onder meer Finance/Moneybird, Inzet, klantportaal, digitale control, Outputgenerator en verdere managementsturing.

| Fase | Actuele status | Indicatief |
| --- | --- | ---: |
| 0. Procesfundering en governance | gerealiseerd en leidend | 95% |
| 1. Technische basis en Integration API | grotendeels gereed, productie-integraties aanwezig | 90% |
| 2. Canoniek gebouw- en projectmodel | stevige ruggengraat; consolidatie resteert | 80% |
| 3. Centrale dossierlaag | breed aanwezig; verdere uniformering nodig | 75% |
| 4. Mail Intake | kern operationeel; historie en verdieping open | 85% |
| 5. Actie-, signaal- en controlemotor | meerdere controls gebouwd; centrale motor nog verbinden | 60% |
| 6. Digitale rollen | bouwstenen aanwezig; operationalisering nog niet gesloten | 45% |
| 7. Feedback en organisatorisch leren | richting en deelbouwstenen aanwezig | 30% |
| 8. Dashboards en managementsturing | Project Cockpit en financiële sturing aanwezig; portfolio/directie verder uitbouwen | 55% |

## Fase 0 — Procesfundering en governance

**Status: gerealiseerd als functionele bron**

- Proceshandboek v1.0 is beschikbaar als vastgestelde procesarchitectuur.
- Het gebouw staat centraal.
- De BREBO Lens Inzicht — Regie — Realisatie is leidend.
- Eén keer vastleggen, overal hergebruiken geldt als vast werkprincipe.
- Digitale rollen en AI ondersteunen; formele bevoegdheid en menselijke eindverantwoordelijkheid blijven bestaan.
- Appendix A bewaakt aanvullingen en ontwikkelbesluiten zonder het handboek stilzwijgend te wijzigen.

## Fase 1 — Technische basis en Integration API

**Status: grotendeels gereed**

- Beveiligde Drupal ↔ Integration API-koppeling is gerealiseerd.
- HMAC v1-authenticatie is de geldende beveiligingslaag.
- Deployment via GitHub Actions naar de SBOFFICE-runtime is ingericht.
- Cloudflare Worker / Integration API wordt gebruikt voor externe integratieketens.
- Moneybird-ketens zijn op deze integratielaag aangesloten.
- Productie-smokechecks en deploymentguards zijn onderdeel van de werkwijze.

Open aandachtspunt is het volledig canoniek maken van de runtimeconfiguratie: `sboffice` moet de enige operationele waarheid zijn en resterende legacy-runtime/configuratie moet gecontroleerd worden uitgefaseerd. Mail-runtimevariabelen/readiness moeten daarbij expliciet worden gecontroleerd.

## Fase 2 — Canoniek gebouw- en projectmodel

**Status: grotendeels gebouwd; consolidatie loopt**

De Drupal-code bevat onder meer `brebo_building`, gebouwzones, clusters, woningen/gebruiksobjecten, productposities, `brebo_project` en projectscope.

Canonieke richting:

```text
Gebouw
  -> gebouwzone / cluster
      -> woning / ruimte
          -> productpositie

Project
  -> projectscope per gebouw
      -> selectie van permanente gebouwobjecten
```

Resterende hoofdpunten:

1. historische projectrelaties uit permanente gebouwstructuren ontwarren;
2. legacy locatie-/weergavevelden beoordelen tegenover het permanente Gebouw;
3. cross-building relaties technisch blokkeren;
4. bestaande data verliesvrij migreren zonder dubbel model.

## Fase 3 — Centrale dossierlaag

**Status: substantieel aanwezig; samenhang verder uniformeren**

Bestaande bouwstenen omvatten communicatie, documenten, controles/verificaties, afwijkingen, werkpakketten, vrijgavepoorten, organisaties/contactpersonen, calculatie, werkbegroting, inkoop, contracten, facturen, bewoners/service en projectdossierfunctionaliteit.

De volgende stap is niet meer voor ieder begrip een nieuwe registratie bouwen, maar bestaande acties, signalen, risico's, besluiten, bewijs, garanties, nazorg en financiële controles via één samenhangende dossier- en control-laag verbinden.

## Fase 4 — Mail Intake en communicatie

**Status: kern operationeel; verdere migratie en verdieping open**

De centrale Mail Intake-kernketen is productiegeaccepteerd. Inkomende communicatie wordt gecontroleerd naar `brebo_communication` en beoordelings-/uitzonderingswerkbakken verwerkt zonder automatische formele dossierwaarheid.

Sinds de vorige peildatum is de Mail-laag verder uitgebouwd, waaronder mailboxprojectie en HTML-weergave. De bewezen tabs/reader/compose/linking-baseline blijft beschermd.

Open:

1. Zoho-historie (~19.000 berichten) gecontroleerd migreren, pas na readiness en expliciete vrijgave;
2. runtimeconfiguratie voor de canonieke `sboffice`-omgeving volledig aantoonbaar maken;
3. threads/bijlagen en contextverrijking verdiepen;
4. AI-concepten toevoegen zonder automatische dossierwaarheid;
5. retentie, rapportage en kwaliteitsmetingen verder uitbouwen.

## Fase 5 — Actie-, signaal- en controlemotor

**Status: van gepland naar gedeeltelijk gerealiseerd**

Er bestaan inmiddels meerdere control-services, cockpit-signalen, readiness-/release-gates, contract- en financiële controles en leveranciers-/scorecardbouwstenen. De hoofdopgave is deze niet als losse controls te laten eindigen maar te verbinden tot één centrale risicogestuurde motor.

De centrale motor bewaakt uiteindelijk onder andere deadlines, ontbrekend bewijs, afwijkingen, hold points, risico's, ontbrekende reacties, financiële uitzonderingen en fase-/vrijgavevoorwaarden met bron, eigenaar, termijn, status en afsluitbewijs.

## Fase 6 — Digitale rollen

**Status: functioneel voorbereid en gedeeltelijk gevoed door echte data**

Projectleider, Werkvoorbereider, Uitvoerder, Calculator/Inkoper en KAM-/Kwaliteitsmanager gebruiken dezelfde centrale informatiebron. De benodigde operationele bouwstenen groeien snel, maar de rollen zijn nog niet als volledige gesloten digitale collega's geoperationaliseerd.

Niveaus blijven:

1. signaleren — autonoom binnen regels en bewijsstatus;
2. voorstellen/voorbereiden — autonoom conceptueel mogelijk;
3. beslissen/extern handelen — uitsluitend binnen aantoonbaar mandaat en waar vereist met menselijke goedkeuring.

## Fase 7 — Feedback, tevredenheid en organisatorisch leren

**Status: richting vastgesteld; deelbouwstenen aanwezig**

Bewoners/service, meldingen, klachten, schade, nazorg en kwaliteitsinformatie leveren al relevante bronnen. De organisatiebrede leerkring moet nog expliciet worden gesloten:

```text
gebeurtenis / communicatie
-> reactie en ervaring
-> feedback / tevredenheid
-> oorzaak
-> verbeteractie
-> aangepaste werkwijze
-> effectmeting
```

## Fase 8 — Dashboards en managementsturing

**Status: gedeeltelijk gerealiseerd**

De Project Cockpit is een echte operationele stuurlaag met projectcontext voor onder meer planning, geld/cash, inzet, kwaliteit en risico. Projectgebonden tabs en financiële stuurinformatie zijn aanwezig. De volgende uitbreiding ligt op directie-/portfolioniveau, prognoses, organisatiebrede KPI's, faalkosten en leerpatronen.

## Dwarsdoorsnijdende productlijnen

### Calculatie

De calculatiemodule beschikt over een spreadsheetachtige hiërarchische werkbank, AJAX/autosave, subtotalen, commerciële opbouw, scenariovergelijking en een prijsbronnenfundering. Eerstvolgende functionele stap is prijsbronnen rechtstreeks vanuit calculatieregels bedienen, controleren en naar OA laten boeken met volledige herleidbaarheid.

### Finance en Moneybird

Finance is uitgegroeid tot een kernlaag met projectbegroting, inkoop, contracten, facturen, termijnschema's, btw-logica, stelpostbewaking, verkoopfactuurconcepten, gecontroleerde vrijgave en integratieketens richting Moneybird. Inkoopfacturen en leveranciers/masterdata worden verder naar één gecontroleerde keten gebracht.

### Inzet en personeelssturing

BREBO Office bevat inmiddels mobiele/PWA-bouwstenen voor inzet, projectgebonden klokregistratie, GPS/klokzones, aanwezigheid/vertrek en afwijkingsafhandeling. Dit vormt de basis van de eigen personeelsplanning- en urenregistratielaag.

### Bewoners en resident service

Bewoners/service gebruikt het canonieke gebouwmodel en ondersteunt bewonerscontext, meldingen, klachten, schade, nazorg, toegang en readiness. Formele vrijgave blijft via bewaakte gates lopen.

### Klantportaal

De veilige interne foundation voor externe projecttoegang is aanwezig in `brebo_client_portal`. BREBO Office blijft bron; alleen expliciet vrijgegeven projectinformatie mag extern worden geprojecteerd. Interne projectbesturing en veilige projectvoortgangprojectie zijn gebouwd. Publieke login/routes worden pas geopend nadat toegang, publicatiegrenzen, concurrency en security aantoonbaar gesloten zijn.

### Outputgenerator

De generieke Outputgenerator is architectonisch vastgesteld als platformvoorziening voor offertes, inkoop, projectrapportage, bewoners/service, KAM, gebouw/MJOP en management. Implementatie moet de vastgestelde scheiding tussen bronobject, outputmodel, lay-outprofiel, bijlagenpakket, snapshot en distributie behouden.

## Actuele hoofdprioriteiten

1. `sboffice` als enige canonieke runtimewaarheid bevestigen en legacy/runtimeconfiguratie opschonen.
2. Mail/Zoho-readiness aantoonbaar sluiten en daarna historische migratie gecontroleerd uitvoeren.
3. Finance/Moneybird voor leveranciers, inkoopfacturen en financiële controles verder sluiten.
4. Bestaande acties, signalen, readiness en controls verbinden tot één centrale controlemotor.
5. Digitale rollen operationaliseren bovenop betrouwbare dossier- en controldata.
6. Calculatieprijsbronnen en inkooponderbouwing sluiten.
7. Klantportaal veilig verder openen na access/publication/security-hardening.
8. Outputgenerator implementeren.
9. Management- en portfoliosturing verder uitbouwen.
10. Canonieke gebouw-/projectconsolidatie en legacy-afbouw continu bewaken.

## Actuele positie

BREBO Office heeft niet langer slechts één bewezen workflow. Er staat een groeiend geïntegreerd bedrijfsplatform met echte operationele ketens voor communicatie, calculatie, projectsturing, inzet en Finance.

De ontwikkelrichting verschuift daarom van **losse functionaliteit bouwen** naar:

```text
consolideren
-> koppelen
-> automatiseren
-> controleren
-> digitaal ondersteunen
-> managementsturing
```

Nieuwe functionaliteit mag bestaande canonieke objecten en platformvoorzieningen niet dupliceren.

## Continuïteitsregel

Belangrijke besluiten, actuele technische stand, open punten en eerstvolgende acties mogen niet uitsluitend in een chat bestaan. Zij worden duurzaam vastgelegd in de repositorydocumentatie en, wanneer functioneel relevant, in Appendix A of het wijzigingsregister.

Bij overgang naar een nieuwe chat of ontwikkelsessie wordt eerst de actuele continuïteitsdocumentatie, Appendix A, CIM, roadmap en GitHub-stand gelezen voordat nieuwe architectuur of implementatie wordt voorgesteld.
