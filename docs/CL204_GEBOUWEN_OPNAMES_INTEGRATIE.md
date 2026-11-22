# CL-204 — Gebouwen > Opnames

CL-204 is een **projectopname**, geen inspectieopdracht. De UI-plaatsing is **Gebouwen → Opnames → Projectopname**. Technische inspecties en inspectierapporten blijven afzonderlijke opdrachten.

## Integratiecontract
- Toon alle opnames bij het betreffende gebouw; een opname kan optioneel een project-ID hebben. Geen kopie van adres, contactpersonen of gebouwstamgegevens.
- Gebruik het bestaande `OfficeProcessFormRepository` en `OfficeProcessFormContext` voor opslag, revisies en gebeurtenissen. De huidige contextcode `buildings_inspection` is een **technische catalogussleutel** en wordt niet zonder migratie hernoemd; in de interface staat uitsluitend **Opnames**.
- De host controleert vóór iedere read/write: sessie, gebouwtoegang, rolrechten, CSRF bij mutaties, bestaande schema's en eventuele featureflags. De fragmentklasse `OfficeCl204FormView` voert zelf geen opslag of autorisatie uit.
- V1–V6 is de *inhoudelijke bruikbaarheidsstatus van de opname*, niet de algemene formulierworkflowstatus en niet de status van Lead/Deal/Project.
- Bewijs verwijst naar bestaande documenten, met herleidbaarheid naar gebouw, bouwdeel, datum en opnemer. Geen parallelle documentopslag.
- Meerdere opnames per gebouw en meerdere disciplines/bevindingen per opname blijven toegestaan.
- Eerst functionele acceptatie en centrale styling; geen aparte inline CSS en geen automatische calculatievrijgave.

## Nog niet gerealiseerd
Gebouwenroute/tab **Opnames**, server-side controller, veilige conceptopslag, documentkoppeling en functionele Office-test-acceptatie. Deze notitie activeert geen route of featureflag.
