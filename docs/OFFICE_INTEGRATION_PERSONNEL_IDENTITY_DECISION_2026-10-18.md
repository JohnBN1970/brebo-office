# Gate A — personeelsidentiteit en projecttoewijzing: concrete codebevindingen

Datum: 18 oktober 2026. Status: analyse; geen migratie of schrijfroute.

## Vastgestelde broncode

1. In PR #828 gebruikt `web/modules/custom/brebo_inzet/src/Controller/ProjectInzetHubController.php` voor het **projectteam** `field_brebo_project_team` op de projectnode, en voor de **weekplanning** nodes van type `brebo_personnel_assignment` met `field_brebo_plan_user` (Drupal-gebruiker) en `field_brebo_project_ref`. Dit zijn dus twee verschillende concepten: teamlidmaatschap en gedateerde planning.
2. In PR #1407 haalt `OfficeInzetManageablePeopleRepository::listForOperator()` uitsluitend actieve medewerkers op uit `office_inzet_people` gekoppeld via `office_inzet_project_team`, beperkt tot een actief Office-account met `manage`-recht op hetzelfde project.
3. In PR #1407 verifieert `OfficeInzetShiftCommand::createForOperator()` bij iedere schrijfactie Office-projectbeheer en actieve projectteamkoppeling, waarna `OfficeInzetShiftWriter` opnieuw controleert binnen een transactie.
4. `office-independent/docs/inzet-native-planning-contract.md` eist eerst een autoritatieve Office-werknemer-/leveranciersidentiteit en projecttoewijzing.
5. `OnSiteOfficeCatalogProjection` is expliciet een projectie van geverifieerde Office-masterdata; niet een tweede personeelsmaster.

## Beslissing voor implementatie

- **Niet** automatisch Drupal `uid`, `brebo_personnel_assignment`-node-ID en `office_inzet_people.id` gelijkstellen: het zijn verschillende ID-domeinen.
- **Niet** uit `brebo_personnel_assignment` een personeelsmaster afleiden: dit zijn planningsregels; teamlidmaatschap komt uit projectteam.
- **Niet** zonder gecontroleerde bronidentiteit records in `office_inzet_people` aanmaken; een naam is geen betrouwbare unieke sleutel.
- **Wel** één expliciete, controleerbare mapping ontwerpen: canonieke personeelsidentiteit -> onafhankelijke Office-person-ID, plus afzonderlijke projectteamkoppeling en planningprojectie. Leveranciersidentiteit apart houden.
- Het importeren/projecteren van identiteit mag nooit impliciet Office-toegang of OnSite-activering geven.
- De project-ID's tussen Drupal-projecten en zelfstandige Office-projecten eerst mappen; een gelijk nummer is niet automatisch hetzelfde project.

## Open bewijspunten vóór schrijven

1. Welke huidige Office-/Drupal-tabel of service is de canonieke personeelsmaster, en welke onveranderlijke identifier is beschikbaar?
2. Welke mapping bestaat al voor Drupal-projectnode-ID -> `office_projects.id`?
3. Welke levenscyclus geldt bij uitdiensttreding, inactivatie en wijziging van projectteam?
4. Zijn testmigratie en terugdraaien verliesvrij en herhaalbaar?
5. Welke ACL geeft bevoegdheid tot bronprojectie, los van het recht om diensten te plannen?

## Eerste acceptatietests voor de uiteindelijke adapter

- Zelfde bronpersoon tweemaal projecteren -> één Office-persoon, stabiele ID.
- Twee verschillende personen met gelijke naam -> nooit samengevoegd.
- Persoon uit team verwijderd -> geen nieuwe diensten, bestaande historie behouden.
- Projectleider zonder manage-recht -> geen teammutatie of inzage in andere teams.
- Geprojecteerde medewerker -> geen automatische login- of OnSite-rechten.
- Verkeerde project-ID mapping -> weigeren, geen cross-project data.
- Herhaalbare projectie en rollback -> geen dubbele regels of verloren auditspoor.

## Gate-status

**Nog open.** Dit document voorkomt dat het teambeheer als tijdelijke tweede personeelsadministratie wordt geïmplementeerd. De eerstvolgende codewijziging moet een geteste mapping-/projectiegrens zijn, niet een los invoerformulier.
