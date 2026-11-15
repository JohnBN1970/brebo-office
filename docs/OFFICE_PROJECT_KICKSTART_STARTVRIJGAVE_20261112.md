# BREBO Office – Startdossier, Kickstart en vierogen-startvrijgave

Status: functioneel vastgesteld; beleid en contracttests voorbereid, **niet live geïnstalleerd**.

## Plaats
Bestaand projecttabblad **Werkvoorbereiding**, onder **Startdossier & Kickstart**.
Geen nieuw documentarchief, personeelsdossier, planningsmodule of inkoopadministratie.

## Werkwijze
1. Projectleider en werkvoorbereider stellen het startdossier samen.
2. Bouwplaatsdocumenten koppelen via de bestaande Office-documentenmodule, met revisie en geldigheid.
3. Werkinstructie per project/gebouw/zone en uitvoeringsfase vastleggen en versiebeheer toepassen.
4. Projectteam uit de bestaande Inzet-/Personeelsmodule selecteren.
5. Logistiek vastleggen: bouwplaats, losplaats, opslag, levervensters, deelleveringen, verantwoordelijken. Verwijzen naar de bestaande inkooporder- en ontvangstgegevens.
6. Kickstartoverleg registreren: deelnemers, agenda, vragen, antwoorden, besluiten, acties, eigenaren en deadlines.
7. Kritieke open punten blokkeren startvrijgave. Niet-kritieke punten blijven zichtbaar als taken.
8. Projectleider geeft eerste goedkeuring op een vastgelegde dossierrevisie.
9. Tweede, **andere** bevoegde persoon geeft onafhankelijke goedkeuring op exact dezelfde revisie.
10. Pas daarna status **Uitvoering vrijgegeven** voor de opgegeven scope (project, gebouw, zone of fase).

## Vierogencontract
- Beide goedkeuringen hebben verschillende **geverifieerde Office-gebruikers-ID's**.
- Eerste goedkeurder: projectleider. Tweede: apart geautoriseerde reviewer, volgens bestaande Office-ACL.
- Server-side authenticatie, projecttoegang en rolverificatie zijn verplicht. Niet vertrouwen op meegestuurde IDs/rollen.
- Iedere goedkeuring is gekoppeld aan dossier-ID, scope, revisie, beslisser, besluit, tijdstip en optionele toelichting.
- Afwijzing geeft het dossier terug voor aanpassing; een nieuwe revisie vereist nieuwe goedkeuringen.
- Materiële wijzigingen aan veiligheid, werkinstructie, documenten, team, logistiek, scope of kickstartbesluiten trekken eerdere vrijgave voor de geraakte scope in totdat opnieuw beoordeeld.
- Audittrail is append-only. Geen hard delete of stilzwijgend overschrijven.
- De startblokkade moet **server-side** gelden voor de actie die uitvoering daadwerkelijk activeert, niet alleen als UI-waarschuwing.
- Nooduitzonderingen alleen na afzonderlijk vast te stellen bestuursbeleid, niet via een verborgen bypass.

## Opslag en terugvinden
Project-ID + scope + dossierrevisie zijn de vaste sleutels. Documenten, medewerkers, taken en inkooporders worden via bestaande IDs gekoppeld.
Overzicht toont huidige status, ontbrekende onderdelen, open vragen, beslissingen, goedkeuringen en startvrijgavehistorie.
Projectoverzicht toont de startstatus zonder eigen tweede administratie.

## Technische uitrolstappen
1. Inventariseer de actuele Office-test project-, document-, taak-, Inzet- en inkooprepositories, bestaande ACL en actuele projectrouter.
2. Voeg schema, repository, transacties, CSRF, revisievergrendeling, audittrail en geautoriseerde goedkeuringsacties toe.
3. Koppel bestaande bronnen; geen kopieën van documenten of personeelsgegevens.
4. Integreer startdossier UI in bestaande V3-Werkvoorbereiding, achter testfeatureflag.
5. Blokkeer uitvoering server-side tot twee goedkeuringen op dezelfde revisie aanwezig zijn.
6. Test gelijktijdige goedkeuring, dubbele verzoeken, rolwisseling, ingetrokken rechten, gewijzigde revisie, scopewissel, kritieke open punten en rollback.
7. Pas na groene acceptatietests en expliciete toestemming test-only uitrollen.

## Status codewijzigingen 13 november 2026
- `OfficeProjectKickstartRepository::saveChecklist`: optimistische revisiecontrole, wijzigingen auditen en status terug naar concept.
- `OfficeProjectKickstartRepository::submitForApproval`: alleen de toegewezen projectleider kan een compleet dossier op de actuele revisie indienen.
- `OfficeProjectKickstartRepository::approve`: transacties en twee verschillende goedkeurders, maar **nog geen gekoppelde Office HTTP-controller of ACL-implementatie**.
- `OfficeProjectKickstartReadModel` en `OfficeProjectKickstartView`: read-only teruglezen en veilig weergeven.
- **Open:** database-installatie, formulieracties, CSRF, persistente kickstartvragen/antwoorden/besluiten, wijzigingsaudit voor alle gekoppelde bronnen, live V3-integratie en server-side gate in iedere uitvoeringsroute.
- **Belangrijk:** de testfeatureflag mag pas worden ingeschakeld na volledige end-to-end-acceptatie; de aanwezigheid van `assertReleased` betekent niet dat de uitvoeringsroutes reeds worden geblokkeerd.
