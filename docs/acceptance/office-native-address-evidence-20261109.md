# Office-native adres- en bewonersvoorstellen (9 november 2026)

Status: **concept voor review**, geen merge, geen productieactivatie.

## Bestaande onderdelen
- OfficeDocumentRegistry / OfficePrivateFileStorage: blijvende originele binaire bron en bronidentiteit.
- OfficeIntakeBuildingResolver: uitsluitend exacte postcode + adres + plaats, zonder automatische koppeling bij ambiguïteit.
- OnSiteXlsxCellReader: veilige niet-uitvoerende XLSX-celverwerking.
- Drupal managed extraction: uitsluitend bestaande website-legacy voor PDF/afbeeldingen; géén Office-native afhankelijkheid.

## Nieuwe onderdelen
- OfficeSourceAddressExtractor: beperkte CSV, XLSX en eenvoudige text/plain EML extractie.
- OfficeAddressProposalService: enkel na Office-autorisation en geverifieerde documentidentiteit; resultaten worden **voorstellen**, nooit bewoners- of gebouwmutaties.
- OfficeAddressProposalSchema: afzonderlijk goed te keuren additieve SQL-tabel.
- Provenance is document_id + locatie (CSV rij / XLSX sheet en rij / EML regel), zonder kopiëren van originele bestanden.
- Idempotentie via document_id + SHA-256 van rijlocatie en rijinhoud; dubbele bewonersnamen op verschillende adressen worden niet samengevoegd.

## Gaten en activering
- De repository bevat nog geen Office-native adres-/bewonersdossiertabel voor bevestigde adressen per gebouw. Maak die pas na schema- en UX-review.
- Deze wijziging voegt geen publiek endpoint, automatische upload-hook of bewonersmutatie toe. De bestaande uploader en gebruikersrechten moeten expliciet worden aangesloten vóór testuitrol.
- EML multipart/attachments krijgen veilige review-status, niet stilzwijgend verwijderde bijlagen; voor uitgebreid MIME-parsen is een afzonderlijke gecontroleerde adapter nodig.
- Exacte gebouwmatches blijven onbevestigde voorstellen.
- De oudere OfficeBuildingSchema gebruikt street_address; actieve office-buildings en resolver gebruiken address_line. Eerst runtime-schema controleren.
- Voor nieuwe SQL is afzonderlijke expliciete goedkeuring vereist; deploy/migratie/merge zijn niet geautoriseerd.

## Regressie
Run: php office-independent/tests/OfficeSourceAddressExtractorTest.php
Run: php office-independent/tests/OfficeAddressProposalContractTest.php
Vervolg: isolated MySQL-test van proposal insert, herimport en meerdere gebouwmatches in bestaande acceptance jobs.
