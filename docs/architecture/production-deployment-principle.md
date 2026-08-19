# BREBO Office — centraal productie-deployprincipe

Status: vastgesteld architectuur- en ontwikkelprincipe.

## Doel

BREBO Office gebruikt één centrale, controleerbare productiedeployketen. Modules en features introduceren geen eigen alternatieve Hostinger-deploymethode.

## Standaardketen

featurebranch → pull request → CI/acceptance groen → merge naar `develop` → centrale GitHub Actions productiedeploy → Hostinger → Drupal database-updates → cache rebuild → live smoketest.

Een wijziging geldt pas als afgerond wanneer alle relevante stappen groen zijn:

1. CI en acceptance checks;
2. productiedeploy;
3. Drupal database-updates;
4. Drupal cache rebuild;
5. relevante live smoketests.

Bij een fout stopt de deployment. Automatische rollback wordt als volgende veiligheidslaag toegevoegd.

## Bestaande Hostinger-infrastructuur

Gebruik de bestaande GitHub repository secrets:

- `DEPLOY_HOST`
- `DEPLOY_PORT`
- `DEPLOY_USER`
- `DEPLOY_SSH_KEY`

Bekende serverpaden:

- applicatie: `~/domains/brebobv.nl/public_html/sboffice_app`
- publieke BREBO Office-koppeling: `~/domains/brebobv.nl/public_html/sboffice`

De bestaande workflow `.github/workflows/sboffice-webroot-repair.yml` is de referentie voor SSH-configuratie, Hostinger-connectiviteit en deze paden.

## Regels voor alle BREBO Office-ontwikkeling

- Geen handmatige productie-release als normale werkwijze.
- Geen module-specifieke alternatieve deploypipelines wanneer de centrale pipeline volstaat.
- Nieuwe modules moeten deploybaar zijn via de centrale keten.
- Module-specifieke acceptance gates mogen bestaan en voeden de centrale releasebeslissing.
- Productiedeploys moeten reproduceerbaar en via GitHub Actions gelogd zijn.
- `drush updb -y` en `drush cr` behoren tot de centrale releaseprocedure.
- Na deploy worden kritieke live routes gecontroleerd; minimaal de BREBO Office-home en relevante gewijzigde modulepagina's.
- Een mislukte database-update, cache rebuild of smoketest maakt de deployment rood.
- Secrets worden uitsluitend via GitHub Secrets gebruikt en nooit in repositorybestanden opgeslagen.

## Architectuurprincipe

Git is de bron voor applicatiecode; `develop` is de integratiebasis voor productie-uitrol. Hostinger is een deployment target en geen alternatieve bron van waarheid voor applicatiecode.

Dit principe geldt BREBO-breed voor alle huidige en toekomstige BREBO Office-modules.