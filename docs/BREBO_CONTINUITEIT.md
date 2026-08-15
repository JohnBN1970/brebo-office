# BREBO Office — Continuïteitsdocument

## Doel

Dit document voorkomt dat de BREBO Office-ontwikkeling bij een volle of nieuwe chat opnieuw vanaf nul moet worden opgebouwd. Het bevat de compacte actuele werkstand en verwijst naar de leidende bronnen.

Het is geen vervanging van het Proceshandboek, CIM, Appendix A, roadmap of wijzigingsregister.

## Startvolgorde voor iedere nieuwe ontwikkelsessie

Lees eerst, in deze volgorde:

1. het vastgestelde BREBO Proceshandboek;
2. `docs/APPENDIX_A.md`;
3. `docs/CIM.md`;
4. `docs/ROADMAP.md`;
5. `docs/BMS_CIM_DRUPAL_ALIGNMENT.md`;
6. dit continuïteitsdocument;
7. de actuele GitHub-stand van `develop` en open pull requests.

Verzin geen nieuwe architectuur wanneer een onderwerp al in deze bronnen is vastgesteld.

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

## Actuele objectstructuur

```text
Relatie = met wie BREBO zaken doet

Gebouw = permanente gebouwkennis
  -> BAG/PDOK-adressen en gebruikseenheden
  -> technische zone / cluster
      -> woning / ruimte indien technisch relevant
          -> productpositie / element indien technisch relevant
  -> permanente documentatie, historie en gerealiseerde kennis

Project = tijdelijke operationele sturing
  -> gebouwen en projectscope
  -> WBS / werkpakketten
  -> planning, toegang, taken, inkoop, financiën, uitvoering, kwaliteit en oplevering

Toegang & aanspreekpunt
  Project -> Gebouw -> Technische zone -> Woning
  meest specifieke geldige afspraak wint
```

Gebouw levert kennis aan het project. Het project bestuurt de uitvoering. Na oplevering vloeit gerealiseerde blijvende kennis terug naar het gebouw.

## Bewoners, woningen, toegang en service

Branch `agent/resident-service-module` / draft PR #286 bevat `brebo_resident_service`.

Vastgelegd en/of gebouwd:

- koppeling aan canoniek `brebo_building`; geen tweede gebouwmodel;
- BAG/PDOK-gevalideerde adressen/gebruiksobjecten met herkomst van adresvoorstellen;
- gebouwpagina met woningen/gebruiksobjecten en bewoners/servicecontext;
- technisch woningdetail alleen wanneer de technische zone dit vereist;
- meldingen, klachten, schade en service/nazorg aan woning/project;
- onveranderlijke foto's met afzonderlijke niet-destructieve annotatielagen;
- toegang/contact op project-, gebouw-, zone- en woningniveau;
- effectieve toegang erft woning -> zone -> gebouw -> project;
- bewoning, contact, toegang en startgereedheid zijn afzonderlijke begrippen;
- leegstand geeft nooit automatisch toestemming tot betreden;
- `ZoneAccessReadiness` berekent startgereedheid voor technische scope;
- `WorkPackageAccessReadiness` vertaalt dit naar het werkpakket;
- werkpakketten hebben een toegang/startgereed-cockpit;
- `brebo_release_gate` blijft het enige formele vrijgaveobject;
- release-gate-formulier toont automatische toegangsreadiness maar zet nooit zelfstandig `Akkoord`;
- `LookAheadAccessReadiness` gebruikt nu de bestaande `field_brebo_planned_start` van werkpakketten voor vooruitkijkende signalering;
- standaard kijkvenster is 42 dagen; alleen toekomstige werkpakketten binnen het venster worden beoordeeld;
- groen = toegang gereed of niet van toepassing; oranje = toegang nog niet gereed maar start ligt meer dan 7 dagen weg; rood = toegang nog niet gereed en start ligt binnen 7 dagen;
- look-ahead is signalering en wijzigt geen formele release-gate of planning zelfstandig;
- legacy `brebo_dwelling` -> BAG-residence koppeling via adres blijft tijdelijk en moet een directe canonieke referentie worden.

## PDOK/BAG-principe

Adresinformatie wordt waar mogelijk niet handmatig overgetypt. Een opgegeven huisnummerreeks wordt eerst geïnterpreteerd en daarna tegen BAG via PDOK gevalideerd. BREBO genereert geen fictieve tussenliggende huisnummers. BAG-identiteiten zijn leidend voor deduplicatie en projectoverstijgende herkenning.

```text
communicatie / aanvraag / notitie / import
  -> adres- of rangeherkenning
  -> PDOK/BAG-validatie
  -> scopevoorstel
  -> menselijke bevestiging waar vereist
  -> canoniek gebouw
  -> officiële adressen/gebruiksobjecten
  -> projectscope en operationele sturing
```

## Toegang, look-ahead en formele vrijgave

```text
Toegangsdata
  -> effectieve regel
  -> ZoneAccessReadiness
  -> WorkPackageAccessReadiness
  -> LookAheadAccessReadiness + geplande start
       groen / oranje / rood
  -> automatische beslisinformatie op brebo_release_gate
  -> formele menselijke poortbeoordeling
  -> uitvoering
```

Automatische readiness en formele vrijgave zijn bewust gescheiden. Het systeem berekent risico's en blokkades, maar simuleert geen menselijke goedkeuring. De bestaande release-gate blijft het auditbare besluitobject.

## Huidige ontwikkelfase

BREBO Office consolideert het canonieke gebouw- en projectmodel en bouwt centrale dossier- en operationele lagen daarop door. Nieuwe functionaliteit gebruikt deze ruggengraat en introduceert geen parallelle objectstructuren.

De bewoners/service-bouwslag bevindt zich op `agent/resident-service-module` / PR #286 en geldt niet als productie-deployment zolang deze niet via de bestaande route is beoordeeld, gemerged en gedeployd.

## Eerstvolgende technische punten

1. `LookAheadAccessReadiness` zichtbaar maken in de projectplanning/look-ahead UI met werkpakket, geplande start, dagen tot start, percentage en rood/oranje/groen signaal.
2. Automatische readiness-evidence auditbaar aan release-gate-historie vastleggen zonder de menselijke poortbeslissing te overschrijven.
3. Directe canonieke relatie realiseren tussen technische woningscope (`brebo_dwelling`) en BAG-backed residence.
4. Oude directe `access_status` op `brebo_residence` uitfaseren als primaire waarheid; `brebo_access_contact` + resolver wordt leidend.
5. UI verder uitbouwen voor gebouw-, project-, zone- en woningniveau, inclusief effectieve regel en herkomst.
6. Foto-editor voor niet-destructieve markeringen mobiel uitwerken.
7. Bewoners/service-objecten aansluiten op centrale taken, workflow, communicatie en oplever-/kwaliteitsprocessen zonder duplicatie.
8. Historische verplichte `Cluster -> Project`-relatie en legacy `field_brebo_location` binnen canonieke consolidatie beoordelen/migreren zonder dataverlies.

## Integration API en deployment

- Worker: `brebo-integration-api`.
- Testendpoint: `https://brebo-integration-api.john-boon.workers.dev`.
- HMAC v1-beveiliging blijft leidend.
- Worker healthcheck is als Drush-script gedeployd.
- Echte end-to-end healthcheck met `BREBO_SHARED_SECRET` staat geparkeerd totdat het secret veilig beschikbaar is.
- Productie/deploymentwijzigingen verlopen via de bestaande GitHub Actions-route; geen ad-hoc handmatige Git-merge op de server.

## Ontwikkelregel bij nieuwe chats

Een nieuwe chat is een voortzetting van dezelfde BREBO Office-ontwikkeling. Begin niet opnieuw met architectuurverkenning. Herstel eerst de actuele stand uit de hierboven genoemde bronnen en ga verder vanaf de eerstvolgende technische stap.

Bij iedere betekenisvolle bouwstap moet dit bestand daadwerkelijk worden bijgewerkt wanneer architectuur, implementatiestatus, open technische punten of eerstvolgende stap verandert. Alleen in de chat melden dat continuïteit is bijgewerkt is niet voldoende.

## Beheer

Werk dit document bij bij een belangrijke mijlpaal, architectuurbesluit, merge, deploymentwijziging of wijziging van de eerstvolgende ontwikkelstap.

Laatst bijgewerkt: 15 augustus 2026.
