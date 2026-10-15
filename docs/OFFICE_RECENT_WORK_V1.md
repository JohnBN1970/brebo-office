# Mijn laatste werkzaamheden — contract v1

Status: ontwerp op featurebranch; **niet uitgerold**. Geen databasewijzigingen uitvoeren voordat de afzonderlijke testdatabase is bewezen.

## Doel
Iedere ingelogde Office-gebruiker ziet op Mijn werkplek de laatste vijf betekenisvolle werkzaamheden en kan via **Verder waar je gebleven was** veilig terugkeren. Dit geldt voor Projecten, Calculatie, Inkoop, Finance en toekomstige modules.

## Vast te leggen gebeurtenis
- `operator_id`: uitsluitend uit de gevalideerde Office-sessie, nooit uit browserinput.
- `module`: vaste allowlist, bijvoorbeeld `projects`, `calculation`, `procurement`, `finance`.
- `resource_type`, `resource_id`: gevalideerde interne Office-identificatie; geen vrije URL.
- `view_key`: toegestane werkpositie binnen de module, bijvoorbeeld `calculation.chapter`.
- `position`: kleine, schema-gevalideerde set van niet-gevoelige navigatievelden, zoals hoofdstuk-ID.
- `updated_at`: server-tijdstip.
- `label`: bij tonen opnieuw afleiden uit de bron, niet vertrouwen op browsertekst.

## Gedrag
1. Registreer alleen openen, wisselen naar een werkobject, of succesvol opslaan; geen muisklikken of toetsaanslagen.
2. Bewaar maximaal vijf verschillende module/object/positie-combinaties per gebruiker, nieuwste bovenaan.
3. Toon alleen items waarvoor de huidige gebruiker **op dit moment** nog leesrechten heeft; verlopen/verwijderde items worden overgeslagen.
4. Bij hervatten wordt de huidige Office-sessie gecontroleerd, daarna module- en objectrechten, en pas dan wordt een veilige interne route opgebouwd.
5. Calculatie gebruikt dezelfde startwijzen: vanuit Project met projectcontext; vanuit Calculatie leeg, met latere zoekmogelijkheid. Een herinnerde calculatie mag nooit autorisatie of eenmalige Calc-overdracht omzeilen.
6. Niet-opgeslagen wijzigingen worden niet als opgeslagen gemarkeerd; waarschuw vóór navigatie indien de module dirty-state kent.
7. Bij uitloggen blijft de werkgeschiedenis per gebruiker bestaan; sessietokens, eenmalige launch-tokens, documentinhoud en persoonsgegevens worden **nooit** in de werkgeschiedenis opgeslagen.
8. Bied per gebruiker een mogelijkheid de geschiedenis te wissen; definieer een bewaartermijn voordat de functie actief wordt.

## Database (voorstel, nog niet toepassen)
`office_user_recent_work`: `operator_id`, `module`, `resource_type`, `resource_id`, `view_key`, `position_json`, `updated_at`. Unieke index op gebruiker + module + resource_type + resource_id + view_key. Index op gebruiker + updated_at. Per gebruiker atomair opschonen tot vijf items.

## Uitrol en tests
- Eerst testdatabase-migratie en rollback testen, zonder productieaanpassingen.
- Tests: anonieme toegang, gebruikersisolatie, intrekking rechten, verwijderd object, manipulatie resource-ID, ongeldige view_key, XSS in label, dubbele events, opslagstoring, en maximaal vijf.
- Featureflag standaard uit; dashboard toont de sectie alleen wanneer repository en toegangscontrole gereed zijn.
- Geen afhankelijkheid van een proxy of van de Calc-Node-database.
