# BREBO Office — Canonical Information Model (CIM)

## Status

Dit document is de technische referentie in de repository voor het BREBO Canonical Information Model (CIM). De formeel vastgestelde bedrijfs- en procesdocumentatie blijft leidend; deze Markdown-versie is bedoeld om ontwikkelwerk in GitHub daarop aan te laten sluiten.

## Leidende bronnen

De inhoud van dit referentiedocument is afgeleid van de vastgestelde BREBO-documentatie in de bibliotheek, in het bijzonder:

- `PA-001_BREBO_Visie_Doel_Scope_BMS_v1.0.docx`
- `PA-005_BREBO_BMS_Metamodel_v1.0.docx`
- `PA-006_BREBO_Domeinmodel_Digitale_Objectarchitectuur_v1.0.docx`
- `PA-012_BREBO_Procesreferentiemodel_v1.0.docx`
- `PA-002_BREBO_Documentstructuur_Documenthierarchie_v1.0.docx`
- `BREBO_Bedrijfshandboek_v1.0_Concept.docx`

## Vast architectuurprincipe

**Het gebouw staat centraal.**

Het gebouw is het permanente beheerde object en vormt de vaste kern van BREBO Office. Een project is een tijdelijke uitvoeringseenheid die aan een of meer gebouwen kan zijn gekoppeld.

Daarom wordt informatie niet uitsluitend als projectinformatie gemodelleerd. Waar relevant moet informatie ook aan het gebouw en de onderliggende fysieke objectstructuur gekoppeld kunnen blijven, zodat historie over meerdere projecten behouden blijft.

## Kernobjecten en relaties

De functionele hoofdlijn is:

```text
Gebouw
  -> Cluster / bouwkundige groepering
  -> Woning / ruimte / object
  -> Bouwdeel / productpositie

Gebouw
  -> Project(en)
      -> Opdracht / werkzaamheden
      -> Documenten en communicatie
      -> Acties en signalen
      -> Kwaliteitscontroles
      -> Risico's en besluiten
      -> Oplevering, garanties en nazorg
      -> Feedback en leerinformatie
```

### Gebouw

Permanent beheerd vastgoedobject. Het gebouwendossier blijft bestaan wanneer projecten worden afgesloten en groeit door de tijd heen met inspecties, werkzaamheden, documenten, kwaliteitsinformatie, garanties, klachten, feedback en onderhoudshistorie.

### Project

Tijdelijke uitvoeringseenheid. Een project beschrijft werkzaamheden en organisatie rond een opdracht en verwijst naar het gebouw of de gebouwen waarop het betrekking heeft. Het project is niet de eigenaar van de permanente gebouwinformatie.

### Onderliggende fysieke objecten

Clusters, woningen, ruimten, bouwdelen en productposities beschrijven de fysieke structuur van het gebouw. Bestaande BREBO Office-objecten zoals `brebo_cluster`, `brebo_dwelling` en `brebo_product_position` moeten waar mogelijk worden hergebruikt en niet door parallelle modellen worden gedupliceerd.

## Ontwikkelregel

Nieuwe Drupal-contentmodellen, API's, workflows en AI-functies worden afgeleid van het BMS en CIM en mogen daar niet zelfstandig een concurrerend informatiemodel naast creëren.

De ontwikkelvolgorde is daarom:

1. BMS / procesarchitectuur;
2. CIM en objectrelaties;
3. Drupal-contentmodel;
4. API's en integraties;
5. workflows;
6. AI-ondersteuning.

## Gevolg voor Projectdossier en Gebouwendossier

De centrale functionele dossierlaag is het **Gebouwendossier**. Projectdossiers zijn tijdelijke, projectgebonden doorsneden van de informatie die op of rond een gebouw ontstaat.

Voor nieuwe implementaties geldt daarom minimaal:

- een project kan aan een of meer gebouwen worden gekoppeld;
- gebouwinformatie blijft beschikbaar na afsluiting van een project;
- informatie-items, acties, signalen, controles, risico's, besluiten en feedback moeten waar relevant naar gebouw én project kunnen verwijzen;
- bestaande objectrelaties worden hergebruikt voordat nieuwe contenttypen of velden worden toegevoegd;
- technische implementatie wordt getoetst op overeenstemming met dit document en de formele bronstukken.

## Wijzigingsbeheer

Dit bestand is een repository-referentie en vervangt de formele vastgestelde BREBO-documentatie niet. Bij strijdigheid geldt de vastgestelde bedrijfs-/procesdocumentatie als bron van waarheid en moet dit bestand worden aangepast.
