# BREBO Office - omgevingscontract

Vastgesteld: 17 oktober 2026. Dit document definieert de ontwikkelroute; het voert geen deployment of migratie uit.

## Rollen

| Omgeving | Rol | Toegestane wijzigingen |
| --- | --- | --- |
| SBOFFICE | Grabbelton en referentie voor bestaande losgekoppelde modules | Lezen, analyseren, hergebruik identificeren. Niet als doel voor nieuwe Office-features. |
| office-test | Nieuwe geisoleerde integratie- en acceptatieomgeving | Modules integreren, testdata gebruiken, gecontroleerde testdeploy na expliciete goedkeuring. |
| office | Bestaande productie | Alleen release na groen CI, functionele acceptatie, migratie-/rollbackplan en expliciete opdracht. |

## Ontwikkelafspraken

1. Inventariseer eerst de SBOFFICE-implementatie, datamodel, tests en nog aanwezige Drupal-afhankelijkheden.
2. Hergebruik bedrijfslogica via duidelijke interfaces; geen rechtstreekse koppeling aan de oude SBOFFICE-database in productie zonder vastgesteld migratieontwerp.
3. Office-test is de enige bestemming voor integratie- en acceptatiewerk. Test-URL, routering, credentials en DB-target vooraf controleren; verwissel nooit `/office-test/` met productie.
4. Alle modules delen Office-identiteit, rollen, project-ACL, documenten en auditspoor. OnSite gebruikt een afgebakende eigen worker-API.
5. Modulevolgorde: bestaande zelfstandige onderdelen inventariseren; Inzet/OnSite en weekstaatcontrole; koppelingen Finance, calculatie, offerte, projectkosten en rapportage.
6. Release naar Office pas na geautomatiseerde tests, autorisatie-/AVG-review, datamigratieproef, back-up, rollbackplan en expliciete vrijgave van de directeur.
7. Nooit automatisch mergen naar develop of deployen naar office of SBOFFICE. De geisoleerde featurebranch is geen bewijs dat testcode live staat.

## Inzet acceptatiegrens

- Projectteam, planning, meerdere geofences, automatische OnSite IN/UIT en uitzonderingsbehandeling.
- Leveranciersweekstaat als origineel Excelbestand bewaren; alle celnotities en bronverwijzingen behouden.
- Verplichte herkenning van week/jaar, medewerker, leverancier, project en dag; onduidelijkheden in review.
- Dagelijkse vergelijking van OnSite met leveranciersuren; autorisatie en audit bij correcties/goedkeuring.
- Factuurmatching per leverancier tegen goedgekeurde uren en regietarieven.
- Kostenprognose tegen vaste aanneemsom, inclusief overige kosten.
- Geen betaling, goedkeuring of contractconclusie op basis van alleen een geofence of een Excel-notitie.

## Huidige implementatie

De XLSX-onderdelen en synthetische tests staan in de geisoleerde branch. Er is nog geen bevestigde end-to-end testdeployment, live OnSite-app-koppeling, volledig uploadproces of productie-integratie.
