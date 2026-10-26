# BREBO Office — continuïteit en releaseoverdracht

Bijgewerkt: 2026-10-26  
Werkstroom: Office-test / OnSite / Inzet / CRM / Calculatie  
Status: **NIET UITROLLEN — releaseverschillen eerst inhoudelijk beoordelen**

## GitHub

- Repository: `JohnBN1970/brebo-office`
- Doelbranch: `develop`
- PR #1431 is gemerged; mergecommit `76dd891efbdc4dff2f481ee36c59a9583481870a`.
- PR #1431 betrof de Hostinger Office-test OnSite/Inzet-synchronisatie en de activatie-uitnodigingspreflight.
- Nieuwe diagnosebranch: `diagnostics/office-test-release-diff-20261026`.
- Nieuwe workflow: `.github/workflows/office-test-release-diff-readonly.yml`. **Geen verwijzing naar sboffice**, uitsluitend de bestaande Hostinger-deploy-secrets.
- Workflow gebruikt `DEPLOY_HOST`, `DEPLOY_PORT`, `DEPLOY_USER` en `DEPLOY_SSH_KEY`. Deze namen zijn gebaseerd op bestaande repo-workflows; daadwerkelijke secrettoegang moet blijken uit de run.

## Hostinger Office-test

- Root: `/home/u213420663/domains/brebobv.nl/office_calc_test`.
- Actieve private release: `release-office-test-20261018/private`.
- Voorbereide release: `release-next/private`.
- Router: `public_html/office-test/index.php`; leest `OFFICE_PRIVATE_ROOT` uit `config/config.php`.
- Actieve configuratie wijst nog naar `release-office-test-20261018/private`. **Niet aanpassen vóór vrijgave.**
- Database: `u213420663_office_test`; test-DSN en DB-gebruiker zijn gecontroleerd.
- Geen productie-deploy, routerwissel of databasemigratie uitgevoerd tijdens deze releasevoorbereiding.

## Uitgevoerde controles

- Git checkout rapporteerde commit `76dd891e`.
- Git checkout is niet schoon: twee gewijzigde mailgatewaybestanden (CRLF/LF-waarschuwing) en vier vreemde untracked bestanden. **Niet verwijderen of resetten zonder afzonderlijke inspectie.**
- In `release-next/private` zijn 324 PHP-bestanden zonder gerapporteerde syntaxfouten gelint.
- Essentiële bestanden `public/office-home.php`, `public/onsite-activate.php` en `src/Inzet/OnSiteActivationInvitationReadiness.php` zijn aanwezig.
- In de testdatabase zijn `fk_onsite_invitation_person` en `fk_onsite_invitation_project` bevestigd. Vier activatie-uitnodigingen waren reeds verbruikt.
- De nieuwe release bevat extra CRM/Calculatie/website-intakebestanden die niet zonder beoordeling mogen worden verwijderd.
- Veertien ontbrekende PHP-bestanden zijn vanuit de actieve release met `rsync --ignore-existing` toegevoegd aan `release-next`: vier CRM-klassen, zes Project/Calculatie-klassen, één UI-klasse en drie Workflow-klassen. Hierna is de PHP-syntaxcontrole zonder gerapporteerde fouten afgerond.
- **Let op:** dit herstel is alleen in `release-next` uitgevoerd; de overgenomen klassen zijn niet automatisch teruggebracht naar GitHub.

## Openstaande verschillen

Het rapport `/home/u213420663/domains/brebobv.nl/office_calc_test/release-diff-review.txt` bevat circa 1.254 regels / 91 KB met verschillen tussen de actieve en voorbereide release.

Er zijn 20 inhoudelijk verschillende PHP-bestanden:
- `public/`: calc-workbench, crm-workspace, office-home, office-onsite-invitation, office-onsite-project-map, office-onsite-zone-create, office-project, office-projects, onsite-bootstrap, onsite-devices, onsite-presence.
- `src/Crm/`: OfficeCrmRepository, OfficeCrmSchemaPreflight.
- `src/Project/`: OfficeNativeProjectAccess.
- `src/Inzet/`: OfficeInzetPlanningWorkbench, OfficeInzetWeekPlanning, OfficeInzetWeekShiftReadRepository, OnSiteBootstrapService.
- `src/Buildings/`: OfficeBuildingRepository, OfficeBuildingZoneRepository.

## Vervolgactie

1. Draai de read-only diagnoseworkflow op de nieuwe branch en controleer of het artifact beschikbaar is.
2. Haal het rapport op via de GitHub-workflow-artifacts en beoordeel de diff inhoudelijk per module.
3. Leg per bestand vast welke wijzigingen behouden of gecombineerd worden. Geen automatische overschrijving van de actieve release.
4. Breng ontbrekende/gewijzigde functionaliteit gecontroleerd terug in GitHub via aparte reviewbare commits/PR.
5. Controleer daarna volledige PHP-lint, databasepreflight, routes, CRM, Calculatie, Projecten, Inzet, OnSite-activatie en zones.
6. Pas na expliciete go/no-go de routerconfiguratie aan; houd de oude release als rollback beschikbaar.

## Veiligheidsgrenzen

- Geen secrets, databasewachtwoorden of privé-sleutels in GitHub-code of artifacts opnemen.
- Het diagnose-artifact kan broncodefragmenten bevatten: alleen binnen de private repository beoordelen en na gebruik verwijderen indien nodig.
- De diagnoseworkflow leest één bestaand rapport; hij wijzigt geen bestanden op Hostinger.
- Niet mergen of deployen zonder gecontroleerde uitkomst en autorisatie.
