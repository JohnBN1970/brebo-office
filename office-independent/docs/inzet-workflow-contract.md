# BREBO Office Inzet - workflowcontract v1

Status: ontwerp op de geisoleerde office-test branch. Geen automatische migratie, geen productieaanpassing.

## Ingangen

- Hoofdmenu **Inzet**: overzicht van projecten waarvoor de operator projecttoegang heeft.
- Projectdetail **Inzet**: dezelfde module, gefilterd op het geselecteerde Office-project.
- Gebruik uitsluitend `office_projects.id` en `office_project_memberships` als project- en toegangsbron. Geen Drupal node-ID als impliciete Office-project-ID.

## Bestaande BREBO Inzet workflow (bron: brebo_inzet)

1. Projectteam: medewerkers structureel koppelen aan een project. Loskoppelen verwijdert geen historische planning of klokregistraties.
2. Beschikbaarheid: verlof en niet-beschikbaarheid toetsen voordat diensten worden ingepland.
3. Dag-/weekplanning: selecteer projectteamleden; plan een dag of maandag-vrijdag; standaard 07:00-16:00; status gepland of bevestigd.
4. Dubbele daginzet voorkomen; overlappende diensten en beschikbaarheidsconflicten signaleren.
5. Kloklocaties per gebouw/project: instelbare werkzone, meerdere zones mogelijk, locatie alleen gebruiken voor noodzakelijke aanwezigheidstoetsing.
6. Aanwezigheid: inklokken, pauze, uitklokken; actieve sessie per medewerker; afwijkingen registreren.
7. Urencontrole: geplande versus werkelijke uren, goedkeuring door bevoegde projectbeheerder.
8. Financiele verwerking: pas na gecontroleerde koppeling aan de projectkosten en arbeidsbudgetten.

## BREBO OnSite - mobiele app en bestaande webapp

- De bestaande Inzet-webapp blijft als bewezen functionaliteit beschikbaar tijdens de overgang.
- BREBO OnSite (iOS eerst, Android later) gebruikt dezelfde Office-projecten, projectteams, planning, klokzones en registraties als Office Inzet; geen tweede klokadministratie.
- App-projectselectie volgt toegewezen projectteam en/of geldige daginzet, nooit uitsluitend een vrij opgegeven project-ID.
- Bestaande OnSite device-/OTP-koppeling moet als identiteitsworkflow behouden en opnieuw beveiligd worden voor zelfstandige Office API's.
- De app legt feitelijke aanwezigheid vast: project-ID, tijdstip, IN/UIT, technische bron, optionele locatie/accuracy/zone en auditspoor.
- Registratie is een waarneming; uren ontstaan pas na beoordeling en goedkeuring in Office. Geen automatische boeking op basis van locatie.
- Geen continue GPS-tracking, geen routehistorie. Locatie uitsluitend op relevante klok-/herkenningsmomenten en volgens vast te leggen AVG-bewaarbeleid.
- Offline registratie, retries en dubbele app-verzoeken vereisen idempotente gebeurtenis-ID's en server-side reconciliatie.
- De bestaande Inzet-clocksession en geofence-logica dienen als functionele referentie; geen rechtstreekse Drupal API-afhankelijkheid in de uiteindelijke Office/OnSite-koppeling.

## Scheiding van bevoegdheden

- Alleen ingelogde operators met `office_project_memberships.permission IN ('read','manage')` mogen projectinzet bekijken.
- Alleen `manage` mag team, planning, zones of urenbeoordeling wijzigen.
- Werknemer-selfservice (klokken) vereist een afzonderlijk ontworpen identiteit en beperkte projectautorisatie; beheerrechten zijn daarvoor niet vereist en mogen niet worden hergebruikt.
- Alle schrijfoperaties vereisen CSRF-controle, server-side validatie en auditregistratie.
- Projectgegevens nooit uitleveren op basis van een vrij meegegeven project-ID zonder ACL-check.

## Voorafgaand aan eerste test-DB-migratie

- Verifieer types van `office_projects.id` en `office_operator_accounts.id`.
- Bepaal personeelsidentiteit (medewerker/onderaannemer) en mapping naar loginaccounts.
- Bevestig tabelnamen en sleutels van de Inzet-schemaconcepten; leg referentiele integriteit vast.
- Leg tijdzone, datumgrenzen, overlapregels en historiebeleid vast.
- Voer pas daarna een gereviewde, expliciet goedgekeurde migratie uit in `u213420663_office_test`.
- Zet geen Calc-launch of andere productievlaggen aan.

## Implementatiestatus

De onafhankelijke geofence-, zone-, rooster-, pauze- en kloktijdlogica en de read-only project-ACL zijn aanwezig. De Inzet-databasetabellen zijn alleen voorgesteld; de daadwerkelijke schermgegevens, planningwrites, clockwrites en financiele synchronisatie zijn nog niet aangesloten.
