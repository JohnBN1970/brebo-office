# Kwaliteit, risico's en inkoop — aansluitcontract Office

Peildatum: 8 november 2026. Werkbranch: `integration/quality-risk-procurement-20261108`.
PR: [#1725](https://github.com/JohnBN1970/brebo-office/pull/1725).

## Besluitbasis

Bestaande domeinregels hergebruiken. Office bezit de operationele waarheid; opslag,
HTTP, sessie en presentatie zijn adapters. Het gebouw is permanent, het project is
context voor tijdelijke werkzaamheden. Projectlidmaatschap verleent niet automatisch
modulebevoegdheden. Office- en Drupal-identiteiten zijn afzonderlijke namespaces.

Bronnen: `BREBO_CONTINUITEIT.md`, `CIM.md`, `ACTION_SIGNAL_RISK_ASSESSMENT.md`,
`OFFICE_WORKING_CHAINS_2026-10-18.md`, de actuele broncode op `develop` en de
actieve Office-testdatabase. De integratieregel verbiedt nieuwe tabellen/endpoints
zolang canonieke bron en contract niet vaststaan. Daarom activeert deze PR geen
gebruikersroute, nieuwe administratie of automatische identiteitskoppeling.

## Bevestigd runtimebewijs

[Run 42442102336](https://github.com/JohnBN1970/brebo-office/actions/runs/42442102336)
is geslaagd op commit `7f261f620213964215132b5d54e6285021e73958`.
De controle gebruikt uitsluitend de geïsoleerde Office-testconfiguratie en een
read-only databasetransactie. Geen dossierinhoud of persoonsgegevens worden gelogd.

| Onderdeel | Waarneming | Betekenis |
| --- | --- | --- |
| Gebouwen/projecten | `office_buildings`, `office_projects`, `office_project_buildings` aanwezig | Office-identiteiten beschikbaar |
| Autorisatie | Operatoren, sessies, projectlidmaatschappen en modulepermissies aanwezig | Hergebruik bestaande Office-sessie; modulemandaat afzonderlijk toetsen |
| Inspecties | `office_inspections`, bevindingen, bewijs en rapportconfiguratie aanwezig | Bestaande gebouwgebonden inspectiebron hergebruiken |
| Risico's | Geen tabel met risico-/risknaam aangetroffen in de onderzochte Office-tabellen | Geen bewezen zelfstandige risicobron; geen nulrisico-KPI tonen |
| Inkoop | `brebo_finance_commitment`, regels en prestatieontvangsten aanwezig | Bestaande verplichtingenbron aantoonbaar aanwezig |
| Identiteit | `office_finance_project_mapping` aanwezig, **0 geverifieerde koppelingen** | Verplichtingen nog niet aan Office-projecten toewijzen |
| Procurement | `brebo_procurement_decision` en `brebo_procurement_award` ontbreken | Beslis-/gunningsketen niet operationeel in deze testdatabase |

De releasevingerafdruk van deze controle is
`40385e122361129d21141c9c2703c4a5ab6fd085d70b51da9bfb992b12680907`.
Bestandshashes van de actieve toegangs-, navigatie- en projectadapters staan in de
run. Deze hashes zijn geen verklaring dat de release gelijk is aan `develop`.

## Bronnen en adaptergrenzen

| Keten | Bestaande canonieke betekenis | Aansluiting | Vrijgaveblokkade |
| --- | --- | --- | --- |
| Kwaliteit | `brebo_verification` met verplichte productpositie; resultaat, bewijs, blokkeert-vrijgave. `brebo_deviation` voor afwijking en herstel | Kwaliteitsdomein achter contract; inspectiebevindingen blijven afzonderlijke bronobjecten met gebouw/project | Geen bewezen mapping van productpositie, controle en afwijking naar native Office-gebouw/project |
| Risico | `brebo_risk`: oorzaak, gebeurtenis, gevolg, kans, impact, maatregel, eigenaar, termijn, restrisico, status, bron en context | Dezelfde semantiek via frameworkvrij contract; actor/context door adapters vertalen | Geen bewezen native opslag of overdrachtsbesluit voor bestaande risico-objecten |
| Inkoop | Verplichtingen en prestatieontvangsten in Finance; leverancierskeuze/gunning via Procurement-services | Bestaande `ProcurementControlRepositoryInterface` met Office-opslagadapter; Finance blijft eigenaar van verplichtingen en facturen | Geen geverifieerde projectidentiteit, beslis-/gunningstabellen ontbreken; actor-/mandaatmapping niet bewezen |

Inspectie is niet automatisch een formele kwaliteitsgoedkeuring. Een financieel
`quality_accepted`-veld is geen zelfstandig kwaliteitsdossier. Een inkoopfactuur is
niet hetzelfde als een inkoopopdracht. Deze betekenissen blijven gescheiden.

## Vereiste contracten vóór implementatie/activatie

1. **Bronbesluit per keten.** Bepaal of bestaande objecten met expliciete identiteit
   worden overgedragen of dat de native Office-runtime hun nieuwe canonieke opslag
   wordt. Geen parallelle schrijfbare waarheid. Ontwerp een migratie pas na dit
   besluit; bestaande fictieve Drupal-projecten zijn geen Office-testprojecten.
2. **Context.** Elk relevant dossierobject behoudt een native gebouwrelatie en
   optionele projectrelatie. Valideer de combinatie aan `office_project_buildings`.
   Een gebouwgebonden item zonder project vereist een aantoonbaar gebouwmandaat;
   het ontbrekende mandaat wordt niet vervangen door toegang tot een willekeurig
   project van hetzelfde gebouw.
3. **Toegang.** Actieve Office-sessie, specifieke modulebevoegdheid en toegang tot
   het geselecteerde project/object zijn alle vereist. Geen rechten ontlenen aan
   `can_edit_crm`, een zichtbare tegel of alleen `manage` op een project.
4. **Kern.** Domeinregels blijven frameworkvrij. Actoren, rollen en context gaan als
   vertrouwde waarden naar de kern; PDO, Drupal entities en sessies blijven in
   adapters. Geen rechtstreeks hergebruik van Drupal-user-ID als Office-operator.
5. **Writes.** Alleen geautoriseerde POST met CSRF, revisiecontrole, transactie,
   idempotentie en audittrail. Kern bewaakt verplichte risico-inhoud, herstelbewijs,
   formele sluiting en bestaande Procurement-beslis-/gunningsvoorwaarden.
6. **Projecties.** Dossier en dashboard lezen dezelfde objecten. Onbekende of
   ongekoppelde bron wordt 'niet aangesloten', nooit nul, groen of vrijgegeven.
   Gebouwhistorie blijft bestaan na afsluiten van een project.

## Voorgestelde gebruikersroutes na bronvrijgave

Dit zijn gereserveerde ontwerpbestemmingen, **geen geïmplementeerde endpoints**.

| Werkplek | Overzicht | Context |
| --- | --- | --- |
| Kennis & kwaliteit | `/office-test/kwaliteit` | Optioneel project/gebouw, server-side gevalideerd |
| Kennis & kwaliteit | `/office-test/risicos` | Dezelfde context; eigen risicolifecycle |
| Bedrijfsvoering | `/office-test/inkoop` | Verplichtingen, leverancierskeuze, gunning en prestaties; afzonderlijke Finance-factuurlink |

Project- en gebouwpagina's gebruiken deze zelfde bestemmingen met context. Geen
extra projectregistratie, tweede inspectieregister of module-eigen login.

## Acceptatie en resterende uitvoering

- **Code:** audit gebouwd; bestaande modules en broncontracten geïnventariseerd.
- **CI/runtime audit:** PHP 8.3 lint en servercontrole groen in bovengenoemde run.
- **Adapters en gebruikersroutes:** niet geïmplementeerd; geblokkeerd op bron- en
  identiteitsbesluiten hierboven.
- **Databasewijzigingen en testdeploy:** niet uitgevoerd.
- **Gebruikersacceptatie:** niet uitgevoerd; de drie modules zijn niet aangesloten.

Vervolg na bronbesluit: adapters bouwen, echte bronnen aan dossiers koppelen,
positieve en negatieve toegangstests uitvoeren, vervolgens bestaande testreleaseketen
gebruiken en de drie gebruikersstromen controleren. Geen eigen moduledeploystraat.
De auditworkflow is uitsluitend diagnostiek en geen deployment.
