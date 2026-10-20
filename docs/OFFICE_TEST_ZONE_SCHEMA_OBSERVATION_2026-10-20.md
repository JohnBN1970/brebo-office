# Office-test: werkelijk schema vastgesteld op 20 oktober 2026

Bron: twee door de gebruiker in de bestaande MobaXterm-SSH-sessie uitgevoerde
read-only information_schema-controles. Geen rijgegevens of secrets ontvangen.
Geen databasewijziging uitgevoerd.

## Bevestigd
- office_projects, office_operator_accounts, office_project_memberships,
  office_buildings en office_project_buildings bestaan en gebruiken InnoDB.
- office_building_zones ontbreekt (ook geen kolommen of indexen gevonden).
- office_buildings.id is bigint(20) unsigned, PRIMARY KEY, auto_increment.
  Beschikbare velden: code varchar(128), name varchar(255),
  address_line varchar(255), postal_code varchar(32), city varchar(128),
  created_at timestamp en updated_at timestamp.
- office_projects heeft onder andere id bigint(20) unsigned, code, title,
  status, project_kind, description en timestamps; geen latitude/longitude.
- office_project_buildings heeft project_id/building_id bigint(20) unsigned
  als samengestelde primaire sleutel.
- office_operator_accounts.id en office_project_memberships.operator_id zijn
  varchar(128). Account-ID's zijn opaque tekst, geen gegarandeerde getallen.
  Het account heeft active en can_edit_crm.
- Het gebouwregister heeft geen active, building_name, street_address,
  latitude of longitude. Die velden mogen niet als bestaande data worden gebruikt.

## Grens van het bewijs
Nullability, defaults, collations, alle foreign keys, actuele bronrecords,
geografische juistheid en deployed release-SHA zijn niet met deze uitvoer bewezen.
De CI-fixture weerspiegelt de waargenomen kolommen/typen, niet alle onbekende
serverconstraints.

## Gevolg voor PR #1409
- Bestaande gebouwvelden lezen via aliases, geen dubbele naam/adreskolommen.
- Geen numerieke conversie van Office-account-ID's.
- Geen nieuw gebouw-actiefbeleid afleiden uit een niet-bestaande kolom.
  Zones hebben hun eigen active-status; toegang vereist actuele projectkoppeling.
- Geen project-/gebouwcoördinaten verzinnen of een nieuwe masterlocatie invoeren.
  De kaart start vanuit een opgeslagen gebouwzone of een expliciet ingevoerd
  middelpunt. Buitenkaartprovider blijft pas na inschakeling actief.
- Voor deze database is de fresh-install CREATE-proposal
  office-building-zone-create-review.sql van toepassing, niet de ALTER-proposal.
  Beide zijn uitsluitend reviewbestanden en worden niet automatisch uitgevoerd.


## Door gebruiker bevestigde aanmaak
De gebruiker heeft het bewaakte fresh-installcommando in Moba uitgevoerd en meldde: CREATED office_building_zones in Office-test, aantal zones 0. Bestaande gebouwen/projecten bleven ongewijzigd en de schrijfroute bleef uit. Dit is bewijs van de schema-aanmaak via gebruikersuitvoer; de volledige post-create-preflight, actuele runtimebestanden, testdeploy en browsersmoke zijn nog niet bevestigd.
