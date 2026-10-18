# BREBO Office — gecontroleerde integratie naar werkende ketens

Peildatum: 18 oktober 2026. Branch: `integration/office-working-chains-20261018`.

## Doel en begrenzing

Opleveren van één bruikbaar BREBO Office vanuit de bestaande Office-login, projectcontext en canonieke gegevens. Dit is een integratie- en acceptatietraject, geen herbouw. Dit document is een werkplan, **geen bewijs dat een keten al operationeel is**.

Bronnen: `docs/BREBO_CONTINUITEIT.md`, `docs/ROADMAP.md` (besluit 13 oktober en herijking 14 oktober), `docs/BREBO_PROJECT_CONTINUITY.md` en de actuele Office-testobservaties van 18 oktober.

## Vastgestelde uitgangspunten

- Eén gegevenswaarheid; geen tweede personeels-, project- of calculatieadministratie.
- Gebouw is permanent; project is tijdelijke uitvoeringseenheid.
- Office-login en CRM-intake behouden; publieke website en productie ongemoeid laten.
- Domeinregels in de kern, opslag en UI als adapters; geen rechtentoekenning via alleen een zichtbare tegel.
- Alleen GitHub-wijzigingen via PR, review, tests, gecontroleerde testdeploy en runtimebewijs.
- Geen nieuwe tabel, endpoint of migratie voordat bestaande canonieke bron en contract zijn vastgesteld.

## Vastgesteld versus onbewezen

| Keten | Bevestigd | Nog te bewijzen |
| --- | --- | --- |
| Login en Office-shell | Eerder werkende PHP-login bevestigd; roadmap beschrijft shell en rolprofielen | Sessie-isolatie Office/Office-test, module-autorisatie, dashboard met echte brondata |
| Projecten/Gebouwen | Office-test: 2 projecten, 1 gebouw; native projecttabellen en PDO-verbinding bevestigd | Navigatie, rechten, projectcontext door de gehele gebruikersstroom |
| Inzet | Tabellen bestaan; shift-write met projectrechten, CSRF en overlapcontrole in code | Personeelsbron, teamkoppeling, daadwerkelijke dienst opslaan en weekoverzicht |
| Calculatie | Calc API/DB-health eerder bevestigd; beveiligde Calc-route verwacht Office-launch | Eén login, ondertekende launch, project-/calculatierechten, terugkeer bij verlopen sessie |
| Finance/Inkoop/Documenten | Bestaande bouwstenen volgens roadmap | Aansluiting in Office-shell en complete gebruikersacceptatie |

## Cruciale architectuurvraag vóór Inzet-schrijffuncties

De oudere Inzet-werkplek gebruikt volgens PR #828 `brebo_personnel_assignment` als bron voor weekbezetting. De zelfstandige Office-test bevat `office_inzet_people` en `office_inzet_project_team`, beide leeg op 18 oktober. **Niet aannemen dat de lege testtabellen de nieuwe canonieke personeelsbron zijn.** Eerst contract, eigenaarschap en migratie-/projectieregel verifiëren; daarna pas medewerkers schrijven of koppelen. Geen duplicaten en geen directe productiemigratie.

## Grote, gecontroleerde uitvoeringsstappen

### Gate A — Baseline en integratiecontract

- Controleer actuele `develop`, open integratie-PR's (#1406 Office-test-sessie, #1393 Calc-ACL, #1407 Inzet-review) en de bestaande module-/personeelscontracten.
- Leg per module vast: eigenaar van data, start- en terugkeerroute, autorisatie, teststatus en blocker.
- Verifieer expliciet dat Office-test gescheiden is van productie en dat oude Drupal-Inzet geen onbedoelde tweede waarheid vormt.

**Acceptatie:** bronmatrix, routes en testcases herleidbaar naar code; geen onduidelijke eigenaarschapspunten voor de eerste keten.

### Gate B — Eén volledige gebruikersketen

- Login -> Office-start -> project -> Inzet -> bevoegde medewerker -> dienst plannen -> dienst zichtbaar in weekoverzicht.
- Test positief en negatief: geen sessie, verkeerde projectrechten, vervallen CSRF, ongeldige medewerker, overlap, dubbel indienen.
- Alle writes in test; nul productie-impact.

**Acceptatie:** reproduceerbare integratietest en testdeploybewijs met dezelfde project-/persoonsidentiteit van begin tot eind.

### Gate C — Calc aansluiten op dezelfde Office-context

- Login -> project -> geautoriseerde calculatie -> Calc -> terug naar Office.
- Kortlevende ondertekende launch; geen tweede login; sessieverval en verboden project fail-closed.
- Geen lokale calculatiewaarheid toevoegen.

**Acceptatie:** geautomatiseerde controles plus browser-/runtimebewijs op Office-test.

### Gate D — Rolgestuurde Office-shell en overige modules

- Werkende moduletegels en doorklik naar bronobjecten.
- KPI's met bron/peildatum/btw-definitie; kritieke signalen en Mijn werk per geautoriseerde rol.
- Finance, Inkoop, Gebouwen en Documenten per volledige keten accepteren, niet per losse route.

**Acceptatie:** per rol gecontroleerde echte werkstroom en aantoonbaar correcte rechten.

## Releasebeleid

1. Eén integratiebranch en PR per afgebakende, toetsbare wijziging; geen directe productiecommits.
2. Review, CI, security en contracttests verplicht vóór merge.
3. Eerst Office-testdeploy, smoke + negatieve autorisatietests, daarna expliciete productie-releasebeslissing.
4. Stop bij onbekende canonieke bron, ontbrekend acceptatiebewijs of risico op gegevensverlies.
5. Werk roadmap/continuïteit na elke geaccepteerde keten bij. Status is: **code**, **CI**, **testdeploy**, **runtime geaccepteerd**; nooit één ongedefinieerd percentage.

## Huidige beslissing

Start bij Gate A. Geen nieuw personeelsbeheer of database-write-pad bouwen voordat het verschil tussen de bestaande personeelsbron en de onafhankelijke Office-test Inzet-tabellen is opgelost. Daarna Gate B als eerste volledige werkende keten.
