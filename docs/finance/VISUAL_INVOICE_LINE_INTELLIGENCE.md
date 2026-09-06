# BREBO Finance — visuele factuur en regelintelligentie

Status: implementation contract.

## Doel

De inkoopfactuurwerkbank moet niet alleen de genormaliseerde factuurkop tonen, maar ook het originele bronbestand en de echte factuurregels. Factuurregels vormen daarnaast een gecontroleerde prijsbron voor de BREBO-calculatie.

## Vaste keten

`origineel bronbestand -> extractie -> factuurkop + factuurregels -> menselijke/automatische controle -> Finance matching -> productkandidaat -> BREBO-product -> calculatieprijsbron`

Het originele document blijft canoniek bewijs. Finance maakt geen tweede documentkopie. De viewer gebruikt de bestaande bronverwijzing uit de centrale intake.

## Visuele factuur

De factuurdetailpagina krijgt een documentpaneel naast de Finance-werkbank.

- PDF: inline viewer met paginanavigatie en zoom via de browserviewer.
- PNG/JPG/JPEG/WEBP: inline afbeelding.
- Overige bestandstypen: metadata plus veilige actie `Origineel openen`.
- Alleen geautoriseerde Office-gebruikers mogen het private bronbestand ophalen.
- De viewer resolveert uitsluitend een bestaande intake/file-verwijzing; nooit een willekeurig pad uit requestdata.

## Factuurregels

Elke bronregel wordt als transactiewaarheid vastgelegd. Waar aanwezig bewaren we minimaal:

- bronregelnummer;
- leveranciersartikelnummer / externe artikelreferentie;
- omschrijving zoals op de factuur;
- aantal;
- eenheid;
- prijs per eenheid excl. btw;
- regelbedrag excl. btw;
- btw-code / btw-percentage;
- btw-bedrag;
- regelbedrag incl. btw;
- bron van de extractie;
- extractieconfidence;
- ruwe bronwaarde of provenance waarmee de regel herleidbaar blijft.

Ontbrekende waarden worden niet verzonnen. Berekende waarden worden als berekend herkenbaar gehouden en niet als letterlijk gemeten/gelezen brongegeven gepresenteerd.

De som van de regels wordt gecontroleerd tegen de factuurkop. Een materieel verschil wordt een review/control-signaal en wordt nooit stil gladgestreken.

## Bronnen en prioriteit

Factuurregels kunnen afkomstig zijn uit het originele document of uit genormaliseerde providerdata, bijvoorbeeld Moneybird. Deze bronnen worden niet blind over elkaar heen geschreven. Wanneer beide beschikbaar zijn, bewaart BREBO provenance en vergelijkt de waarden. Een betekenisvol verschil vereist review.

## Calculatiepromotie

Een factuurregel is niet automatisch een calculatieproduct.

1. `factuurregel`: onveranderde transactiewaarheid;
2. `productkandidaat`: regel lijkt een herbruikbaar inkoopartikel;
3. `BREBO-product`: gecontroleerde centrale calculatiebouwsteen;
4. `prijswaarneming`: feitelijke leverancierprijs op datum X, gekoppeld aan product en leverancier.

Automatische productpromotie is niet toegestaan. Office mag wel automatisch een bestaande productmatch voorstellen op sterke identiteit, zoals leverancier + artikelnummer, EAN of een reeds bevestigde alias.

Kostenregels zoals vracht, korting, administratie, schadeherstel of incidentele regie worden niet automatisch productkandidaat. Ze kunnen later een eigen kostensoort/staartkostenbehandeling krijgen.

## Prijsintelligentie

Een goedgekeurde productmatch mag de prijswaarnemingshistorie aanvullen. Een nieuwe factuurprijs overschrijft nooit stil de calculatiebasis. De calculatie kan vervolgens gecontroleerd tonen:

- laatste werkelijke inkoopprijs;
- leverancier en factuurdatum;
- historische prijswaarnemingen;
- afwijking ten opzichte van de huidige calculatieprijs;
- beschikbare leveranciersalternatieven voor hetzelfde BREBO-product.

De gebruiker of een expliciete prijsregel bepaalt welke prijs als calculatieprijs geldt.

## Eerste implementatieslice

1. Resolveer per Finance-inkoopfactuur het canonieke intake-bronbestand en maak dit geautoriseerd zichtbaar in de detailwerkbank.
2. Leg een bronneutraal contract vast voor genormaliseerde factuurregels en vul `brebo_finance_purchase_invoice_line` idempotent vanuit beschikbare brondata.
3. Toon provenance en een regel/kop-totalencontrole.
4. Introduceer daarna pas productkandidaten en prijswaarnemingen richting `brebo_calculation`.

Deze volgorde voorkomt dat onvolledige of fout geëxtraheerde regels de calculatiecatalogus vervuilen.
