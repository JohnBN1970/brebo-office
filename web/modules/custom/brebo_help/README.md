# BREBO Help

Centraal helpcentrum voor BREBO Office op `/brebo/help`.

## Principes

- Office blijft de bron; Help legt de bestaande werking uit en introduceert geen tweede bedrijfsadministratie.
- Inhoud is taak- en procesgericht: wat doet de gebruiker, waarom doet BREBO dit zo en waar hoort de informatie thuis.
- Zoeken werkt zonder extra zoekinfrastructuur zodat de module veilig als zelfstandige eerste help-laag kan worden uitgerold.
- `?context=<term>` kan vanuit Office-schermen worden gebruikt om relevante artikelen bovenaan te zetten.
- Toegang loopt via `access brebo help`; beheeruitbreidingen via `administer brebo help`.

## Uitbreidpad

De huidige catalogus is bewust centraal in `HelpController::articles()` gehouden zodat deze PR zonder nieuwe contenttypes, databasevelden of configuratie-afhankelijkheden kan landen. Een volgende stap kan de catalogus achter een repository/service plaatsen en beheerbare kenniscontent aansluiten zonder routes of gebruikersinterface te breken.

Contextuele links kunnen bijvoorbeeld verwijzen naar `/brebo/help?context=finance`, `/brebo/help?context=project` of `/brebo/help?context=clock`.

## Acceptatie

1. Module inschakelen: `drush en brebo_help -y`.
2. Cache legen: `drush cr`.
3. Geef de gewenste rollen `access brebo help`.
4. Controleer `/brebo/help`, zoeken en minimaal één artikel.
5. Controleer dat een gebruiker zonder permissie HTTP 403 krijgt.
6. Controleer een contextlink, bijvoorbeeld `/brebo/help?context=finance`.

## Rollback

De module bevat geen schema en schrijft geen bedrijfsdata. Rollback is daarom: module uitschakelen/verwijderen en cache legen. Er is geen datamigratie nodig.
