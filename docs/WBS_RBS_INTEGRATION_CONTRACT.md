# WBS en RBS in BREBO Office — integratiecontract

Status: ontwerp en implementatiecriteria; geen bewijs van geinstalleerde functionaliteit.

## Canonieke bronnen
- WBS groepeert projectwerk in hierarchische werkpakketten, met een unieke code per project.
- Bestaande calculatie, werkbegroting en planning blijven eigenaar van hun bedragen, activiteiten en datums. WBS legt verwijzingen vast, nooit een tweede begroting.
- RBS classificeert bestaande risico's uit office_risks. De bestaande OfficeGovernanceService blijft eigenaar van status, kans, impact, maatregelen, revisies en bevoegdheden.
- Projecten en gebouwen blijven gekoppeld via de bestaande Office-project-gebouwrelatie. Drupal blijft buiten Office.

## Implementatie-eisen
1. Controleer bestaande DDL, router, featureflags, project-ACL en migrator alvorens schema toe te voegen.
2. Maak een additieve werkpakkettabel met project_id, parent_id, code, titel, omschrijving, eigenaar, status, sortering en revisie. Valideer parent-projectgelijkheid en verbied cycli.
3. Maak een RBS-categoriehierarchie, plus referentiekoppelingen van bestaande office_risks naar RBS-categorie en optioneel WBS-pakket. Dupliceer nooit de risicoregistratie.
4. Koppel werkpakketten via getypeerde referenties aan bestaande calculatie-, werkbegrotings- en planningsobjecten, na bronvalidatie en projectrechtencontrole.
5. Voeg WBS- en RBS-tabs toe op het bestaande projectdetail met inklapbare boom, lijst, filter en bewerkacties. Geen parallelle login of los projectdossier.
6. Toon een 5x5 risicomatrix op basis van kans maal impact, met afzonderlijke weergave van gesloten risico's. KPI's: pakketten, voortgang, budgetafwijking, open hoge risico's, verlopen maatregelen. Onbekende bronwaarden tonen als niet gekoppeld, niet als nul.
7. Vereis Office-sessie, project-ACL, CSRF, optimistic locking en audit bij elke mutatie. Risicowijzigingen lopen uitsluitend via bestaande governance-contracten.
8. Registreer de migratie expliciet in de centrale Office-test-migrator met checksum. Seed uitsluitend herkenbare fictieve testrecords op Office-test.
9. Test boom, lijst, cycli, projectisolatie, rechten, CSRF, revisieconflict, audit, KPI's en alle modulekoppelingen. Voer de centrale Office-test CI/deployment uit en verifieer HTTP-acceptatie.
10. Geen productie-uitrol zonder afzonderlijke releasegoedkeuring.

## Acceptatiebewijs
Rapporteer afzonderlijk: code gebouwd, automatische tests groen, schema op Office-test, HTTP-gebruikersketen geaccepteerd en productie ongemoeid. Een document of groene CI alleen betekent niet dat de functie werkt.
