# Office-test zone deployment — gecontroleerde toevoeging

Actuele read-only serveruitvoer van de gebruiker: PHP 8.5.11, MariaDB
11.8.9-MariaDB-log. Alle schemagates PASS; zonetabel bestaat met 0 zones.
Private root en router wijzen naar release-office-test-20261018/private.
Zone-write/schemaflags staan uit; zone-secret ontbreekt. De twee routes en
negen nieuwe runtimebestanden zijn nog niet gedeployed.

## Te behouden
De gedeelde OfficeNativeProjectAccess op de server heeft canRead én canManage.
De lokale historische versie mist canRead. Dit pakket vervangt deze adapter
niet. De kaart gebruikt een eigen OnSiteOfficeZoneProjectAccess. Ook de bestaande
office-project.php en de database blijven bij deze deploy ongemoeid.
De navigatielink in de PR is niet onderdeel van dit afgebakende deploypakket;
gebruik voor de smoke de directe Office-test-kaart-URL met een bestaand project.

De installer verwacht exact de gemelde hashes van de gedeelde adapter,
projectpagina en router. Bij afwijking stopt hij voordat hij publiceert.
Geen productiepad, nieuwe symlink, databasewrite of vervanging van gedeelde
bestaande bestanden buiten de twee expliciet toegevoegde routerregels.

## Stappen
1. Upload het gegenereerde PHP-pakket via Moba SFTP naar de bestaande
   office_calc_test-map buiten public_html.
2. --check is alleen-lezen: schema, configuratie, hashes, afwezigheid van nieuwe
   bestandsnamen en PHP-syntax moeten kloppen.
3. --install schrijft uitsluitend negen ontbrekende private runtimebestanden,
   bewaart router/config privé als snapshot en publiceert daarna de twee routes.
   Config, secrets en schrijfactivatie blijven ongewijzigd.
4. Na controle van de install-uitvoer: --enable verifieert opnieuw code/schema en
   ongewijzigde config, genereert lokaal een secret indien nodig en schakelt alleen
   de twee testzoneflags in. Het secret wordt nooit getoond.
5. Browser-smoke: Office-login -> directe projectkaart -> bevoegd gebouw kiezen
   -> middelpunt invullen -> kaart inschakelen -> zone opslaan -> dezelfde
   formulieraanvraag herhalen -> dezelfde zone-ID. Controleer ook lezer/ander
   project, verlopen sessie/token en gewijzigde herhaling.
6. --rollback herstelt de exacte vorige router/config alleen als die nog de
   bekende hashes hebben. De nieuwe code blijft privé bewaard; zonedata en
   database worden nooit verwijderd. Bij latere handmatige edits stopt rollback.

De installer geeft JSON met status/booleans, geen configuratie-inhoud of secrets.
Bestaande configuratiewaarden blijven behouden; snapshots/config zijn mode 0600.
Installatie is niet hetzelfde als browser- of mobiele acceptatie. Geen merge of
productie-/iPhone-release via dit pakket.

## Validatie
De deploytest controleert een mutatievrije check, installatie met uitgeschakelde
writes, stabiele herhaling, behoud van canRead-adapter en config, secretprivacy,
activering, rollback, schemaweigering, gewijzigde bronbestanden, bestaande
doelbestanden, ongeldige PHP en weigering na een latere configuratiewijziging.
De CI-matrix gebruikt PHP 8.5 met MySQL 8 en MariaDB 11.8.

