# BREBO Office - vaste ontwikkel- en testafspraak

Status: vastgesteld op 15 november 2026. Geldt voor alle volgende Office-ontwikkelwerkzaamheden.

## 1. Omgevingen
- **Office-test** is de ontwikkel-, integratie- en acceptatieomgeving. Hier mogen zonder herhaalde toestemming fictieve projecten, medewerkers, relaties, calculaties, startdossiers, documenten en andere noodzakelijke testrecords worden aangemaakt.
- **Productie** ontvangt alleen geteste code, noodzakelijke database-schema's, migraties, rollen en configuratie. Fictieve testrecords worden niet meegeleverd. Een lege bedrijfsdataset bij eerste uitrol is toegestaan.
- Productiegegevens en externe diensten blijven beschermd. Geen echte betalingen, berichten, factuurverzending of destructieve acties door een testseed. Gebruik duidelijk herkenbare fictieve gegevens.
- Testdata nooit impliciet kopieren naar productie. Scheid testseed/migratie expliciet en technisch.

## 2. Uitvoering in een gebundelde run
1. Bestaande architectuur, status en afhankelijkheden inspecteren; dubbel werk voorkomen.
2. Alle samenhangende wijzigingen bouwen en integreren in de bestaande Office-modules.
3. Noodzakelijk test-schema installeren en fictieve testdata aanmaken op Office-test, met idempotente en begrensde scripts.
4. Automatische tests uitvoeren en daarna de volledige gebruikersketen via de echte Office-test-routes en rollen beproeven.
5. Fouten herstellen en dezelfde keten opnieuw testen; niet bij iedere kleine commit stoppen.
6. Pas daarna compact rapporteren: **werkt / getest / blokkeert**, met concrete run-, PR- en schermlinks.

## 3. Acceptatie
- Groene CI of geslaagde deployment is **geen** bewijs dat de gebruikersfunctie werkt.
- Een onderdeel is pas 'gereed' na aantoonbare end-to-end acceptatie op Office-test.
- Ontbrekende testrecords zijn geen blokkade: maak ze aan. Ontbrekende echte integraties, rechten of secrets mogen niet worden gefingeerd.
- Meld eerlijk wat niet is uitgevoerd; claim geen installatie, live test of werkende functie zonder bewijs.
- Geen onnodige nieuwe workflows, losse tussenrapporten of herhaalde toestemming voor gewone Office-testdata.

## 4. Projectvoorbereiding - verplichte acceptatieketen
Projectleider expliciet toewijzen -> werkvoorbereider en team aanwijzen -> startdossier aanmaken -> bouwplaatsdocumenten, werkinstructies, vragen, besluiten en deelleveringen vastleggen -> kickstart -> eerste en onafhankelijke tweede goedkeuring -> uitvoeringsstatus vrijgeven.

Verifieer ook: afkeuren met reden; wijziging/revisie maakt oude goedkeuringen ongeldig; onbevoegden worden geweigerd; uitvoeringsstart wordt server-side geblokkeerd zonder geldige vrijgave. Alle gegevens zijn terug te vinden in de bestaande projectmodule en werkbegroting wordt niet dubbel bijgehouden.

## 5. Releasegrens
- Scheid schema-migraties van testseed. Seed alleen bij aantoonbare Office-testomgeving en expliciete testmodus.
- Behoud autorisatie, audittrail, CSRF, backups en terugdraaimogelijkheden waar relevant.
- Geen automatische productie-uitrol op basis van een groene test; productie vraagt afzonderlijke release-acceptatie.

## 6. Een opdracht = een volledige uitvoeringsrun
- De gebruiker hoeft niet steeds 'door', 'en' of 'doorpakken' te typen. Een opdracht om te bouwen omvat ook integratie, noodzakelijke testdata, regressietests, foutcorrecties en eindcontrole binnen dezelfde beschikbare werkronde.
- Stop niet bij een losse commit, groene CI, gedeeltelijke codewijziging of diagnose als er nog uitvoerbare vervolgstappen zijn.
- Bundel toolacties waar veilig mogelijk. Rapporteer pas na een verifieerbaar eindresultaat of een concrete, niet zelfstandig oplosbare blokkade.
- Vraag alleen tussentijds om een beslissing bij wezenlijke ontwerpambiguiteit, ontbrekende bevoegdheden of risicovolle/onherroepelijke handelingen; normale fictieve Office-testdata vereisen geen nieuwe toestemming.
- Een chatantwoord kan geen onbeperkte achtergrondtaak voortzetten. Doe geen toezeggingen over doorwerken na het antwoord zonder een daadwerkelijk ingestelde uitvoerings- of automatiseringsvoorziening.

## 7. Minimale handelingen voor de opdrachtgever
- De opdrachtgever formuleert de functionele wens en beoordeelt het zichtbare Office-test-resultaat; normaal geen handmatige GitHub-merges, Hostinger-login, SSH, SQL, featureflag- of deployhandelingen.
- De uitvoerder organiseert waar mogelijk de volledige technische keten via bestaande connectors en GitHub Actions; vraagt niet om herhaald 'door' en draagt routinematige uitvoering niet over aan de opdrachtgever.
- Uitzonderingen uitsluitend bij noodzakelijke toestemming, ontbrekende rechten/geheimen, wezenlijke ontwerpkeuzes of expliciete productieacceptatie. Leg de blokkade en precies een benodigde gebruikersactie uit.
- De voormalige automatische productieroute `sboffice-deploy-develop.yml` is afgeschermd: geen pushtrigger op develop, productieworkflow uitgeschakeld en expliciete releasebeveiliging. Gebruik de centrale Office-test-route en de daadwerkelijk ingestelde branchregels; zie `docs/OFFICE_TEST_DEVELOPMENT_PIPELINE.md` voor uitvoering en uitzonderingen.

## 8. Centrale ontwikkelstraat en productiegrens
- Centrale route: `.github/workflows/office-test-all-modules-auto.yml`. Feature/fix en PR: broncontroles zonder Hostinger-secrets. Alleen develop: geverifieerde Office-test-acties.
- `scripts/ci/check_deployment_policy.py` blokkeert automatische remote routes buiten de centrale straat. De historische routes in `docs/DEPLOYMENT_LEGACY_WORKFLOWS.json` zijn uitsluitend handmatige onderhoudsprocedures; geen standaard ontwikkelstraat.
- Productie: uitsluitend workflow_dispatch op `release-*` tag, bevestiging `RELEASE-PRODUCTION` en GitHub Environment `production` met verplichte releasegoedkeuring. Nooit een develop-push gebruiken als productieakkoord.
- Bestaande geplande bedrijfsoperatie Moneybird-debiteuren en twee read-only Office-test-audits blijven bestaan. Zij vormen geen ontwikkeldeployment; wijzigingen eraan vergen aparte beoordeling.
- Eerste centrale run inventariseert de echte actieve release, testdatabase, flags en brondrift. Zonder reconciliatie van de runtime mag een volledige bronkopie geen werkende Hostinger-mounts overschrijven.
- Een pipeline-inventarisatie is geen installatie of functionele acceptatie. Controleer concrete runuitvoer en rapporteer resterende blokkades letterlijk.

## 9. Uitrol en functionele acceptatie
- Na de bronpoort installeert de centrale develop-run een nieuwe `release-ci-<sha>/private` naast de actieve release, met proceslock en een driewegsamenvoeging (actieve bestanden, laatst aangeboden bron, nieuwe bron). Conflicten blokkeren; runtime-only afhankelijkheden blijven behouden.
- `scripts/deployment/office_test_migrate.php` voert uitsluitend de geregistreerde additieve startdossier-migratie uit, op de exact toegestane testdatabase. Nieuwe schemawijzigingen moeten expliciet aan deze migratieroute worden toegevoegd met checksum en acceptatiescenario; nooit alle SQL-bestanden blind uitvoeren.
- Testconfig en eerdere release worden behouden. De actieve root wordt atomair gewisseld. Mislukte HTTP-acceptatie zet de oude config/release terug. Additieve schema's worden niet met destructieve downmigraties teruggedraaid. Voor destructieve migraties is deze automatische route niet toegestaan.
- `office_test_seed.php` gebruikt uitsluitend herkenbare fictieve CI-projecten en vier afgebakende accounts, controleert botsingen en is herhaalbaar. Tests voeren echte HTTP-login, project-ACL, CSRF, opslag, revisies, afwijzen, twee onafhankelijke goedkeuringen en uitvoeringsstart uit. Fixtureaccounts en sessies worden na afloop uitgeschakeld.
- Testactivatie schakelt geconfigureerde mail-, bank-, Zoho-, webhook- en betaaltransporten uit; tests benaderen geen externe bedrijfsdiensten. Echte koppelingen moeten afzonderlijk worden geaccepteerd.
- De productieomgeving heeft verplichte review, geen zelfreview en geen beheerdersbypass. De productieworkflow blijft uitgeschakeld totdat een afzonderlijke productieopdracht wordt gegeven. Ook historische onderhoudsflows met productiepaden gebruiken de beschermde productieomgeving. De geplande debiteuren-bedrijfsoperatie blijft een expliciete uitzondering.
- Ontwikkelmerges worden door de uitvoerende ChatGPT-sessie of GitHub auto-merge afgehandeld zodra verplichte checks groen zijn. Nooit branchregels omzeilen. Een standaard GitHub-token-merge in een workflow kan opvolgende push-workflows onderdrukken; gebruik geen eigen merge-bot zonder aantoonbare triggerketen.
