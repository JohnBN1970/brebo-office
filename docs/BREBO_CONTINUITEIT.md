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
- annotaties kunnen pijlen, vormen, vrije markering, pins/nummers en tekst vertegenwoordigen en aan dossierobjecten worden gekoppeld;
- toegang en aanspreekpunt zijn niet afhankelijk van technische woningdetaillering;
- toegang/contact kan worden vastgelegd op project-, gebouw-, technische-zone- en woningniveau;
- effectieve toegang erft van specifiek naar algemeen: woning -> technische zone -> gebouw -> project;
- toegang kan een formele startvoorwaarde voor uitvoering zijn;
- bewoningsstatus, contactstatus, toegangsstatus en startgereedheid zijn afzonderlijke begrippen;
- leegstand geeft nooit automatisch toestemming tot betreden;
- een lege woning kan startgereed zijn zonder bewonerscontact wanneer bevoegde toegang aantoonbaar is geregeld;
- projectcockpit toont toegang/startgereedheid en leegstand afzonderlijk;
- `ZoneAccessReadiness` berekent startgereedheid voor een technische zone/cluster binnen een project;
- een zone zonder woningen in technische scope veroorzaakt niet automatisch woningregistratie: readiness wordt dan op zone/gebouw/projectniveau beoordeeld;
- wanneer een zone wel woningen bevat, wordt alleen die expliciete technische scope beoordeeld en worden bewoning en toegang afzonderlijk meegenomen;
- `WorkPackageAccessReadiness` vertaalt de technische-zone-readiness nu naar het gekoppelde `brebo_work_package`;
- werkpakketten hebben een cockpit `/node/{node}/toegang-startgereed` met percentage, aantallen, blokkades en detailregels;
- een werkpakket met ongeregelde verplichte toegang krijgt de operationele status `GEBLOKKEERD`; bij volledig geregelde toegang `GEREED`;
- de bestaande `brebo_release_gate` blijft het formele vrijgaveobject en wordt niet gedupliceerd; toegangsreadiness levert het bewijs/input voor die poort;
- de huidige koppeling van legacy `brebo_dwelling` naar de BAG-backed residence gebruikt tijdelijk het adres als bridge; dit moet bij de canonieke migratie een directe referentie worden.

## PDOK/BAG-principe

Adres- en woninginformatie wordt waar mogelijk niet handmatig overgetypt. Een opgegeven huisnummerreeks uit ondersteunde communicatie wordt eerst geïnterpreteerd en daarna tegen de officiële BAG via PDOK gevalideerd. BREBO genereert geen fictieve tussenliggende huisnummers. BAG-identiteiten zijn leidend voor deduplicatie en projectoverstijgende herkenning van dezelfde gebruikseenheid.

De keten is:

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

## Toegang en aanspreekpunt

Toegang en aanspreekpunt vormen een zelfstandige operationele laag. Algemene afspraken mogen hoger in de hiërarchie worden vastgelegd en hoeven niet per woning te worden gedupliceerd.

Voorbeeld:

```text
Project: centrale bewonersbegeleider
  -> Gebouw: sleutelbeheer via huismeester
      -> Technische zone: woningtoegang vereist
          -> Woning: specifieke afspraak met bewoner
```

De meest specifieke geldige regel geldt. Wanneer toegang voor een activiteit vereist is, wordt de effectieve toegangsstatus onderdeel van de startgereedheidscontrole en vervolgens input voor de bestaande release-gate van het werkpakket.

De technische zone bepaalt daarbij de operationele populatie. Geen woningniveau in de technische scope betekent geen kunstmatig gegenereerde woningverplichting. Bij expliciete woningscope wordt readiness uitsluitend over die woningen berekend.

## Huidige ontwikkelfase

BREBO Office consolideert het canonieke gebouw- en projectmodel en bouwt de centrale dossier- en operationele lagen daarop door. Nieuwe functionaliteit moet deze bestaande ruggengraat gebruiken en geen parallelle objectstructuren introduceren.

De actuele bewoners/service-bouwslag bevindt zich op `agent/resident-service-module` / PR #286 en is nog niet als productie-deployment beschouwd zolang deze niet via de bestaande route is beoordeeld, gemerged en gedeployd.

## Eerstvolgende technische punten

1. Toegangsreadiness als automatische beoordeling/input aansluiten op bestaande `brebo_release_gate`-records van het werkpakket, zonder menselijke formele vrijgave te omzeilen.
2. Dezelfde readiness opnemen in planning/look-ahead zodat komende werkpakketten vroegtijdig rood/oranje/groen worden gesignaleerd.
3. Directe canonieke relatie realiseren tussen technische woningscope (`brebo_dwelling`) en BAG-backed residence, zodat de tijdelijke adres-bridge verdwijnt.
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
- Deze geparkeerde controle blokkeert de functionele ontwikkeling niet.
- Productie/deploymentwijzigingen verlopen via de bestaande GitHub Actions-route; geen ad-hoc handmatige Git-merge op de server.

## Ontwikkelregel bij nieuwe chats

Een nieuwe chat is een voortzetting van dezelfde BREBO Office-ontwikkeling. Begin niet opnieuw met architectuurverkenning. Herstel eerst de actuele stand uit de hierboven genoemde bronnen en ga verder vanaf de eerstvolgende technische stap.

Bij iedere betekenisvolle bouwstap moet dit bestand daadwerkelijk worden bijgewerkt wanneer de actuele architectuur, implementatiestatus, open technische punten of eerstvolgende stap verandert. Alleen in de chat melden dat de continuïteit is bijgewerkt is niet voldoende.

## Beheer

Werk dit document bij wanneer een belangrijke mijlpaal, architectuurbesluit, merge, deploymentwijziging of wijziging van de eerstvolgende ontwikkelstap plaatsvindt.

Laatst bijgewerkt: 15 augustus 2026.
