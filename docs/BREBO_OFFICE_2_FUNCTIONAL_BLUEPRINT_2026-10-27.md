# BREBO Office 2.0 — functionele blauwdruk en uitvoeringscontract

Status: vastgesteld als ontwerpuitgangspunt; implementatie nog te valideren.
Datum: 2026-10-27.
Referentie: Zoho CRM als inspiratie voor navigatie, werkvoorraad en dossieropbouw, niet als te kopiëren product.

## Doel
BREBO Office is één bedrijfsomgeving voor Inzicht, Regie en Realisatie. Eerst informatiearchitectuur en proceslogica; daarna hergebruik en consolidatie van bestaande code; vervolgens ontbrekende functies; styling als laatste.

## Applicatieschil
- Vaste linkerzijbalk met zes openklapbare hoofdgroepen; selectie en open/dichtstatus blijven behouden waar technisch verantwoord.
- Bovenbalk met globale zoekfunctie, meldingen en account.
- Eén hoofdnavigatie; geen concurrerende navigatie-injecties.
- Drie inhoudstypen: dashboard, werkvoorraad (tabel/kanban), dossier (kop, procesbalk, tabs, tijdlijn).
- Rol- en objectgebaseerde toegangscontrole op iedere route en handeling; verborgen menu-items zijn geen autorisatie.

## Schermenboom
1. **Mijn werkplek:** dashboard, taken, agenda, mijn projecten, meldingen en goedkeuringen.
2. **Relaties & kansen:** relaties/contacten, leads/kansen, aanvragen/intake, calculaties/offertes, contracten/opdrachten.
3. **Projecten:** portefeuille, projectdossier, werkvoorbereiding, planning/inzet, uitvoering/OnSite, GOTIK-projectbeheersing, oplevering/nazorg.
4. **Gebouwen:** register, gebouwwaarheid (gebouw/bouwdeel/element/component), inspecties, onderhoud/MJOP, gebouwdossier.
5. **Bedrijfsvoering:** finance, inkoop, personeel/capaciteit, urencontrole, managementrapportages, beheer.
6. **Kennis & kwaliteit:** kennisbank, documentcentrum, KAM/VCA, afwijkingen/verbetering, processen/besluiten.

## Dossiercontract
- De dossierkop identificeert uniek object, verantwoordelijke, relatie, status en relevante acties.
- De procesbalk toont workflowfase; dossier-tabs tonen inhoud; deze zijn onafhankelijk.
- De tijdlijn toont gebeurtenissen en bronverwijzingen met herleidbare actor/tijd.
- Relaties, projecten, gebouwen, documenten, calculaties en medewerkers worden gekoppeld via stabiele IDs, niet gekopieerd als losse dossiers.
- Projectdossier bevat minimaal overzicht, scope/contract, calculatie, werkvoorbereiding, inkoop, planning, inzet, uitvoering, financieel, kwaliteit, documenten, besluiten en oplevering.
- Geen gefingeerde KPI's: ontbrekende of niet-gekoppelde data wordt expliciet als zodanig getoond.

## Standaard schermcontract
Elk scherm beschrijft: context, status, aandachtspunten, inhoud, handelingen, historie, autorisaties, herkomst en lege/foutstatus. Een overzicht moet informatie tonen, niet uitsluitend navigatieknoppen.

## Procesketen
Aanvraag → beoordeling → calculatie → offerte → opdracht → voorbereiding → uitvoering → bewaking → oplevering → nazorg.
Een overgang bewaart bronstukken, scope, beslissingen en koppelingen; fasewissels zijn traceerbaar en alleen bevoegd uit te voeren.

## Eerste repositorybevindingen (beperkte broninspectie)
- `office-independent/public/office-projects.php`: projectenportefeuille en Inzet-view; bestaande horizontale module-tabs. Hergebruik, niet opnieuw bouwen.
- `office-independent/public/office-project.php`: native projectdossier met gebouw-/calculatie-/Inzet-koppelingen. Inhoudelijke aansluiting op dossiercontract toetsen.
- `office-independent/deployment/hostinger/office-test-index.php`: geïsoleerde test-router. Eén navigatie-integratie behouden.
- `office-independent/src/Intake/OfficeNavigation.php`: via PR #1441 toegevoegd; bestaande moduletabs behouden. Regressietest nodig.
- `office-independent/src/Inzet/OnSiteSupplierWeekReadRepository.php`: leveranciersweekstaten bestaan; plaatsing en bevoegdheden controleren.
Deze lijst is geen volledige code-inventarisatie en zegt niets over productiegereedheid.

## Implementatiewerkpakketten en acceptatie
**WP0 Inventarisatie:** alle routes, pagina's, datamodellen, rechten, tests, flags en deploybronnen registreren. Output: per blauwdrukscherm status *bestaand / gedeeltelijk / ontbrekend / dubbel* en eigenaar van brongegevens. Geen ongetoetste claims.
**WP1 Shell:** één zijbalk, bovenbalk en broodkruimel; stabiele navigatie, geen dubbele menu's; mobiel bruikbaar.
**WP2 Werkvoorraden:** gedeelde tabel-, filter- en kanbanpatronen met dezelfde onderliggende query en autorisatie; geen fictieve faseovergangen.
**WP3 Dossiers:** consistente dossierkop, tabs, tijdlijn en projectcontext; autorisaties en bronkoppelingen blijven intact.
**WP4 Processen:** intake→offerte→opdracht→project met expliciete overdrachts- en auditregels.
**WP5 Gebouwen & bedrijfsvoering:** gebouwwaarheid, MJOP, Finance, Inkoop, Inzet en rapportage aan bestaande kern koppelen.
**WP6 Acceptatie:** rolgerichte gebruikerstests, regressie, beveiliging, performance, deploy/rollback; pas daarna styling.

## Veiligheids- en leveringsregels
- `develop` is bron voor nieuwe branches; elke wijziging via gerichte PR met toetsbare acceptatiecriteria.
- Geen productieaanpassingen, database-migraties of feature-flag-wijzigingen zonder afzonderlijke review.
- Office-test blijft beschikbaar; deploys met back-up, smoke tests en rollback.
- Geen verplaatsing van functionaliteit zonder routes, permissies, links en gegevensherkomst mee te nemen.
- Bestaande werkende modules worden niet verwijderd omdat de nieuwe navigatie anders is.

## Eerstvolgende uitvoerbare taken
1. Maak machineleesbare route/scherm-inventaris uit `office-independent/public` en Hostinger-router.
2. Leg de mapping route → hoofdgroep → schermtype → dossiercontext vast.
3. Voeg tests toe voor behoud van `aria-label="Module"` naast centrale navigatie.
4. Maak eerste werkende shell achter testflag, zonder bestaande routegedragingen te wijzigen.
5. Beoordeel de projectdossierweergave tegen het dossiercontract en vul die gefaseerd in.

## Definition of Done
Niet alleen code groen: gebruiker kan via één menu het juiste werkgebied vinden, een werkvoorraad openen, een dossier selecteren, binnen de dossiercontext werken en zonder gegevensverlies terugkeren. Rolrechten, lege toestanden, fouten en bestaande routes zijn getest.
