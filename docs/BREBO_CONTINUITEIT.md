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
6. de actuele GitHub-stand van `develop` en open pull requests.

Verzin geen nieuwe architectuur wanneer een onderwerp al in deze bronnen is vastgesteld.

## Vaste functionele uitgangspunten

- Het gebouw staat centraal.
- Het gebouw is het permanente beheerde object en blijft over meerdere projecten bestaan.
- Een project is een tijdelijke uitvoeringseenheid rond een of meer gebouwen.
- Projectscope selecteert tijdelijk welke permanente gebouwobjecten binnen een opdracht vallen.
- BMS en CIM zijn leidend; Drupal is de technische vertaling.
- Eén keer vastleggen, overal hergebruiken.
- Persoonlijke mailboxen, WhatsApp, telefoon en losse berichten zijn aanvoerkanalen, geen blijvende primaire waarheid.
- Digitale rollen en AI mogen signaleren en voorbereiden; formele materiële besluiten blijven binnen aantoonbaar mandaat en waar vereist bij bevoegde mensen.
- Geen aannames wanneer bewijs nodig is.
- Belangrijke functionele implementatie geldt pas als duurzaam wanneer zij in GitHub staat.

## Actuele objectstructuur

De huidige code bevat onder andere:

```text
Gebouw
  -> gebouwzone / cluster
      -> woning / ruimte
          -> productpositie

Project
  -> projectscope per gebouw
      -> selectie van permanente gebouwobjecten
```

Daarnaast bestaan reeds objecten en functies voor communicatie, controles/verificaties, afwijkingen, werkpakketten, vrijgavepoorten, projectroutes/procesvereisten, organisaties/contacten en financiële/calculatieprocessen.

## Huidige ontwikkelfase

BREBO Office bevindt zich tussen fase 2 en fase 3 van de roadmap:

- fase 2: canoniek gebouw- en projectmodel consolideren;
- fase 3: centrale dossierlaag toetsen op samenhang, doublures en hiaten.

De eerstvolgende ontwikkelopgave is niet een nieuw Projectdossier bouwen, maar de bestaande structuur canoniek maken.

## Eerstvolgende technische punten

1. Historische verplichte `Cluster -> Project`-relatie veilig ontwarren van de permanente gebouwstructuur.
2. Legacy `field_brebo_location` op Project beoordelen tegenover Gebouw.
3. Relaties technisch beschermen tegen koppelingen naar objecten uit het verkeerde gebouw.
4. Bestaande data zonder verlies migreren.
5. Daarna vaststellen welke canonieke lagen voor acties, signalen, risico's, besluiten, garanties, feedback en leren reeds bestaan of nog ontbreken.
6. Vervolgens Migrerende Mail Intake realiseren op de bestaande communicatiestructuur.

## Integration API en deployment

- Worker: `brebo-integration-api`.
- Testendpoint: `https://brebo-integration-api.john-boon.workers.dev`.
- HMAC v1-beveiliging blijft ongewijzigd leidend.
- Worker healthcheck is als Drush-script gedeployd.
- De echte end-to-end healthcheck met `BREBO_SHARED_SECRET` staat geparkeerd totdat het secret veilig beschikbaar is.
- Deze geparkeerde controle blokkeert de functionele ontwikkeling niet.
- Productie/deploymentwijzigingen verlopen via de bestaande GitHub Actions-route; geen ad-hoc handmatige Git-merge op de server.

## Actuele documentatie-PR

PR #90 op branch `docs/cim-reference` bevat de actuele consolidatiedocumentatie, waaronder:

- `docs/CIM.md`;
- `docs/APPENDIX_A.md`;
- `docs/BMS_CIM_DRUPAL_ALIGNMENT.md`;
- bijgewerkte `docs/ROADMAP.md`;
- dit continuïteitsdocument;
- bijgewerkte documentindex.

Controleer bij een nieuwe sessie altijd of deze PR inmiddels is gemerged en wat de actuele `develop`-HEAD is.

## Ontwikkelregel bij nieuwe chats

Een nieuwe chat is een voortzetting van dezelfde BREBO Office-ontwikkeling. Begin niet opnieuw met architectuurverkenning. Herstel eerst de actuele stand uit de hierboven genoemde bronnen en ga verder vanaf de eerstvolgende technische stap.

## Beheer

Werk dit document bij wanneer een belangrijke mijlpaal, architectuurbesluit, merge, deploymentwijziging of wijziging van de eerstvolgende ontwikkelstap plaatsvindt.

Laatst bijgewerkt: 11 augustus 2026.
