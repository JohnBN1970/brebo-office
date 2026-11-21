# BREBO Office — basisketen 1 t/m 5: inventarisatie en acceptatie

Peildatum: 21 november 2026. Uitgangscommit `develop`: `8913912bdf691a1fe4c8d3ae1e40552077013d23`.
Omgeving: uitsluitend `https://office-test.brebobv.nl/office-test/`. Productie valt buiten deze run.

## Scope en bewijsregels

1. Technische basis, 2. Intake/Leads, 3. CRM/Deals, 4. Calculatie/Offerte, 5. Projecten/Gebouwen.
Gebouw blijft het permanente object; project is een tijdelijke uitvoeringseenheid. Directe opdracht zonder offerte/calculatie is toegestaan. Geen dubbele database- of UI-implementaties.
Status wordt per controle apart gemeld: **broncode**, **CI**, **VPS-release**, **schema/config**, **geauthenticeerde HTTP/UAT**. Aanwezige code of groene CI is geen gebruikerstest.
Een nieuw testrecord alleen aanmaken met een bevoegde testsessie en expliciete acceptatie van de testdata; nooit schrijven naar productie.

## Bevestigd op broncode en release

- [x] De centrale `develop`-pipeline en VPS deployment voor `8913912bdf691a1fe4c8d3ae1e40552077013d23` zijn geslaagd; `VPS_ACTIVE_RELEASE_SHA_MATCH=PASS` in [run 44941861242](https://github.com/JohnBN1970/brebo-office/actions/runs/44941861242).
- [x] Route `/office-test/crm/aanvragen` en lead-naar-Deal controller aanwezig; `office-crm-opportunity-create.php` valideert gekwalificeerde lead, relatie en transactielock.
- [x] Gedeelde Leads/Deals-boardbron `public/office-crm-shared-board.js` en lead-transitieroute aanwezig. Contracttests zijn groen; echte opslag tijdens UAT nog niet bevestigd.
- [x] Directe gegunde Deal-naar-project implementatie `OfficeCrmDirectProjectAcceptance` / `OfficeCrmDirectProjectTransfer` / `OfficeCrmNativeProjectAdapter` aanwezig. Transactie, autorisatie, terugkoppeling en dubbele overdracht worden in code gecontroleerd.
- [x] Native projectoverzicht leest `office_project_buildings` voor gebouwtellingen en controleert projectlidmaatschap.

## Gate 1 — technische basis

- [x] `develop` en VPS-release op hetzelfde SHA bevestigd via GitHub deploymentlog.
- [ ] Met bevoegde read-only VPS-check: actuele database-tabellen, kolommen, indexen, schrijfrechten en migratiestatus van CRM, Calc, Projecten en Gebouwen inventariseren (geen aannames uit `CREATE TABLE IF NOT EXISTS`).
- [ ] Met bevoegde read-only VPS-check: PHP-FPM flags voor CRM writes, Leads, Deals, native projecten, directe overdracht en Calc lezen. Secrets afschermen.
- [ ] Ingelogde en uitgelogde HTTP-routes voor Office, CRM, Calc, Projecten en Gebouwen testen met verwachte 200/302/403/404/503-responses.
- [ ] Testoperator-ACL en dezelfde identiteit door de hele keten verifiëren.

## Gate 2 — intake en leads

- [ ] Handmatige testlead met herkomst, eigenaar en relatie registreren; bron- en eventhistorie controleren.
- [ ] Lead tussen toegestane fasen slepen en alternatief `Verplaatsen` gebruiken; na herladen dezelfde opgeslagen fase aantonen.
- [ ] Ongeldige overgang, verlopen CSRF, ontbrekende rechten en dubbel indienen blokkeren.
- [ ] Gekwalificeerde lead eenmaal in Deal omzetten; herhaling mag geen tweede Deal creëren.
- [ ] Website- en e-mailintake apart aantonen: dit is niet bewezen door een werkend handmatig Leadformulier.

## Gate 3 — CRM en Deals

- [ ] Deal openen met relatie, contactpersoon, gebouw, dienst, eigenaar en historiek; ontbrekende veldkoppelingen expliciet documenteren.
- [ ] Toegestane faseovergang inclusief `won` opslaan; herladen en eventhistorie bewijzen.
- [ ] Ongeldige overgang, CSRF, rechten en gelijktijdige updates testen.
- [ ] Gegunde Deal zonder project mag niet als reeds overgedragen worden getoond.
- [ ] Correctie van foutief akkoord niet vrijgeven zolang de rollback- en auditregels uit [PR #2059](https://github.com/JohnBN1970/brebo-office/pull/2059) niet zijn bewezen.

## Gate 4 — calculatie en offerte

- [ ] Read-only databasepreflight van `office_crm_opportunities.revision` en unieke `office_calc_crm_drafts.opportunity_id` uitvoeren; [PR #2105](https://github.com/JohnBN1970/brebo-office/pull/2105) is nog open, dus niet uitgaan van geactiveerde Deal-start.
- [ ] Eén geautoriseerde Deal -> Calc-concept -> regel opslaan -> heropenen -> revisiecontrole -> rechtencontrole testen.
- [ ] Offerte uit vastgestelde calculatieversie genereren en acceptatiebewijs controleren; geen kostprijzen/marges in klantdocument.
- [ ] Na gegunde offerte maximaal één Office-project en een vergrendelde werkbegroting uit exact de geaccepteerde versie aantonen.
- [ ] Directe opdrachten testen zonder verplichte Calc of offerte. Geen fictieve vergrendelde begroting aanmaken.

## Gate 5 — projecten en gebouwen

- [ ] Gegunde directe Deal -> knop `Maak project voor directe opdracht` -> exact één native project + leesbaar projectdossier aantonen.
- [ ] Herhaalde overdracht, ontbrekende `projects/create` permissie, ontbrekende mapping en conflicterende Calc-mapping veilig weigeren.
- [ ] Project-gebouw-relatie vastleggen en teruglezen uit bestaande `office_project_buildings`. **Broncode-aandachtspunt:** `OfficeCrmNativeProjectAdapter` maakt project en membership, maar bevat geen gebouwkoppeling; bestaande beheeractie / aansluitcontract eerst vaststellen, geen tweede gebouwmodel creëren.
- [ ] Gebouw blijft behouden na projectafsluiting; projectoverzicht en detail tonen hetzelfde object.
- [ ] Projectstart en werkbegroting onderscheiden van akkoord: een `won` Deal is niet hetzelfde als vrijgegeven uitvoering.

## Eerste verticale acceptatie (minimale bedrijfswaarde)

1. Eén testlead handmatig aanmaken -> kwalificeren -> aan bestaande actieve relatie koppelen.
2. Omzetten naar één Deal -> statussen en historie bewijzen -> als directe opdracht gegund markeren.
3. Directe projectoverdracht met bevoegd account -> precies één bestaand native project.
4. Bestaand gebouw koppelen -> hetzelfde gebouw zowel op project als in gebouwendossier aantonen.
5. Negatieve controles: geen dubbele conversie/projecten, geen ongeautoriseerde acties, geen doorsijpeling naar productie.

**Vrijgavepoort:** pas 'basisketen werkt' zeggen bij geslaagde authentieke HTTP/UAT, bevestigde schema's, flags, datarelaties, audittrail en negatieve tests. De broncode alleen rechtvaardigt deze conclusie niet.

## Afstemming met bestaand werk

- De [gedeelde CRM-boardfix #2112](https://github.com/JohnBN1970/brebo-office/pull/2112) is reeds gemerged; geen tweede board bouwen.
- [CRM -> Calc #2105](https://github.com/JohnBN1970/brebo-office/pull/2105) blijft eigen integratie-eigenaar; geen parallelle launchroute toevoegen.
- [Offertestudio -> werkbegroting #1874](https://github.com/JohnBN1970/brebo-office/pull/1874) blijft eigen acceptatiepad.
- [Correctie akkoord #2059](https://github.com/JohnBN1970/brebo-office/pull/2059) is draft; geen onbewezen rollback.
- Voor startdossier, OnSite en Finance gelden volgende ketengates (6 t/m 9), buiten deze eerste acceptatie.

## Concrete uitkomst van deze inventarisatie

**Reeds aanwezig:** Lead/Deal controllers, board, Deal-transities, native projectadapter, idempotente directe overdracht, projectportfolio met gebouwrelatietabel en geslaagde VPS-code-uitrol.

**Nog onbewezen:** VPS-database, featureflags, bevoegdheden, ingelogde persistente browserketen en project-gebouwkoppeling na Deal-overdracht.

**Eerstvolgende technische actie:** read-only VPS-schema/flag-preflight tegen de huidige release, daarna één bevoegd ingestelde testoperator en één directe testopdracht door Gate 2, 3 en 5. Een nieuwe patch pas maken wanneer de concrete falende stap en bron zijn vastgesteld.
