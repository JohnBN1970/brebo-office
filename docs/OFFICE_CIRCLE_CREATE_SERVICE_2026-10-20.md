# Office-native cirkel: gebouwopslag en beveiligde POST
PR #1409 bouwt op de samengevoegde #1408. #1407 blijft afzonderlijk concept.

## Gegevenswaarheid
De bestaande kaart en opslagroute gebruiken al OfficeBuildingZoneRepository:
het gebouw bezit de zone, projecten krijgen toegang via office_project_buildings.
De service is hierop aangesloten. De eerdere variant die naar
office_onsite_canonical_zones schreef is vervangen; die historische projectie
wordt niet de tweede opslagplaats voor nieuwe gebouwzones. De oude
OnSiteOfficeZoneCreator is ongemoeid gelaten.

Een Office-zone blijft actief tot expliciete inactivatie; toegang vereist steeds
een actief gebouw en de actuele project-gebouwrelatie. Externe canonical-zone-
projecties behouden hun bestaande 24-uurs TTL. Deze PR past die readers, de
mobiele bootstrap en de geregistreerde iPhone niet aan. De huidige bootstrap
leest nog de oude projectie: een volledig mobiele gebouwketen is dus nog niet
geaccepteerd.

## Opslag en formulier
De bestaande test-only route /office-test/inzet/zone/aanmaken gebruikt uitsluitend
de Office-cookie-sessionprovider. De service vergrendelt actief account,
manage-lidmaatschap en project-gebouwrelatie in de opslagtransactie. Zone-ID en
revisie komen uit de bestaande gebouwzonetabel.

Het formulier krijgt een HMAC-bevestiging met willekeurige nonce en 30 minuten
geldigheid, gebonden aan operator, Office-sessie, project en gebouw. Geen losse
double-submit-cookie meer. De SHA-256 van dit token is de unieke aanvraagcode.
Herhalen van dezelfde aanvraag met dezelfde opgeslagen geometrie retourneert
dezelfde zone-ID; gewijzigde inhoud geeft 409. Ook een herhaling controleert de
actuele rechten. Een nieuw geopend formulier is een nieuwe aanvraag.

De repository vergrendelt het gebouw voordat zij op aanvraagcode zoekt, zodat
gelijktijdige herhalingen naar één resultaat leiden. Een unieke database-index
dwingt dit ook in opslag af. Mislukte transacties bewaren geen aanvraagcode.
Bestaande repository-aanroepen zonder aanvraagcode blijven ondersteund.

De HTTP-grens accepteert uitsluitend POST, HTTPS op de exacte testhost en
same-origin application/x-www-form-urlencoded, maximaal 4096 bytes. Onbekende,
dubbele en arrayvelden worden geweigerd; operator-ID's, zone-ID's en revisies
worden niet uit browserdata overgenomen. Na succes volgt een vaste lokale 303
terug naar de geselecteerde gebouwkaart. De CSP staat deze same-origin
formulieractie nu expliciet toe.

## Schema en activering
OfficeBuildingZoneSchema bevat twee nullable metadatavelden en een unieke index
op de bestaande tabel, zonder een nieuw zoneregister. Het reviewvoorstel staat in
office-independent/deployment/hostinger/office-building-zone-request-review.sql.
Dit SQL-bestand wordt nergens automatisch uitgevoerd.

De read-only office-building-zone-preflight.php controleert op Office-test de
werkelijke InnoDB-tabellen, benodigde kolommen, database-ID, foreign keys,
volledige unieke aanvraagindex en metadataformaten. Het toont alleen PASS/BLOCK-
labels, geen bronrecords of credentials. Endpoint en kaart vereisen dezelfde
schemacontrole naast OFFICE_TEST_BUILDING_ZONE_SCHEMA_VERIFIED=enabled.
Benodigde serverconfig: OFFICE_TEST_ZONE_CSRF_SECRET (minimaal 32 bytes),
OFFICE_TEST_ZONE_WRITE_ENABLED=enabled en de bestaande Office-test-/sessieflags.
Zonder deze controles blijft schrijven uitgeschakeld.

De werkelijke Hostinger-controle, schematoepassing en testdeploy zijn nog niet
uitgevoerd. Het nieuwe schema is uitsluitend in de geïsoleerde CI-database
toegepast. Rollback: schrijfroute uitschakelen, metadata behouden zodat
aanvraagidentiteiten niet verloren gaan.

## Testbewijs en resterende acceptatie
OfficeOnSiteCircleCreateServiceMySqlTest controleert gebouwopslag/teruglezen,
herhaalde en gelijktijdige aanvragen, gewijzigd herhaalverzoek, sessie-/project-/
gebouwgebonden CSRF, verlopen tokens, reader/inactief/onbekend account,
ingetrokken rechten of gebouwkoppeling, ontbrekend/inactief gebouw, vervalste
velden, ongeldige geometrie, methode/herkomst/contenttype/payload, SQL-rollback,
behoud van caller-transacties en weigering zonder de unieke index.
De bestaande gebouwzone- en presence-regressies blijven in dezelfde workflow.

CI is codebewijs. Nog nodig: read-only Hostinger-schemaresultaat, afzonderlijk
gereviewde testmigratie en deploy, browser-smoke op de kaart en daarna een eigen
acceptatieslice voor gebouwzones in de mobiele bootstrap/presence-compositie.
Geen merge, productieaanpassing of iPhone-build zonder afzonderlijke vrijgave.

