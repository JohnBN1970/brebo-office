# Gate A — bron- en afhankelijkhedenaudit (18 oktober 2026)

Status: **gedeeltelijk bewezen; Gate A nog niet gesloten**. Deze audit is gebaseerd op concrete GitHub-bestanden en open PR-diffs, niet op aannames over de draaiende Hostinger-runtime.

## 1. Omgevingsgrenzen zijn al expliciet vastgelegd

Bron: `office-independent/docs/office-environment-contract.md` op PR #1407.

- `sboffice`: bestaande modulebron/referentie, geen doelomgeving voor nieuwe features.
- `office-test`: geïsoleerde integratie- en acceptatieomgeving.
- `office`: productie, alleen na CI, acceptatie, rollback en expliciete vrijgave.
- Bestaande modules delen Office-identiteit, rollen, project-ACL, documenten en auditspoor.
- **Niet automatisch mergen of deployen**; een branch bewijst geen runtimewerking.

## 2. Reeds aanwezige technische ketens — niet dupliceren

| Onderdeel | Concreet bestaand bewijs | Wat ontbreekt |
| --- | --- | --- |
| Office-test sessie-isolatie | PR #1406 wijzigt `OfficeMount`, cookie/session/login/logout/router, project- en CRM-routes en voegt isolatietests toe | PR-status en CI beoordelen, regressie tussen Office en Office-test testen |
| Calc-autorisatie | PR #1393: `WebsiteOfficeCalculationAccessPolicy.php`, ACL-migratie, smoke test | Werkelijke schema- en project-calculatiekoppeling, signed launch en browseracceptatie |
| Inzet schrijven | PR #1407: `OfficeInzetShiftCommand`, `OfficeInzetShiftWriter`, `OfficeInzetAuthenticatedShiftAction`, HTTP-routes, MySQL-integratietests | Personeelsidentiteit en teamtoewijzing, end-to-end testdeploy |
| Inzet review/OnSite | PR #1407: review repository, OnSite projection, API, tests, iOS client | Werkelijke runtimekoppeling en vrijgegeven proces; geen onbewezen live-status |
| Bestaande Drupal weekplanning | PR #828: `ProjectInzetHubController.php` gebruikt `brebo_personnel_assignment` | Vaststellen welke bestaande bron canoniek blijft en hoe zelfstandige Office die leest |

## 3. Personeelsidentiteit is de concrete blokkade

Bron: `office-independent/docs/inzet-native-planning-contract.md` op PR #1407 stelt expliciet dat de autoritatieve Office-werknemer-/leveranciersidentiteit en projecttoewijzing eerst vastgesteld moeten worden.

- `OfficeInzetSchema` stelt `office_inzet_people` (personeelsnummer, naam, actief) en `office_inzet_project_team` voor als **geïsoleerd schema**; schema is geen bewijs van canonieke masterdata.
- `OnSiteOfficeCatalogProjection` noemt zichzelf een **privileged projection** van geverifieerde Office-masterrecords, geen tweede master. Nieuwe OnSite-projecties beginnen inactief.
- PR #828 beschrijft de bestaande `brebo_personnel_assignment` als bron van weekbezetting.
- Hostinger office-test-check van 18 oktober: `office_inzet_people=0`, `office_inzet_project_team=0`, `office_inzet_shifts=0`. Dat is géén reden om handmatig een tweede personeelsregister te beginnen.

**Architectuurconclusie:** vóór nieuwe INSERT-routes eerst het contract voor master identity, employee-versus-supplier, projecttoewijzing en projectie/synchronisatie vaststellen. Een migratie of sync mag geen rechten impliciet toekennen.

## 4. Afhankelijkheden tussen open PR's

- [#1406](https://github.com/JohnBN1970/brebo-office/pull/1406): Office-test mount en sessie-isolatie, raakt Inzet- en projectroutes.
- [#1393](https://github.com/JohnBN1970/brebo-office/pull/1393): Calc-rechten en schema, raakt Office-operatorcontext.
- [#1407](https://github.com/JohnBN1970/brebo-office/pull/1407): brede Inzet-/OnSite-wijzigingen inclusief dezelfde Office-test-router en Inzet-pagina.
- **Risico:** #1406 en #1407 raken beide `office-test-index.php`, `office-projects.php` en Inzet-bronbestanden. Niet onafhankelijk of blind mergen; eerst op actuele `develop` samenvoegen en isolatie-/Inzettests herhalen.

## 5. Eerste technische integratievolgorde

1. Bestaande personeelsmaster en projecttoewijzing in `develop` en de betrokken PR's herleiden; contract en sleutelconversies vastleggen.
2. #1406 sessie-isolatie en #1407 Inzet-wijzigingen gecontroleerd integreren op één testbranch; conflicten op routes, login en sessies oplossen zonder productiewijzigingen.
3. Bestaande Inzet MySQL-/HTTP-tests en Office-test router/CSRF-tests als CI-gate draaien; ontbrekende tests aanvullen.
4. Alleen via geautoriseerde bronprojectie testmedewerker en teamkoppeling beschikbaar maken.
5. Office-test runtime: login -> project -> Inzet -> dienst -> weekoverzicht; negatieve ACL/CSRF/overlapgevallen aantonen.
6. Daarna Calc-launch aansluiten, niet parallel nieuwe autorisatiesystemen introduceren.

## 6. Gate A stopcriteria

Gate A blijft **open** totdat personeelsmaster, identity mapping, projecttoewijzing, Office-test route/sessie en PR-conflictstrategie expliciet in code en tests bewezen zijn. Geen productie-deploy, geen handmatige medewerkersregistratie, geen automatische merge.

## 7. Reikwijdte van dit bewijs

Deze audit inspecteerde de GitHub-diffs en relevante documenten op PR #1407 en de veranderde bestanden van #1406/#1393/#828. Er is **nog geen volledige CI-run, geen code-merge en geen runtime-acceptatietest uitgevoerd**.
