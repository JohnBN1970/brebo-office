# Office-native cirkel: server-side schrijfgrens

Vervolg op PR #1408 (samengevoegd als `d62eda5`) en de stoppositie in
`BREBO_CONTINUITEIT.md`. PR #1407 blijft een afzonderlijke concept-PR;
deze wijziging bouwt op actuele `develop` zonder die PR te mergen.

## Code

`OnSiteOfficeCircleCreateService::create()` ontvangt een geverifieerde
`WebsiteCrmOperatorContext`, een server-side geselecteerd Office-project en
uitsluitend cirkelvelden. De aanroeper mag de context nooit uit formulierdata
construeren. CRM-editrechten zijn geen vervanging voor projectbeheerrechten.

De service valideert de cirkel via `forAuthorizedProject()` en hergebruikt
`OnSiteOfficeZoneCreator`. Die controleert nu project- en eventuele gebouwrechten
binnen de eigen transactie. De service leest en vergrendelt het actieve account,
het `manage`-lidmaatschap en de Office-projectlocatie met `FOR UPDATE`, voordat
de database een zone-ID uitgeeft. Bij een fout worden toewijzing en opslag
teruggedraaid. Bestaande transacties worden geweigerd en intact gelaten.

Geen Drupal-identiteit of -locatie wordt gebruikt. De cirkel wordt opgeslagen
via de bestaande canonical-zone-opslag en is zichtbaar voor de bestaande
geautoriseerde zonelezer. Dit bewijst geen volledige mobiele bootstrapketen.

## Levensduur en herkomst

De nieuwe aanroepgrens gebruikt uitsluitend Office-account, Office-project en
Office-ACL als bron. Het huidige schema bevat geen afzonderlijk herkomstveld.
Deze slice introduceert daarvoor geen migratie en claimt geen persistente
herkomstscheiding. De bestaande 24-uurs versheidseis blijft ook voor deze zones
gelden: zonder gecontroleerde verversing vervalt hun beschikbaarheid. Geen
TTL-uitzondering of verruiming voor externe projecties.

## Verificatie

De MySQL-workflow draait `OfficeOnSiteCircleCreateServiceMySqlTest.php` met
uitsluitend tijdelijke InnoDB-fixtures. Positieve creatie, canonical-zonelezing,
lezer/inactief/onbekend account, ander/ontbrekend project, ontbrekende locatie,
vervalste contextvelden, ongeldige geometrie, ingetrokken rechten, rollback na
allocatie, caller-transactiebehoud en TTL-verval worden gecontroleerd.
Een groene workflow is code-/CI-bewijs; geen testdeploy- of runtime-acceptatie.

## Open vóór een publiek opslagpad

- Werkelijke Office-testkolommen, sleutels en InnoDB-transacties controleren;
  er is hier geen schema-inspectie op Hostinger of migratie uitgevoerd.
- Office-native herkomst en verversings-/inactivatiebeleid expliciet afronden.
- Een sessiegebonden POST-adapter met CSRF, payloadlimiet, same-origincontrole
  en bescherming tegen dubbele requests bouwen en negatief testen. Twee losse
  service-aanroepen zijn twee create-opdrachten; deze service is niet idempotent.
- Daarna pas kaartformulier, testdeploy en browser-/bootstrapacceptatie.

Geen HTTP-route geactiveerd, database gemigreerd, productie gedeployed of
iPhone-build aangepast. Geen merge zonder afzonderlijke vrijgave.
