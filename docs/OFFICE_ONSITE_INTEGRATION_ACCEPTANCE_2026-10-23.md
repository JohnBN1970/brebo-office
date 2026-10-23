# PR #1409 — identiteit → iPhone → aanwezigheidsbewijs

## Code en grenzen

De bestaande Office-medewerker blijft de enige persoon in deze keten. Een private,
handmatig beoordeelde JSON-koppeling gebruikt OfficeInzetIdentityMappingGuard en
OnSiteActivationIdentityBridge. De uitgifteroute laadt deze alleen uit
`OFFICE_ONSITE_VERIFIED_IDENTITY_MAP_FILE` (absoluut privaat pad buiten webroots;
beheerder schrijft dit bestand, nooit een HTTP-upload). Ontbrekende of conflicterende
koppelingen weigeren uitgifte. Geen import, provisioning, telefoonregistratie of
Drupal-tokenhergebruik. Een Drupal UID of legacy employee_id is een aparte authority.

Formaat (uitsluitend fictief voorbeeld, geen voorgestelde servermapping):

```json
{
  "version": 1,
  "verified_by": "beoordelaar-referentie",
  "verified_at": "2026-10-23T08:00:00Z",
  "mappings": [
    {"source_system": "legacy-onsite", "source_person_id": "employee:42", "office_person_id": 101}
  ]
}
```

Leg bewijs van de persoonscontrole afzonderlijk vast. Een ingevulde referentie is
geen bewijs dat die controle al is uitgevoerd. De code controleert vorm,
uniciteit en provenance; de beheerder verifieert de werkelijke persoon en bestaande
Office-medewerker. De huidige lege Office-testpersoneelsadministratie wordt niet
gevuld door deze wijziging. Eerst een medewerker via het bestaande Office-proces
inrichten, controleren en aan het testproject toewijzen; nooit legacy-ID 1 als
Office-ID 1 aannemen.

Uitgifte controleert de bestaande actieve operator, manage-rechten en projectteam
binnen de transactie. Iedere opnieuw bevestigde uitgifte maakt oudere ongebruikte
codes ongeldig. Bestaande apparaten blijven afzonderlijk intrekbaar. Activatie
controleert de actieve medewerker en de oorspronkelijke projecttoewijzing opnieuw,
bewaart alleen tokenhashes en consumeert de code eenmaal. Een verloren activatierespons
geeft geen recht op opnieuw ophalen van het geheime apparaattoken: vraag dan een
nieuwe code aan. De iPhone onderdrukt gelijktijdige activaties, bewaart credentials
in Keychain en verwijdert die niet bij offline/503.

Bootstrap kan dezelfde bestaande gebouwzones lezen als presence, onder dezelfde
native schema- en zonegates. De oude projectie met 24-uurs TTL blijft afzonderlijk
beveiligd. Presence leidt de medewerker uitsluitend af uit het apparaattoken;
herhalingen zijn idempotent, gewijzigde/cross-person replays geven 409. Het overzicht
blijft `evidence_only`, zonder uren goed te keuren.

## Werkelijke Office-testacceptatie — nog open

Het aangeleverde serverbewijs blijft geldig: geen nieuwe migratie voor
`office_onsite_device_tokens` of `office_onsite_event_inbox`. Voer de oude apply
niet opnieuw uit. De uitnodigingstabel is een afzonderlijke bestaande codecomponent;
deze wijziging bindt uitnodigingen met `project_id BIGINT UNSIGNED NOT NULL` aan
hun oorspronkelijke Office-project. De twee-tabellen-preflight bewijst dit
uitnodigingsschema niet. `OnSiteActivationInvitationSchema::statements()` is het
reviewvoorstel voor een lege testomgeving; geen runtime-DDL. Als de server die
kolom niet heeft, blijft activatie dicht totdat een afzonderlijk beoordeelde
uitnodigingsschema-stap is uitgevoerd. Geen blinde ALTER of data-aanvulling.

1. Download het CI-artifact `office-onsite-integration-0` of `-1` van de uiteindelijke
   groene head. Controleer `manifest.json` tegen de broncommit. Pak het pakket uit
   in een nieuwe private reviewmap onder `office_calc_test`, nooit over de release.
   Dit is een bron-/testpakket, geen installer. Het bevat geen config of secrets.
2. Herstel de reeds bewezen oude review-repository gericht. Vergelijk eerst
   SHA-256 van de releaseversie met
   `1a905f672756925de7469d58570a6c23b7bbe2d0d3fb553761c235feae69e48d`.
   Alleen bij die exacte hash: bewaar een private kopie en vervang uitsluitend
   `src/Inzet/OnSitePresenceReviewRepository.php` door de pakketversie. Voer `php -l`
   uit en controleer de nieuwe hash
   `c74b61db117c4b7bb744be55517367aaf0e8045fa724bf0d03dbf57d7c792b76`.
   Bij een andere hash: stop deze vervanging en behoud dat nieuwere serverwerk.
   De gedeelde `OfficeNativeProjectAccess` met canRead én canManage blijft intact.
3. Review de overige pakketbestanden gericht ten opzichte van de testrelease.
   Geen volledige mapkopie, routervervanging, productiepad, credentialsimport,
   flagwijziging of iOS-release is in dit pakket geautomatiseerd. De bestaande
   zone-/reviewinstallers behouden hun eigen poorten; de oude reviewinstaller die
   afwezige bestanden verwacht is geen updater voor deze reeds aanwezige bestanden.
4. Voor mobiele acceptatie zijn nog nodig: één expliciet geverifieerde bestaande
   Office-medewerker, teamtoewijzing, de private mapping, passend uitnodigingsschema,
   actieve gebouwzone en expliciete vrijgave van de bestaande testgates. Laat alle
   huidige flags ongewijzigd totdat die stappen beoordeeld zijn. Native bootstrap
   en presence vereisen `OFFICE_ONSITE_NATIVE_BUILDING_ZONES_VERIFIED`,
   `OFFICE_NATIVE_PROJECTS_SCHEMA_VERIFIED`, `OFFICE_NATIVE_BUILDING_ZONES_SCHEMA_VERIFIED`
   en `OFFICE_ONSITE_SCHEMA_VERIFIED`; iedere API heeft daarnaast zijn eigen flag.
   Uitgifte behoudt sessie, HTTPS/host/origin, CSRF-secret, bevestiging en issueflag.
5. Gebruik in een afzonderlijk goedgekeurde testbuild `OnSiteAPIBaseURL` =
   `https://brebobv.nl/office-test`. De bestaande routes zijn
   `/api/onsite/v1/activate`, `/bootstrap` en `/presence` onder dat prefix.
   SMS `/link/request` en `/link/verify` zijn niet op Office-test vrijgegeven.
   Deze PR bevat geen Codemagic-trigger of wijziging van de geregistreerde iPhone.
6. Browser: projectdetail → gebouwzonekaart → opslaan/herhalen → aanwezigheid.
   Verifieer beheerder, lezer en ander project, plus het verdwijnen van eerdere
   HTTP500/404. Routevermeldingen tellen niet als browserbewijs.
7. Geef een code uit voor de gecontroleerde medewerker; geef opnieuw uit en
   bevestig dat de eerste code 401 krijgt. Activeer de tweede op de iPhone; dezelfde
   code op een ander apparaat krijgt 401. Bootstrap toont uitsluitend toegewezen
   projecten met actieve gekoppelde gebouwzones. Test offline herstel zonder
   verlies van Keychain-token, hervatten en expliciete apparaatintrekking.
8. Stuur IN/UIT met stabiele UUID's: 202; identiek opnieuw: duplicate=true;
   gewijzigde replay: 409; andere medewerker/project/zone: weigering. Het
   aanwezigheidsoverzicht toont de twee event-ID's en `evidence_only`, geen
   goedgekeurde uren. Intrekking, expiry of inactieve medewerker blokkeert het token.

Bewaar tijdstip, broncommit, endpointstatussen en geanonimiseerde event-ID's als
acceptatiebewijs. Geen tokens, codes, persoonsgegevens of configinhoud publiceren.
Rollback: herstel uitsluitend de bewaarde testcode als de huidige hashes nog bij
deze deploy horen; behoud alle medewerkers, apparaten en events. Niets verwijderen.

## Geautomatiseerde validatie

De bestaande PHP 8.5 / MySQL 8 / MariaDB 11.8-matrix draait de regressies en de
nieuwe `OfficeOnSiteIdentityActivationPresenceMySqlTest.php` in een afzonderlijke,
lege CI-database. Die test gebruikt echte bestaande repositories en schema's,
UID 42 → Office 101, tekstuele accounts, uitnodiging/heruitgifte, projectverlies,
expiry, native bootstrap (ook een straal >1000 m), iPhone-eventenvelop, replay,
ACL, intrekking en bewijsprojectie. Bestaande iOS/API-routingcontracten controleren
ook credentialherstel en gelijke native gates. Dat zijn PHP-/broncontracttests,
geen bewijs van een Swift-build of fysieke iPhone-/browseracceptatie.
