# BREBO Office-test — één calculatie-identiteit voor Deal en Project

Status: **ontwerpbesluit ter implementatie; niet installeren/activeren** (23 november 2026).

## Vastgesteld op de echte VPS
- `brebo_office_test` is de geïsoleerde testdatabase.
- `office_crm_opportunities.id` en `office_operator_accounts.id`: BIGINT UNSIGNED.
- Twee Deals (1, 2) zijn `won / closed`; nul Calc-acceptatieoverdrachten, nul werkbegrotingen en nul begrotingsregels.
- `office_crm_opportunities.revision`, `office_calc_crm_drafts`, `calculations`, `calculation_versions`, `calculation_lines` ontbreken.
- Backup `brebo_office_test_before_calc_20261123-120429.sql.gz` is geverifieerd en naar een tijdelijke database hersteld (`RESTORE_TEST=PASS`). Dat is geen goedkeuring voor schemawrites.

## Conflicterende contracten in develop
1. `deployment/hostinger/sql/office-calc-calculations-access.sql`: `office_calc_calculations.id` is de Office-calculatie-identiteit, met verplichte `project_id`; `office_calc_project_access` verwijst ernaar met een samengestelde FK. `OfficeCalcLaunchAccess` eist expliciet calculatierecht én projectrecht.
2. `OfficeCalcCrmDraftLineRepository::version()` gebruikt ten onrechte `office_calc_crm_drafts.id` rechtstreeks als `calculations.office_calculation_id`. De geblokkeerde conceptmigratie zou dat via een FK vastleggen, waardoor projectcalculaties een andere betekenis voor hetzelfde veld krijgen.
3. `OfficeCalcCrmDraftAccess` en `OfficeCalcDraftCollaboration` autoriseren op `draft_id`, niet op projectlidmaatschap; die afbakening moet intact blijven.

## Eén eigenaar, twee contexten
- `office_calc_calculations.id` blijft **de enige** Office-calculatie-ID.
- Dealconcepten houden een aparte `office_calc_crm_drafts.id` als **CRM-concept-ID**, niet als calculatie-ID.
- Nieuwe koppeling: `office_calc_crm_drafts.calculation_id BIGINT UNSIGNED NOT NULL UNIQUE` → `office_calc_calculations.id`; ook `opportunity_id` blijft UNIQUE.
- `calculations.office_calculation_id` → `office_calc_calculations.id` (UNIQUE). `calculations.id` is uitsluitend de lokale rij-ID van de Calc-opslag, niet de Office-identiteit.
- `calculation_versions` en `calculation_lines` blijven de enige financieel mutabele bron voor beide routes.
- Voor projectloze concepten moet `office_calc_calculations.project_id` **nullable** worden, met expliciete restricties voor toegestane eigenaar/context. Huidige projecttoegang mag nooit een nul-projectcontext impliciet als bevoegdheid behandelen.
- Projecttoegang (`office_calc_project_access`) blijft uitsluitend voor projectcalculaties en vereist een niet-null project + per-calculatie `manage` grant. Dealtoegang blijft op Deal-ID + maker/expliciet toegekende medewerkers + actieve CRM-rechten.
- Geen impliciete migratie van bestaande projectcalculaties, geen tweede set calculatieregels, geen automatische projectcreatie.

## Benodigde codewijzigingen vóór SQL-installatie
1. **Identiteitsservice:** start een Dealconcept transactioneel en onder lock van de Deal; maakt precies één `office_calc_calculations`-record aan zonder project, vervolgens precies één `office_calc_crm_drafts`-record met `calculation_id`; verifieert revision/status en rechten; atomair rollback bij fouten. Bij heropenen geen nieuwe calculatie-ID.
2. **Repository:** `version()` en `OfficeCalcDraftCollaboration::state()` lezen de Office-calculatie-ID via `office_calc_crm_drafts.calculation_id`, niet via `draft_id`. Schrijven blijft uitsluitend in `calculations/calculation_versions/calculation_lines`.
3. **Schema:** herzien van `office-crm-calc-projectless-bootstrap-REVIEW.sql`; de huidige geblokkeerde FK naar `office_calc_crm_drafts(id)` moet weg. Voeg gecontroleerde, compatibele Office-identiteitstabellen/kolommen toe, nadat is geverifieerd of `office_calc_calculations` en `office_calc_project_access` reeds bestaan.
4. **CRM-revision:** aparte, reviewbare ALTER voor reeds bestaande tabel; beginwaarde 1 documenteert alleen nieuwe teller en reconstrueert geen historische Deal-revisies.
5. **Tests:** MySQL-integratietest met beide routes, UNIQUE+FK+NOT NULL, projectvrije start/heropenen, twee gelijktijdige starters, geweigerde onbevoegde gebruiker, stale revision, bestaande project-Calc launch, vergrendelde geaccepteerde versie en rollback van calculatie-/auditfouten.
6. **Uitrol:** preflight met inspectie van echte tabellen en kolomtypen; expliciete schema-goedkeuring; pas dan op geïsoleerde testdatabase; featureflags pas na ingelogde browser-E2E.

## Niet doen
- De geblokkeerde `office-crm-calc-projectless-bootstrap-REVIEW.sql` niet uitvoeren.
- Geen `CREATE TABLE IF NOT EXISTS` gebruiken als bewijs van correcte schema-inhoud.
- Geen bestaande `office_calc_work_budget_*` of `office_calc_crm_acceptance_transfers` overschrijven.
- Geen merge, flags, grants, projectcreatie of productieaanpassingen zonder tests en review.

**Acceptatiecriterium:** Deal → Calc starten → regel opslaan → heropenen → offerte; daarna acceptatie → project + vergrendelde werkbegroting, met één Office-calculatie-ID en zonder regressie van bestaande projectcalculaties.
