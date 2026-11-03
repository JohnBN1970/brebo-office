# BREBO Office — Projectoverzicht V2

## Doel
Een schermvullende, rustige projectwerkplek die de bestaande donkerblauwe sidebar behoudt. Geen tweede menustructuur en geen lange technische dossierpagina als startscherm.

## Bronnen
- CRM: opdrachtgever, contactpersonen, klantvraag, projectomschrijving. Lees via bestaande geautoriseerde koppeling; geen dubbele invoer.
- Projecten: projectidentiteit, projectleider, status, gebouwen en aantallen.
- Contract: oorspronkelijke contractwaarde apart van goedgekeurd meer- en minderwerk; actuele opdrachtsom is een afgeleide som, niet een overschreven oorspronkelijke contractwaarde.
- Inzet: gepland, werkelijk en afwijkingen, uitsluitend geautoriseerde data.
- Planning: start, oplevering, mijlpalen en voortgang; ontbrekende koppelingen duidelijk als 'Niet beschikbaar'.
- Finance: resultaat en facturatie alleen tonen bij expliciete autorisatie.

## Layout
- Full-width content naast de bestaande vaste sidebar (geen smalle max-width).
- Bovenbalk: breadcrumb, projectnaam/code, status, primaire actie.
- Identiteitsstrook: opdrachtgever, projectleider, locatie, start- en einddatum, contactpersonen.
- Eerste KPI-rij: opdrachtsom, voortgang, prognose eindresultaat, planningstatus.
- Tweede rij: projectproductie, uren, openstaande acties/risico's.
- Grafieken: voortgang tegen planning, begroot vs gerealiseerd, uren gepland vs werkelijk. Alleen met geverifieerde waarden en duidelijke legenda.
- Onderste helft: 'Aandacht nodig', aankomende mijlpalen en laatste projectactiviteiten.
- Dossiernavigatie naar Gebouwen, Calculatie, Planning & inzet, Financieel en Documenten. Geen inline lange invoerformulieren.

## Visuele uitgangspunten
- Sidebar #162639 behouden; geselecteerde item #30516d.
- Achtergrond #f4f6f9, witte kaarten, zachte grenzen #dbe1e8.
- Eén accentblauw #175a91, statuskleuren alleen semantisch.
- Grote leesbare KPI-getallen, compacte labels, veel witruimte, consistente kaartafstanden.
- Responsive: 4 kolommen desktop, 2 tablet, 1 mobiel.
- Toegankelijke focus, tabvolgorde, contrast en betekenisvolle lege staten.

## Acceptatie
1. Projectoverzicht is het eerste zichtbare paneel en vult de beschikbare schermbreedte.
2. Sidebar blijft zichtbaar en functioneel.
3. Geen dubbele navigatie.
4. CRM-velden komen uitsluitend uit CRM; niet aanwezige gegevens worden niet verzonnen.
5. KPI's zijn herleidbaar naar geautoriseerde bronnen en tonen bron/peildatum waar relevant.
6. Geen databasewijzigingen, geen aanpassing aan bestaande contractwaarde of projectautorisatie.
7. Alle bestaande detailfuncties blijven bereikbaar.
8. Eerst testrelease en regressietests, pas daarna productie.
