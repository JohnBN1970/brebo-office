# Office-native verkoopfacturen

BREBO beheert debiteur, adresmomentopname, factuurregels, btw, bedragen, vervaldatum, status en verzendhistorie. De verzendkoppeling krijgt uitsluitend een vastgezet verzendcommando. Er worden geen facturen uit Moneybird opgehaald. Het door Moneybird bevestigde factuurnummer wordt als verzendbewijs op de BREBO-factuur vastgelegd; er wordt geen tweede nummerreeks aangemaakt.

## Beschikbaar

- Losse facturen en facturen bij een toegankelijk Office-project.
- Begin met één factuurregel; voeg naar behoefte regels toe of verwijder regels, tot maximaal 100.
- PDF-download van de opgeslagen Office-factuur in BREBO-huisstijl. Concepten blijven herkenbaar als concept; de download verstuurt niets en haalt niets uit Moneybird op.
- Concept aanmaken en aanpassen met revisiecontrole; regels en bedragen in gehele centen, btw afgerond per regel.
- Normale 21%, 9% en 0% btw per regel. Verlegd/vrijgesteld en creditfacturen zijn nog niet ondersteund en worden niet als normaal 0% verwerkt.
- Gecontroleerd verzenden met geadresseerde en bericht; de factuur bevriest voordat de externe aanroep begint.
- Verzendstatus, vervaldatum, herinneringen en historie. Een herinnering mag alleen bij een vervallen openstaande factuur, maximaal eenmaal per kalenderdag.
- Een onzekere uitkomst blokkeert verdere verzending tot handmatige reconciliatie. Geen automatische retry, geen automatische betalingsbevestiging uit bankmutaties.

Een Moneybird-bevestiging betekent dat de verzendopdracht is verwerkt, niet dat de ontvanger de e-mail heeft gelezen. Betaald/deels betaald vereisen geboekte ontvangsten in BREBO; de bankkoppeling of Moneybird-status neemt dit niet stilzwijgend over. Ontvangsten kunnen na handmatige bankcontrole met datum en unieke referentie worden vastgelegd. De huidige versie bevat nog geen creditnota's of reconciliatiebeheerscherm.

## Installatie en activering

De code activeert zichzelf niet. Er zijn geen klantmails of facturen verstuurd door de tests.

1. Test de PHP-module met `php office-independent/tests/OfficeSalesServiceTest.php` en de integration-API met de bestaande PR-controles.
2. Plaats `src/Sales`, `public/office-sales.php` en de installatiehelper uitsluitend in de private Office-test-release. Behoud bestaande router- en navigatieaanpassingen bij het toevoegen van `/office-test/finance/verkoop`.
3. Voer de expliciete helper uit met `--apply-office-test-sales`. Deze maakt alleen vier nieuwe tabellen, overschrijft geen bestaande tabellen en weigert een reeds aanwezige of gedeeltelijk geïnstalleerde verkoopregistratie. Controleer bij een fout eerst welke tabellen zijn aangemaakt; er wordt niets automatisch verwijderd.
4. Verifieer het nieuwe schema, zet `OFFICE_SALES_SCHEMA_VERIFIED=enabled` en `OFFICE_SALES_ENABLED=enabled` in de bestaande testconfiguratie. Dit schakelt concepten in; het verleent geen rechten. Lezen vereist Finance `read`; wijzigen/verzenden vereist de bestaande Finance `draft_edit`. Losse facturen blijven voor de maker, projectfacturen volgen actuele projectlidmaatschappen.
5. Verifieer de bestaande Moneybird-relatiekoppeling in `office_crm_external_identities` (`provider_code=moneybird`) en de factuuradressen. Configureer `OFFICE_SALES_MONEYBIRD_TAX_RATES` als JSON met echte provider-ID's voor de gebruikte normale btw-tarieven. Stel nooit fictieve of voorbeeld-ID's in.
6. Reconcileer de integration-API-bron met de daadwerkelijk actieve Worker voordat een nieuwe Worker-versie wordt uitgerold. Behoud de actieve bank- en calculatieroutes. Deze branch alleen uitrollen kan ongewijzigde, elders uitgerolde routes missen.
7. Controleer een verzending met een eigen testdebiteur en expliciet gekozen ontvanger. Zet pas daarna `OFFICE_SALES_SEND_ENABLED=enabled`. Herinneringen vereisen daarnaast de nieuwe API-route met `MONEYBIRD_REMINDERS_ENABLED=enabled` en `OFFICE_SALES_REMINDERS_ENABLED=enabled`.

De bestaande Office-release, inkoopfacturen, Drupal-verkoopstromen en bankrechten worden niet door deze installatie veranderd. Er is geen achtergrondtaak die zelfstandig herinneringen verstuurt.

De aanvullende workflowkeuze `UPDATE-OFFICE-SALES-UI` plaatst uitsluitend de PDF-uitvoer en regelinvoer in een reeds geactiveerde testmodule. De helper vergelijkt de twee bestaande bestanden met de gecontroleerde revisie, maakt geverifieerde back-ups en controleert de nieuwe uitvoer. Configuratie en database worden niet gewijzigd. De PDF gebruikt de opgeslagen versie; sla wijzigingen eerst op.


## Inclusief btw, G-rekening en termijnfacturen

De standaard nieuwe factuur komt uit een beschikbare Office-projecttermijn. Een losse factuur blijft mogelijk. De aanneemsom komt uit het projectcontract. Het invoerbare termijnpercentage berekent deze factuur; het oorspronkelijke termijnschema wordt niet overschreven. De arbeidsprijs van deze factuur exclusief btw is een afzonderlijke, expliciete invoer. Het G-rekeningpercentage geldt uitsluitend over die arbeidsprijs. Er wordt geen arbeidspercentage uit het totale factuurbedrag geschat.

Losse facturen ondersteunen prijsinvoer inclusief of exclusief btw, met een uitsplitsing per tarief. Bij inclusief-btw-invoer wordt eerst het regelbedrag inclusief btw berekend en daarna de btw teruggerekend; de oorspronkelijke eenheidsprijs en modus worden behouden voor het verzendcommando. Termijnbedragen worden berekend over de aanneemsom exclusief btw.

Het contractoverzicht toont de aanneemsom, deze termijn, eerder gefactureerd exclusief btw, geregistreerd betaald inclusief btw, openstaande facturen inclusief deze termijn, en nog te factureren werk exclusief btw. Deze laatste twee bedragen blijven afzonderlijk. Concepten gelden niet als betalingen. Het overzicht is een momentopname bij opslaan of vrijgeven en verschijnt uitsluitend bij gekoppelde termijnfacturen. Eerdere als gefactureerd of betaald gemarkeerde termijnen zonder gekoppelde Office-factuur maken de volledigheid onzeker; dan worden geen volledige resterende bedragen geclaimd.

De extra tabel `office_finance_sales_context` bewaart de prijsmodus, arbeidsbasis, G-verdeling, brontermijn en contractmomentopname. Legacy-facturen zonder context blijven standaard exclusief btw. Een unieke brontermijn voorkomt twee facturen voor dezelfde termijn in deze flow. Concepten reserveren gezamenlijk de aanneemsom; verdere facturatie boven die som wordt geweigerd. Bestaande facturen en termijnen worden niet gemigreerd of overschreven.

`UPGRADE-OFFICE-SALES-BILLING` controleert de huidige bronnen, maakt uitsluitend de aanvullende contexttabel aan en bouwt een complete private versie van de verkoopmodule. Pas na controles wordt alleen het verkooproute-manifest naar die versie omgezet. Daardoor worden oude en nieuwe PHP-klassen niet binnen een verzoek gemengd. De Office-release, configuratie, bankkoppeling en bestaande factuurbedragen blijven behouden. Een bestaande nieuwe tabel of afwijkende bron blokkeert de upgrade voor beoordeling; de helper verwijdert nooit tabellen.

Een G-rekeningnummer moet expliciet zijn ingevuld en op IBAN-formaat zijn gecontroleerd voordat een factuur met G-verdeling wordt vrijgegeven. De software kan niet vaststellen of het rekeningtype door de bank als G-rekening is ingericht. Het afgesproken nummer en percentage worden door de gebruiker bevestigd. De verdeling verschijnt op de eigen PDF en in de uitgaande betalingsinstructie; er wordt geen bankbetaling uitgevoerd.

Betalingstermijn: het conceptformulier gebruikt hele kalenderdagen (0–3650). Factuurdatum plus dit aantal dagen bepaalt de vervaldatum, zowel in het formulier als bij opslaan op de server. Bestaande concepten leiden hun aantal dagen af uit de opgeslagen datums; verzonden facturen blijven ongewijzigd. De UPDATE-OFFICE-SALES-PAYMENT-DAYS-uitrol wijzigt geen schema of bestaande factuurgegevens en vereist de exacte bronversie van de voorgaande billing-v2-uitrol.
