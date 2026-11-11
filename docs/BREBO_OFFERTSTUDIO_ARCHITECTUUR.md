# BREBO Offertestudio - implementatiecontract v1

Status: ontwerp en implementatiebasis; niet productie-vrijgegeven.

## Doel
Een native Office-verkoopdocument op basis van CRM en een optionele calculatie. Ook directe opdrachten, MJOP en regievoorstellen zonder calculatie ondersteunen.

## Documenttypen
- project: uitgebreide projectofferte met introductie, scope, werkwijze, prijs, planning, termijnschema, referenties, bijlagen en voorwaarden
- mjop: doel, opname, methodiek, bouwdelen, rapportageproducten, prijs en voorwaarden
- regie: werkzaamheden, tarieven, materiaalafspraken, werkbonnen, nacalculatie en facturatie
- compact: introductie, scope, prijs en akkoord

## Gegevenscontract
Offerte: id, crm_opportunity_id nullable, crm_relation_id, contact_id nullable, building_id nullable, calculation_id nullable, calculation_version nullable, type, title, status, currency, version, created_by, approved_by nullable, approved_at nullable.
Sectie: quotation_id, section_key, sort_order, enabled, text, provenance, approved_at nullable.
Prijsregel: calculation_line_id nullable, description, quantity, unit, price_ex_vat, vat_rate, optional, provenance.
Termijn: quotation_id, sequence, trigger, percentage, amount_ex_vat. Som percentages = 100 wanneer een termijnschema is gekozen; bedragen aansluiten op overeengekomen contractwaarde.
Referentie: quotation_id, project_id, approved_description, selected_media_ids; foto's alleen uit eigen projectdossier en na publicatiecontrole.
Bijlage: quotation_id, document_id, filename, version, sort_order, included_in_pdf, checksum.
Versie: immutable snapshot van secties, prijsregels, termijnen, referenties, bijlagen en voorwaarden met documenthash.

## Proces
CRM verkoopkans -> conceptofferte -> calculatie of handmatige prijsbron -> redactiestudio -> interne controle -> PDF-preview -> goedkeuring -> verzending -> klantakkoord -> opdracht/project -> vergrendelde werkbegroting en FinanceCenter.
Geen project vereist om offerte te starten. Geen calculatie verplicht voor advies/MJOP/regie. Geen projectbudget of factuur vóór opdrachtacceptatie.

## Regels
- Calculatie blijft financiële bron waar gekoppeld; commerciële teksten mogen financiële gegevens niet wijzigen.
- AI kan uitsluitend conceptwerkomschrijvingen voorstellen op basis van geselecteerde scope. Nooit automatisch hoeveelheden, prijzen, materialen, garanties of prestaties toevoegen.
- Bevestigde offerteversies zijn onveranderbaar; wijzigingen leiden tot een nieuwe versie.
- Contractuele termijnschema's na acceptatie vastleggen als snapshot; FinanceCenter neemt alleen goedgekeurde termijnen over.
- Bijlagen met toegangscontrole, veilige PDF-samenvoeging en virus-/bestandsvalidatie.
- Rechten: opsteller, controleur, fiatteur, verzender; scheiding tussen concept en definitieve versie.
- Geen Drupal-afhankelijkheid in native Office. Bestaande outputgenerator en documentopslag hergebruiken.

## UI
Office > CRM > Offertes: lijst, filters, status en nieuwe offerte.
Editor: links hoofdstukken en volgorde, midden tekst/gegevens, rechts live voorbeeld en controles.
Acties: sjabloon kiezen, hoofdstuk toevoegen/verbergen, calculatie synchroniseren, termijnschema beheren, referenties kiezen, bijlagen selecteren, preview, ter goedkeuring aanbieden, definitief genereren.
Standaardtypes en hoofdstukken zijn configureerbaar.

## Acceptatietests
1. Projectofferte vanuit CRM zonder project.
2. MJOP-offerte zonder calculatie.
3. Regievoorstel met uurtarieven en afspraken.
4. Calculatieversie overnemen zonder brondata te wijzigen.
5. Termijnpercentages valideren op 100%.
6. Referenties uitsluitend met vrijgegeven foto's.
7. Bijlagen veilig en in juiste volgorde opnemen.
8. Definitieve offerteversie onveranderbaar.
9. Akkoord draagt geaccepteerde prijs en termijnen éénmaal over.
10. Rechten, PDF-output, versiehistorie en regressie op CRM/FinanceCenter.

## Implementatievolgorde
1. Native domeinmodel, repositories en migraties.
2. CRM-route, autorisatie en editor.
3. Calculatie-adapter, termijnen en referenties.
4. Outputgenerator, PDF-samenvoeging en versiebeheer.
5. Acceptatie, FinanceCenter-overdracht, regressietests en gefaseerde uitrol.
