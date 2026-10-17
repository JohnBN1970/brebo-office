# SBOFFICE - inventarisatie voor hergebruik in office-test

Status: werkdocument. Aanwezigheid van onderstaande functionaliteit is nog geen verificatie van concrete bronbestanden, API's of databasetabellen in SBOFFICE. Geen migratie of deployment uitgevoerd.

| Domein | Te inspecteren in SBOFFICE | Doel in office-test | Acceptatie |
| --- | --- | --- | --- |
| Inzet | Projectteams, planning, klokzones, sessies, bevoegdheden | Office Inzet + OnSite | Persoon/projecttoegang, zone, automatische events en review werken |
| Calculatie | Rekenregels, deelcalculaties, hoofdstukken, versies | Office Calc | Bestaande berekeningen en offertes reproduceerbaar |
| Finance | Debiteuren, inkoop, leveranciers, Moneybird-koppeling | Office Finance | Zelfde leveranciersidentiteit, bedragen en audit |
| Inkoop | Leveranciers, tarieven, inhuurafspraken | Inzet + Procurement | Factuurmatching op leverancier en goedgekeurde weekstaten |
| Documenten | Intake, extractie, uploads, classificatie | Office documentkern | Bron, versie, privacy en herleidbaarheid |
| Gebouwen | Gebouw, bouwdeel, element, component | Office gebouwwaarheid | Eenduidige project-/gebouwkoppeling |
| Rapportage | Sjablonen, rapportgenerator, MJOP | Office rapportage | PDF/rapport met gecontroleerde brondata |
| Autorisatie | Accounts, rollen, projectrechten | Centrale Office-ACL | Geen rechtenovername via onbetrouwbare oude IDs |

## Inspectie per module

Vul pas na codeonderzoek in: repo/path, huidige teststatus, Drupal-koppelingen, externe API's, DB-tabellen, datamigratiebehoefte, voorgestelde Office-interface, risico's, eigenaar en acceptatietest.

## Prioriteit nu

1. Inventariseer de bestaande Inzet- en Finance-implementaties in de SBOFFICE-bron.
2. Sluit de XLSX-importer aan op de bestaande dagvergelijking, met review van onzekere project- en persoonsmapping.
3. Maak de urencontrole en leveranciersfactuurmatching in office-test zichtbaar.
4. Integreer pas daarna mobiele OnSite-events en projectkostenprognoses.

**Stopconditie:** geen code rechtstreeks uit SBOFFICE kopieren zonder herkomst, licentie/afhankelijkheden, security en tests te beoordelen.
