# BREBO Inzet — echte Excel als afgeschermde testbron

De aangeleverde leveranciersurenverantwoording is lokaal ingelezen in een
afzonderlijke SQLite-testdatabase (niet in GitHub en niet in productie).
De database bevat bronbladen, originele naam- en projectlabels, dagcellen,
celopmerkingen, bronrijen en reviewproblemen.

## Testwerkbank
De lokaal gegenereerde testkopie heeft drie views:
- inzet_source_workbench: bronrij, blad, originele labels, dagcellen,
  plausibele uren en bronwaarschuwingen.
- inzet_person_candidates: unieke naamvermeldingen (geen bewezen unieke personen).
- inzet_project_candidates: projectvermeldingen (geen Office-projectkoppeling).

Twee lege audittabellen zijn voorbereid:
- confirmed_identity_mappings: expliciete Office-identiteitskoppelingen,
  optioneel leveranciersgebonden; geen fuzzy matching.
- source_review_decisions: controlebesluiten met beoordelaar en tijd.

De oorspronkelijke urenbron bevat geen bevestigd ISO-kalenderjaar.
Geen datumtoewijzing, automatische urenacceptatie, factuurvrijgave of
OnSite-aanwezigheidsclaim op basis van dit Excel-bestand.

## Nog niet verbonden
Deze lokale SQLite-testkopie is geen Hostinger Office-database. Een native
Office-importscherm, beheerde opslag en migratie voor de server vereisen
een aparte implementatie, beveiligingscontrole en goedkeuring.
Geen persoonsgegevens, originele uren, tarieven of Excel-bestanden committen.
