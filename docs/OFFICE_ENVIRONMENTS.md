# BREBO Office — omgevingen en verantwoordelijkheden

**Vastgelegd op 15 oktober 2026 — expliciete opdracht van de projecteigenaar.**

## Canonieke betekenis

- **sboffice = sandbox / ontwikkel- en testomgeving.** Hier worden BREBO Office-modules gebouwd, geïntegreerd, getest en geaccepteerd. De naam `sboffice` duidt niet op de productieomgeving.
- **office = productieomgeving.** Dit is de omgeving die eindgebruikers uiteindelijk gebruiken. Alleen gevalideerde, vrijgegeven wijzigingen mogen hier terechtkomen.
- **Calc** is functioneel een module binnen BREBO Office, ook als de runtime technisch afzonderlijk is gehost.

## Verplichte werkwijze

1. Onderzoek eerst bestaande functionaliteit in `sboffice`; bouw geen tweede versie van reeds aanwezige modules.
2. Ontwikkel en test wijzigingen in sandbox of een expliciet geïsoleerde testomgeving. Gebruik nooit de productie-database als testdatabase.
3. Promoveer wijzigingen pas naar `office` na geslaagde functionele, autorisatie-, integratie- en deploymentcontroles en expliciete vrijgave.
4. Houd de canonieke project-, gebouw-, financiële en calculatiegegevens consistent. Geen nieuwe parallelle bron van waarheid.
5. Een bestaande GitHub-workflow, directorynaam of oudere documentatie die `sboffice` 'production' noemt, is **geen bewijs** dat die omgeving daadwerkelijk productie is. Verifieer de werkelijke Hostinger-deploytargets, hostnamen, databaseverbindingen en workflowgevolgen voordat workflows worden aangepast of gestart.
6. **Geen automatische herinterpretatie van bestaande deploymentpijplijnen.** De oudere documenten `docs/architecture/production-deployment-principle.md` en `docs/BREBO_CONTINUITEIT.md` bevatten productieaanduidingen voor `sboffice`. Dit is een expliciet te reconciliëren documentatie-/configuratieconflict; wijzig of start die workflows niet zonder audit.

## Actuele technische observaties (niet gelijkstellen aan omgevingsbeleid)

- Op Hostinger is een zelfstandige PHP-router aangetroffen bij `public_html/office/index.php`, met private bestanden onder `office_private`.
- Er bestaat een `sboffice` Drupal-applicatiestructuur in de repository.
- Een afzonderlijke Calc Node.js-runtime is aangetroffen.
- De exacte productie- en sandboxdatabase, publieke domeinroutering en deployworkflow-targets moeten nog expliciet worden geverifieerd.

**Leidende afspraak: sboffice = sandbox; office = productie.**

## Deployment-audit — 15 oktober 2026

Gecontroleerd in `.github/workflows/sboffice-deploy-develop.yml`:

- Workflow heet `Deploy BREBO Office Production`, triggert automatisch op push naar `develop` (behalve uitsluitend `config/sync/**`) en kan handmatig worden gestart.
- De feitelijke SSH-doelmap is `$HOME/domains/brebobv.nl/public_html/sboffice`; Drush gebruikt `--uri=https://sboffice.brebobv.nl`.
- De workflow voert `rsync --delete`, module-enablement, `drush updb` en `drush cr` uit. Dit is een **muterende** workflow, niet slechts een codekopie.
- **Afwijking:** de workflowbenaming en oude architectuurdocumentatie zeggen 'production', maar de projecteigenaar definieert `sboffice` als sandbox en `office` als productie.
- **Beveiligingsregel:** merge naar `develop` of handmatige dispatch kan de `sboffice`-omgeving direct veranderen. Geen wijziging van deploytarget naar `office` zonder afzonderlijk getest releaseproces.
- Nog te verifiëren: werkelijke databaseverbinding achter `sboffice/web/sites/default/settings.php`, sandbox-backups/rollback, exacte DNS/webroot en de productiedeployroute naar `office`. Deel geen credentials.

Dit is een repository-audit; er is geen workflow uitgevoerd en geen Hostinger-omgeving aangepast.
