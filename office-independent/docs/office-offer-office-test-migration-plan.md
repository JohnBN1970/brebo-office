# Office Offertes — Office-test migratieplan

Status: voorbereid, **niet uitgevoerd**. PR #1470 is nog niet uitgerold.

## Grenzen
- Alleen de Office-test database; nooit productie of de sboffice-bron.
- Geen automatische schema-aanmaak tijdens webrequests.
- Geen mutaties aan bestaande projectcontracten, wijzigingsopdrachten of Finance-tabellen.
- Maak vooraf een herstelbare databaseback-up en verifieer de doel-databasenaam.

## Preflight (allemaal verplicht)
1. Bevestig actieve Office-test release, database-identiteit en gebruikte databasegebruiker zonder wachtwoorden te tonen.
2. Controleer dat de vier nieuwe tabellen nog ontbreken: `office_offers`, `office_offer_versions`, `office_offer_acceptances`, `office_offer_change_links`. Bij bestaande tabellen: stop en vergelijk structuur; niet blind overschrijven.
3. Controleer dat de benodigde bestaande Office-project-, contract- en wijzigingstabellen bestaan en dat projectlidmaatschappen zijn ingericht.
4. Controleer dat er geen actieve webroute of featureflag is die de offertemodule al aanspreekt.
5. Maak en controleer een herstelbare back-up van Office-test.

## Migratie (expliciete operatoractie)
Voer uitsluitend de vier CREATE TABLE-statements uit `OfficeOfferSchema::statements()` en `OfficeOfferChangeLinkSchema::statements()`, in die volgorde. Bewaar een log met tijdstip, schema en resultaat. MySQL DDL commit impliciet: behandel gedeeltelijke uitvoering als mogelijk en hervat alleen na inspectie.

## Acceptatie na migratie
- Alle vier tabellen aanwezig met verwachte unieke sleutels en indexen.
- Bestaande project-, contract- en Finance-tabellen ongewijzigd.
- Geen routes of schrijfacties activeren vóór autorisatie- en sessiecontrole.
- Eerst geïsoleerde rooktest, daarna expliciete beslissing over activering.

## Terugdraaien
Zolang alle vier tabellen leeg zijn en geen module is geactiveerd: verwijder uitsluitend deze vier tabellen in omgekeerde volgorde, na bevestiging van database-identiteit en een back-up. Zodra er gegevens zijn: **geen automatische DROP**; eerst een herstelplan met behoud van data.

## Open punten
- De acceptatie-registratie heeft nog geen volledig geautoriseerde applicatiegrens.
- De projectleestoegang is nu bewust beperkt tot beheerders.
- De huidige CI bewijst domein- en databasegedrag, niet de Office-test deployment of sessie-integratie.
