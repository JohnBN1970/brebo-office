# BREBO Office-test – integrale demoketen

Scenario: `TEST-2026-001` – Renovatie 232 woningen.

## Proces en eigenaarschap

1. CRM: lead, klant en contactpersoon → projectaanvraag.
2. Projecten: één canoniek project-ID → gebouwkoppelingen.
3. Gebouwen: gebouw-ID's, 232 woningidentiteiten, bouwdelen.
4. Inspectie: waarnemingen → gebreken → herstelmaatregelen.
5. Calculatie: meetstaat → begroting → verkoopprijs.
6. Offerte: specificaties → offerte → acceptatie.
7. Contracten: contractbedrag, termijnen, goedgekeurd meer-/minderwerk; contractwaarde blijft ongewijzigd.
8. Inkoop: leveranciers → bestellingen → verplichtingen.
9. Personeel: actieve medewerkers → projectrollen.
10. Inzet/OnSite: geplande diensten → aanwezigheid → gewerkte uren.
11. Uitvoering: mijlpalen → voortgang → risico's.
12. Finance: verkoopfacturen → ontvangsten, inkoopfacturen → betalingen → projectresultaat.
13. MJOP: maatregelen en reserveringen, gescheiden van aannemersprijzen.
14. Rapportage: uitsluitend KPI's berekend uit bovenstaande brongegevens.

## Acceptatievoorwaarden

- Demo-data wordt **niet** door een dashboard of HTML-renderer bedacht.
- Iedere verwijzing gebruikt de bestaande Office-ID's; geen duplicaatproject of duplicaatgebouw.
- Een seeder is idempotent en weigert productiedatabases, productie-URL's en onherkende schema's.
- Geen externe Moneybird-post, e-mail, WhatsApp-activatie of bankbetaling vanuit de demo.
- Factuur- en betaalstatussen zijn lokaal gesimuleerd en duidelijk als DEMO gemarkeerd.
- Geen demo-data voor een ontbrekende module verzinnen: rapporteer `BLOCKED_SCHEMA`.
- Financiële invariant: contractwaarde wijzigt niet bij meer-/minderwerk; termijnfacturen plus afzonderlijke wijzigingen zijn herleidbaar.
- Ureninvariant: medewerker/project/dag-koppelingen herleiden dashboarduren tot onderliggende aanwezigheidsrecords.
- KPI-invariant: dashboardtotalen moeten exact overeenkomen met onderliggende records.
- Testdata mag uitsluitend na expliciete handmatige bevestiging worden geschreven.

## Implementatiestatus

De eerste PR definieert uitsluitend het centrale scenario en de test-isolatiecontrole.
**Er is nog geen database-seeder uitgevoerd.** De volgende stap is schema-inventarisatie
per module, gevolgd door idempotente seeders en end-to-end acceptatietests.
