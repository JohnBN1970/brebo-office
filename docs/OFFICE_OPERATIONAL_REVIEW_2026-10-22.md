# Operationele uren beoordelen — implementatie 22 oktober 2026

Deze aanvulling op PR #1411 implementeert de ontbrekende geautoriseerde webworkflow boven de bestaande `OperationalInzetStore` uit #1407. De integratie van #1409 en #1410 blijft behouden. Importcontrole is een afzonderlijke beoordeling en wordt niet naar geaccordeerde werkuren omgezet.

## Gebruikersroute

Na expliciete configuratie verschijnt op de testpagina Inzet de link **Ingediende uren beoordelen** naar `/office-test/inzet/uren-beoordelen`. Een bevoegde beheerder ziet uitsluitend registraties van eigen beheerprojecten, maximaal 25 per pagina. Elke kaart toont medewerker, project, datum, omschrijving, geplande en geregistreerde tijd en afwijking. De acties zijn **Accorderen**, **Afwijzen** en **Correctie vragen**. Een toelichting is verplicht bij afwijzen, correctie en iedere urenafwijking. Bij gelijke tijden krijgt accorderen zonder toelichting de tekst “Uren gecontroleerd”. De opgeslagen status en toelichting blijven na opnieuw openen zichtbaar.

Eigen registraties kunnen niet worden beoordeeld. Leestoegang tot een project geeft geen beoordelingsrecht. Zowel beoordelaar als indiener vereisen een vooraf geverifieerde koppeling van hun exacte tekstuele Office-account-ID naar een native personeels-ID; namen, spreadsheetlabels en numerieke casts leveren geen koppeling op.

## Beveiliging en opslag

De route is uitsluitend voor de testmount, HTTPS en de geconfigureerde host. Opslaan controleert de actuele Office-sessie, actief account, beheerrecht, actieve medewerker en projectteam. MySQL/MariaDB-rijsloten houden deze bevoegdheden vast totdat de afzonderlijke SQLite-transactie klaar is; de Office-database ontvangt geen schrijfacties. Het formulier bindt account, sessie, registratie, revisie en een momentopname van planning, registratie en auditgeschiedenis met een HMAC. Gewijzigde gegevens vereisen verversen. Dubbele of verouderde beslissingen voegen geen auditregel toe. Beslissing en auditregel worden atomair opgeslagen.

De loader accepteert uitsluitend een bestaande private SQLite buiten de webroot met het bestaande operationele schema. Bron- en importreviewbestanden zijn uitgesloten als doelbestand. WAL, onverwachte schemaobjecten en ontbrekende bestanden worden geweigerd; de route maakt geen database aan en roept geen migratie aan. GET opent alleen-lezen en verandert geen bestanden. Huidige importreviewpakketten en hun gedeelde `OfficeMount` blijven intact.

## Nog niet geactiveerd

Er is geen database aangemaakt of geconverteerd, geen configuratie gewijzigd en niets gedeployed. De operationele dataset en identiteitskoppeling moeten inhoudelijk worden gecontroleerd voordat deze route kan worden ingeschakeld. Dit document geeft geen toestemming voor die handelingen.

Vereiste instellingen, met alle schakelaars standaard uit:

| Instelling | Betekenis |
| --- | --- |
| `OFFICE_TEST_INZET_OPERATIONAL_REVIEW_ENABLED=enabled` | Testpagina leesbaar |
| `OFFICE_TEST_INZET_OPERATIONAL_REVIEW_WRITE_ENABLED=enabled` | Afzonderlijke schrijfvrijgave |
| `OFFICE_TEST_INZET_OPERATIONAL_SCHEMA_VERIFIED=enabled` | Bestaand schema vooraf beoordeeld |
| `OFFICE_TEST_INZET_OPERATIONAL_IDENTITIES_VERIFIED=enabled` | Native koppelingen vooraf beoordeeld |
| `OFFICE_TEST_INZET_OPERATIONAL_DATA_ROOT` | Private gegevensmap buiten webroot |
| `OFFICE_TEST_INZET_OPERATIONAL_SQLITE_PATH` | Bestaande afzonderlijke operationele SQLite |
| `OFFICE_TEST_INZET_OPERATIONAL_IDENTITY_MAP_PATH` | Bestaand privaat JSON-koppelbestand |
| `OFFICE_TEST_INZET_OPERATIONAL_CSRF_SECRET` | Geheim van minimaal 32 tekens, alleen nodig bij schrijven |

JSON-contract: `{"version":1,"mappings":[{"source_system":"office_operator","source_person_id":"exact-account-id","office_person_id":123}]}`. Waarden zijn voorbeelden; gebruik uitsluitend werkelijk geverifieerde koppelingen. De bestaande Office-login- en hostinstellingen blijven van toepassing.

Dit is beoordeling van reeds ingediende operationele registraties. Een invoer-/herindieningspagina, mobiele native gebouwzone-bootstrap, inhoudelijke opschoning van bronimporten en financiële verwerking zijn afzonderlijk resterend werk. Accorderen geeft geen factuur vrij.

## Validatie

De gerichte tests gebruiken uitsluitend wegwerpfixtures en controleren projectafscherming, eigen beoordelingen, exacte account-ID's, ingetrokken en verlopen bevoegdheden, gewijzigde planning/registratie/audit, HMAC, herhaalde opslag, auditrollback, Unicode-toelichtingen, paginering en blijvende weergave. De echte endpoint wordt in subprocessen getest, inclusief herkomstcontrole, POST-vrijgave, opgeslagen formulierwaarden en ongewijzigde Office-/bron-/importreviewbestanden. De configuratietests controleren private grenzen, geen aanmaak, aliasweigering en geen leesbijwerkingen. MySQL/MariaDB CI verifieert met twee verbindingen dat gelijktijdige intrekking blokkeert tot na de beslissing en daarna direct geldt. Alleen de expliciet genoemde wegwerp-CI-database mag hiervoor worden gebruikt.

Lokaal: 75 portable tests op PHP 8.3.35 geslaagd; Linux-specifieke installer en MySQL/MariaDB/Xcode volgen de bestaande CI-matrices. Kaartweergave visueel bekeken met fictieve gegevens. CI-resultaten voor de gepubliceerde code worden bij PR #1411 vastgelegd. De eerder door de gebruiker bevestigde importreview-opslagtest hoeft niet te worden herhaald.
