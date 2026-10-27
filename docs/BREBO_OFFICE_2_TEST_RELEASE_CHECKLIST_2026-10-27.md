# BREBO Office 2.0 — gecontroleerde testrelease

Datum: 2026-10-27. Scope: alleen `/office-test`. Geen productie-deploy.

## Voorwaarden (stop bij rood)
1. Alle relevante GitHub Actions-checks groen op de exacte te deployen commit.
2. Controleer dat de release uit `develop` komt en dat `release-next/private` bij de test-router hoort.
3. Bestaande Hostinger-testconfig en private DB blijven intact; geen productiecredentials of databases hergebruiken.
4. HTTP-authenticatie voor de testomgeving blijft actief. Login, rollen, projectlidmaatschap en bestaande module-flags blijven leidend.
5. Maak een rollback-referentie van de huidige werkende testrelease vóór omschakeling.

## Gefaseerde activering
1. Deploy de geteste code naar de geïsoleerde testrelease, zonder V2-flags te wijzigen.
2. Smoke-test login/logout, startpagina, CRM (indien enabled), Projecten, Inzet/OnSite en de bestaande projectdetailpagina.
3. Zet uitsluitend in de testconfig `OFFICE_TEST_UI_V2_ROLLOUT_ALLOWED=enabled` en `OFFICE_TEST_NAV_V2_ENABLED=enabled`; laat `OFFICE_TEST_WORKSPACE_V2_ENABLED=disabled`.
4. Controleer zes uitklapgroepen, geen dode links naar disabled modules, geen dubbele hoofdnavigatie, behoud van Projecten/Inzet-tabbladen en mobiele breedte.
5. Activeer pas daarna `OFFICE_TEST_WORKSPACE_V2_ENABLED=enabled` en controleer persoonlijke projecttelling, review-only importcontrole en geen gegevens van andere gebruikers.
6. Controleer projectdossier: Basisgegevens, Gebouwen en scope, Projectwerkbank, Inzet en uren; projecttoegang en ongeldige IDs blijven afgeschermd.
7. Noteer bewijs (URL, tijdstip, release-SHA, rol, screenshot en resultaat) per controle.

## Rollback
- Schakel eerst `OFFICE_TEST_UI_V2_ROLLOUT_ALLOWED=disabled` om V2 direct te deactiveren.
- Indien bestaande pagina's regressies vertonen: herstel de eerder vastgelegde werkende testrelease volgens de bestaande releaseprocedure.
- Niet verdergaan bij een autorisatielek, foutieve projectkoppeling of ontbrekende loginbeveiliging.

## Grenzen
Dit document activeert of deployt niets. Een groene CI-run bewijst geen werkende Hostinger-config, database of browserervaring.
