# BREBO Office — continuïteit en releaseoverdracht

Bijgewerkt: 2026-10-26  
Werkstroom: Office-test / OnSite / Inzet / CRM / Calculatie  
Status: **NIET UITROLLEN — diff beoordeeld, kritieke regressies en schemaconflict vastgesteld**

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

## Inhoudelijke diff-review — ontvangen gebruikersrapport 26 oktober

Bron: door gebruiker aangeleverd `release-diff-review(1).txt`; richting van diff is **actieve release (-)** tegenover **release-next (+)**. 20 unieke PHP-bestanden, waarbij `onsite-presence.php` als laatste diffblok tweemaal in de rapportkop voorkomt. Deze beoordeling betreft uitsluitend de getoonde verschillen, niet een volledige runtime- of repositorytest.

### Besluit per bestand

| Bestand | Besluit | Motivering en vereiste controle |
| --- | --- | --- |
| `public/calc-workbench.php` | ACTIEF BEHOUDEN | Next verwijdert `OfficeMount::isTest()` uit loginredirect; kan `/office-test` verkeerd routeren. Test ongeauthenticeerde toegang. |
| `public/crm-workspace.php` | ACTIEF BEHOUDEN | Next verwijdert kansenlijst, Kanban, handmatige kansinvoer, workflowindicaties en commerciële links. Niet terugdraaien. |
| `public/office-home.php` | NEXT SELECTIEF | `form-action 'self'` is CSP-verruiming voor eigen formulieren; alleen opnemen met passende formuliertest. |
| `public/office-project.php` | NEXT SELECTIEF | Zelfde CSP-aanpassing; controleer POST en form-action. |
| `public/office-projects.php` | COMBINEREN | CSP; next geeft `$projectTeams` door en toont formulier bij schrijf-gate, met lege-teamfeedback. Controleer teamrechten en methodehandtekening. |
| `src/Crm/OfficeCrmRepository.php` | ACTIEF BEHOUDEN | Next verwijdert `createManualOpportunity`. |
| `src/Crm/OfficeCrmSchemaPreflight.php` | ACTIEF BEHOUDEN | Next verwijdert `requireOpportunityReady`; daarnaast afwijkende regex-escaping, afzonderlijk testen. |
| `src/Project/OfficeNativeProjectAccess.php` | ACTIEF BEHOUDEN | Next verwijdert `canRead`, wijzigt `canManage` van string naar int en verwijdert fail-closed exception handling. Kritisch ACL-contract. |
| `src/Inzet/OfficeInzetPlanningWorkbench.php` | COMBINEREN | Next introduceert Europe/Brussels-weergave, projectkaarten en projectteamcomponent. Test UTC-opslag, zomertijd, team-ACL en HTML-structuur. |
| `src/Inzet/OfficeInzetWeekPlanning.php` | NEXT SELECTIEF | Lokale ISO-weekgrenzen met UTC-vergelijking: test winter-/zomertijd en jaarwisseling. |
| `src/Inzet/OfficeInzetWeekShiftReadRepository.php` | NEXT SELECTIEF | UTC-querygrenzen afgeleid van Europe/Brussels; test grensdiensten en DST. |
| `src/Inzet/OnSiteBootstrapService.php` | ACTIEF BEHOUDEN | Next verlaagt toegestane straal van 5000 naar 1000 meter; moet consistent zijn met zonebeleid en bestaande zones. |
| `src/Buildings/OfficeBuildingRepository.php` | BLOKKEREND SCHEMACONFLICT | Next gebruikt `building_name/street_address/active/latitude/longitude` i.p.v. `code/name/address_line`; kan op bestaande database falen. Eerst schema/compatibiliteit vaststellen. |
| `src/Buildings/OfficeBuildingZoneRepository.php` | ACTIEF BEHOUDEN | Next verwijdert idempotency `requestKey`, fingerprint, row lock en compare-and-swap `update`; verhoogt straal 1000 naar 5000. Kritisch voor zonebeheer. |
| `public/office-onsite-invitation.php` | ACTIEF BEHOUDEN | Next verwijdert GET-landing, verified identity bridge en veilige issued-page; risico voor WhatsApp-activatiestroom. |
| `public/office-onsite-project-map.php` | ACTIEF BEHOUDEN; IDEEËN SELECTIEF | Next vervangt PDOK/Nominatim en bestaande-zone-edit door read-only projectseed/OSM; verwijdert adreszoekfunctie, revisiebewerking en beveiligde formulier-tokenflow. Test kaartbron/consent afzonderlijk. |
| `public/office-onsite-zone-create.php` | ACTIEF BEHOUDEN | Next vervangt servergebonden CSRF, payload-/origincontrole, test-DB guard en idempotente create-service door cookie-token en directe repository-call. Kritisch regressierisico. |
| `public/onsite-bootstrap.php` | ACTIEF BEHOUDEN | Next verwijdert native building-zone mode en bijbehorende schema-gates; bootstrap-signatuur wijzigt. |
| `public/onsite-devices.php` | NEXT SELECTIEF | Next zet legacy device console standaard op 503 achter verificatie-gate. Alleen na bevestiging dat Office-native alternatief werkt. |
| `public/onsite-presence.php` | ONDERZOEKEN | Next verwijdert expliciete include van `OnSiteEventReplayConflict`; verifieer autoload en replay-/dubbel-event-tests. |

### Releaseblokkades en herstelvolgorde

1. **P0:** Geen promotie van `release-next`: behoud actieve project-ACL, CRM-kansen, OnSite-activatie, zone-create/update/idempotency en native bootstrap.
2. **P0:** Verifieer het werkelijke `office_buildings` schema en contracten vóór integratie van building-repositorywijzigingen; geen migratie op basis van alleen deze diff.
3. **P1:** Maak een gerichte integratiebranch vanaf actuele `develop`; combineer planning/teamweergave en tijdzonecorrecties met bestaande functionaliteit; voeg gerichte regressietests toe.
4. **P1:** Test loginredirect op `/office-test`, CRM-kansen, WhatsApp-uitnodiging, zoneaanmaak/-wijziging en idempotentie, geautoriseerde projectkaart, bootstrap, DST-weekgrenzen en presence replay.
5. **P2:** Beoordeel CSP `form-action`, OSM/PDOK-keuze en legacy-device-console-gate los van de veiligheidskritische wijzigingen.
6. Pas na groene CI, schema-/runtimepreflight en expliciete releasebeslissing een aparte Office-test deploy uitvoeren. Productie blijft buiten scope.

### Diagnoseworkflow

Het rapport is rechtstreeks aangeleverd: **SSH-workflow en GitHub-artifact zijn voor deze review niet meer nodig**. De diagnoseworkflow in PR #1433 vereist nu `DEPLOY_KNOWN_HOSTS` (geverifieerde hostregel); gebruiker meldde dat dit secret ontbreekt. Workflow niet uitvoeren of mergen als vereiste releasevoorwaarde. Het rapport bevat geen bewijs van een geslaagde runtime-release.

## Vervolgactie

1. Maak een afzonderlijke integratie-PR met behoud van de actieve beveiligings- en functionaliteitscontracten.
2. Voer gerichte regressietests en databasepreflight uit, gevolgd door volledige PHP-lint en route-/end-to-end-controles.
3. Leg een expliciete go/no-go vast vóór een Office-test release; behoud de oude release als rollback.

## Veiligheidsgrenzen

- Geen secrets, databasewachtwoorden of privé-sleutels in GitHub-code of artifacts opnemen.
- Het diagnose-artifact kan broncodefragmenten bevatten: alleen binnen de private repository beoordelen en na gebruik verwijderen indien nodig.
- De diagnoseworkflow leest één bestaand rapport; hij wijzigt geen bestanden op Hostinger.
- Niet mergen of deployen zonder gecontroleerde uitkomst en autorisatie.
