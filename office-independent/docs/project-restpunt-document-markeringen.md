# Projectoplevering: documentmarkeringen

Implementatiecontract, nog niet geactiveerd.

- Het bestaande documentregister blijft eigenaar van documenten en onveranderlijke originelen (office_documents, office_document_originals).
- Een markering behoort tot een projectgebonden restpunt in office_quality_checks. Geen kopie van het restpunt of wijziging van het originele bestand.
- Bewaar document-ID, original-ID, SHA-256, paginanummer (1 voor foto), x en y als genormaliseerde gehele miljoensten (0 tot 1000000), gemeten vanaf linksboven op de canoniek georiënteerde pagina.
- Een nieuwe documentversie krijgt geen automatische overdracht van markeringen. Een expliciete herplaatsing is vereist.
- Controleer bij lezen en schrijven zowel de bestaande documenttoegang als kwaliteitsrechten voor het projectgebouw; controleer de documentcontext, restpuntsoort en projectgebouw. Een gebouwdocument mag niet automatisch toegang geven tot restpunten van andere projecten.
- Controleer MIME, werkelijk paginabereik en oorspronkelijke oriëntatie server-side. Geen opslagpad of hash vertrouwen vanuit de browser.
- Gebruik revisiecontrole en audit voor verplaatsing en verwijdering; geen overschrijving van historische opleverrapporten.
- Nummering stabiel per restpunt; kleur volgt actuele status (open/afwijking rood, hercontrole oranje, akkoord groen). Toon ook tekststatus.
- Viewer: zoom/pan, klikken of tikken voor plaatsing, toetsenbordalternatief, selectie van bestaande stip. Coördinaten onafhankelijk van zoom en schermformaat.
- Rapportage: leg bronidentiteit, positie en status vast in de definitieve opleversnapshot.

Acceptatie: verkeerde projectcontext, verkeerd document, verkeerde pagina, ongeldige positie, ontbrekende rechten en verouderde revisie weigeren; zoom en schermrotatie behouden positie; origineel blijft byte-identiek.

Open: bestaande document-ACL en viewer inspecteren, opslag en audit implementeren, servervalidatie, gebruikersinterface en integratietests. Geen schema-installatie of vrijgave op basis van dit contract.
