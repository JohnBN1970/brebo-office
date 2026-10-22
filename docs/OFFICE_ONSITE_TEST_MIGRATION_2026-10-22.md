# Office-test OnSite inbox en apparaattokens

Deze migratie maakt uitsluitend ontbrekende `office_onsite_event_inbox` en
`office_onsite_device_tokens` aan volgens de bestaande review-schema's.
Geen productie, imports, backfill, medewerkercreatie, tokenuitgifte, activering,
routerwijziging of aanpassing van configuratie/featureflags. De migratie wordt
nooit door een webrequest of applicatiebootstrap aangeroepen.

## Voorwaarden en grenzen

- Alleen CLI, vaste Office-test-config en vaste release-root
  `/home/u213420663/domains/brebobv.nl/office_calc_test/release-office-test-20261018/private`.
  Symlinks naar andere locaties worden geweigerd. Een toekomstige release vraagt
  om een afzonderlijke review van deze begrenzing.
- Alleen database en account `u213420663_office_test`, lokale MySQL-host en
  `/office-test` op `brebobv.nl`. Geen DSN- of doeloverride via argumenten of environment.
- Preflight/apply weigeren als de gezamenlijke runtimepoort
  `OFFICE_ONSITE_SCHEMA_VERIFIED` in config of het CLI-environment al `enabled`
  is. Laat die poort handmatig uit gedurende dit werk; de migratie wijzigt geen
  flags. Zo kan het aanmaken van tabellen bestaande endpoints niet als bijeffect
  activeren. Controleer ook eventuele afzonderlijke webserver-overrides handmatig.
- `office_inzet_people` moet al bestaan met InnoDB, unsigned bigint primaire
  identiteit en niet-null tinyint `active`. De migratie wijzigt deze tabel nooit.
- Bestaande doeltabellen moeten exact compatibele kolommen, defaults, sleutels,
  InnoDB en een lokale restrictieve person-FK hebben; geen triggers. Afwijkingen
  blokkeren de hele uitvoering voordat de eerste ontbrekende tabel wordt gemaakt.
  Er is bewust geen automatische ALTER/reparatie.
- Het rapport toont uitsluitend statussen en aantallen. Geen namen, tokens,
  tokenhashes, persoonsgegevens of databasewachtwoorden.
- De database-advisory lock sluit gelijktijdige uitvoeringen van deze migratie uit.
  Stop andere schemawerkzaamheden en OnSite-consumenten handmatig gedurende het
  onderhoudsvenster. De lock blokkeert geen reguliere applicatieschrijvers.

## Voorbereiden en preflight (alleen lezen)

Maak eerst een versleutelde, herstelbaar geteste back-up van de Office-testdatabase
en de bestaande testcode/configuratie. Bewaar die buiten public_html en buiten
CI-artifacts. Noteer vooraf de bestaande flags en actieve testconsumenten. Zet
consumenten zo nodig via de bestaande beheerprocedure stil; dit pakket doet dat
niet en vereist geen activering om het schema te kunnen aanmaken.

CI publiceert `office-onsite-test-migration-20261022.zip` met vier PHP-bestanden,
dit draaiboek en een manifest met SHA-256 per bestand. Pak het handmatig uit in
een nieuwe private map onder `office_calc_test`, buiten public_html. Behoud de
mapstructuur: de migratieklasse gebruikt de meegeleverde schema's onder `src/Inzet`.
Controleer de bestandskenmerken tegen het manifest uit de vertrouwde CI-build.

Vanuit de uitgepakte pakketmap:

```sh
php deployment/hostinger/office-onsite-test-migrate.php --preflight
```

`READY_TO_APPLY` betekent dat alleen ontbrekende tabellen aangemaakt kunnen
worden. `SCHEMA_VERIFIED` betekent dat beide al compatibel bestaan. `BLOCK` of
exitcode 1/2 vraagt onderzoek; niet omzeilen. Preflight maakt geen tabellen.
De afzonderlijke oudere `office-onsite-schema-preflight.php` controleert ook
legacy-tabellen en vormt geen vervanging voor deze migratiecontrole.

## Handmatig uitvoeren en postflight

Pas na beoordeling van preflight en back-up, expliciet:

```sh
php deployment/hostinger/office-onsite-test-migrate.php --apply --confirm=office-test-onsite-schema-20261022
php deployment/hostinger/office-onsite-test-migrate.php --postflight
```

Verwacht `SCHEMA_VERIFIED`, `activation: UNCHANGED`, `data_changes: NONE` en een
lijst met alleen werkelijk aangemaakte tabellen. Postflight retourneert BLOCK
wanneer nog een tabel ontbreekt. Herhaling van apply maakt niets opnieuw aan en
laat bestaande rijen intact. Bij een fout kan een eerste CREATE al gecommit zijn:
MySQL/MariaDB DDL is niet transactioneel terug te draaien met PDO rollback.
Herhaal eerst preflight en daarna dezelfde expliciete apply om veilig te hervatten.

## Apparaten en medewerkers: afzonderlijke acceptatie

De echte Office-code gebruikt SHA-256 van het aangeboden apparaatcredential in
`office_onsite_device_tokens.token_hash` en de FK `person_id` naar
`office_inzet_people.id`. Alleen actieve medewerkers met niet-ingetrokken,
niet-verlopen tokens kunnen authenticeren. Meerdere apparaten per medewerker
zijn toegestaan. Pre-/postflight tellen verweesde koppelingen (BLOCK), koppelingen
naar inactieve medewerkers, bruikbare apparaten en waar beschikbaar apparaten
met een bestaande projectteam-/projectkoppeling. Ongeldige hashvormen blokkeren.
Een schema PASS met nul bruikbare apparaten bewijst geen werkende appkoppeling.

Drupal bewaart apparaten via `DrupalOnSiteDeviceStore` in KeyValue-collectie
`brebo_inzet.onsite_devices`; medewerkers zijn Drupal-users. Legacy
`onsite_device_tokens`/`onsite_event_inbox` vormen ook een andere gegevensgrens.
Het rapport vermeldt alleen de aanwezigheid van legacy-tabellen en verklaart de
externe identiteitsbrug expliciet ONGEVERIFIEERD. Het opent geen Drupal-verbinding,
deserialiseert geen credentials en kopieert niets. Een toevallig gelijk numeriek
Drupal-UID of legacy employee-ID is geen bewezen Office-medewerker-ID.

Controleer vóór een latere, afzonderlijk goedgekeurde activering voor elk bestaand
testapparaat in welke authority het credential leeft en welke geverifieerde
Office-medewerker/projecttoewijzing erbij hoort. Gebruik een expliciet beoordeelde
identiteitsmapping of een afzonderlijk goedgekeurde Office-activatie; deze migratie
maakt die koppeling niet. Verifieer daarna met een bestaand Office-testapparaat:
bekende actieve medewerker geaccepteerd, onbekend/inactief/verlopen/ingetrokken
credential geweigerd, project-/zonerechten gecontroleerd, herhaald event niet
verdubbeld en afwijkende replay geweigerd. Deel geen tokens of medewerkergegevens
in de console. Serveracceptatie en daadwerkelijke apparatenmapping staan nog open.

## Rollbackprocedure met behoud van gegevens

```sh
php deployment/hostinger/office-onsite-test-migrate.php --rollback-plan
```

Dit is alleen een inspectie en procedure, geen automatische down-migratie.
Bij compatibel schema is het resultaat `RETAIN_TABLES_AND_DATA`. Bij BLOCK kan
de inspectie de toestand niet certificeren; laat tabellen en data ook dan staan.

1. Stop testconsumenten handmatig via de bestaande beheerprocedure. Maak een
   aanvullende back-up wanneer er sinds de eerste back-up events/tokens bij zijn.
2. Herstel zo nodig de vorige testcode en expliciet vastgelegde testconfig vanuit
   de beoordeelde back-up. Als alleen deze migratie is uitgevoerd, zijn code,
   configuratie en flags niet veranderd en is daar geen herstel nodig.
3. Laat beide tabellen en alle events/tokens staan, ook als ze leeg lijken.
   Hiermee blijven bestaande apparaten en auditgegevens behouden. Dit pakket
   bevat geen DROP, DELETE, tokenrevocatie of automatische herstelactivering.
4. Controleer de vorige testfunctionaliteit en voer opnieuw preflight uit voor
   een toekomstige hervatting. Structurele verwijdering of databaseherstel is
   een aparte, expliciet te beoordelen operatie met exclusief onderhoudsvenster,
   geverifieerde eigendom en back-up; voer geen blinde DROP uit.

## CI

De bestaande PHP 8.5-matrix draait de nieuwe tests op MySQL 8 en MariaDB 11.8 in
de aparte database `office_onsite_migration_ci`. De tests dekken doelbegrenzing,
mutatievrije preflight, ontbrekende parent, eerste/herhaalde/onderbroken apply,
lockconflict, schemadrift, primaire sleutel/FK/triggercontroles, verweesde en
ongeldige credentials, bestaande medewerker-/projectkoppeling, geweigerde
apparaten, inbox-idempotentie en behoud van gegevens bij rollbackinspectie.
CI maakt uitsluitend fixtures aan; geen Hostingerverbinding of deployment.
