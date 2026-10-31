# BREBO Office — CRM, offertes, calculatie en projectmeerwerk

Status: architectuurbesluit ter implementatie; geen claim van werkende functionaliteit.

## Eigenaarschap
- CRM: klantrelaties, leads, kansen en commerciële navigatie. CRM is procescontext, niet de eigenaar van alle objecten.
- Offertes (Office): zelfstandig offerteobject, regels, versies, scope, voorwaarden, btw, geldigheid, acceptatie en documentmomentopname.
- Calculatie (Calc): calculaties, rekenregels, kostprijs/verkoopprijs, calculatieversies. Optionele bron voor een offerte.
- Projecten (Office): project, contract, contractwijzigingen, projectbetaaltermijnschema en geaccepteerde contracttermijnen.
- Finance (Office): facturatie, betalingen, openstaande posten; leest vastgelegde contractafspraken en beheert geen concurrerend projectbetaalschema.

## Twee ingangen, één offerteobject
1. CRM: relatie/kans -> offerte zonder calculatie OF calculatie -> offerte -> acceptatie -> project/opdracht.
2. Project: bestaand project -> meer-/minderwerkverzoek -> optionele calculatie -> offerte -> akkoord -> contractwijziging -> Finance.

Dezelfde offerte- en calculatiedomeinen worden vanuit CRM en vanuit het project geopend; geen kopieën of afzonderlijke projectofferte-engine.

## Relaties en statussen
- Offerte heeft een eigen ID en versie, relatie-ID, optioneel kans-ID, optioneel project-ID, optioneel calculatie-ID + onveranderlijke calculatieversie, en optioneel change-order-ID.
- Een offerte mag ontstaan zonder project en zonder calculatie. Bij acceptatie volgt een expliciete projectkoppeling of projectaanmaak.
- Meerwerkofferte is gekoppeld aan exact één bestaand project en één wijzigingsdossier; minderwerk kan negatief zijn.
- Statusvoorstel: concept -> ter goedkeuring -> verzonden -> geaccepteerd/afgewezen/vervallen; verzonden versies blijven historisch intact.
- Concepten en niet-geaccepteerde offertes verhogen de contractwaarde niet.
- Alleen expliciet geaccepteerd meer-/minderwerk leidt tot een geautoriseerde contractmutatie, met audittrail en oorspronkelijke contractwaarde intact.

## Betaaltermijnen
- Projecten is eigenaar van het gestructureerde betaaltermijnschema (termijn, percentage/bedrag, omschrijving, trigger/voorwaarde).
- Offerte kan een voorstel/snapshot overnemen en een eigen commerciële formulering opslaan; dit wijzigt het projectstandaard-schema niet.
- Bij acceptatie wordt een onveranderlijke contractuele termijnversie vastgelegd. Finance factureert uitsluitend volgens de geautoriseerde contractversie.
- Meerwerk kan eigen termijncondities krijgen, onafhankelijk van het oorspronkelijke schema. Geen stilzwijgende overschrijving of herberekening.
- Percentages, bedragen en btw-regimes blijven gestructureerd; vrije tekst is geen financiële bron van waarheid.

## Grensvlakken
- Calc -> Offerte: expliciete, versiegebonden commerciële snapshot met regels, bedragen en btw-regimes; nooit live meekoppelen na uitgifte.
- Offerte -> Project/Contract: geaccepteerde versie met besluit, datum, verantwoordelijke en contractmutatie.
- Project/Contract -> Finance: geautoriseerde termijnversie en contractwaarde, inclusief afzonderlijke meer-/minderwerkmutaties.
- Outputgenerator: weergave/PDF op basis van bevroren offerteversie, niet de eigenaar van de offerte.

## Migratie en acceptatie
1. Inventariseer bestaande Office-native projectcontract-, wijzigings- en termijndiensten en legacy Drupal OfferVersionForm; voorkom dubbele opslag.
2. Maak een zelfstandige Office-offerte-API en opslag met optionele calculatie- en projectkoppelingen; introduceer geen verplichte calculatie-FK.
3. Ondersteun offertecreatie vanuit CRM én project met dezelfde service; projectcontext zet wijzigingsdossier en project-ID.
4. Integreer calculatiesnapshot via een expliciet contract; verander Calc niet zonder noodzaak.
5. Koppel goedkeuring aan geautoriseerde contractmutatie en termijnen; borg autorisatie en idempotentie.
6. Test: zelfstandige offerte, calculatieofferte, meerwerk, minderwerk, afwijzing, versiebehoud, aparte betaalafspraak, Finance zonder ongeautoriseerde mutatie.
7. Pas na groene tests en acceptatie een afzonderlijke Office-testrelease toe; productie blijft onaangeroerd.

## Huidige afwijking
De in develop gemergede PR #1468 past legacy Drupal `OfferVersionForm` aan en vertrekt vanuit een calculatie. Dit is niet de definitieve zelfstandige Office-offertearchitectuur en is niet automatisch actief in de standalone Office-testrelease of de Node.js Calc-app.
