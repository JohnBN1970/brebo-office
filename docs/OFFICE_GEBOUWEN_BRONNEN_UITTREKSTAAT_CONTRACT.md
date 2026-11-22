# Gebouwen — Opname, documentlezer en uittrekstaat (integratiecontract)

Status: ontwerpcontract voor Office-test. Geen nieuwe opslagbron of automatische vrijgave.

## Eigenaarschap
Gebouwen is eigenaar van de canonieke structuur: gebouw → woningtype → woning/zone → bouwdeel → element. NL-SfB, STABU en vrije code zijn classificaties, niet de identiteit van het element. Calculatie blijft eigenaar van financiële regels en versies; de uittrekstaat levert gecontroleerde hoeveelheden, geen kostprijzen.

## Drie afzonderlijke werkbanken
1. **Opname:** registreert waarnemingen, metingen, foto's en afwijkingen per bestaande gebouwlocatie; kan ontbrekende elementen voorstellen.
2. **Tekening-/documentlezer:** registreert document, versie, pagina/blad en herkende elementen, maatvoering en specificaties als *voorstellen*, niet als vastgestelde feiten.
3. **Uittrekstaat:** stelt regels samen uit gekoppelde elementen en bronwaarnemingen, rekent hoeveelheden met expliciete eenheid en formule, toont verschillen en biedt gecontroleerde vrijgave richting calculatie.

## Voorstelcontract
Elk voorstel bevat minimaal: `building_id`, optioneel `building_type_id` / `unit_id` / `zone_id`, `element_ref` (indien al bekend), `element_type`, `classification_system`, `classification_code`, `source_type` (opname/tekening/document/handmatig), `source_id`, `source_revision`, `source_locator` (ruimte, blad of pagina), `measurement_value`, `measurement_unit`, `confidence_or_uncertainty`, `created_by`, `created_at` en `status` (voorgesteld/gecontroleerd/afgewezen).

Bij onvolledige gegevens blijven velden leeg en vraagt Office om controle; geen fictieve waarden. Een voorstel wijzigt geen bestaand element zonder expliciete vergelijking en bevestiging. Dubbele herkenningen worden op gebouwcontext, elementreferentie, bron en geometrie/locatie beoordeeld.

## Hoeveelheden
Een uittrekregel verwijst naar de canonieke elementen, de gebruikte bronversies, een rekenregel, eenheid, resultaat en reviewer. Hergebruik per woningtype werkt met concrete gekoppelde woningen en expliciete uitzonderingen per woning; niet met onverklaarde vermenigvuldiging. De hoeveelheid is na vrijgave een onveranderlijk snapshot voor de betreffende calculatieversie. Wijzigingen uit nieuwe opnames of documenten worden als verschillen voorgesteld, nooit stilzwijgend doorgevoerd.

## Grenzen voor eerste implementatie
- CL-204 blijft zelfstandig en behoudt bestaande concepten en revisiehistorie. Nieuwe invoervelden krijgen een eigen versie/migratiepad; historische snapshots blijven leesbaar.
- Bestaande gebouwtabellen en routes eerst inventariseren; geen parallelle gebouw-, woningtype- of elemententabellen zonder expliciete schema-analyse.
- Geen automatische schrijfoperaties vanuit AI/documentherkenning; eerst review, dan pas canonieke koppeling.
- Geen automatische calculatieoverdracht voordat rolrechten, audit, CSRF, idempotentie en revisieconflicten getest zijn.

## Acceptatieketen
Tekening met 6 kozijnen → zes voorstellen met bladverwijzing → vergelijking met opname (eventueel 5 aangetroffen) → verschil zichtbaar → bevoegde controle → elementkoppelingen → uittrekstaat met formule/eenheid en uitzonderingen per woning → expliciete overdracht van een vastgezette hoeveelheid naar calculatie. Beide bronwaarnemingen blijven zichtbaar.
