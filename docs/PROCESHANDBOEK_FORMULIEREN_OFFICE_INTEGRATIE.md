# BREBO Office — proceshandboekformulieren (bronregister en integratiecontract)

Bron: **BREBO_Proceshandboek_Compleet-beheerst-exemplaar_versie-1.0.pdf**, 442 pagina's, beheerd exemplaar 7 augustus 2026. PH-000 v1.2 is leidend. Dit bestand is een implementatiecontract, **geen** verklaring dat formulieren al live zijn.

## Formulieren en doelmodule

| Code | Document | Doelmodule |
|---|---|---|
| SJ-001 | Dossieridentiteit en bronverwijzing | Office-breed |
| FR-201 | Klantvraag en intake | CRM / Leads |
| CL-204 | Projectopname | Gebouwen / Inspectie |
| FR-210 | Calculatie-uitgangspunten | Calculatie |
| CL-220 | Offerte- en opdrachttoets | Deals / Offertestudio |
| FR-300 | Projectoverdracht | Projecten / Startdossier |
| CL-310 | Planning, capaciteit en logistiek | Planning |
| FR-320 | Inkoop- en onderaannemersvrijgave | Inkoop |
| CL-330 | Uitvoeringsgereedheid | Projecten / Startdossier |
| FR-400 | Startvrijgave | Projecten / Uitvoering |
| RG-401 | Digitaal bouwdagboek | Projecten / OnSite |
| CL-410 | Keurings- en kwaliteitsplan | Kwaliteit |
| FR-430 | Wijzigings- en meerwerkformulier | Projecten / Calculatie |
| CL-500 | Vooroplevering | Projecten / Oplevering |
| RG-501 | Restpuntenregister | Projecten / Oplevering |
| FR-510 | Oplevering en overdracht | Projecten / Oplevering |
| RG-520 | Service-, klacht- en garantieregister | Service |
| CL-600 | Afsluitcontrole | Projecten |
| FR-601 | Nacalculatie en evaluatie | Calculatie / Finance |
| RG-610 | Financiële projectbewaking | FinanceCenter |
| RG-620 | Contract-, claim- en juridisch beheer | Contractbeheer |

Centrale registers RG-001 t/m RG-009 worden eveneens als beheerde gegevensdragers ingericht; geen tweede schaduwregister naast bestaande Office-domeintabellen.

## Brongetrouwe minimale procespoorten

- **FR-201**: uniek INT-YYYY-NNNN, ontvangstkanaal en -tijd, REL-ID en contractpartij, contactpersoon, locatie, klantvraag in eigen woorden, scope, aanleiding, termijnen, onzekerheden, eigenaar en besluit/vervolg. Registratie is geen opdracht.
- **FR-300**: projectidentiteit, geldende contractbasis, scope/WBS, calculatie/budget, planning, opname, inkoop, risico's en open punten. Ontvangende rollen bevestigen hun toepasselijke deel; projectleider accepteert projectbasis.
- **FR-400**: nooit starten zonder actuele CL-330, exacte werkpakket-/locatiegrens, startvenster, bevestiging door werkvoorbereider en uitvoerder en bevoegd gedateerd besluit. Kritische ontbrekende voorwaarde blokkeert; vervallen voorwaarde vereist stop/hertoets.
- **FR-430**: geen gewijzigde uitvoering zonder vastgelegde aanleiding, classificatie, scope, prijs, tijd, risico, voorwaarden en bevoegd besluit; noodmaatregelen voor veiligheid/schadebeperking zijn begrensde uitzondering.

## Implementatievoorwaarden

1. Behoud documentcode, bronversie, eigenaar, status, gekoppeld proces en bewijsvereisten. Een formulierinstantie heeft project-/gebouw-/relatiecontext, actor, timestamps, status, revisie en audittrail.
2. Hergebruik bestaande Office-relaties, contactpersonen, gebouwen, projecten, calculaties, documenten en autorisatie. Geen dubbele stamgegevens en geen Drupal als bron.
3. Formulieren: concept → ingediend → controle → akkoord/afgekeurd; waar proces vereist ook blokkade, terugzetten, hertoets en formele vrijgave. Historie append-only, inclusief reden bij correctie.
4. Bevoegdheden worden per handeling server-side getoetst aan RL-001 t/m RL-016 en toepasselijk mandaat. Vierogenprincipe configureerbaar per blueprint; nooit dezelfde actor als onafhankelijke controleur wanneer ingeschakeld.
5. In testfase rolweergave 'bekijk als' uitsluitend als UI-simulatie; geen privilege-escalatie of bypass van echte serverrechten.
6. Per formulier velddefinities, conditionele verplichting, validatie, bewijsstukken, eigenaar, vervaldatum en beslisregels uit het eigen brondocument overnemen vóór functionele oplevering.
7. UAT per formulier: opslaan/heropenen, verplichtingen, rollen, audit, versieconflict, bewijs, statusovergangen, rollback en gekoppelde module. Geen productieactivatie zonder pilot, onafhankelijke audit en directiebesluit.

## Bronbeperking

De bundel meldt dat PH-110, RR-001, OS-001 en MM-001 niet als afzonderlijke actuele bronbestanden zijn opgenomen; de normatieve inhoud is functioneel vertegenwoordigd in PH-000, RL-001–016 en procesdocumenten. Niet doen alsof afzonderlijke definitieve bronbestanden aanwezig zijn.

## Volgorde zonder dubbelbouw

1. Bestaande Startdossier- en CRM-intakefunctionaliteit tegen FR-300/CL-330/FR-400 en FR-201 toetsen.
2. Generiek formulierenregister en instantie-/besluit-/bewijskoppelingen realiseren met migraties en tests.
3. Modules aansluiten in volgorde CRM → projectoverdracht → startvrijgave → uitvoering → oplevering → afsluiting.
4. Pas daarna functioneel vrijgeven op Office-test.
