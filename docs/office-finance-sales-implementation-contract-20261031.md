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
