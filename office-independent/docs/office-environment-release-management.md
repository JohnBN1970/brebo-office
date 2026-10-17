# BREBO Office — omgevings- en releasebeheer (bouwblok)

Status: ontwerpbesluit, nog niet operationeel. Branch: feat/office-isolated-test-mount.
Scope: een samenhangende beheerfunctie, geen losse deployknoppen.

## Omgevingen
- **SBOFFICE**: bestaande Drupal-sandbox; niet verwarren met de aparte PHP Office-testmount.
- **Office-test**: geïsoleerde PHP Office-acceptatieomgeving op een eigen testdatabase, eigen cookies en afgesloten toegang.
- **Office-productie**: live PHP Office, nooit automatisch wijzigen vanuit de testomgeving.

## Beheerscherm
- Alleen voor expliciet geautoriseerde beheerders.
- Zichtbare omgevingsindicator op elke pagina; test toont permanent TESTOMGEVING.
- Navigatie tussen omgevingen via externe URL, zonder tokens, sessies of data mee te nemen.
- Moduleregister: module, broncommit/release-id, testresultaat, afhankelijkheden, database-migraties, eigenaar, vrijgavestatus en productierevisie.
- Statusstappen: in ontwikkeling -> testklaar -> getest -> vrijgave aangevraagd -> goedgekeurd -> uitgerold -> geverifieerd; afgekeurd/geblokkeerd/teruggedraaid als aparte statussen.
- Vrijgave is een expliciete aanvraag; geen automatische deploy bij een klik of een groen CI-resultaat.

## Releasecontract
1. Vastgepinde immutable release-artifact met SHA-256 manifest en herleidbare commit.
2. CI en functionele rooktest groen, inclusief auth, rollen, modules en afhankelijkheden.
3. Test- en productieconfiguratie volledig gescheiden; DB-identiteit fail-closed.
4. Migraties voorwaarts en achterwaarts beoordeeld, backup/herstelplan vooraf gereed.
5. Goedkeuring door bevoegde beheerder vastgelegd (actor, tijd, release, scope).
6. Productiedeploy alleen na expliciete menselijke goedkeuring, via geautoriseerde uitvoeringsroute.
7. Productiesmoke en auditlog; bij falen uitrol stoppen en herstelprocedure volgen.

## Eerstvolgende oplevering
- Testmount afsluiten met werkende host-level toegangsbeveiliging.
- Test-only router en auth-/sessiepad in CI testen, inclusief negatieve tests voor productiehost en productiedatabase.
- Werkende browserproef in Office-test.
- Beheerscherm met read-only omgevingsstatus en module-overzicht, daarna vrijgaveworkflow.

## Veiligheidsgrens
Geen merges naar develop, productieaanpassingen, database-migraties of deploys zonder expliciete goedkeuring. Geen productiegeheimen in repository, logs of browser.