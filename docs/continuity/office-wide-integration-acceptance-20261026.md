# BREBO Office — integratie- en acceptatiematrix (26 oktober 2026)

Status: **NO-GO voor promotie van release-next**. Deze matrix is een werkdocument; geen bewijs dat een module functioneel is geaccepteerd.

## Leidende architectuur

- Office is de zelfstandige PHP/MySQL-bron. Geen Drupal-services, routes, tabellen of authenticatie als runtime-afhankelijkheid.
- CRM, projecten, gebouwen, calculatie, offerte, inkoop, finance, documenten, communicatie, inzet en rapportage delen geverifieerde Office-identiteit, project-ID en rechten.
- OnSite (iOS/Android) is een client van de Office API; IN/UIT is bewijs en nooit automatisch urenakkoord.
- Geen automatische DDL, productieconfiguratiewijziging of promotie vanuit deze branch.

## Integratieketens en vrijgavebewijs

| Ketting | Vereiste acceptatie | Huidige beoordeling |
| --- | --- | --- |
| Aanmelden -> dashboard -> rolmenu | Sessie, module-autorisatie, geen ontoegankelijke links, Office-test redirect | Open |
| CRM -> kans -> calculatie | Handmatige kansinvoer, lijst/Kanban, klantkoppeling, projectcontext | P0 regressierisico in release-next |
| Calculatie -> offerte -> opdracht | Eén canoniek project, herleidbare versie, autorisatie, PDF/documenten | Open |
| Projectlijst -> detail -> planning | Lijst, Kanban, agenda, Gantt, team-ACL, lokale weekgrenzen | P1 combineren |
| Project -> gebouw -> zone | Werkelijk schema, lees/schrijfrechten, CSRF, idempotentie, revisies | P0 schemaconflict |
| Project -> inzet -> OnSite | Medewerker, uitnodiging, activatie, zone, offline IN/UIT, replay | P0 contractbehoud |
| IN/UIT -> urenreview -> finance | Gesloten sessie, expliciete reviewer, UTC-goedkeuring, audit | Open; geen geofence-autogoedkeuring |
| Inkoop -> kosten -> projectresultaat | Leverancier, opdracht, factuur, budget, KPI | Open |
| Document -> opname -> rapportage | Upload, projectkoppeling, review, rapportgenerator, MJOP | Open |
| Communicatie -> dossier | E-mail/WhatsApp/verslag gekoppeld aan contact/project | Open |

## Vastgestelde release-next-blokkades

De read-only vergelijking van actieve Hostinger Office-test met release-next (zie `docs/continuity/office-test-release-20261026.md` op diagnosebranch) meldt twintig afwijkende PHP-bestanden. Prioriteit:

1. **P0 CRM**: behoud kansenlijst, Kanban, handmatige kansinvoer, `createManualOpportunity` en `requireOpportunityReady`.
2. **P0 project-ACL**: behoud `OfficeNativeProjectAccess::canRead`, bestaande `canManage`-contracten en fail-closed gedrag.
3. **P0 gebouw**: verifieer `office_buildings`-schema vóór gebruik van afwijkende `OfficeBuildingRepository`.
4. **P0 zone/OnSite**: behoud servergebonden CSRF, idempotentie, revisie-update, projectkaart, WhatsApp-uitnodiging, native bootstrap en replayconflict.
5. **P1 planning**: combineer nieuwe teamweergave met bestaande toegang; test Europe/Brussels/UTC op DST en jaargrens.
6. **P1 calculatie**: loginredirect moet op `/office-test` blijven.
7. **P2**: CSP-form-action en legacy-device-console afzonderlijk beoordelen.

## Releasepoorten

- [ ] Exacte actieve release en voorbereide release met checksums vastleggen; geen blind overschrijven.
- [ ] Werkelijk Office-test DB-schema read-only toetsen op gebouwen, zones, CRM, projecten en urenreview.
- [ ] P0-contracten van actieve release in integratiebranch behouden.
- [ ] Automatische regressies: CRM-kansen, project-ACL, uitnodiging, zone-create/update/idempotentie, presence replay.
- [ ] Functionele acceptatie: login, dashboard, projectlijst/detail, calculatie, planning, documenten.
- [ ] Volledige PHP-lint + relevante MySQL/MariaDB/iOS-tests groen op **definitieve** release-SHA.
- [ ] Rollbackpad en afzonderlijke expliciete go/no-go vóór Office-testpromotie.

## Werkvolgorde

**Eerst** P0 regressies en schema opheffen; **dan** centrale projectketens integreren; **daarna** de ontbrekende modules afronden. OnSite PR #1435/#1436 is een parallelle deelstroom en mag niet als bewijs voor Office-brede acceptatie worden gebruikt.
