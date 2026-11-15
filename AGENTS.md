# BREBO Office - ontwikkelinstructies

Voor alle wijzigingen aan BREBO Office geldt de bindende werkwijze in [docs/DEVELOPMENT_POLICY.md](docs/DEVELOPMENT_POLICY.md).

Kern: Office-test is de ontwikkel- en acceptatieomgeving; maak daar zo nodig fictieve testdata aan. Bundel bouwen, integreren, seeden, end-to-end testen en herstellen in een run. Productie krijgt geen testdata. Meld een functie pas als gereed na echte Office-test-acceptatie; groen CI alleen is onvoldoende. Behoud de bestaande Office-architectuur, autorisaties en veiligheidsgrenzen.
