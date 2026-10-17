# Inzet: leveranciersweekstaat in bestaande Office-navigatie

## Bevestigde ingang
De bestaande tegel **Inzet** in `office-independent/public/office-home.php` verwijst in office-test naar `/projecten?view=inzet`. De bestaande `office-projects.php` verifieert HTTPS, host, Office-sessie en projectlidmaatschap. Geen aparte, publiek toegankelijke leveranciersroute toevoegen.

## Nog aan te sluiten (niet live)
1. Bepaal de leverancierslijst uitsluitend via een server-side geverifieerde project-/leveranciersrelatie, **expliciete koppeling van elk geïmporteerd weekbestand aan de relevante projecten**, en operatorrechten. Een leverancier op twee projecten mag niet automatisch al zijn weekbestanden zichtbaar maken. Projectlidmaatschap op zichzelf verleent **geen** recht op alle leveranciersweekstaten.
2. Importeer de originele XLSX met expliciet ISO-jaar en -week; bewaar SHA-256, bestandsnaam en bronbestand in afgeschermde opslag. Leg leverancier en week vast op de importrecord.
3. Verwerk Excel naar gedateerde regels, los medewerker/project uitsluitend op met bevestigde leveranciersspecifieke mapping en blokkeer regels met opmerkingen of afwijkingen voor review.
4. Vergelijk met **goedgekeurde** OnSite-daguren; bouw `OnSiteSupplierWeekWorkbench` en render via `OnSiteSupplierWeekReviewPresenter`. Toon alleen gegevens waarvoor de operator rechten heeft.
5. Voor een POST: eis server-side sessie, CSRF-token, rol/leveranciersmachtiging, en laad de actuele import en OnSite-gegevens opnieuw. **Accepteer nooit** `can_approve`, `reviewer_id`, leverancierrechten of bronhash uit een browserformulier als vertrouwde waarden.
6. Leg besluiten vast via `OnSiteSupplierWeekReviewService` en `OnSiteSupplierWeekReviewStore`. Bij herbeoordeling moet ook een stale-versiecontrole komen: controleer onder transactie of de actuele import, OnSite-goedkeuringen en laatste beslissing nog overeenkomen met het beoordeelde snapshot. Dit ontbreekt nog en is een blocker voor een live goedkeuringsknop.
7. Installeer tabellen uitsluitend via een expliciet bevestigde migratie op de geïsoleerde `office-test` database. Pas daarna featureflags en routes aan; nooit rechtstreeks op productie.

## Huidige grens
Presenter en businesslogica zijn geïsoleerd aanwezig. Er is nog geen geautoriseerde leverancier-week read repository, geen CSRF-beschermde formulierroute, geen stale-review-lock, geen live database-installatie en geen Finance-factuurvrijgave. Een overeenkomst in uren is **geen** factuurautorisatie.

## Isolatiecontrole (2026-10-17)
- `OnSiteSupplierWeekReadRepository` koppelt imports via `office_inzet_supplier_week_import_projects` aan toegankelijke projecten en controleert actieve operator, lidmaatschap en leverancierstoewijzing.
- `tests/office_onsite_supplier_week_read_repository_test.php` bevat synthetische integratiecases met dezelfde leverancier op twee projecten, ontoegankelijke projecten en inactieve operator.
- Nieuwe import-project-koppelingen mogen alleen worden aangemaakt na controle van de inhoud van de weekstaat. Een hele workbook aan alle projecten van een leverancier koppelen is verboden.
- Dit is een geïsoleerde ontwerp-/testwijziging; testdatabase, navigatie en productie zijn nog niet aangepast.
