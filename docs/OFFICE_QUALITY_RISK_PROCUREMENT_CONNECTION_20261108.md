# Kwaliteit, risico's en inkoop — zelfstandige Office-modules

Peildatum: 8 november 2026. PR [#1725](https://github.com/JohnBN1970/brebo-office/pull/1725).

## Vastgesteld bronbesluit

**Gebruikersbesluit 8 november 2026: Drupal blijft uitsluitend voor de website.**
Kwaliteit, risico's en inkoop gebruiken zelfstandige Office-opslag, Office-identiteiten,
Office-sessies en expliciete Office-modulemandaten. Dit besluit vervangt de eerdere
open bronkeuze in deze PR. Er komt geen afhankelijkheid van Drupal-runtime,
Drupal-objecten, Drupal-user-ID's, `project_nid` of legacy-projectmapping.
De publieke website blijft buiten deze wijziging.

Gebouw is de permanente dossierkern; project is tijdelijke uitvoeringscontext.
Functionele eisen uit BMS/CIM blijven behouden. Opslag en HTTP zijn adapters rond
frameworkvrije regels en contracten. Geen nieuwe login, dubbele factuuradministratie
of alternatieve productiedeployketen.

## Gebouwde native ketens

| Module | Canonieke Office-bron | Werkstroom |
| --- | --- | --- |
| Kwaliteit | `office_quality_checks` | Open → afwijking → hercontrole → akkoord; eis, constatering, eigenaar, termijn, maatregel en bewijs; akkoord vereist apart mandaat en opgeheven blokkade |
| Risico | `office_risks` | Open → in beheersing → gesloten, met heropenen; oorzaak, gebeurtenis, gevolg, kans/impact 1–5, maatregel, eigenaar, termijn, restrisico en afsluitbewijs |
| Inkoop | `office_procurement_orders` | Concept → ter goedkeuring → goedgekeurd → opdracht verstrekt → ontvangen; actieve CRM-leverancier, onderbouwde keuze, exacte eurocenten excl. btw, tweede bevoegde beoordelaar, opdracht-/prestatiebewijs |

Dit is een eerste native keten. Een inkoopopdracht bevat in deze versie één omschrijving,
een geheel aantal eenheden en een eenheidsprijs. Meerdere orderregels, economische
vergelijking van meerdere aanbiedingen, de volledige oude Procurement-leerkring,
leveranciersdistributie en de Finance-scherminbedding zijn nog niet aangesloten.
Een prijswijziging na goedkeuring is geblokkeerd. Er worden geen orders aan leveranciers
verzonden en geen betalingsopdrachten uitgevoerd.

Inspectiebevindingen kunnen als gecontroleerde bron naar de kwaliteitscontrole
verwijzen. Ze blijven eigen objecten. Een inspectie is niet automatisch een formeel
kwaliteitsakkoord. Een kwaliteitsblokkade wordt geregistreerd; projectbrede vrijgave
moet deze bron via een afzonderlijk te accepteren consumentencontract gebruiken.

## Implementatiegrenzen

- `OfficeGovernanceService`: invoer, lifecycle, bevoegdheden, selectieonderbouwing,
  exacte opdrachtwaarde en immutable goedgekeurde opdrachtinhoud.
- `Contract/OfficeGovernanceStore`: opslag-/autorisatiegrens.
- `Infrastructure/PdoOfficeGovernanceStore`: native PDO-adapter, live Office-ACL,
  contextcontrole, compare-and-swap revisie, command-deduplicatie en atomair auditspoor.
- `OfficeProcurementOrderSource`: leescontract voor Finance/projectconsumenten over
  dezelfde canonieke opdracht; uitsluitend verstrekte/ontvangen opdrachten. De
  Finance-schermen worden hiermee niet automatisch aangepast.
- `office-governance.php`: geauthenticeerde GET/POST-adapter voor alle drie routes.
  Selecties voor eigenaar, leverancier en inspectiebevinding komen uit Office.
- `office_governance_commands` en `office_governance_audit`: deduplicatie en
  wijzigingshistorie. Geen automatische delete of cascade op gebouw/project.

Elke registratie verwijst rechtstreeks naar een bestaand Office-project en -gebouw,
met samengestelde foreign key naar `office_project_buildings`. Verwijderen van de
koppeling mag de historie niet stilzwijgend verwijderen. Zelfstandige gebouwregistraties
zonder project vallen buiten deze eerste keten; een eigen gebouwmandaat is daarvoor
nog vereist. Afsluiten van een project verwijdert de gebouwgebonden historie niet.

Toegang vereist actieve Office-operator, expliciet modulemandaat **en** passend
projectlidmaatschap. `read` is nodig voor alle acties; `write` vereist projectbeheer.
Formeel akkoord/sluiting vereist `approve`; opdrachtverstrekking `award`, ontvangst
`receive`. Voor inkoop kan de maker niet zelf goedkeuren. Er worden geen rechten
ontleend aan CRM-edit, Finance-toegang of alleen een zichtbare link.

POST controleert HTTPS, toegestane host, Origin, maximale requestgrootte en een
sessie-/module-/project-/gebouwgebonden CSRF-token met vervaltijd. Actoren komen
uitsluitend uit de bestaande geverifieerde Office-sessie. Alle writes zijn transactioneel;
het ontbreken van auditopslag maakt de gehele wijziging ongedaan.

## Routes en navigatie

| Onderdeel | Route | Menu |
| --- | --- | --- |
| Kwaliteit | `/office-test/kwaliteit` | Kennis & kwaliteit |
| Risico's | `/office-test/risicos` | Kennis & kwaliteit |
| Inkoop | `/office-test/inkoop` | Bedrijfsvoering |

De native router en projectdetailpagina verwijzen naar dezelfde routes. Vanuit het
project kan eerst het bijbehorende gebouw worden gekozen. Het overzicht toont
maximaal 200 registraties; het Finance-leescontract heeft deze presentatielimiet niet.

## Migratie en activering

`php office-independent/deployment/hostinger/office-governance-schema-plan.php`
levert reviewbare SQL op; het script maakt geen verbinding met een database.
De SQL verandert geen bestaande tabellen en kent geen rechten toe. Controleer vóór
testinstallatie de unsigned BIGINT-sleutels en engines van de bestaande contexttabellen,
back-up/herstel, en de gezamenlijke testrelease met de andere open module-PR's.

De HTTP-routes en links blijven uit totdat beide instellingen `enabled` zijn:

- `OFFICE_TEST_GOVERNANCE_ENABLED`
- `OFFICE_TEST_GOVERNANCE_SCHEMA_VERIFIED`

Schrijven vereist daarnaast `OFFICE_TEST_GOVERNANCE_CSRF_SECRET` van minimaal
32 tekens in private serverconfiguratie. Geen secret opnemen in Git.
Modulemandaten `quality`, `risk` en `procurement` worden via bestaande Office-rechten
expliciet toegekend. Geen automatische verheffing van bestaande gebruikers.

## Bewijs en actuele status

- Read-only serveraudit [42442102336](https://github.com/JohnBN1970/brebo-office/actions/runs/42442102336)
  bevestigt native projecten, gebouwen, modulepermissies, CRM en inspectie-opslag.
- De eerder aangetroffen nul legacy-Finance-projectkoppelingen en ontbrekende
  legacy-Procurement-tabellen zijn **geen afhankelijkheid** van dit ontwerp.
- Lokale SQLite-ketentest en navigatieregressies zijn groen: project/gebouwgrenzen,
  lees-/schrijf-/beoordelingsmandaten, inactieve actor, ongeoorloofde eigenaar,
  inspectieherkomst, lifecycle, vier ogen, revisie, deduplicatie, auditrollback en CSRF.
- HTTP-fixtures testen alle drie pagina’s, projectlinks, sessie, leesrechten, verkeerde
  context, Origin/CSRF, POST-redirect en HTML-escaping.
- CI [42443511036](https://github.com/JohnBN1970/brebo-office/actions/runs/42443511036)
  is groen op de eerste native commit, inclusief SQLite en MySQL 8. De aanvullende
  HTTP-regressietest draait ook in de vervolgcommit.
- Nieuwe migratie, modulemandaten, secret, testdeploy en gebruikersacceptatie zijn
  **nog niet uitgevoerd**. Gebouwde code betekent niet dat de modules al live staan.

Geen productiemigratie, geen Drupal-migratie, geen wijziging aan de website.

## Aansluiting projectbrede inkoopverplichtingen — 8 november 2026

Het canonieke read-contract `OfficeProcurementOrderSource` ondersteunt nu ook `awardedProjectOrders(actor, project, buildingIds)` en `awardedProjectCommitment(actor, project, buildingIds)`. Deze totaliseren alleen verstrekte/ontvangen opdrachten over expliciet doorgegeven projectgebouwen, dedupliceren gebouw-ID's en opdracht-ID's, weigeren een lege gebouwscope en hanteren de bestaande native project- en module-ACL. Het resultaat is in exacte eurocenten exclusief btw; dit is een **inkoopverplichting**, geen geboekte factuur, betaling of marge.

De contractaanvulling is **geen FinanceCenter-scherminbedding** en bewijst geen serverdeployed functionaliteit. Gebruik in een volgende afzonderlijke integratiestap een projectgebouwenlijst uit een geautoriseerde Office-bron, scheid financiële leesrechten van procurement-rechten, en voorkom dubbele kostenregistratie. Browseracceptatie, migratie en modulemandaten blijven openstaande gates.

## Project-Finance schermkoppeling (vervolgcommit)

Het native projectdossier `office-independent/public/office-project.php` toont in de Finance-tab nu een afzonderlijke read-only totalisatie van verstrekte/ontvangen inkoopopdrachten. De bron is uitsluitend `OfficeProcurementOrderSource`, over alle gekoppelde projectgebouwen (niet de tot 100 regels beperkte presentatielijst). De weergave vereist een geslaagde Finance-project-ACL én de bestaande procurement-module-ACL, plus beide governance-featureflags. Een ontbrekende scope of ontbrekend mandaat wordt als niet beschikbaar getoond, nooit als nul. Bedragen blijven exclusief btw, in centen berekend; er wordt geen boeking, leveranciersfactuur of betaling aangemaakt.

**Niet geverifieerd:** PHP-lint, CI op deze nieuwe commits, schema-installatie, modulemandaten en browseracceptatie. De code staat op de PR-branch, niet bewezen in de actieve Office-testrelease. Controleer tijdens review ook de bestaande project-Finance-ACL en rechtenmatrix voor gebruikers die wel Finance maar geen Inkoop mogen lezen.

## Gecontroleerde CI-acceptatie — commit 4479475

GitHub Actions op commit `44794752e11ba770e92bdc419d3551487f003b89`: **14/14 workflows geslaagd**, inclusief native governance-acceptatie, PHP-lint, projectdetail V3, projectdashboard, CRM, Inzet, calculatie en MySQL-intakeketens. Governance-run: https://github.com/JohnBN1970/brebo-office/actions/runs/42444570064.

De Finance-tab heeft nu een read-only inkoopverplichtingenblok. Een onverwachte fout in de procurement-lezer wordt afzonderlijk afgevangen, zodat het contractoverzicht niet door deze optionele bron uitvalt. De oudere passages hierboven beschrijven de toenmalige tussenstand; dit onderdeel is de actuele code-/CI-status.

**Openstaande productie- en testgates:** geen bewijs van SQL-uitvoering op de host, rechtenuitgifte, secretconfiguratie, live routeacceptatie of deploy van de PR-branch. Een groene CI of rollout-gate is geen bewijs van actieve serverinstallatie. Eerst schema-/back-upcontrole en expliciete mandaten, daarna gecontroleerde testdeployment en browseracceptatie. Geen productieaanpassing.

## Actuele Office-test uitrol — 8 november 2026

Dit hoofdstuk vervangt de eerdere, historische tussenstanden hierboven.

- De native governance-PR [#1725](https://github.com/JohnBN1970/brebo-office/pull/1725) is naar `develop` gemerged.
- De originele SQL-run [42456210705](https://github.com/JohnBN1970/brebo-office/actions/runs/42456210705) eindigde rood, **na** een volledige Office-test SQL-back-up van 45.646.126 bytes. De aparte read-only audit [42456386379](https://github.com/JohnBN1970/brebo-office/actions/runs/42456386379) stelde vervolgens vast dat alle vijf tabellen wel aanwezig waren.
- Onafhankelijke runtimecontrole [42457183737](https://github.com/JohnBN1970/brebo-office/actions/runs/42457183737) bevestigde correcte kolommen en composite foreign keys. De database hoefde niet opnieuw gemigreerd te worden.
- Code-only uitrol [42457300011](https://github.com/JohnBN1970/brebo-office/actions/runs/42457300011) plaatste de acht benodigde PHP-bestanden in de actieve geïsoleerde testrelease, zonder router- of featurewijziging.
- Beveiligde routermount [42457394939](https://github.com/JohnBN1970/brebo-office/actions/runs/42457394939) plaatste drie routes en de RewriteRule, met terugvalkopieën.
- De read-only rechtencontrole [42457479460](https://github.com/JohnBN1970/brebo-office/actions/runs/42457479460) bevestigde dat alle opslag- en inspectieafhankelijkheden bestaan, twee operators actief zijn en er maar één projectmanager is. De vervolgaudit [42457526802](https://github.com/JohnBN1970/brebo-office/actions/runs/42457526802) bevestigde na een tijdelijke SSH-timeout dat die ene projectmanager ook een bestaand `settings:manage`-mandaat heeft.
- De afgeschermde activatie [42457647579](https://github.com/JohnBN1970/brebo-office/actions/runs/42457647579) slaagde. Beide featureflags, de CSRF-sleutel en de drie `read`/`write`-mandaten voor uitsluitend die bestaande beheerder zijn actief. Geen `approve`, `award` of `receive`-mandaten automatisch verstrekt. Configuratieback-up is aanwezig.
- Na activering eindigde de onafhankelijke servercontrole met `GOVERNANCE_RUNTIME_READY`. De publieke HTTPS-smoke [42457713566](https://github.com/JohnBN1970/brebo-office/actions/runs/42457713566) kreeg op alle drie routes HTTP 302 (verwacht voor niet-aangemelde bezoekers). Er zijn geen HTTP 404/500 waargenomen in deze rooktest.

**Nog uit te voeren:** browseracceptatie met een aangemelde, gemandateerde Office-operator; veilige proefregistratie per module; afzonderlijke tweepersoonsmandaten voor goedkeuring en opdrachtverstrekking; en bevestigen of de project-Finance read-only inkoopverplichtingenweergave ook daadwerkelijk in de actieve release staat. De laatstgenoemde Finance-code bestaat in `develop`, maar is niet door de code-only governancedeployment overgezet.

Geen wijzigingen in productie, geen Drupal-afhankelijkheid.

## Aanvullende project-Finance audit

De onafhankelijke read-only serveraudit [42457760318](https://github.com/JohnBN1970/brebo-office/actions/runs/42457760318) eindigde opnieuw met `GOVERNANCE_RUNTIME_READY`, maar rapporteert `OPTIONAL_PROJECT_FINANCE_PROCUREMENT_WIDGET=NOT_INSTALLED`. De governancefuncties zelf zijn actief; de afzonderlijke Finance-blokweergave van inkoopverplichtingen is **nog niet** in de actieve projectdetailpagina geplaatst. Een latere, versie-gecontroleerde gerichte wijziging van `office-project.php` is nodig; vervang niet blind de bestaande actieve projectpagina.

## Afgeronde Finance-mount en functionele acceptatie — 8 november 2026

- De eerste gecontroleerde Finance-mount [42459829767](https://github.com/JohnBN1970/brebo-office/actions/runs/42459829767) stopte **zonder de projectpagina te wijzigen** op `FINANCE_GOVERNANCE_LIVE_ANCHOR_MISMATCH`. De actieve projectpagina gebruikt een andere HTML-assemblage dan de repositoryversie.
- Read-only audits [42459867138](https://github.com/JohnBN1970/brebo-office/actions/runs/42459867138) en [42460007709](https://github.com/JohnBN1970/brebo-office/actions/runs/42460007709) hebben het daadwerkelijke veilige invoegpunt vastgesteld: `$moduleHtml .= $financeHtml;`.
- De aangepaste, additieve mount [42460049024](https://github.com/JohnBN1970/brebo-office/actions/runs/42460049024) is **groen**: `FINANCE_GOVERNANCE_WIDGET_MOUNTED`, `FINANCE_GOVERNANCE_BACKUP_PRESENT`, `FINANCE_GOVERNANCE_ACTIVE_PROJECT_VIEW_VERIFIED`; PHP-lint van de actieve pagina is geslaagd. De bestaande projectpagina is niet volledig vervangen.
- De HTTPS-smoke [42460089268](https://github.com/JohnBN1970/brebo-office/actions/runs/42460089268) is groen: alle drie governance-URL's en `/office-test/projecten/detail?id=1` retourneren HTTP 302 voor niet-aangemelde bezoekers, geen 404/500.
- De read-only **functionele** acceptatie [42460246756](https://github.com/JohnBN1970/brebo-office/actions/runs/42460246756) draait tegen de echte Office-testdatabase en de actieve PHP-serviceklassen. Resultaat: `GOVERNANCE_FUNCTIONAL_READONLY_PASS`, één toegankelijke project-/gebouwcontext per module, één project gecontroleerd voor de Finance-aggregatie, ACL's voor read/write bevestigd en approve/award/receive niet verleend. Er zijn geen dossiers, rechten of financiële records geschreven.

**Resterende gebruikersacceptatie:** browserweergave onder een aangemelde operator en een proefregistratie inclusief teruglezen; expliciet afzonderlijk mandaatproces voor goedkeuring, verstrekking en ontvangst. Dit is niet afgedekt door de read-only serveracceptatie.
