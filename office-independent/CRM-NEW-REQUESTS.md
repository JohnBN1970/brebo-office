# Zelfstandig Office CRM — nieuwe aanvragen

Nieuwe (niet-gemigreerde) websiteaanvragen staan in `office_website_intake` en `office_website_opportunity`. De zelfstandige leesroute is `GET /office/crm/aanvragen`, geïmplementeerd door `public/crm-new-requests.php`. De route is **niet** automatisch gepubliceerd of gedeployed. De publieke website blijft Drupal; deze Office-route gebruikt geen Drupal.

## Voorwaarden voor activering

1. Koppel de route uitsluitend via een vertrouwde Office reverse proxy die de operator **daadwerkelijk authenticeert** en autoriseert voor CRM. Strip de inkomende `X-Office-Crm-Workbench-Token` header en injecteer die pas na verificatie vanuit een server-side secret. Een browser mag het token nooit ontvangen. Een header van een bezoeker is geen authenticatie.
2. Configureer `OFFICE_CRM_WORKBENCH_TOKEN` (minimaal 32 tekens) en `OFFICE_INTAKE_PDO_DSN`, `OFFICE_INTAKE_DB_USER`, `OFFICE_INTAKE_DB_PASSWORD`. Gebruik een read-only SQL account met SELECT op de twee nieuwe intake-tabellen.
3. Installeer de schema's via de bestaande gecontroleerde intake-migratie vóór het aanzetten van de route. Ontbrekende tabellen geven 503; er wordt niets automatisch aangemaakt.
4. Test 403 zonder proxy-identiteit/token, 404 voor onbekend pad, 405 voor andere methodes, 503 bij ontbrekende DB en 200 met lege en gevulde aanvragen.
5. Activeer alleen via expliciet goedgekeurde uitrol. De oude Drupal Office intake-route wordt hierdoor niet gewijzigd.

Het overzicht toont 50 nieuwe aanvragen per pagina, gesorteerd op ontvangstdatum, met een werkende detailpagina en vorige/volgende-navigatie. Voor een volgende pagina wordt één extra record opgevraagd, zodat er geen onterechte volgende-pagina-link verschijnt. Het CRM is voorlopig **alleen-lezen**. De Office-sessiekoppeling en bewerken moeten nog volgen. Er bestaat een fail-closed toegangsbeleid voor geverifieerde Office-operators, maar dit is nog **niet aangesloten op de route**; het reverse-proxy-contract hierboven blijft daarom verplicht. Bestaande Drupal CRM-gegevens worden niet gemigreerd.
