# BREBO Office - ontwikkelinstructies

Kern: Office-test is de ontwikkel- en acceptatieomgeving; maak daar zo nodig fictieve testdata aan. Bundel bouwen, integreren, seeden, end-to-end testen en herstellen in een run. Productie krijgt geen testdata. Meld een functie pas als gereed na echte Office-test-acceptatie; groen CI alleen is onvoldoende. Behoud de bestaande Office-architectuur, autorisaties en veiligheidsgrenzen.

Bij een ontwikkelopdracht is **een gebruikersopdracht een volledige uitvoeringsrun**. Vraag niet na elke commit om 'door' of 'en'. Voer binnen de beschikbare toolronde alle samenhangende stappen uit en rapporteer pas het verifieerbare eindresultaat of een echte blokkade. Vraag alleen om een beslissing wanneer die inhoudelijk of voor veiligheid vereist is. Werk niet zogenaamd op de achtergrond door na het afronden van een antwoord.

Gebruik de bestaande GitHub-workflows voor de ontwikkel- en testcontroles. Productie is een afzonderlijke goedgekeurde `release-*` tag via Environment `production`; een develop-merge mag geen remote productieacties starten. Herintroduceer geen automatische triggers in historische onderhoudsflows. Behoud geplande bedrijfsoperaties en reconcileer runtime-mounts vóór volledige bronvervanging.

De complete actuele uitvoeringsroute en uitzonderingen staan in [docs/OFFICE_TEST_DEVELOPMENT_PIPELINE.md](docs/OFFICE_TEST_DEVELOPMENT_PIPELINE.md). Nieuwe migraties en HTTP-scenario’s horen in de centrale installer en acceptatie; geen losse deploymentworkflow toevoegen. Controleer na elke develop-integratie het definitieve Hostinger-acceptatieresultaat en herstel fouten voordat de opdracht wordt afgerond.

## Samenwerking tussen meerdere ChatGPT-ontwikkelkanalen

Lees bij **iedere** nieuwe ontwikkelrun eerst [docs/OFFICE_DEVELOPMENT_STATUS.md](docs/OFFICE_DEVELOPMENT_STATUS.md) als overdracht, maar controleer daarna altijd live de actuele `develop`-HEAD, open PR's, overlappende branches en GitHub Actions. Het overdrachtsbestand is een gedateerde momentopname en vervangt de actuele repository- en Office-test-status niet. Voorkom dubbel werk, respecteer branchbeveiliging en rapporteer alleen bewezen tests, merges en VPS-/gebruikersacceptatie. Werk de overdracht bij bij wezenlijke voortgang; behoud de productiegrens.
