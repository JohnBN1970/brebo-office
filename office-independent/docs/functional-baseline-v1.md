# BREBO Office — Functionele baseline v1.0

## Keten
Intake → Lead → Deal → optionele Calculatie/dienstenbegroting → Offertestudio → Onderhandeling → Acceptatie → Projectinitiatie → Werkbegroting → Take-off → Voorbereiding → Uitvoering → Oplevering → Onderhoud (indien van toepassing) → Gereed. Directe opdrachten blijven mogelijk. Take-off is een zelfstandig projecttabblad binnen voorbereiding, geen extra projectfase.

## Eigenaarschap
Deal: geaccepteerde scope en verkoopprijs. Calculatie: oorspronkelijke kosten en uren, revisies behouden. Werkbegroting: vastgestelde kostprijs, arbeidsuren en hoeveelheden; gecontroleerde revisies, geen automatische wijziging door commerciële korting, planning of inkoop. Planning: geplande inzet. OnSite: werkelijke uren. Inkoop: behoeften per begrotingsregel, totalisatie, prijsaanvraag, vergelijking, order, verplichting, deellevering en factuurcontrole. Finance: facturen, boekingen en betalingen. Resources: zelfstandig tabblad en dashboard-KPI's voor arbeid, materiaal, materieel, onderaanneming en overige kosten, met verdieping naar bronregistratie. Geen dubbele administratie.

## Organisatie
Projectleider, assistent-projectleider (andere persoon), werkvoorbereider (rol mag gecombineerd worden), start- en einddatum. Deal: verantwoordelijke, menselijke back-up en calculator/opsteller waar relevant. Mandaten centraal configureerbaar per rol en handeling; binnen mandaat geen extra verplichte controle, buiten mandaat bevoegde menselijke goedkeuring. Digitale resultaten altijd bevoegde menselijke controle vóór vrijgave. Bestaande beveiliging behouden; rolpreview is geen recht.

## Categorieën en uitvoering
Meerdere bestaande categorieën per Deal/project; categorie stuurt beschikbaarheid van instrumenten, fase stuurt proces. Inmeting is een taak (Measure), bestaande historie behouden. MJOP en projectmanagement als diensten meenemen na controle bestaande categoriebron. Meerdere gebouwen mogelijk. Take-off omvat overdracht, startoverleg, werkmap, tekeningen, instructies, besluiten, taken en uitvoeringsgereedheid. WBS/RBS, kwaliteit, VCA, rapportages, opleverformulier, restpunten, nazorg en financiële afsluiting aansluiten.

## Geconstateerde afwijkingen (develop d591540457451060954ad75deed777041769c59b)
- `src/Demo/OfficeCommercialCalcHandoff.php`: inmeting nog categorie; controleer alle consumenten en historische snapshots vóór migratie.
- `src/Approval/OfficeApprovalPolicy.php`: procesgebonden single/four-eyes, nog geen rolmandaat in deze klasse. Onderzoek bestaande autorisatie en governance vóór uitbreiding.
- `src/Approval/OfficeTestRolePermissionPolicy.php`: RL-015 virtuele staf aanwezig; preview verleent geen rechten. Onderzoek effectieve identiteit en vrijgavepaden.
- `src/Project/OfficeProjectKickstartPolicy.php`: bestaande dossiercontrole en tweede goedkeuring. Hergebruik, wijzig geen beveiliging zonder bewezen vervangend beleid.
- WorkingBudget, Inzet, Governance en Project bevatten bestaande inkoop-, uren-, begrotings- en projectcomponenten; bestandsinventarisatie is geen bewijs van werking.

## Acceptatie
Per domein bron, eigenaar, routes, schema, autorisatie, overdracht en regressietests aantonen. Geen productie-uitrol; eerst PR-controles en integrale Office-test. Open punten: meer/minderwerk, termijnfacturatie, liquiditeitsprognose en evaluatie op ketenniveau verifiëren.
