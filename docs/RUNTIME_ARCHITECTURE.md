# BREBO Office runtime-architectuur

## Vast architectuurprincipe

> GitHub mag BREBO Office bouwen, testen en deployen, maar BREBO Office mag GitHub niet nodig hebben om te functioneren.

BREBO Office moet op productie zelfstandig blijven functioneren wanneer GitHub of GitHub Actions tijdelijk niet beschikbaar is.

## Verantwoordelijkheden

### Productieruntime

De productieruntime draait op de productieomgeving en omvat ten minste:

- Drupal en PHP;
- database en bestanden;
- Drupal cron;
- Drupal queues en queue workers;
- geplande applicatietaken;
- Mail Intake en andere periodieke verwerking;
- runtime logging;
- noodzakelijke externe integraties via hun eigen runtime-koppelingen.

Periodieke bedrijfsprocessen mogen niet afhankelijk zijn van een GitHub Actions `schedule`.

### GitHub

GitHub wordt gebruikt voor:

- broncode en versiebeheer;
- featurebranches en pull requests;
- CI en acceptance tests;
- release- en deployautomatisering;
- technische kwaliteitscontrole.

GitHub Actions mag een nieuwe versie naar productie deployen, maar is geen scheduler of runtime worker voor BREBO Office.

## Cron en achtergrondverwerking

Drupal is het centrale applicatiepunt voor periodieke verwerking. Waar een externe systeemcron nodig is, roept de productieomgeving Drupal aan, bij voorkeur via `drush cron`. Drupal verdeelt het werk vervolgens via cron hooks en queues.

Voor Mail Intake geldt bij heractivering:

1. productiecron start Drupal cron;
2. Drupal plant/verwerkt Mail Intake via de daarvoor bedoelde cron/queue-logica;
3. locking, retries, logging en statusbewaking blijven binnen BREBO Office;
4. er wordt geen periodieke GitHub Actions runner gebruikt.

## Continuiteitsregel

Een storing bij GitHub mag geen bestaande BREBO Office-runtimeprocessen stilleggen. Alleen ontwikkeling, CI en nieuwe deployments mogen daardoor tijdelijk niet beschikbaar zijn.

Nieuwe modules en workflows moeten aan dit principe worden getoetst voordat ze als productiegeschikt worden beschouwd.
