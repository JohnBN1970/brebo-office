# BREBO Office — centrale instellingen voor gebruikers, rollen en rechten

Status: ontwerpbesluit, nog geen actieve gebruikersinterface of databasewijziging.

## Instellingen
- Gebruikers: uitnodigen, activeren/deactiveren, rollen toekennen, projecttoegang beheren.
- Rollen en rechten: rollen maken en per module bevoegdheden beheren.
- Organisatie: bedrijf, vestigingen en personeelsgegevens.
- Modules: modulebeschikbaarheid en instellingen.
- Auditlog: wie heeft welke rechten, goedkeuring of configuratie gewijzigd.
- Algemeen: centrale Office-instellingen.

## Autorisatiemodel
Één Office-identiteit per gebruiker; geen afzonderlijke accounts per module. Gebruikers kunnen meerdere rollen hebben. Per module expliciete rechten `read`, `create`, `update`, `approve`, `manage`. Moduletoegang is niet gelijk aan projecttoegang. Bij projectgebonden acties moeten **beide** controles slagen: modulebevoegdheid én projectlidmaatschap. Bij ontbreken van rechten: weigeren. Deactiveren van gebruiker blokkeert alle toegang.

Voorbeelden van configureerbare rollen: directie, bedrijfsleider, projectleider, werkvoorbereider, calculator, administratie, uitvoering. Dit zijn voorgestelde standaardrollen, geen hardcoded toegangsregels. Alleen bevoegde beheerders kunnen rollen en gebruikers aanpassen; wijzigingen zijn auditbaar.

## Offertes
- Lezen: module `offers.read` plus toegang tot betreffende relatie/project.
- Concept maken/wijzigen: `offers.create` / `offers.update`.
- Uitgeven: afzonderlijk `offers.issue`.
- Acceptatie registreren: afzonderlijk `offers.record_acceptance`, met bewijs en audit.
- Geaccepteerde offerte aan meer-/minderwerk koppelen: `offers.link_change` én projectbeheerrechten.
- Goedkeuren van meer-/minderwerk blijft expliciet bij Projecten; nooit impliciet bij offerteacceptatie.

## Invoering
1. Inventariseer bestaande Office-tabellen `office_operator_accounts`, `office_project_memberships`, `office_module_permissions` en bestaande sessiecontrole; migreer niet blind.
2. Maak centrale autorisatie-API/service en tests voor combinatie module + project, actieve gebruiker en meerdere rollen.
3. Maak Instellingen-schermen en auditlog; beperk beheertoegang.
4. Sluit Offertes aan op deze service, vóór activering in Office-test.
5. Controleer rechten voor CRM, Projecten, Calculatie, Finance en Inzet zonder bestaande toegang stilzwijgend te verruimen.

**Geen nieuwe rechten activeren of schema uitvoeren vanuit een webrequest.**
