# BREBO Inzet — hergebruikaudit en integratiegrenzen

Peildatum: 19 oktober 2026. Werkbranch: `integration/office-working-chains-20261018`.

## Doel
Voorkom dubbele ontwikkeling en dubbele gegevensadministraties bij de overgang van bestaande Drupal Inzet/OnSite naar zelfstandige BREBO Office. **Bestaande code is geen bewijs van werkende test- of productieruntime.** Geen nieuwe tabel, migratie of endpoint voordat het broncontract is vastgesteld.

## Bevestigde bestaande implementaties

| Keten | Bestaande code | Beslissing / integratievraag |
| --- | --- | --- |
| Werknemersuitnodiging | `web/modules/custom/brebo_inzet/src/Controller/OnSiteInviteController.php`, `src/Service/OnSiteInvitationManager.php` | Hergebruiken; controller maakt WhatsApp-deellink, niet aantoonbaar een automatisch verzonden bericht. Controleer Office-gebruikersidentiteit en afleverproces. |
| Activatie | `web/modules/custom/brebo_inzet/src/Service/OnSiteActivationManager.php`; `office-independent/public/onsite-activate.php` en `src/Inzet/OnSiteActivationService.php` | **Overlap**. Kies één token-uitgifte/validatiecontract en één toestelidentiteit. Niet twee parallelle tokenvoorraden. |
| Installatie en iOS | `web/modules/custom/brebo_inzet/src/Controller/OnSiteInstallController.php`; `ios/CrewPOC/Sources/OnSiteAPIClient.swift` | iOS behouden. Bestaande client gebruikt `/api/onsite/v1/activate`, `bootstrap`, `presence`. Aanvullende personeelsgegevens zijn uitbreidingsscope; app-distributie en runtime apart toetsen. |
| Aanwezigheid | `web/modules/custom/brebo_inzet/src/Controller/OnSitePresenceController.php`; `office-independent/public/onsite-presence.php` | **Contractconflict**: Drupal-endpoint accepteert uitsluitend `trigger=clock_action` en weigert coördinaten; zelfstandige route gebruikt automatische-eventverwerking. Besluit privacy-, toestemming- en eventbeleid vóór koppeling. |
| Planning/personeel | `web/modules/custom/brebo_inzet/src/Controller/PersonnelPlanningController.php`, `src/Infrastructure/DrupalPersonnelAssignmentRepository.php`; `office-independent/src/Inzet/OfficeInzetWeekPlanning.php` | **Bronconflict**: oudere `brebo_personnel_assignment` versus zelfstandige `office_inzet_people` / `office_inzet_project_team`. Leg bron-ID, mapping, eigenaarschap en leesprojectie vast vóór nieuwe writes. |
| Urencontrole | `web/modules/custom/brebo_inzet/src/Controller/ProjectHoursControlController.php` | Bestaande vergelijking gepland/geklokt, afwijkingen, weektotalen en financiële signalen hergebruiken. |
| Goedkeuring | `web/modules/custom/brebo_inzet/src/Form/ProjectHoursApprovalForm.php`, `src/Service/PersonnelActualHoursManager.php` | Bestaande submit/approve en Finance-sync hergebruiken; bevoegdheden en idempotentie bij zelfstandige Office-integratie toetsen. |
| Android | Geen Android-implementatie vastgesteld in deze audit; gebruiker bevestigt dat Android nog gebouwd moet worden | Later bouwen op hetzelfde vastgestelde API-contract; geen tweede backend. |

## Kritieke concrete bevindingen

1. `OnSiteInviteController` maakt een WhatsApp-URL en toont een persoonlijke link. De controller verstuurt zelf geen WhatsApp-bericht. Beschrijf dit niet als automatisch verzenden zonder bewijs van een andere sender.
2. `OnSiteInvitationManager::invite()` vraagt een actieve identiteit met geldig mobiel nummer, maakt een activatietoken en installatielink. Dit is bestaande uitnodigingslogica, geen ontbrekende feature.
3. De iOS-client `OnSiteAPIClient.recordPresence` verstuurt `project_id`, `zone_id`, `kind`, `occurred_at`; de Drupal-controller verlangt daarnaast expliciet `trigger=clock_action`. De combinatie kan niet zonder contracttoets als compatibel worden beschouwd.
4. Het zelfstandige OnSite-presence endpoint is achter `OFFICE_ONSITE_PRESENCE_ENABLED` en `OFFICE_ONSITE_SCHEMA_VERIFIED` afgeschermd. Aanwezigheid van code is geen bewijs van ingeschakelde runtime.
5. Urengoedkeuring is **niet nieuw te bouwen**: de oudere code bevat al `worked` en `approved` statusovergangen en een downstream Finance-synchronisatie.
6. Volgens `docs/OFFICE_WORKING_CHAINS_2026-10-18.md` waren in Office-test op 18 oktober `office_inzet_people` en `office_inzet_project_team` leeg. Niet automatisch een nieuwe canonieke bron aanwijzen.
7. De eerdere CI-meldingen voor de native weekweergave zijn blockers tot een nieuwe groene run; deze audit verandert die status niet.

## Vast te leggen vóór implementatie

- **Persoon:** canoniek werknemer-ID, bron, actieve status, contactgegevens, bevoegdheid tot uitnodigen, mapping naar Office-login.
- **Uitnodiging:** één tokenissuer, eenmaligheid, verloop, toestelregistratie, heruitnodigen en intrekken.
- **App:** één versieerbaar API-contract voor iOS en Android, inclusief backward compatibility en bestaande projectzones.
- **Uren:** expliciete versus automatische registratie, privacygrondslag, bewijs, correcties, review, audit en Finance-sync.
- **Project/gebouw:** stabiele IDs en rechtentoets bij iedere registratie en goedkeuring.
- **Aanvullende gegevens:** veldmatrix met verplichte/optionele velden, documenttypes, autorisaties en bewaartermijnen; pas daarna UI/API-uitbreiding.

## Uitvoervolgorde en acceptatie

1. Herstel de rode native weekweergave-tests op de integratiebranch; geen merge of deploy.
2. Maak een contracttest voor de bestaande uitnodiging → activatie → iOS bootstrap; leg de werkelijke endpoint- en tokencompatibiliteit vast.
3. Verifieer de canonieke personeels- en projectidentiteit; maak alleen een adapter/projectie als nodig, **geen tweede master**.
4. Sluit bestaande aanwezigheid → urenvergelijking → submit → approve → Finance aan op Office-test en toets rechten en negatieve scenario's.
5. Voeg uitsluitend ontbrekende personeelsvelden/documentuploads aan de bestaande iOS-route toe.
6. Bouw Android na vastlegging en acceptatie van hetzelfde API-contract.

## Releasegrens
Dit is een broninventarisatie en implementatiebesluit, **geen runtimeacceptatie**. Geen productiedata wijzigen, geen nieuwe tabellen aanmaken, geen merge, geen test- of productiedeploy zonder afzonderlijke toestemming.
