# BREBO Office — centrale ontwikkelstraat

De opdrachtgever geeft een functionele opdracht, beoordeelt het resultaat op Office-test en geeft afzonderlijk productieakkoord. De uitvoerende sessie verzorgt branches, PR, controles, integratie, uitrol en foutcorrecties binnen dezelfde opdracht.

## Technische route

1. Werk vanaf de actuele `develop` op `feature/*` of `fix/*`. Open een PR naar `develop`.
2. De bestaande workflow `office-test-all-modules-auto.yml` voert de bronpoort uit: deploymentbeleid, negatieve beleidstests, samenvoegtests, PHP-lint en startdossier-regressies. Feature- en PR-jobs krijgen geen Hostinger-secrets.
3. Integreer uitsluitend nadat alle relevante checks geslaagd zijn en volgens de daadwerkelijk ingestelde branchregels. De ChatGPT-sessie voert dit uit; vraag de opdrachtgever niet om een handmatige merge.
4. Een push op `develop` start dezelfde centrale workflow met Environment `office-test`, een serverlock en een exacte commitrelease.
5. De installer bewaart bestaande runtime-aanpassingen met een driewegsamenvoeging. Een conflict blokkeert vóór activering. De laatst aangeboden repositorybron wordt per release bijgehouden; runtime-only afhankelijkheden blijven beschikbaar.
6. Installeer uitsluitend geregistreerde additieve schemawijzigingen op `u213420663_office_test`. Checksums voorkomen dat een reeds toegepaste migratie wordt herschreven. Nieuwe migraties worden in deze route expliciet toegevoegd; geen blind uitvoeren van SQL-bestanden.
7. Bewaar de vorige configuratie en release. Activeer de nieuwe private root atomair, voer echte HTTP-acceptatie uit en zet bij mislukking de vorige configuratie/release terug. Additieve tabellen blijven aanwezig; destructieve downmigraties zijn niet toegestaan.
8. Publiceer het runresultaat en de artefacten `office-test-inventory.txt` en `office-test-acceptance.txt`. Een groene bronpoort of inventarisatie betekent geen functionele acceptatie.

## Eerste acceptatiescenario

De fictieve CI-projecten hebben code `BREBO-CI-<commitprefix>` en een herkenbare titel. Vier afgebakende testaccounts gebruiken per run nieuwe willekeurige wachtwoorden. Het scenario controleert echte login, anonieme toegang, projectrechten, leesrechten, onbevoegde toegang, CSRF, dossiercreatie, opslaan/teruglezen, verouderde revisies, vragen en besluiten, afwijzen, twee verschillende goedkeurders en server-side uitvoeringsstart. Materiële wijzigingen trekken de vrijgave en actieve uitvoeringsstatus in. Accounts en sessies worden na afloop uitgeschakeld.

Testgegevens blijven uitsluitend in de testdatabase. Testactivatie schakelt de geconfigureerde externe mail-, Zoho-, bank-, webhook- en betaaltransporten uit. Tests verzenden geen echte berichten, facturen of betalingen. Koppelingen met externe systemen hebben een afzonderlijke acceptatie nodig.

## Productie

De voormalige productie-pushtrigger op `develop` is verwijderd. De productieworkflow blijft uitgeschakeld. De code accepteert alleen een expliciete dispatch op een `release-*` tag met `RELEASE-PRODUCTION` en de beschermde Environment `production`. Die omgeving heeft een verplichte reviewer, weigert zelfreview en staat geen beheerdersbypass toe. Ook historische handmatige workflows met productiepaden gebruiken deze beschermde omgeving.

89 historische remote workflows hebben geen automatische ontwikkeltriggers meer. Drie reeds bestaande geplande operaties blijven bestaan: debiteurensynchronisatie en twee read-only Office-test-audits. Zij zijn geen ontwikkeldeployment.

## Grensgevallen

- De bronpoort controleert de workflowbestanden, maar vervangt geen GitHub-branchbeveiliging. Voor het opslaan van de voorbereide develop-regel vraagt GitHub opnieuw om accountverificatie; de passkeypoging is geweigerd. Deze beheerstap is nog niet voltooid.
- De bestaande Hostinger-toegang gebruikt repositorysecrets en dezelfde bestaande SSH-identiteit. Er zijn geen nieuwe credentials of rechten aangemaakt. Een aparte beperkt bevoegde serveridentiteit is nog niet ingericht.
- De productieworkflow wordt niet tijdens een ontwikkelopdracht aangezet. Met zelfreviewpreventie moet de uiteindelijke releasegoedkeurder verschillen van de identiteit die de release start.
- Runtime-only afhankelijkheden zijn nog niet allemaal als bron in GitHub aanwezig. De centrale installer bewaart ze; verwijderingen en conflicten vragen een expliciete technische reconciliatie door de uitvoerder.
- De huidige migratie- en acceptatieroute bevat het startdossier als eerste scenario. Voeg nieuwe modulemigraties en functionele scenario's aan deze centrale route toe, niet via losse deployments.
