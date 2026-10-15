# BREBO Office — integraal aansluitbord

Status: 2026-10-14. Dit document beschrijft **geverifieerde code-aanwezigheid**, geen bewezen productiewerking.

## Beslisregel

BREBO Office is één gebruikersomgeving met één login, dashboard, navigatie, rechten en gedeelde project-/gebouwcontext. Een tegel mag alleen actief zijn wanneer de achterliggende route, authenticatie en autorisatie end-to-end zijn getest. Een Drupal-module in de repository is **geen bewijs** van een zelfstandige Office-module.

## Modulebord

| Module | Gevonden code | Onafhankelijke Office-toegang | Volgende verificatie |
| --- | --- | --- | --- |
| Centrale ingang | `office-independent/public/office-home.php` | Implementatie in branch, nog niet live | Browserlogin, sessie en route op staging |
| CRM | `office-independent/public/crm-new-requests.php` | Route opgenomen in centrale ingang | Werkelijke login → CRM → terug |
| Calculatie | `office-independent/public/calc-workbench.php`, Calc-repo | Browserhandoff niet bewezen | Werkende overdracht met bestaande Hostinger-topologie |
| Finance | `web/modules/custom/brebo_finance/` | Nog niet vastgesteld | Services, routes, data-adapters en rechten controleren |
| Inkoop | `web/modules/custom/brebo_procurement_control/` | Nog niet vastgesteld | Services, routes, data-adapters en rechten controleren |
| Gebouwen | `web/modules/custom/brebo_building_data/` | Nog niet vastgesteld | Gebouwwaarheid, API-contract en autorisatie controleren |
| Inzet | `web/modules/custom/brebo_inzet/` | Nog niet vastgesteld | Rollen, privacy, routes en inzetgegevens controleren |
| Mail | `web/modules/custom/brebo_mail_intake/` | Nog niet vastgesteld | Mailomgeving en Office-sessiecontract controleren |
| Projecten | Projectfunctionaliteit in bestaande Office-repo | Nog niet vastgesteld | Projectidentiteit en moduleovergangen inventariseren |
| Documenten | Documentverwerking in bestaande Office-repo | Nog niet vastgesteld | Upload, extractie, rechten en dossierkoppeling inventariseren |

## Aanpak in brede, toetsbare stappen

1. **Fundament:** startscherm, login-landing, uitloggen, sessiecontrole, veilige navigatie. Geen productie-omzetting zonder controle van de bestaande live router/config.
2. **Inventarisatie:** per module concrete route, controller, gegevensbron, autorisatie en afhankelijkheid van Drupal documenteren.
3. **Gedeelde context:** één project-/gebouwidentiteit, gebruiker/rol en modulegrenzen contractueel vastleggen. Geen verborgen directe Drupal-calls in nieuwe Office-routes.
4. **Aansluiten:** modules in samenhangende batches koppelen, maar pas activeren na een geslaagde gebruikersketen.
5. **Werkvloer:** per rol alleen relevante tegels, taken, financiële KPI's en uitzonderingen; geen fictieve of ongeautoriseerde cijfers.

## Definitie van klaar

- Inloggen → Office-home → module → terug → uitloggen werkt in een echte stagingbrowser.
- Alle routes en rechten zijn expliciet en fail-closed; niet-beschikbare modules blijven inactief.
- Integratietests plus staging-smoke groen; geen live deploy, migratie of featureflag zonder toestemming.
- De Hostinger-situatie wordt getest zoals die **werkelijk** is: Office onder `/office`, Calc voorlopig op een afzonderlijke Passenger-origin. Een reverse proxy mag niet worden verondersteld.
- Eén samenvattend statusbord per ontwikkelronde: **code aanwezig / geïntegreerd / staging bewezen / productie bewezen**.

## Eerstvolgende technische controle

Controleer de bestaande live `/office/index.php`-configbootstrap en routing vóór een deployment van de repository-router. De repo-router leest momenteel niet zelfstandig de live `office_private/config.php`; blind vervangen kan de bestaande login verbreken. Onderzoek daarnaast de echte Calc-handoff op staging, zonder nieuwe router-PR's te stapelen.
