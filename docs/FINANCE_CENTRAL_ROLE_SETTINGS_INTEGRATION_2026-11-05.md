# Finance betaalbatchrechten in centraal Office-rollenbeheer

Status: **ontwerp / niet geactiveerd**. Gebaseerd op de actieve Office-test broncode van 5 november 2026; de onderstaande `src/Offer/*` bestanden zijn nog niet aanwezig op deze featurebranch.

## Vastgestelde huidige situatie

- `public/office-role-settings.php` is een private Office-test route met HTTPS-, sessie-, CSRF- en database-isolatiecontroles.
- `OfficeRoleSettingsController::save()` controleert een actieve actor met expliciete `settings.manage` grant en valideert CSRF vóór `OfficeRoleSettings::setModule()`.
- `OfficeRoleSettings::setModule()` accepteert uitsluitend `offers`; de toegestane permissies zijn `read, create, update, issue, record_acceptance, link_change, manage`.
- `OfficeRoleSettingsRead::rolesForManager()` haalt uitsluitend `offers` op uit `office_role_permissions` en `office_role_scopes`.
- De centrale writer gebruikt een transactie, verifieert de opgeslagen waarden en schrijft `office_role_settings_audit`.
- De bestaande Finance-lezer `OfficeFinanceBatchAccess` controleert **directe** grants in `office_module_permissions`, niet `office_role_permissions`.

## Gewenst contract (nog niet geïmplementeerd)

1. Voeg een expliciete modulecatalogus toe: `offers` behoudt zijn eigen permissies; `finance` staat uitsluitend `payment_batch.create`, `payment_batch.review` en `payment_batch.release` toe. Geen generieke cross-module permissielijst.
2. Behoud de bestaande `settings.manage`- en CSRF-controles voor alle schrijfacties. Autorisatie moet server-side gebeuren; geen client-supplied beheerdervlag.
3. Laat de read- en write-modellen dezelfde modulecatalogus gebruiken. De bestaande audittransactie en de rollback bij fouten blijven verplicht.
4. Bepaal en test eerst hoe `office_role_permissions` via roltoewijzingen aan operators wordt gekoppeld. De huidige Finance-autorisatie leest uitsluitend `office_module_permissions`; een rolgrant in de instellingenpagina mag **niet** als effectief recht worden gepresenteerd voordat deze resolver is aangesloten.
5. Leg precedence tussen directe grants en rolgrants vast (inclusief intrekking, inactieve rol/medewerker en caching) vóór activatie.
6. Houd bankproviderverificatie en betaaluitvoering uitgeschakeld. Deze uitbreiding is uitsluitend rechtenbeheer; geen betaalopdracht.
7. Behoud het bestaande vier-ogenbeleid voor daadwerkelijke betaalbatchvrijgave totdat het autorisatie- en vrijgavecontract afzonderlijk is beoordeeld en getest.

## Acceptatiecriteria

- Een onbevoegde, inactieve of CSRF-ongeldige actor kan geen rolrechten wijzigen.
- `offers`-gedrag en bestaande grants blijven ongewijzigd.
- Alleen de drie expliciete Finance-betaalbatchrechten kunnen worden opgeslagen; onbekende permissies worden geweigerd.
- Inactieve rollen kunnen niet worden aangepast; fouten rollen de volledige transactie terug.
- Audit bevat actor, rol, module en before/after.
- Rolgrants worden pas als effectief weergegeven wanneer de operator-rolresolutie aantoonbaar werkt.
- Geen wijziging aan bankuitvoering, providerflags of betaalbatchvrijgave.

## Benodigde bronbestanden vóór implementatie

`src/Offer/OfficeRoleSettingsView.php`, `OfficeRoleSettingsPage.php`, `OfficeRoleSettingsEndpoint.php`, de operator-rolkoppeling en bijbehorende tests/schema. Zonder deze bronnen is een wijziging aan de live instellingenpagina niet veilig te valideren.
