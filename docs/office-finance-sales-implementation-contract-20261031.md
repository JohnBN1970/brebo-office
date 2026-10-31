# Finance verkoopfacturatie — implementatiecontract

## Vastgestelde uitgangssituatie
- De sboffice-database bevat de vier tabellen `brebo_finance_sales_invoice_draft`, `brebo_finance_sales_invoice_draft_line`, `brebo_finance_sales_invoice_outbox` en `brebo_finance_sales_invoice`. Alle vier bevatten nul records (controle 2026-10-31).
- Office-test bevat twee uitsluitend als TEST aangeduide projecten en nul rijen in `office_finance_project_mapping`.
- De huidige Office-native verkoopfactuurcode in deze branch is uitsluitend lezend. Zij mag niet als werkende factuuruitgifte worden beschouwd.
- Geen fictieve projectkoppelingen; geen productie-mutaties.

## Eigenaarschap
- Office Projecten: contractwaarde, goedgekeurd meerwerk en betaalschema.
- Finance: factuurconcept, factuurregels, btw-specificatie, factuurnummer, uitgifte, creditnota, debiteurenstatus en audit.
- CRM: offerte en calculatie; een geaccepteerde offerte kan een projectcontract voeden.
- Een zelfstandige verkoopfactuur moet zonder project mogelijk zijn, maar uitsluitend onder expliciete debiteuren- en Finance-autorisatie.

## Gefaseerde oplevering
1. Leg de werkelijke kolommen, constraints, sleutels en transacties van de lege sboffice-factuurtabellen vast; maak geen aannames over hun schema.
2. Ontwerp Office-native conceptopslag en een expliciete, afgeschermde write-verbinding. Hergebruik bestaande services waar veilig. Scheid leesrechten, bewerkrechten en uitgifterecht.
3. Maak concept aan, voeg regels toe, bereken btw per regel en per regime, valideer debiteur, vervaldatum, totalen en projectautorisatie.
4. Uitgifte uitsluitend via server-side transactie: atomair uniek factuurnummer, onveranderbare definitieve momentopname, idempotentie, audittrail; fout leidt tot rollback. Verzendwachtrij mag geen dubbele verzending veroorzaken.
5. Creditnota als afzonderlijk document met verwijzing naar oorspronkelijke factuur; definitieve facturen nooit overschrijven.
6. Finance-bulkimport: PDF, XML/UBL, XLS/XLSX, CSV, DOC/DOCX, TXT en ZIP. Bewaar bronbestand, controleer MIME/inhoud en limieten, bescherm ZIP-extractie, herken dubbelen, toon preview en importeer uitsluitend als concept na bevestiging.
7. Betaalcentrum: openstaande posten en betaalstatus; koppel termijnfacturen uitsluitend aan gecontroleerde Office-projecten.
8. Automatiseer tests voor rollen, projecttoegang, standalone debiteuren, nummering/concurrentie, btw, credits, foutpaden, importduplicaten en rollback.

## Activeringspoort
Geen route voor schrijven of uitgifte activeren totdat schema, autorisaties, migratie-/rollbackplan, transactietests en Office-test-acceptatie aantoonbaar gereed zijn. Productie blijft buiten scope.

## Gecontroleerde sboffice-schema-inspectie (2026-10-31)
De gebruiker heeft via Drupal `SHOW COLUMNS` bevestigd:
- Concept: `id`, `project_nid` (verplicht), `draft_number`, `status`, `invoice_date`, `due_date`, `description`, drie totalen (`decimal(18,4)`), `moneybird_id` (optioneel), `sales_invoice_id` (optioneel), created/changed en gebruikers.
- Conceptregel: `draft_id`, `project_nid`, `line_number`, `source_type`, `source_id`, `description`, bedragen en `vat_code`, `vat_rate`, `vat_amount`.
- Outbox: `draft_id`, `project_nid`, `command_type`, `status`, unieke `idempotency_key`, `payload_hash`, `payload`, pogingen, integratievelden en tijdstempels.
- Definitieve factuur: `project_nid` verplicht, `instalment_id` en `change_order_id` optioneel, `moneybird_id` en `invoice_number` verplicht en uniek, betaalbedragen, `source_hash` en tijdstempels.
- Geen expliciete debiteurkolom in deze vier tabellen. Geen aanname dat `project_nid=0` zelfstandig factureren autoriseert.
- `SHOW COLUMNS` bewijst geen foreign keys, unieke samengestelde indexen, standaardwaarden of transactie-engine; inspecteer `SHOW CREATE TABLE` of `information_schema` voordat schrijflogica wordt ontworpen.

### Open ontwerpbesluiten vóór schrijfcode
1. Is Moneybird de juridische nummerings-/uitgiftebron, of moet Office zelfstandig uitgeven? De bestaande verplichte `moneybird_id` verhindert native uitgifte zonder nieuw opslagcontract.
2. Welke Office-debiteuridentiteit en factuuradres-snapshot gebruiken we voor zelfstandige facturen?
3. Welke bestaande `source_type`-waarden, `status`-overgangen en btw-regimes zijn toegestaan? Verifieer aan bestaande services, niet aan voorbeeldwaarden.
4. Maak voor Office-test een afzonderlijk migratiepad met rollback; wijzig sboffice niet voor testdoeleinden.
