# BREBO Office - ontwikkelinstructies

Voor alle wijzigingen aan BREBO Office geldt de bindende werkwijze in [docs/DEVELOPMENT_POLICY.md](docs/DEVELOPMENT_POLICY.md).

Kern: Office-test is de ontwikkel- en acceptatieomgeving; maak daar zo nodig fictieve testdata aan. Bundel bouwen, integreren, seeden, end-to-end testen en herstellen in een run. Productie krijgt geen testdata. Meld een functie pas als gereed na echte Office-test-acceptatie; groen CI alleen is onvoldoende. Behoud de bestaande Office-architectuur, autorisaties en veiligheidsgrenzen.

Bij een ontwikkelopdracht is **een gebruikersopdracht een volledige uitvoeringsrun**. Vraag niet na elke commit om 'door' of 'en'. Voer binnen de beschikbare toolronde alle samenhangende stappen uit en rapporteer pas het verifieerbare eindresultaat of een echte blokkade. Vraag alleen om een beslissing wanneer die inhoudelijk of voor veiligheid vereist is. Werk niet zogenaamd op de achtergrond door na het afronden van een antwoord.
