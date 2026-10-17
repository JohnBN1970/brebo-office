# BREBO Office - Inzet continuiteit (2026-10-17)

## Doel
BREBO Inzet: Excel-urenbron veilig importeren, medewerker/project/week tonen, bronafwijkingen beoordelen met audittrail, vervolgens gecontroleerd koppelen aan Office. Geen automatische factuurvrijgave. Kwaliteit en herleidbaarheid boven snelheid.

## Vastgesteld door Hostinger SSH
Testomgeving: https://brebobv.nl/office-test/inzet/importcontrole
Productie office.brebobv.nl niet wijzigen zonder expliciete toestemming.
Testrouter: /home/u213420663/domains/brebobv.nl/public_html/office-test/index.php
Private testconfig: /home/u213420663/domains/brebobv.nl/office_calc_test/config/config.php
Testbron SQLite: /home/u213420663/domains/brebobv.nl/office_calc_test/data/inzet/BREBO_Inzet_werkbank_test.sqlite
Release private: /home/u213420663/domains/brebobv.nl/office_calc_test/release-office-test-inzet/private
OFFICE_TEST_INZET_IMPORT_REVIEW_ENABLED=enabled, OFFICE_TEST_INZET_IMPORT_REVIEWER_ID=1.
Gebruiker bevestigde config en router syntax groen. Browserwerking van laatste release is niet onafhankelijk geverifieerd.

SQLite:
- inzet_source_workbench: 1940 bronregels.
- person_label gevuld: 1940 van 1940.
- project_label gevuld: 1937 van 1940.
- inzet_person_candidates: 0 records.
- inzet_project_candidates: 0 records.
- review_issues: 85 (eerder bevestigd).
- Kolommen bron: source_row_id, sheet_name, row_number, person_label, project_label, review_status, note_text, positive_day_cells, plausible_hours, issue_count, cell_comment_count.
Conclusie: namen en projecten zijn grotendeels bewaard in bron, alleen afgeleide kandidaat-tabellen zijn leeg. Geen herimport of bronmutatie nodig.

## GitHub
Repo: JohnBN1970/brebo-office.
Basis branch: feat/office-isolated-test-mount, PR #1406 draft, broncommit 153479c434a7b14f20b955a32cd2573b8907a8af.
Huidige werkbranch: feat/inzet-review-decision-store, draft PR #1407, base feat/office-isolated-test-mount.
PR URL: https://github.com/JohnBN1970/brebo-office/pull/1407

Bevestigde commits op werkbranch:
- 5ff8151: OfficeInzetImportReviewReadRepository leest unieke, getrimde medewerkers/projecten rechtstreeks uit inzet_source_workbench (in plaats van lege candidate tabellen).
- 76ae844: OfficeInzetReviewDecisionRepository verifieert issue-ID tegen bron-DB en blokkeert link_person/link_project zolang authoritative Office target validation ontbreekt.
- 69541f1: test inzet-review-decision-smoke.php.
- 0f4558a: test inzet-source-label-smoke.php.
Tests zijn toegevoegd maar NIET als uitgevoerd/groen geverifieerd. Geen merge, geen deploy.

Huidige OfficeInzetImportReviewReadRepository summary() selecteert review_issues met werkblad, details/cel en reason, maar nog NIET person_label/project_label. OfficeInzetSourceReviewWorkbench toont bij afwijkingen alleen blad, cel, reden. Pogingen om die bestanden via GitHub update_file te wijzigen zijn meerdere malen geblokkeerd door toolveiligheidscontrole. Niet claimen dat wijziging is opgeslagen.

OfficeInzetReviewDecisionRepository gebruikt afzonderlijke private SQLite decision store en bron-PDO; install() maakt decisions/audit-tabellen. save() met optimistic revision en audit; controleert bronissue en weigert niet-gevalideerde target IDs. Nog geen beveiligd POST-endpoint, CSRF-bescherming, review UI of autoritatieve identiteitskoppeling aangesloten. NIET op productie toepassen.

## Eerstvolgende taken
1. GitHub branch en PR #1407 opnieuw verifiëren; controleer CI en PHP lint/smoke tests. Let op: de source-label smoke-test gebruikt review_issues met een bronkolom die in echte query voor issue-context mogelijk nodig is; tests aanpassen bij schema-uitbreiding.
2. Query voor review_issues uitbreiden met person_label/project_label via LEFT JOIN inzet_source_workbench ON source_row_id; controleer eerst schema review_issues en cardinaliteit om duplicaten te vermijden. Voeg issue_id toe voor beoordelingsacties.
3. Renderer uitbreiden met medewerker/project en werkblad, eventueel filters week/medewerker/project; HTML escaping behouden.
4. Test toevoegen voor context, onbekende bronregels en geen dubbele issue-rijen; geen PII in logs.
5. Gescheiden veilige decision store, autorisatie, CSRF, audit en validatie van bestaande Office medewerker/project-identiteiten afmaken voordat POST of linkacties aanstaan.
6. Na groen testen pas voorstel tot gecontroleerde office-test deploy; geen productie deploy of merge zonder expliciete autorisatie.
7. Na testdeploy browser controleren op zichtbare unieke namen en 85 afwijkingen. Geen aannames over factuurvrijgave.

## Werkwijze
Gebruiker wil doorpakken en beknopte echte voortgang, niet steeds lange instructies. Verifieer claims met GitHub/CI en onderscheid gebouwd/getest/gedeployed. Niet automatisch Office-medewerkers aanmaken uit Excel. Productie niet aanraken.
