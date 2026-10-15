# BREBO Office — modulepromotie van SBOFFICE naar OFFICE

Status: repository-inventarisatie, **geen runtime-acceptatie**. Datum: 15 oktober 2026.

## Omgevingen

- SBOFFICE = sandbox, database `u213420663_sboffice`.
- OFFICE = productie, database `u213420663_office_prod`.
- OFFICE geïsoleerde testdatabase = `u213420663_office_test`.
- Zie `docs/OFFICE_ENVIRONMENTS.md`. Nooit directe writes van sandbox naar productiedatabase.

## Aangetroffen moduleketens in SBOFFICE-broncode

| Domein | Bestaande routes / bron | Vereiste promotiecontrole |
| --- | --- | --- |
| Projecten | `brebo_office_core.projects` (`/projecten`), `project_dashboard` (`/projecten/{node}`) | Projectidentiteit, autorisatie en echte projectdata |
| Projectcockpit | `brebo_project_cockpit.overview` (`/projecten/{node}/cockpit`) | Project-ACL, voortgang en afhankelijkheden |
| Gebouwen | `brebo_office_core.buildings` (`/gebouwen`), `building_dashboard` | Canonieke gebouwidentiteit, koppeling project-gebouw |
| Gebouwwaarheid | `brebo_building_data.truth_workbench` (`/gebouwen/{node}/gebouwwaarheid`) | Lees- en revisierechten, bron-/versiebeheer |
| Gebouw-API | `/brebo-office/api/projects/{project_nid}/buildings`, `/brebo-office/api/buildings/{building_nid}/objects` | Controleer controller-level object-ACL; `access content` alleen is niet voldoende bewijs |
| Calculatie | `/calculaties`, `/calculaties/{node}` (legacy launch), aparte Node Calc | Office login, projectcontext, eenmalige tokenoverdracht, Calc-sessie, rechten |
| Offerte | `/calculaties/{node}/offerte-maken` | Vastgestelde calculatieversie, prijs-/scopegrenzen, PDF-uitvoer |
| Werkbegroting | `/calculaties/{node}/werkbegroting-maken` | Autorisatie, herleidbaarheid en versies |
| Inkoop | `/prijsaanvragen`, `/leveranciersoffertes`, `/projecten/{node}/orders` | Projectcontext, leveranciersrechten en orderproces |
| Finance | `/projecten/{node}/facturen`, termijnschema, verkoopfactuurreview en vrijgave | Financiële rollen, boekhoudintegratie en expliciete vrijgave |
| Planning | `/projecten/{node}/planning` | Projectrechten, gekoppelde inzet, actuele planning |
| Kwaliteit | `/kwaliteit`, `/rapportages/kwaliteit`, projecttekortkomingen | Scope, rechten en rapportage-uitvoer |
| CRM | `/relaties`, `/relaties/funnel` | Bron-neutrale intake, relatie-/projectkoppeling |

De routes staan in `web/modules/custom/brebo_office_core/brebo_office_core.routing.yml`, `web/modules/custom/brebo_project_cockpit/brebo_project_cockpit.routing.yml` en `web/modules/custom/brebo_building_data/brebo_building_data.routing.yml`. Route-aanwezigheid bewijst niet dat een functie werkt of zelfstandig van Drupal draait.

## Prioriteit: verticale acceptatie

1. **Project + gebouw**: verifieer sandboxdata, canonieke identiteit, permissies, broncontract en veilige read-only Office-consumptie.
2. **Project + calculatie**: sluit pas aan na geverifieerde projectcontext, browser-handoff en Calc-sessie.
3. **Calculatie + offerte + inkoop + Finance**: bewijs versie- en goedkeuringsketen zonder parallelle gegevensbron.
4. **Documenten, mail, inzet, planning en rapportage**: sluit per rol en projectcontext aan.

## Vrijgaveblokkades

- De bestaande `sboffice-deploy-develop.yml` wordt bij push naar `develop` automatisch gestart en wijzigt sandboxbestanden én sandbox-Drupal-database. De naam 'Production' in deze workflow is verouderd en moet apart worden gereconcilieerd.
- Geen nieuwe OFFICE-routes, databasedata of migraties activeren zonder autorisatie- en ketentests.
- Niet rechtstreeks de SBOFFICE-Drupal-database als nieuwe OFFICE-productiedatabase gebruiken. Gebruik een expliciet gecontroleerd API-/migratiecontract.
- De definitieve productie-releaseprocedure naar `office` is nog niet bewezen.
