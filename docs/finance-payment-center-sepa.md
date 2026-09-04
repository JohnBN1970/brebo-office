# BREBO Finance - Betaalcentrum, controller en bankreconciliatie

## Doel

BREBO Office is de operationele controle- en beslislaag. Moneybird blijft de boekhoudkundige bron. ABN AMRO blijft de uitvoerende bankomgeving. Een gebruiker hoeft een in BREBO volledig gecontroleerde factuur niet opnieuw inhoudelijk in Moneybird te beoordelen.

De primaire gesloten keten wordt:

`inkoopfactuur -> codering -> prestatie -> three-way match -> betaalvrijgave -> controller -> betaalrun -> ABN AMRO -> bankuitvoering -> Moneybird/bank terugmelding -> automatische reconciliatie`

SEPA `pain.001` blijft beschikbaar als fallback/exportmogelijkheid wanneer directe bankinitiatie niet beschikbaar of niet gewenst is.

Daarnaast controleert BREBO ook alle bankmutaties die buiten een BREBO-betaalrun ontstaan, zoals handmatige overboekingen in ABN AMRO, automatische incasso's, pinbetalingen, bankkosten, rente en overige ontvangsten/uitgaven.

## Niet onderhandelbare principes

1. Geen BREBO-geinitieerde leveranciersbetaling zonder goedgekeurde `brebo_finance_payment_release`.
2. Een release blijft onderworpen aan de bestaande fasepoort, three-way-match en G-rekeningcontrole; de batchlaag implementeert die regels niet opnieuw maar valideert de actuele bronstatus opnieuw.
3. Vier-ogenprincipe op de betaalrun: samensteller en definitieve vrijgever zijn verschillende gebruikers.
4. AI mag nooit een harde financiele blokkade overrulen. Deterministische controles zijn leidend.
5. De digitale controller beoordeelt iedere betaalrun voor vrijgave en toont groen/oranje/rood met concrete redenen en geraakt bedrag.
6. Een gewijzigde betaalrekening na eerdere verificatie maakt een betaalvoorstel ongeldig totdat de rekening onafhankelijk opnieuw is geverifieerd.
7. G-rekeningbetalingen worden als afzonderlijke betaalinstructie in dezelfde gecontroleerde run opgenomen.
8. Een vrijgegeven batch is immutable. Iedere inhoudelijke wijziging maakt een nieuwe batch met een nieuwe hash.
9. Directe bankinitiatie is de primaire route wanneer ABN AMRO Business Account Payment beschikbaar is. SEPA-export is ISO 20022 `pain.001` als fallback; het gegenereerde bestand krijgt een SHA-256-hash in de audittrail.
10. Een succesvolle verzending/export is niet hetzelfde als een uitgevoerde betaling. Pas bank/Moneybird-reconciliatie mag een release/factuur als uitgevoerd/betaald afsluiten.
11. Moneybird blijft bron voor boekhoudkundige factuur/contactstatus; BREBO controleert na bankuitvoering automatisch of Moneybird exact heeft verwerkt wat BREBO heeft vrijgegeven of op de bank heeft waargenomen.
12. Geen stil herstel of handmatige wijziging buiten audit om.
13. Iedere bankmutatie op een gekoppelde BREBO-rekening krijgt in BREBO een controle- en reconciliatiestatus, ook wanneer de betaling niet vanuit BREBO is geinitieerd.
14. Een betaling is pas volledig groen wanneer de relevante keten aantoonbaar sluit: BREBO-verwachting/betaalrun, bankuitvoering en Moneybird-verwerking.

## Betaalrekeningbron

De primaire externe bron voor de crediteurinstructie is de Moneybird-contactregistratie die bij de inkoopfactuur hoort. Moneybird levert onder meer `bank_account`, `sepa_iban`, `sepa_iban_account_name`, `sepa_bic`, `is_trusted` en `max_transfer_amount`. BREBO bewaart voor een betaalrun een gecontroleerde snapshot/hash van de gebruikte betaalinstructie; de actuele bron wordt voor definitieve vrijgave opnieuw gelezen en vergeleken.

Een ontbrekende IBAN, ongeldige IBAN, gewijzigd rekeningnummer, niet-verifieerbare contactkoppeling of overschrijding van een aantoonbare transferlimiet blokkeert automatische vrijgave.

## ABN AMRO - primaire betaalroute

BREBO ondersteunt de ABN AMRO Business Account Payment-koppeling als primaire route voor goedgekeurde betaalruns. De gewenste flow is:

`BREBO vrijgave -> ABN AMRO betaalopdracht -> autorisatie in ABN AMRO -> uitvoering -> status/transactie terug naar BREBO`

De definitieve bankautorisatie blijft buiten BREBO bij de bevoegde bankgebruiker. BREBO mag geen betaling als uitgevoerd markeren alleen omdat de opdracht door ABN AMRO is geaccepteerd.

Per bankinstructie bewaart BREBO minimaal:

- BREBO betaalrun-id;
- ABN AMRO request/payment-id;
- EndToEndId;
- bronrekening;
- tegenrekening;
- bedrag en valuta;
- gewenste uitvoerdatum;
- verzendstatus;
- autorisatie-/bankstatus voor zover beschikbaar;
- uitvoeringsreferentie;
- payload-hash;
- tijdstippen en actor.

### ABN AMRO knop in Finance

Het Finance/Betaalcentrum krijgt een duidelijke externe ingang naar ABN AMRO voor de menselijke bankautorisatie en uitzonderingsafhandeling. Deze wordt als herkenbare knop weergegeven met het officiele ABN AMRO-beeldmerk/logo en tekst zoals `Open ABN AMRO`.

Eisen:

- opent de officiele zakelijke ABN AMRO-bankomgeving in een nieuw tabblad;
- geen inloggegevens, tokens of sessies worden door BREBO opgeslagen of in de URL gezet;
- knop is zichtbaar vanuit het Betaalcentrum en bij een batch die `wacht op bankautorisatie` is;
- externe-link indicatie en toegankelijke tekst/aria-label aanwezig;
- lokaal opgeslagen/meegeleverde merkasset gebruiken conform repository- en licentiebeleid; geen runtime hotlink naar willekeurige derde partij.

## Digitale controller - harde controles

Minimaal per betaalrun:

- alle releases bestaan en staan nog op `approved`;
- onderliggende facturen bestaan, zijn niet betaald/geannuleerd en zijn nog volledig `matched`;
- totaal release = regulier deel + G-rekeningdeel = factuurtotaal;
- G-rekeninginstructie is aanwezig, goedgekeurd en actueel wanneer van toepassing;
- leverancier/contact en betaalrekening zijn herleidbaar;
- betaalrekening is sinds verificatie niet gewijzigd;
- dezelfde release/factuur zit niet in een andere open/vrijgegeven batch;
- batchvaluta is EUR voor SEPA credit transfer;
- uitvoerdatum is geldig;
- samensteller is niet de definitieve vrijgever;
- payload-hash bij vrijgave is identiek aan de beoordeelde payload-hash;
- bronrekening is expliciet aan BREBO en de juiste bankrol gekoppeld;
- een reeds uitgevoerde banktransactie kan nooit nogmaals als nieuw betaalvoorstel worden uitgevoerd.

Een fout in een harde controle geeft `rood` en blokkeert vrijgave/verzending/export.

## Digitale controller - intelligente signalen

Bovenop de harde controles kan de controller signaleren:

- nieuwe of recent gewijzigde IBAN/G-rekening;
- ongebruikelijk betaalbedrag voor leverancier/project;
- opvallende clustering rond procuratiegrenzen;
- versneld betaalverzoek;
- herhaaldelijke match-/factuurafwijkingen;
- afwijkend patroon in beslisser/goedkeurder;
- materieel liquiditeitseffect van de run;
- betaling buiten BREBO terwijl voor dezelfde factuur al een open betaalrelease/batch bestaat;
- onverwachte begunstigde of afwijkend bankrekeningnummer;
- dubbele of bijna-dubbele banktransactie;
- niet aan Moneybird gekoppelde bankmutatie;
- combinatie van meerdere kleine signalen.

Deze signalen zijn geen bewijs van fraude of onjuist handelen. Ze vragen menselijke beoordeling en worden met bron/evidence opgeslagen.

## Betaalcentrum

De Office-bedienlaag toont minimaal:

- Vandaag / vervallen / binnen 7 dagen / later;
- goedgekeurd maar nog niet in batch;
- batch in voorbereiding;
- controller vraagt aandacht;
- wacht op tweede goedkeurder;
- vrijgegeven voor ABN AMRO;
- wacht op bankautorisatie;
- verzonden naar bank;
- SEPA fallback beschikbaar/geexporteerd;
- wacht op bank/Moneybird-reconciliatie;
- uitgevoerd/gereconcilieerd;
- bankmutaties buiten BREBO;
- uitzonderingen.

Per run zichtbaar:

- batchnummer;
- uitvoerdatum;
- aantal facturen en betaalinstructies;
- regulier bedrag;
- G-rekeningbedrag;
- totaal;
- bankrekening waaruit wordt betaald;
- liquiditeit voor/na de run zodra de bron beschikbaar is;
- controller-oordeel en bevindingen;
- samensteller, controleur/vrijgever en tijdstippen;
- payload-hash en eventueel SEPA-bestandshash;
- ABN AMRO betaal-/requestreferentie;
- reconciliatiestatus.

## Bankmutaties buiten BREBO-betaalruns

BREBO leest alle beschikbare transacties van gekoppelde ABN AMRO-rekeningen in en behandelt ook handmatige/automatische betalingen als gecontroleerde financiele gebeurtenissen.

De matcher gebruikt waar beschikbaar:

- bedrag en valuta;
- boekings- en valutadatum;
- IBAN/tegenrekening;
- tegenpartijnaam;
- EndToEndId;
- betalingskenmerk/remittance;
- factuurnummer;
- Moneybird-id;
- BREBO factuur/release/batch-id;
- G-rekeningkenmerken;
- historische leverancier/bankrelatie.

Uitkomsten:

- `groen`: exact/zeker automatisch gekoppeld;
- `oranje`: waarschijnlijke koppeling of classificatie vereist menselijke bevestiging;
- `rood`: materiele afwijking, mogelijke dubbele betaling of onverklaarde transactie;
- `neutraal`: nieuwe bankmutatie die nog niet voldoende is beoordeeld.

Voor gewone bedrijfsuitgaven zonder voorafgaande inkoopfactuur kan BREBO een voorstel doen voor grootboek, vaste-kostenrubriek, project/kostenplaats en benodigd bewijsstuk. De gebruiker bevestigt waar de bron onvoldoende zekerheid geeft.

Inkomende betalingen worden op dezelfde manier aan verkoopfacturen/debiteuren gekoppeld.

## SEPA pain.001 fallback

BREBO genereert uitsluitend na definitieve vrijgave. De export bevat geen niet-goedgekeurde of gewijzigde release. De generator valideert minimaal:

- batch- en message-id uniek;
- EUR;
- uitvoerdatum;
- debiteurrekening van BREBO expliciet geconfigureerd;
- crediteurnaam;
- geldige IBAN;
- bedrag > 0;
- unieke EndToEndId per betaalinstructie;
- remittance/reference herleidbaar naar factuur/release;
- control sum exact gelijk aan de batchinhoud.

## Drieweg-reconciliatie BREBO - bank - Moneybird

Na verwerking worden bank- en Moneybirdgegevens teruggelezen. BREBO vergelijkt minimaal:

- factuur/release/batch-identiteit;
- bedrag;
- valuta;
- betaaldatum;
- betaalreferentie/EndToEndId;
- tegenrekening;
- regulier/G-rekeningdeel waar aantoonbaar beschikbaar;
- Moneybird-koppeling van de bankmutatie aan de juiste inkoop- of verkoopfactuur.

De gewenste eindstatussen zijn:

- `bank uitgevoerd + Moneybird gekoppeld`: groen en volledig gereconcilieerd;
- `bank uitgevoerd + Moneybird nog niet gekoppeld`: oranje, boekhoudkundige koppeling ontbreekt;
- `Moneybird betaald maar bankbewijs ontbreekt/afwijkt`: rood of onderzoek nodig;
- `bedrag/IBAN/referentie wijkt af`: rood;
- `buiten BREBO betaald maar exact aan bekende factuur gekoppeld`: groen na automatische of expliciete bevestiging, met auditmarkering `external_bank_payment`.

Alleen exacte of expliciet beoordeelde reconciliatie sluit de betaalrelease. Verschillen komen als financiele controlebevinding terug in Office en worden door de digitale controller meegenomen.

## Completion acceptance

Finance is op dit onderdeel pas gereed wanneer een normale bevoegde Office-gebruiker zonder Moneybird als operationele werkplek kan:

1. goedgekeurde betaalvoorstellen selecteren;
2. een betaalrun samenstellen;
3. het controller-oordeel en alle blockers zien;
4. vier-ogen vrijgave uitvoeren;
5. de run rechtstreeks naar ABN AMRO sturen en daar autoriseren, met SEPA `pain.001` als fallback;
6. de batch na bankverwerking automatisch laten reconciliëren;
7. alle overige inkomende en uitgaande bankmutaties automatisch laten matchen/classificeren;
8. ontbrekende Moneybird-bank/factuurkoppelingen in BREBO als uitzondering zien;
9. afwijkingen in Office oplossen/beoordelen;
10. de volledige eurotrace en audittrail terugzien;
11. vanuit het Betaalcentrum via een herkenbare ABN AMRO-logo-knop de officiele bankomgeving openen.

Moneybird blijft boekhoudkundige sturing en bronadministratie; dubbele handmatige inhoudelijke controle in Moneybird is geen onderdeel van de doelworkflow.
