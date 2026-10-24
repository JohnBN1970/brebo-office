# Office-test OnSite runtime-update — 23 oktober 2026

## Vastgesteld uit gebruikersbewijs

De gebruiker bevestigde het gerichte herstel van
`OnSitePresenceReviewRepository.php`: nieuwe SHA-256
`c74b61db117c4b7bb744be55517367aaf0e8045fa724bf0d03dbf57d7c792b76`,
back-up gecontroleerd, database/config/flags ongewijzigd.

Het daarna aangeleverde `onsite-runtime-vergelijking.tar.gz` bevat de daadwerkelijke
private Inzet-/Buildings-bronnen, gedeelde projectadapter en publieke OnSite-API's.
De bestaande mobiele keten leest `onsite_employees`, `onsite_device_tokens`,
`onsite_activation_tokens`, `onsite_project_zone_assignments` en `onsite_event_inbox`.
De methoden `resolve/register`, `receive` en `accept` horen bij die authority;
ze zijn niet compatibel met een gedeeltelijk geplaatste Office-keten met
`resolveEmployee`, `store` en `ingest`. Een gelijke numerieke ID bewijst niets.
De live Office-compositieroot behoudt ook het waargenomen aanlevervenster:
maximaal zeven dagen oud en vijf minuten vooruit. De ontvangstklok is
server-side UTC; de iPhone kan deze niet aanleveren of wijzigen.

Ook planning, uren-/leverancierscontrole en andere serveraanpassingen zijn
waargenomen. Die worden niet door de repositoryversies vervangen. De gedeelde
OfficeNativeProjectAccess heeft canRead én canManage en blijft byte-identiek.
De bestaande gebouwzone-repository en het herstelde reviewbestand blijven intact.
De legacy apparaatconsole blijft behouden en wordt niet als Office-uitgifte gebruikt.

## Afgebakende update

`build-office-onsite-runtime-test-update.py` bouwt één zelfstandig CLI-bestand.
Het bevat de bestaande, geteste Office-keten, de updater en de geverifieerde oude
bronhashes. De set bestaat uit 29 PHP-bestanden: 14 daarvan zijn in het archief
waargenomen (13 verschillen, de activate-entrypoint is al gelijk), 15 ontbreken
in die snapshot. Nieuwe bestanden die intussen exact dezelfde geteste inhoud
hebben gekregen worden behouden. Andere wijzigingen blokkeren de gehele update.

De updater past uitsluitend deze set, de expliciete vijf testroutes en hun
Apache-forwarding aan. Bestaande routes, HTTP-authenticatie en de werkelijke
private root blijven behouden. De router wordt gericht aangevuld; hij wordt
niet vervangen door de historische release-next-router uit GitHub.

Geen PDO, SQL, DDL, imports, medewerkercreatie, tokenuitgifte, config- of
flagwijzigingen. De bewezen compatibele event-/device-tabellen blijven intact.
De uitnodigingstabel/projectbinding en personeelsidentiteit blijven afzonderlijke
acceptatievoorwaarden, zoals vastgelegd in OFFICE_ONSITE_INTEGRATION_ACCEPTANCE_2026-10-23.md.

## Uitvoeren via de bestaande Moba-sessie

Download `office-onsite-runtime-testupdate-20261023.php` uit de groene CI-run
(artifact `office-onsite-runtime-testupdate-0` of `-1`) of de byte-identieke directe
chatdownload. Upload uitsluitend dit bestand buiten public_html naar:

`/home/u213420663/domains/brebobv.nl/office_calc_test/`

Controleer de SHA-256 tegen het manifest van diezelfde broncommit. Daarna:

```sh
php /home/u213420663/domains/brebobv.nl/office_calc_test/office-onsite-runtime-testupdate-20261023.php --check
```

`READY_TO_APPLY` betekent: source/paths/syntax/consumentpoorten/geobserveerde
hashes/beschermde bestanden/routervorm/Apache-anker goedgekeurd. Check schrijft
niets, ook geen state- of lockbestand. Het resultaat is geen schema-, browser-
of iPhone-acceptatie. Bij BLOCK wordt niets geplaatst.

Na een geslaagde check kan de expliciete, code-only plaatsing worden uitgevoerd:

```sh
php /home/u213420663/domains/brebobv.nl/office_calc_test/office-onsite-runtime-testupdate-20261023.php --apply --confirm=office-test-onsite-runtime-20261023
```

Alle bestaande mobile/schema/legacy-admin/Office-uitgifte- en intrekkingspoorten
moeten dicht staan. `enabled`, een onbekende waarde of een gewijzigde config
blokkeren. De bestaande zonewrite- en reviewflags worden niet gewijzigd of
als mobile toestemming behandeld. De wrapper accepteert alleen de exacte bekende
Office-test root, mount, host, database en DB-user. Geen productiepad.

Apply bewaart alle originele code en beide routingbestanden mode 0600 in een
private, pakketgebonden state-map (mode 0700), verifieert die back-ups en schrijft
pas daarna runtimebestanden. Routepublicatie volgt na de implementaties.
Herhaling verifieert de geïnstalleerde hashes en verandert niets.
`--status` geeft `INSTALLED` of `NOT_INSTALLED`, zonder actieve poorten te openen.
De installer is geen activerings- of schemamigratietool.

## Rollback en onderbrekingen

```sh
php /home/u213420663/domains/brebobv.nl/office_calc_test/office-onsite-runtime-testupdate-20261023.php --rollback --confirm=office-test-onsite-runtime-20261023
```

Rollback vereist gesloten consumentpoorten, dezelfde config, intacte back-ups,
ongewijzigde beschermde bestanden en bekende geïnstalleerde/oude hashes.
Een onderbroken apply met `preparing`-journal kan zo worden teruggedraaid;
apply hervat niet blind. Onbekende tussentijdse edits blijven behouden en blokkeren.
Originele runtime/routingbestanden worden hersteld; uitsluitend door dit pakket
nieuw toegevoegde PHP-code wordt verwijderd. Back-ups, journal, medewerkers,
apparaten, gebeurtenissen en alle tabellen blijven behouden. Een reeds
teruggedraaide state wordt niet automatisch hergebruikt.

## Testbewijs en resterende acceptatie

`OfficeOnSiteRuntimeTestUpdateTest.php` gebruikt synthetische vorige code in een
tijdelijke geïsoleerde directory. Een optionele lokale fixture controleert de
echte aangeleverde snapshot tegen de vastgelegde fingerprints. De private
serverbron wordt niet in GitHub of CI-artifacts opgenomen. Tests: mutatievrije check, volledige
plaatsing, herhaling, alle vijf routes, Apache-forwarding met behoud van HTTP-auth,
beide rechtenmethoden, behoud serverwerk, rollback, onderbreking, state-lock,
source-/backup-/configdrift, afwijkende servercode, symlinks, actieve poorten,
ontbrekende sessiedependency en productieconfig. De test laadt de geplaatste klassen samen in een apart PHP-proces en weigert
een gemengde legacy-class/Office-interface. De bestaande PHP 8.5 / MySQL 8 /
MariaDB 11.8-matrix draait ook de volledige identiteit-activatie-presence-keten.

De nieuwe updater is nog niet op Hostinger uitgevoerd. De eerdere succesvolle
reparatie bewijst uitsluitend het reviewbestand. Na codeplaatsing blijven nodig:
expliciet gecontroleerde bronmapping en bestaande Office-persoon/team, beoordeling
van het uitnodigingsschema, vrijgave van de testpoorten en fysieke/browseracceptatie.
De bestaande server-projectpagina wordt niet vervangen; de twee nieuwe
POST-routes zijn API-/uitgiftegrenzen, geen bewijs dat hun formulieren al in de
serverpagina zijn geïntegreerd. De behouden legacy console is geen vervanger
voor de native projectgebonden uitgifte. Geen productiehandeling of iOS-release.
