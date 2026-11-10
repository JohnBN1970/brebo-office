# BREBO Office – gebouwgericht proces: technische gap-analyse (4 november 2026)

## Besluit en scope
Het gebouw is de blijvende gegevensdrager van het primaire proces. Marketing en Finance ondersteunen dit proces en zijn geen onderliggende gebouwmodules. Een aanvraag identificeert of creëert (eventueel voorlopig) een gebouw en wordt geclassificeerd op discipline: levering, montage, levering + montage, bouw, advies, expertise of MJOP. MJOP en expertise zijn zelfstandige diensten, geen nazorg. Een bestaande klant behoudt zijn klantidentiteit bij nieuwe aanvragen.

Dit document is een **code-steekproef en implementatieplan**, geen productieacceptatie of bevestiging dat onderstaande ketens live werken.

## Bevestigde broncode en aandachtspunten

| Keten | Bron / bestaand | Gat / vereiste controle |
|---|---|---|
| Website en CRM-aanvragen | `office-independent/public/crm-new-requests.php` toont beveiligde, read-only verkoopkansen | Gebouwidentiteit bij intake en disciplinegestuurde routing end-to-end aantonen |
| Gebouw | `office-independent/public/office-buildings.php` en `office-independent/public/office-project.php` gebruiken `office_buildings`, `office_project_buildings`; projecttab gebouw is browsergetest | Consistente repository en adresidentiteit controleren; voorlopige gebouwrecords en deduplicatie |
| Gebouwrepository | `office-independent/src/Buildings/OfficeBuildingRepository.php` gebruikt `building_name`, `street_address`, `active`, `latitude`, `longitude` | **Schema-afwijking:** actuele Office-test gebouwlijst gebruikt `name`, `address_line`, `code`; deze repository niet zonder migratie gebruiken. Verifiëren welke paden hem aanroepen |
| Taxonomie | Legacy veld `config/sync/field.storage.node.field_brebo_discipline.yml` aanwezig | Eén Office-native, versieerbare disciplinecatalogus, multi-discipline aanvraag en routering nog niet aangetoond |
| Contract | `office-independent/src/Project/OfficeProjectContractService.php` maakt geautoriseerde conceptcontracten | Geaccepteerde offerte naar opdracht, opdrachtbevestiging en idempotente projectaanmaak bewijzen |
| Werkbegroting | Historische bouwstenen in `docs/ROADMAP.md` | Versiebevriezing offerte/calculatie en budget-baseline; zonder calculatie expliciete alternatieve route |
| Uitvoering | Office OnSite, inzet, projectgegevens zijn eerder getest | Quickstart, werkpakketten, inkoop, planning, opnames, snaglijsten en oplevering als samenhangende keten |
| Finance | Finance-projecttoegang en contractservice | Start bij opdracht, autorisatie en reconciliatie bewijzen; geen ongecontroleerde financiële mutaties |
| Nacalculatie | Roadmap benoemt feedbacklus | Begroot/werkelijk en verbetermaatregelen aantoonbaar koppelen |

## Uitvoeringsvolgorde en acceptatiepoorten

1. **Inventarisatie en schema-audit**: vind alle schrijfpaden voor aanvragen, gebouwen, offertes, projecten; vergelijk de live test-schema's met repository; geen datamutaties.
2. **Aanvraagcontract**: immutable aanvraag-ID, bron/campagne, partij-ID, gebouw-ID (of provisional), discipline(s), specialisme en status. Taxonomie centraal en versieerbaar.
3. **Gebouwresolver**: bestaand gebouw hergebruiken; onzeker adres in review; geen automatische samenvoeging; audittrail.
4. **Procesrouter**: discipline-specifieke templates, expliciete bevoegdheden, parallelle werkpakketten; geen universele verplichte bouwflow.
5. **Offerte-acceptatie**: idempotente orderbevestiging, projectcreatie en overname van scope, budget-baseline, planning, inkoopvoorbereiding en Quickstart; expliciete menselijke vrijgave waar nodig.
6. **Uitvoering**: opnames, dag-/weekstaten, kwaliteit, hoeveelheden, snaglijsten, deelopleveringen en overdracht aan hetzelfde gebouw koppelen.
7. **Afsluiting**: nacalculatie, na-evaluatie en herbruikbare inzichten; klant blijft klant.
8. **Installatie**: eerst tests en PHP-lint, dan beperkte Office-test deploy met back-up, daarna functionele browseracceptatie; productie pas na aparte vrijgave.

## Veiligheids- en datagaranties
- Geen automatische SQL-migratie, gebouwmerge of wijziging van bestaande financiële transacties.
- Gebouwidentiteit, projectautorisatie en klantrechten blijven gescheiden.
- Statusovergangen zijn idempotent en gelogd; geen dubbele projecten of orders bij retries.
- Geen afhankelijkheid van Drupal als Office-domeinwaarheid.
- De architectuur wordt getoetst aan het vastgestelde BREBO Proceshandboek en CIM; dit document wijzigt die bronnen niet.
