# BREBO Office 2.0 — route- en schermmapping (eerste geverifieerde tranche)

Datum: 2026-10-27. Basis: broncode `develop` in `office-independent/deployment/hostinger/office-test-index.php`, `public/office-home.php` en `public/office-projects.php`.

**Status:** gedeeltelijke inventaris, geen claim van volledige dekking. Deze mapping is de input voor WP0 en moet worden aangevuld met alle andere entrypoints, permissies en feature flags.

| Bestaande test-URL | Entrypoint | Hoofdgroep 2.0 | Schermtype | Dossiercontext | Behoud / aandachtspunt |
|---|---|---|---|---|---|
| `/office-test` en `/office-test/` | `office-home.php` | Mijn werkplek | dashboard | ingelogde operator | bestaande sessiecontrole en recent-werk ACL behouden; huidige modulekaarten herordenen |
| `/office-test/crm/aanvragen` | `crm-new-requests.php` | Relaties & kansen | werkvoorraad | aanvraag | CRM-toegang/flags controleren |
| `/office-test/crm/aanvragen/detail` | `crm-new-requests.php` | Relaties & kansen | dossier | aanvraag | procesbalk en tabs ontwerpen zonder route te breken |
| `/office-test/projecten` | `office-projects.php` | Projecten | werkvoorraad | projectlijst | bestaande membershipfilter behouden |
| `/office-test/projecten?view=inzet` | `office-projects.php` | Projecten | werkvoorraad | project/inzet | `aria-label="Module"` tabs behouden; zelfde route als Projecten |
| `/office-test/projecten/detail` | `office-project.php` | Projecten | dossier | project | dossiercontract en permissies toetsen |
| `/office-test/inzet/projectkaart` | `office-onsite-project-map.php` | Projecten | dossier-werkblad | project/zone | projectgebonden kaart, niet eigen hoofdgroep |
| `/office-test/inzet/zone/aanmaken` | `office-onsite-zone-create.php` | Projecten | actieformulier | project/zone | toegangs- en CSRF-controles behouden |
| `/office-test/inzet/zone/bijwerken` | `office-onsite-zone-update.php` | Projecten | actieformulier | project/zone | idem |
| `/office-test/inzet/dienst/nieuw` | `office-inzet-shift-create.php` | Projecten | actieformulier | project/dienst | idem |
| `/office-test/inzet/dienst/annuleren` | `office-inzet-shift-cancel.php` | Projecten | actieformulier | project/dienst | idem |
| `/office-test/inzet/dienst/verplaatsen` | `office-inzet-shift-reschedule.php` | Projecten | actieformulier | project/dienst | idem |
| `/office-test/inzet/importcontrole` | `office-inzet-import-review.php` | Bedrijfsvoering | controlewerkblad | import/inzet | rolbevoegdheid toetsen |
| `/office-test/inzet/medewerker/personeelsnummer` | `office-inzet-personnel-number.php` | Bedrijfsvoering | actieformulier | medewerker | persoonsgegevens niet in navigatie tonen |
| `/office-test/inzet/onsite/aanwezigheid` | `office-onsite-presence-review.php` | Projecten | controlewerkblad | project/aanwezigheid | privacy en projectrechten toetsen |
| `/office-test/inzet/onsite/activatie-uitgeven` | `office-onsite-invitation.php` | Projecten | actieformulier | project/apparaat | tokenuitgifte niet via algemene GET tonen |
| `/office-test/inzet/onsite/apparaat-intrekken` | `office-onsite-device-revoke.php` | Projecten | actieformulier | project/apparaat | idem |
| `/office-test/inzet/apparaten` | `onsite-devices.php` | Bedrijfsvoering | beheerwerkblad | apparaat | router behandelt apart |
| `/office-test/inloggen` | `office-login.php` | buiten hoofdmenu | authenticatie | sessie | niet decoreren met shell |
| `/office-test/uitloggen` | `office-logout.php` | buiten hoofdmenu | authenticatie | sessie | niet decoreren met shell |

API-routes (geen scherm): `/office-test/api/onsite/v1/activate`, `bootstrap`, `presence`. Router behandelt deze apart; **geen HTML-decoratie**.

## Concrete bronbevindingen

1. De router registreert `OfficeNavigation::register()` voor GET-HTML routes buiten inloggen/uitloggen. Bij nieuwe shell **niet opnieuw** per pagina een tweede sidebar toevoegen.
2. `office-projects.php` bouwt nu ook een eigen `office-shell` met sidebar én een `<nav class="office-tabs" aria-label="Module">`. De gedeelde navigatie moet de lokale module-tabs intact laten; voorkom twee zijbalken.
3. Dezelfde pagina gebruikt `view=inzet` en binnen Inzet `tab` met Overzicht, Planning, Medewerkers, OnSite, Controle en Leveranciers. Dit is **dossier-/werkbladnavigatie**, geen zes nieuwe hoofdgroepen.
4. Projectlijsten worden met `office_project_memberships` en actieve operator gefilterd. Een nieuw menu mag nooit worden gebruikt als vervanging van deze server-side autorisatie.
5. `office-home.php` heeft aparte gates voor CRM, Calculatie, Projecten en recent werk. Nieuwe navigatie moet de bestaande gates respecteren en niet-geautoriseerde routes niet activeren.
6. Router laat `OFFICE_CALC_LAUNCH_ENABLED=enabled` expliciet niet toe in Office-test. Dus geen nieuwe calculatieknop naar een onbewezen testendpoint.

## Implementatievolgorde na deze tranche

- **WP0a**: overige `public/*.php` en eventuele alternatieve routers inventariseren; route naar exacte rechten/flags/GET-POST/API mappen.
- **WP0b**: navigatie-eigenaar aanwijzen; dubbele `office-shell` verwijderen of via expliciete compatibiliteit omzeilen, pas na regressietests.
- **WP1**: vaste zes hoofdgroepen met ingeklapte subnavigatie, **achter testflag**, zonder wijzigingen aan routes of toegangscontroles.
- **WP1 acceptatie**: `/projecten`, `?view=inzet`, `?view=inzet&tab=planning` behouden de module-tabs; login en API krijgen geen navigatie; zonder flag is HTML identiek.

Deze inventaris geeft geen toestemming om productiedata, databases, Hostinger-config of deployment te wijzigen.
