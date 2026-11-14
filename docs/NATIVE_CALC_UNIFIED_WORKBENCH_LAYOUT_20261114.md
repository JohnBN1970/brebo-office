# BREBO Office — uniforme native calculatiewerkbank

Status: UI-integratiecontract; **geen live UI-uitrol**. Gebaseerd op bestaande native Office-architectuur, `docs/BREBO_CALCULATIE_ARCHITECTUUR.md`, `docs/OFFICE_MATERIAL_IMPORT.md` en de geïnstalleerde samenwerkingsmodule (#1861). Werk de bestaande Office-test calculatiepagina bij; maak geen tweede berekeningsbron of parallelle calculatie.

## Pagina en context
Bestaande route `/office-test/calculatie?draft_id={id}` blijft leidend. De bestaande router, sessiecontrole, projectvrije CRM-kans, draft-id, autorisatie, opslag, audit, revisie en prijsbron worden hergebruikt. `/office-test/materialen` blijft een zelfstandige beheermodule; binnen Calc alleen een contextuele materiaalzoeker, preview en link naar beheer.

## Visuele hiërarchie
1. **Vaste paginakop**: breadcrumb CRM / verkoopkans / calculatie, naam, draft-ID, status, revisienummer, laatst gewijzigd, verantwoordelijke; primaire actie Opslaan. Bij onvoldoende rechten read-only. Toon conflictmelding HTTP 409 zonder invoer te verliezen.
2. **KPI-rij**: verkoopbedrag, directe kostprijs, marge (€ en %), niet-geprijsde regels, optionele posten. Bedragen uitsluitend uit de bestaande server-side totalisatie; geen client-only financiële waarheid. Bij ontbrekende data `—`, nooit verzonnen 0.
3. **Twee kolommen** (desktop): links een inklapbare hoofdgroep/paragraafboom (NL-SfB/STABU/Eigen; maximaal drie paragraafniveaus); rechts het centrale bewerkbare calculatieraster. Op mobiel boom als uitklapbare sectie boven raster; raster horizontaal scrollbaar, geen paginabrede verspringing.
4. **Calculatieraster**: omschrijving, locatie, regeltype, hoeveelheid, eenheid, arbeid, materiaal, materieel, OA, overig, kostprijs, verkoopprijs, signaal. Regels alleen in eindparagrafen. Detailpaneel voor bronprijs, leverancier, artikelcode, onderbouwing en opmerkingen. Regeltypen: normaal, stelpost, optie, notitie, verdisconterend, verrekenbaar.
5. **Contextpanelen binnen dezelfde pagina**: Materialen, Tekeningen/gebouwelementen, Vragenlijst, Deelcalculaties, Revisies & audit. Geen afzonderlijke financiële opslag. Materiaalkeuze gebruikt de bestaande canonieke SALES-prijsbron en expliciete review; bestaande bronprijzen nooit ongemerkt wijzigen.
6. **Actiebalk**: Opslaan, Revisiemoment vastleggen, Controle uitvoeren, Offerte genereren. Laatste actie alleen wanneer geldige actuele immutable Calc-revisie, CRM-relatie/kans en rechten aanwezig zijn. Bij ontbreken duidelijke fout met herstelactie; nooit bronbinding omzeilen.

## Bediening en status
- Tabs zijn panelen binnen de bestaande pagina; geselecteerde tab blijft stabiel bij opslag en validatiefouten.
- Bij twee gelijktijdige schrijvers: 409, oude formulierwaarden behouden, revisies vergelijken. Elke mutatie met actor/tijd/revisie in bestaande audit.
- Geen handmatige ad-hoc prijswijziging onder de vlag van een goedgekeurd leveranciersartikel; override verbreekt de artikelclaim conform #1861.
- Vraaggestuurde calculatie geeft voorstellen voor kraan, steiger, toilet, actuele uurtarieven en logistiek; pas na expliciete bevestiging regels aanmaken.
- Tekeningen zijn documenten bij Gebouwen/elementen. Herkenning produceert voorstelregels die een gebruiker controleert; geen automatische definitieve calculatie.
- Import van externe offerte of inkoopfactuur gaat via de bestaande materiaal-/documentbronnen met preview, mapping, review en audit.

## Integratievolgorde
1. Inspecteer de **actief geïnstalleerde** native calculatiepagina en router via read-only hostcheck; de referentierouter in GitHub is niet automatisch de samengestelde productierouter.
2. Verplaats alleen presentatie/HTML/CSS naar bovenstaande indeling; laat bestaande POST-acties, CSRF, sessie, grants, data-repositories en server-totalisatie intact.
3. Voeg paneelnavigatie en responsieve rasterstijl toe met stabiele breedtes en zonder layoutverspringing.
4. Sluit bestaande materialenroute, samenwerking/revisies en CRM-Calc launch aan; markeer nog niet beschikbare tekeningen- of vragenlijstfuncties zichtbaar als niet beschikbaar, geen schijnknoppen.
5. E2E: anoniem geweigerd, read-only gebruiker, twee schrijvers, stale prijs, revisie, uploadrechten, Calc→offerte immutable binding, replay, browsernavigatie en mobiel.
6. Pas na groen bewijs: Office-test installatie met manifest/rollback, daarna gebruikersacceptatie en merge.

## Acceptatiecriteria
- Eén centrale werkbank, één native calculatie-ID en één canonieke financiële waarheid.
- Geen dubbele navigatie naar aparte calculator of dubbele artikelcatalogus.
- Overzicht/KPI's tonen exact dezelfde bedragen als opgeslagen revisie.
- Alle bestaande beveiligings- en auditcontracten blijven groen.
- Offerte #1 zonder bronbinding blijft terecht geblokkeerd; nieuwe offerte vanuit actuele Calc-revisie slaat binding automatisch op.
- Project ontstaat pas na aantoonbare acceptatie, met één vergrendelde werkbegroting.
