# Office-native verkoopfacturen

BREBO beheert debiteur, adresmomentopname, factuurregels, btw, bedragen, vervaldatum, status en verzendhistorie. De verzendkoppeling krijgt uitsluitend een vastgezet verzendcommando. Er worden geen facturen uit Moneybird opgehaald. Het door Moneybird bevestigde factuurnummer wordt als verzendbewijs op de BREBO-factuur vastgelegd; er wordt geen tweede nummerreeks aangemaakt.

## Beschikbaar

- Losse facturen en facturen bij een toegankelijk Office-project.
- Concept aanmaken en aanpassen met revisiecontrole; regels en bedragen in gehele centen, btw afgerond per regel.
- Normale 21%, 9% en 0% btw per regel. Verlegd/vrijgesteld en creditfacturen zijn nog niet ondersteund en worden niet als normaal 0% verwerkt.
- Gecontroleerd verzenden met geadresseerde en bericht; de factuur bevriest voordat de externe aanroep begint.
- Verzendstatus, vervaldatum, herinneringen en historie. Een herinnering mag alleen bij een vervallen openstaande factuur, maximaal eenmaal per kalenderdag.
- Een onzekere uitkomst blokkeert verdere verzending tot handmatige reconciliatie. Geen automatische retry, geen automatische betalingsbevestiging uit bankmutaties.

Een Moneybird-bevestiging betekent dat de verzendopdracht is verwerkt, niet dat de ontvanger de e-mail heeft gelezen. Betaald/deels betaald vereisen geboekte ontvangsten in BREBO; de bankkoppeling of Moneybird-status neemt dit niet stilzwijgend over. De huidige versie bevat nog geen invoer voor geboekte ontvangsten, creditnota's of een reconciliatiebeheerscherm.

## Installatie en activering

De code activeert zichzelf niet. Er zijn geen klantmails of facturen verstuurd door de tests.

1. Test de PHP-module met `php office-independent/tests/OfficeSalesServiceTest.php` en de integration-API met de bestaande PR-controles.
2. Plaats `src/Sales`, `public/office-sales.php` en de installatiehelper uitsluitend in de private Office-test-release. Behoud bestaande router- en navigatieaanpassingen bij het toevoegen van `/office-test/finance/verkoop`.
3. Voer de expliciete helper uit met `--apply-office-test-sales`. Deze maakt alleen drie nieuwe tabellen, overschrijft geen bestaande tabellen en weigert een reeds aanwezige of gedeeltelijk geïnstalleerde verkoopregistratie. Controleer bij een fout eerst welke tabellen zijn aangemaakt; er wordt niets automatisch verwijderd.
4. Verifieer het nieuwe schema, zet `OFFICE_SALES_SCHEMA_VERIFIED=enabled` en `OFFICE_SALES_ENABLED=enabled` in de bestaande testconfiguratie. Dit schakelt concepten in; het verleent geen rechten. Lezen vereist Finance `read`; wijzigen/verzenden vereist de bestaande Finance `draft_edit`. Losse facturen blijven voor de maker, projectfacturen volgen actuele projectlidmaatschappen.
5. Verifieer de bestaande Moneybird-relatiekoppeling in `office_crm_external_identities` (`provider_code=moneybird`) en de factuuradressen. Configureer `OFFICE_SALES_MONEYBIRD_TAX_RATES` als JSON met echte provider-ID's voor de gebruikte normale btw-tarieven. Stel nooit fictieve of voorbeeld-ID's in.
6. Reconcileer de integration-API-bron met de daadwerkelijk actieve Worker voordat een nieuwe Worker-versie wordt uitgerold. Behoud de actieve bank- en calculatieroutes. Deze branch alleen uitrollen kan ongewijzigde, elders uitgerolde routes missen.
7. Controleer een verzending met een eigen testdebiteur en expliciet gekozen ontvanger. Zet pas daarna `OFFICE_SALES_SEND_ENABLED=enabled`. Herinneringen vereisen daarnaast de nieuwe API-route met `MONEYBIRD_REMINDERS_ENABLED=enabled` en `OFFICE_SALES_REMINDERS_ENABLED=enabled`.

De bestaande Office-release, inkoopfacturen, Drupal-verkoopstromen en bankrechten worden niet door deze installatie veranderd. Er is geen achtergrondtaak die zelfstandig herinneringen verstuurt.
