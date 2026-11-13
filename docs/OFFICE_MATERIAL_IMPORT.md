# Materiaalimport en vaste calculatieartikelen

## Eén bron

De import schrijft uitsluitend naar het bestaande SALES-artikelmodel: `brebo_supplier`, `brebo_article`, `brebo_supplier_article`, `brebo_article_price`, `brebo_catalog_import`. De adapter kiest niet automatisch een andere database en maakt deze tabellen niet aan. Geen tweede artikelcatalogus in Calc of in een lege native database.

De aanvullende `brebo_material_import_reviews`-tabel bewaart de goedkeurende Office-gebruiker, oorspronkelijke bronhash/-verwijzing, de review en bronbedragen/prijsbasis/korting. Deze ledger moet naast de canonieke artikelbron staan, zodat artikel-/prijsopslag en audit in dezelfde transactie vallen.

## Bediening

Native route `/office-test/materialen` ontvangt een bestand, geplakte tekst, een bestaande native inkoopfactuur of een bestaand Office-document. Een document uit een e-mail-/projectdossier wordt via zijn bestaande document-ID geopend: de originele binary wordt niet nogmaals opgeslagen.

Herkenning geeft voorstellen, nooit goedkeuring. Elke geselecteerde regel vraagt leverancier, artikelcode, omschrijving, bronprijs, prijsaantal, eenheid, korting, valuta en prijsdatum. Expliciete bevestiging van prijzen exclusief btw is verplicht. Afwijkende bronkolommen worden op veldnamen herkend; de domeininterface ondersteunt ook expliciete veldkoppeling. Het scherm toont ook bronkolommen, werkbladkeuze en een expliciete veldkoppeling met eerste gegevensregel wanneer koppen niet herkenbaar zijn.

Ondersteund: CSV/TSV/tekst, gelabelde tekstblokken, SALES-XML, XLSX en DOCX-tabellen. PDF/afbeeldingen gebruiken uitsluitend de bestaande geconfigureerde Office-extractieprovider. Onbekende bestandsformaten of onvoldoende herkenning blijven open. Dit is geen garantie dat iedere willekeurige vorm of vrije tekst automatisch kan worden herkend.

Voorbeeld: €45 per 100 stuks, 10% regelkorting → €0,4050 per stuk exclusief btw. Dubbelzinnige getalnotatie vraagt expliciet NL/internationaal. Onbekende prijzen worden niet als nul vastgelegd.

Een leveranciersartikel wordt hergebruikt op leverancier + artikelcode, met behoud van voorloopnullen. Verschillende leveranciers met dezelfde code blijven verschillende leveranciersartikelen. Een bestaande gebruikseenheid mag niet ongemerkt veranderen. Een nieuwe prijs levert een nieuwe versie; eerdere calculaties/snapshots blijven behouden. Herhalen van exact dezelfde bron en review maakt geen dubbele prijsregistratie.

## Inkoopfacturen

`MaterialInvoiceSource` gebruikt de bestaande native Finance-tabellen. Vereist Finance-leesrecht en toegang tot alle projectcontexten van de factuur. Ongedeelde regels vereisen expliciet `unassigned_read`. Er wordt geen toegang afgeleid uit een legacy leveranciers-ID, CRM-recht of de URL.

De werkelijke nettoprijs volgt uit netto regelbedrag / hoeveelheid; korting wordt niet nogmaals afgetrokken. De native factuurregel heeft nog geen artikelcodeveld, dus die code blijft leeg en vraagt menselijke aanvulling. Alleen positief geprijsde regels zijn kandidaten. De gebruiker moet bepalen welke regels materiaal zijn; transport/arbeid/OA worden niet automatisch als materiaal geboekt.

Bij publicatie worden factuur en bronhash opnieuw gecontroleerd. Een wijziging sinds preview blokkeert verwerking. De ledger bewaart de oorspronkelijke factuur-/regelsnapshot en de review. De factuur wordt niet gewijzigd.

## Autorisatie en installatie

- Featureflag `OFFICE_MATERIALS_ENABLED=enabled` alleen voor Office-test.
- Bestaande Office-cookiesessie, actieve operator en `office_module_permissions` voor module `materials`: `read` om te openen; daarnaast `import` voor elke mutatie. Geen automatische grants.
- `OFFICE_MATERIALS_CANONICAL_SCHEMA_VERIFIED=enabled` en expliciete `OFFICE_MATERIALS_CANONICAL_PDO_DSN`, `OFFICE_MATERIALS_CANONICAL_DB_USER`, `OFFICE_MATERIALS_CANONICAL_DB_PASSWORD`, optioneel `OFFICE_MATERIALS_CANONICAL_TABLE_PREFIX`. Deze wijzen naar de gecontroleerde bestaande artikelbron, niet naar een automatisch aangemaakte kopie.
- Installeer `material-import-review-ledger.sql` op die artikelbron, met hetzelfde prefix als de bestaande tabellen.
- Voor upload/bestaande documenten: het bestaande `OfficeDocumentRegistry::schema()` moet gecontroleerd geïnstalleerd zijn; `OFFICE_DOCUMENT_STORAGE_ROOT` wijst naar private opslag buiten de webroot. Geen DDL tijdens HTTP-verzoeken.
- PDF/scans: bestaande `BREBO_DOCUMENT_EXTRACTION_ENDPOINT` en `DOCUMENT_EXTRACTION_TOKEN` configureren. Zonder provider geen fictieve herkenning.
- De route is toegevoegd aan het referentierouterbestand. Een actieve samengestelde Hostinger-router mag niet volledig door dat bestand worden vervangen; alleen de nieuwe route gecontroleerd toevoegen.

De read-only Hostinger-inventarisatie van 13 november 2026 bevestigde native Finance-tabellen en sessie-/rechtenbeheer, maar geen SALES-artikeltabellen en geen centraal documentregister in de native testdatabase. De gecontroleerde native config bevatte evenmin een ingestelde legacy leesbron of de bovengenoemde extractiesleutels. Een canonieke artikelprovider moet dus expliciet worden aangesloten; het aanzetten van een flag alleen maakt deze keten niet werkend.
