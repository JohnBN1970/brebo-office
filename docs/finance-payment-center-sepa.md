# BREBO Finance - Betaalcentrum, controller en SEPA

## Doel

BREBO Office is de operationele controle- en beslislaag. Moneybird blijft de boekhoudkundige bron. De bank blijft de uitvoerende betaalomgeving. Een gebruiker hoeft een in BREBO volledig gecontroleerde factuur niet opnieuw inhoudelijk in Moneybird te beoordelen.

De gesloten keten wordt:

`inkoopfactuur -> codering -> prestatie -> three-way match -> betaalvrijgave -> controller -> betaalrun -> SEPA pain.001 -> bank -> Moneybird/bank terugmelding -> automatische reconciliatie`

## Niet onderhandelbare principes

1. Geen betaling zonder goedgekeurde `brebo_finance_payment_release`.
2. Een release blijft onderworpen aan de bestaande fasepoort, three-way-match en G-rekeningcontrole; de batchlaag implementeert die regels niet opnieuw maar valideert de actuele bronstatus opnieuw.
3. Vier-ogenprincipe op de betaalrun: samensteller en definitieve vrijgever zijn verschillende gebruikers.
4. AI mag nooit een harde financiële blokkade overrulen. Deterministische controles zijn leidend.
5. De digitale controller beoordeelt iedere betaalrun voor vrijgave en toont groen/oranje/rood met concrete redenen en geraakt bedrag.
6. Een gewijzigde betaalrekening na eerdere verificatie maakt een betaalvoorstel ongeldig totdat de rekening onafhankelijk opnieuw is geverifieerd.
7. G-rekeningbetalingen worden als afzonderlijke betaalinstructie in dezelfde gecontroleerde run opgenomen.
8. Een vrijgegeven batch is immutable. Iedere inhoudelijke wijziging maakt een nieuwe batch met een nieuwe hash.
9. SEPA-export is ISO 20022 `pain.001`; het gegenereerde bestand krijgt een SHA-256-hash in de audittrail.
10. Een succesvolle export is niet hetzelfde als een uitgevoerde betaling. Pas bank/Moneybird-reconciliatie mag een release/factuur als uitgevoerd/betaald afsluiten.
11. Moneybird blijft bron voor boekhoudkundige factuur/contactstatus; BREBO controleert na verzending automatisch of Moneybird exact heeft verwerkt wat BREBO heeft vrijgegeven.
12. Geen stil herstel of handmatige wijziging buiten audit om.

## Betaalrekeningbron

De primaire externe bron is de Moneybird-contactregistratie die bij de inkoopfactuur hoort. Moneybird levert onder meer `bank_account`, `sepa_iban`, `sepa_iban_account_name`, `sepa_bic`, `is_trusted` en `max_transfer_amount`. BREBO bewaart voor een betaalrun een gecontroleerde snapshot/hash van de gebruikte betaalinstructie; de actuele bron wordt voor definitieve vrijgave opnieuw gelezen en vergeleken.

Een ontbrekende IBAN, ongeldige IBAN, gewijzigd rekeningnummer, niet-verifieerbare contactkoppeling of overschrijding van een aantoonbare transferlimiet blokkeert automatische vrijgave.

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
- payload-hash bij vrijgave is identiek aan de beoordeelde payload-hash.

Een fout in een harde controle geeft `rood` en blokkeert vrijgave/export.

## Digitale controller - intelligente signalen

Bovenop de harde controles kan de controller signaleren:

- nieuwe of recent gewijzigde IBAN/G-rekening;
- ongebruikelijk betaalbedrag voor leverancier/project;
- opvallende clustering rond procuratiegrenzen;
- versneld betaalverzoek;
- herhaaldelijke match-/factuurafwijkingen;
- afwijkend patroon in beslisser/goedkeurder;
- materieel liquiditeitseffect van de run;
- combinatie van meerdere kleine signalen.

Deze signalen zijn geen bewijs van fraude of onjuist handelen. Ze vragen menselijke beoordeling en worden met bron/evidence opgeslagen.

## Betaalcentrum

De Office-bedienlaag toont minimaal:

- Vandaag / vervallen / binnen 7 dagen / later;
- goedgekeurd maar nog niet in batch;
- batch in voorbereiding;
- controller vraagt aandacht;
- wacht op tweede goedkeurder;
- vrijgegeven voor SEPA;
- SEPA geëxporteerd;
- wacht op bank/Moneybird-reconciliatie;
- uitgevoerd/gereconcilieerd;
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
- payload-hash en SEPA-bestandshash;
- reconciliatiestatus.

## SEPA pain.001

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

## Reconciliatie

Na verwerking worden Moneybird/bankgegevens teruggelezen. BREBO vergelijkt minimaal:

- factuur/release/batch-identiteit;
- bedrag;
- valuta;
- betaaldatum;
- betaalreferentie;
- regulier/G-rekeningdeel waar aantoonbaar beschikbaar.

Alleen exacte of expliciet beoordeelde reconciliatie sluit de betaalrelease. Verschillen komen als financiële controlebevinding terug in Office.

## Completion acceptance

Finance is op dit onderdeel pas gereed wanneer een normale bevoegde Office-gebruiker zonder Moneybird als operationele werkplek kan:

1. goedgekeurde betaalvoorstellen selecteren;
2. een betaalrun samenstellen;
3. het controller-oordeel en alle blockers zien;
4. vier-ogen vrijgave uitvoeren;
5. een immutable SEPA `pain.001` downloaden;
6. de batch na bankverwerking automatisch laten reconciliëren;
7. afwijkingen in Office oplossen/beoordelen;
8. de volledige eurotrace en audittrail terugzien.

Moneybird blijft boekhoudkundige sturing en bronadministratie; dubbele handmatige inhoudelijke controle in Moneybird is geen onderdeel van de doelworkflow.
