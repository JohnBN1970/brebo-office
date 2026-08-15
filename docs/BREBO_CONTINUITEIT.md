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

- Het gebouw staat centraal.
- Het gebouw is het permanente beheerde object en de projectoverstijgende verzamelplaats/kaartenbak van gebouwinformatie.
- Een project is een tijdelijke uitvoeringseenheid en het stuurmechanisme voor scope, tijd, geld, mensen, toegang, uitvoering en kwaliteit rond een of meer gebouwen.
- Projectscope selecteert tijdelijk welke permanente gebouwobjecten binnen een opdracht vallen.
- Technische zones bepalen welk technisch detailniveau voor de uitvoering nodig is; woning-, ruimte- of elementinformatie wordt alleen verlangd wanneer de technische zone/scope dat vereist.
- BMS en CIM zijn leidend; Drupal is de technische vertaling.
- Eén keer vastleggen, overal hergebruiken.
- Persoonlijke mailboxen, WhatsApp, telefoon en losse berichten zijn aanvoerkanalen, geen blijvende primaire waarheid.
- Digitale rollen en AI mogen signaleren en voorbereiden; formele materiële besluiten blijven binnen aantoonbaar mandaat en waar vereist bij bevoegde mensen.
- Geen aannames wanneer bewijs nodig is.
- Belangrijke functionele implementatie geldt pas als duurzaam wanneer zij in GitHub staat.

## Actuele objectstructuur

```text
Relatie = met wie BREBO zaken doet

Gebouw = permanente projectoverstijgende gebouwkennis
  -> BAG/PDOK-adressen en gebruikseenheden
  -> gebouwzone / technische zone / cluster
      -> woning / ruimte indien technisch relevant
          -> productpositie / element indien technisch relevant
  -> permanente documentatie, historie en gerealiseerde gebouwkennis

Project = tijdelijke operationele sturing
  -> een of meer gebouwen
  -> projectscope per gebouw
      -> selectie van permanente gebouwobjecten / technische zones
  -> WBS / werkpakketten
  -> planning, toegang, taken, inkoop, financiën, uitvoering, kwaliteit en oplevering

Toegang & aanspreekpunt = zelfstandige operationele laag
  Project -> Gebouw -> Technische zone -> Woning
  meest specifieke geldige afspraak wint
```

Gebouw levert kennis aan het project. Het project bestuurt de uitvoering. Na oplevering vloeit gerealiseerde, blijvende gebouwkennis terug naar het gebouw.

## Bewoners, woningen, toegang en service

De branch `agent/resident-service-module` / draft PR #286 bevat de nieuwe module `brebo_resident_service`.

Vastgelegd en/of gebouwd:

- koppeling aan het canonieke `brebo_building`; geen tweede gebouwmodel;
- `building_nid` is de gebouwkoppeling voor woningen/gebruiksobjecten;
- vrije adres- en huisnummerranges uit communicatie kunnen als scopevoorstel worden herkend;
- PDOK/BAG valideert de werkelijk bestaande adressen/gebruiksobjecten voordat deze worden gematerialiseerd;
- broncommunicatie en herkomst van een adresvoorstel blijven bewaard;
- gebouwpagina toont woningen/gebruiksobjecten en bewoners/servicecontext;
- permanent digitaal woningdossier is beschikbaar, maar technische woninginformatie wordt alleen gebruikt wanneer de technische zone dat vereist;
- meldingen, klachten, schade en service/nazorg kunnen aan woning/project worden gekoppeld;
- foto's zijn onveranderlijke bewijsobjecten met afzonderlijke niet-destructieve annotatielagen;
- toegang en aanspreekpunt zijn niet afhankelijk van technische woningdetaillering;
- toegang/contact kan worden vastgelegd op project-, gebouw-, technische-zone- en woningniveau;
- effectieve toegang erft van specifiek naar algemeen: woning -> technische zone -> gebouw -> project;
- bewoningsstatus, contactstatus, toegangsstatus en startgereedheid zijn afzonderlijke begrippen;
- leegstand geeft nooit automatisch toestemming tot betreden;
- `ZoneAccessReadiness` berekent startgereedheid voor technische scope zonder kunstmatig woningniveau af te dwingen;
- `WorkPackageAccessReadiness` vertaalt deze beoordeling naar het gekoppelde werkpakket;
- werkpakketten hebben een toegang/startgereed-cockpit met percentage, aantallen en detailregels;
- `brebo_release_gate` blijft het enige formele vrijgaveobject;
- op het release-gate-formulier wordt nu automatisch de actuele toegangsreadiness van het gekoppelde werkpakket getoond;
- automatische readiness geeft beslisinformatie en mag een release gate nooit zelfstandig op `Akkoord` zetten;
- bij ongeregelde toegang wordt de beoordelaar expliciet gewaarschuwd dat `Akkoord` alleen met aantoonbare afwijkings-/besluitgrond passend kan zijn;
- de huidige legacy `brebo_dwelling` -> BAG-residence koppeling via adres is tijdelijk en moet een directe canonieke referentie worden.

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

## Toegang, readiness en formele vrijgave

```text
Toegangsdata
  -> effectieve regel (woning -> zone -> gebouw -> project)
  -> ZoneAccessReadiness
  -> WorkPackageAccessReadiness
  -> automatische beslisinformatie op brebo_release_gate
  -> formele menselijke poortbeoordeling
  -> planning / uitvoering
```

Automatische readiness en formele vrijgave zijn bewust gescheiden. Het systeem mag risico's en blokkades berekenen, maar simuleert geen menselijke goedkeuring. De bestaande release-gate blijft het auditbare besluitobject.

## Huidige ontwikkelfase

BREBO Office consolideert het canonieke gebouw- en projectmodel en bouwt de centrale dossier- en operationele lagen daarop door. Nieuwe functionaliteit moet deze bestaande ruggengraat gebruiken en geen parallelle objectstructuren introduceren.

De actuele bewoners/service-bouwslag bevindt zich op `agent/resident-service-module` / PR #286 en is nog niet als productie-deployment beschouwd zolang deze niet via de bestaande route is beoordeeld, gemerged en gedeployd.

## Eerstvolgende technische punten

1. Toegangsreadiness opnemen in planning/look-ahead zodat toekomstige werkpakketten vroegtijdig rood/oranje/groen worden gesignaleerd vóór de formele vrijgave.
2. Bepalen hoe automatische readiness-evidence auditbaar aan release-gate-historie wordt vastgelegd zonder de menselijke poortbeslissing te overschrijven.
3. Directe canonieke relatie realiseren tussen technische woningscope (`brebo_dwelling`) en BAG-backed residence.
4. De oude directe `access_status` op `brebo_residence` uitfaseren als primaire waarheid; `brebo_access_contact` + resolver wordt leidend.
5. UI verder uitbouwen voor gebouw-, project-, zone- en woningniveau, inclusief effectieve regel en herkomst.
6. Foto-editor voor niet-destructieve markeringen mobiel uitwerken.
7. Bewoners/service-objecten aansluiten op centrale taken, workflow, communicatie en oplever-/kwaliteitsprocessen zonder duplicatie.
8. Historische verplichte `Cluster -> Project`-relatie en legacy `field_brebo_location` blijven binnen de bredere canonieke consolidatie te beoordelen/migreren zonder dataverlies.

## Integration API en deployment

- Worker: `brebo-integration-api`.
- Testendpoint: `https://brebo-integration-api.john-boon.workers.dev`.
- HMAC v1-beveiliging blijft ongewijzigd leidend.
- Worker healthcheck is als Drush-script gedeployd.
- De echte end-to-end healthcheck met `BREBO_SHARED_SECRET` staat geparkeerd totdat het secret veilig beschikbaar is.
- Productie/deploymentwijzigingen verlopen via de bestaande GitHub Actions-route; geen ad-hoc handmatige Git-merge op de server.

## Ontwikkelregel bij nieuwe chats

Een nieuwe chat is een voortzetting van dezelfde BREBO Office-ontwikkeling. Begin niet opnieuw met architectuurverkenning. Herstel eerst de actuele stand uit de hierboven genoemde bronnen en ga verder vanaf de eerstvolgende technische stap.

Bij iedere betekenisvolle bouwstap moet dit bestand daadwerkelijk worden bijgewerkt wanneer de actuele architectuur, implementatiestatus, open technische punten of eerstvolgende stap verandert. Alleen in de chat melden dat de continuïteit is bijgewerkt is niet voldoende.

## Beheer

Werk dit document bij wanneer een belangrijke mijlpaal, architectuurbesluit, merge, deploymentwijziging of wijziging van de eerstvolgende ontwikkelstap plaatsvindt.

Laatst bijgewerkt: 15 augustus 2026.
