# BREBO Office - CRM-relaties en N:M-koppelingen (ontwerpbesluit, concept)

Status: VOORGESTELD - geen database- of productieaanpassing.

## Functionele definitie
CRM beheert **bedrijven**, **contactpersonen** en **prospects/kansen**. Een CRM-dossier toont daarnaast gerelateerde **gebouwen**, **projecten**, **calculaties**, **offertes** en **financiële gegevens**. De bestaande route `/office-test/crm/aanvragen` is uitsluitend een aanvragenoverzicht en mag niet worden gepresenteerd als volledig CRM.

## Relaties
- Bedrijf <-> contactpersoon: N:M; persoon kan meerdere functies bij meerdere bedrijven hebben.
- Bedrijf <-> gebouw en contactpersoon <-> gebouw: N:M, met rol (eigenaar, beheerder, bewoner, aanspreekpunt, enz.).
- Bedrijf <-> project en contactpersoon <-> project: N:M, met rol (opdrachtgever, uitvoerder, contact, enz.).
- Gebouw <-> project: N:M.
- Kans <-> bedrijven/contactpersonen/gebouwen: N:M waar van toepassing; prospect kan zonder gekoppeld bedrijf beginnen.
- Calculatie <-> kans/project en offerte <-> calculatie/kans/project: expliciete, versievaste koppelingen; geen impliciete duplicatie.
- Financiële transacties: relaties en projecttoerekeningen kunnen N:M zijn waar zakelijk nodig, maar wettelijke debiteur/crediteur en boekhoudkundige eigendom blijven eenduidig.

## Datamodelprincipes
1. Elke entiteit heeft een stabiele eigen ID; geen naam of adres als primaire sleutel.
2. N:M-relaties worden vastgelegd in expliciete koppeltabellen, met unieke relatie-identiteit, bron- en doel-ID, relatie-/roltype, status, begin- en einddatum, herkomst en auditinformatie.
3. Waar één record meerdere rollen of geldigheidsperioden kan hebben, mag een uniqueness constraint geen legitieme historische koppelingen blokkeren.
4. Koppelingen mogen uitsluitend worden aangemaakt naar bestaande, geautoriseerde records. Geen cross-tenant lekken; rechten gelden zowel voor objecten als relaties.
5. Relatiebeheer en de bestaande modules blijven eigenaar van hun eigen gegevens; CRM leest/koppelt en kopieert geen masterdata.
6. Verwijderen gebeurt gecontroleerd (soft delete / beëindigen relatie), met behoud van auditspoor en financiële historie.
7. Migraties zijn additief, idempotent en omkeerbaar; bestaande Office-project-, gebouw-, CRM- en calculatiegegevens blijven intact.

## Minimale UI-acceptatie
- CRM-menu: Bedrijven, Contactpersonen, Prospects & kansen; Aanvragen blijft afzonderlijk bereikbaar.
- Bedrijven- en contactpersonenoverzicht hebben altijd een zichtbare lege toestand en een duidelijke knop voor nieuw record, ook bij nul resultaten.
- Bedrijfs- en persoonsdossier tonen gekoppelde gebouwen, projecten, kansen, calculaties, offertes en toegestane financiële informatie.
- Koppelen en ontkoppelen gebeurt via geautoriseerde selecties van bestaande records, zonder duplicatie.
- Het tonen van een invoerformulier impliceert geen schrijfrecht; onbevoegde gebruikers zien een heldere melding.
- Geen module wordt als 'gereed' gemarkeerd voordat opslag, autorisatie, lege toestand, foutafhandeling en integratietests slagen.

## Bouwvolgorde
1. Inventariseer bestaande entiteiten, tabellen, repositories, routes en ACL in de actieve Office-test-release.
2. Leg canonieke IDs en bestaande relaties vast; ontwerp alleen ontbrekende koppeltabellen.
3. Voeg migraties en repositorytests toe zonder bestaande data te herschrijven.
4. Implementeer bedrijfs- en persoonsregistratie met een altijd zichtbare lege toestand.
5. Implementeer N:M-koppelingen met gebouwen/projecten en rolmetadata.
6. Verbind kansen, calculaties, offertes en financiële overzichten via bestaande module-interfaces.
7. Test per rol (Directie, Kantoor, Werkvloer) en voer gecontroleerde testdeploy uit.

## Vrijgave
Deze ADR is alleen een ontwerpdocument. Geen flags wijzigen, geen merge of deploy zonder expliciete toestemming.

## Repository-inventarisatie (2026-10-24, code bevestigd)

| Onderdeel | Bestaande implementatie | Consequentie |
| --- | --- | --- |
| Bedrijven | Drupal `brebo_organization`, inclusief organisatievelden en CRM-overzicht in `brebo_office_core/src/Controller/CrmController.php` | Bestaand concept en velden inventariseren; niet opnieuw bedenken. Dit is **niet** automatisch een Office-native tabel. |
| Contactpersonen | Drupal `brebo_contact` | Eigen identiteit, niet alleen een subrecord van bedrijf. |
| Contactpersoon-bedrijf N:M | Drupal `brebo_contact_affiliation`, velden `field_brebo_aff_contact_ref`, `field_brebo_aff_org_ref`, rol, afdeling, status, primair, geldig-van/tot | Relatielogica is al uitgewerkt in legacy; Office-native hergebruik/migratie vereist afzonderlijke beslissing. |
| Gebouwen | Office-native `office_buildings` in `OfficeBuildingSchema.php` | Niet opnieuw als CRM-gebouw opslaan. |
| Project-gebouw N:M | Office-native `office_project_buildings` in `OfficeBuildingSchema.php` | Bestaande koppeltabel hergebruiken. |
| Projecttoegang | Office-native `office_project_memberships` en `OfficeNativeProjectAccess` | Operatorautorisatie niet verwarren met commerciële contactpersoon-projectrelaties. |
| Aanvragen | `office-independent/public/crm-new-requests.php` accepteert GET en rendert alleen leesoverzicht | Bestaande CRM-menulink is nog **geen** bedrijven-/contactpersonenformulier. |

### Openstaande verificatie vóór implementatie
- Welke legacy Drupal-organisaties/contacten daadwerkelijk in de actieve Office-test-database aanwezig zijn, is **niet vastgesteld**. Geen automatische database-overname aannemen.
- Canonieke sleutels, migratie-/mappingstrategie, duplicaatdetectie en AVG-retentie eerst specificeren.
- Finance, calculatie, offerte en kans-entiteiten en hun actuele Office-native schema's afzonderlijk onderzoeken vóór nieuwe koppeltabellen.
- Geen bestaande ACL omzeilen om de relatiekaart te vullen.

### Directe vervolgstap
Een read-only schema- en datamappingaudit op de actieve Office-test-omgeving; daarna een afzonderlijke, geteste migratie-PR voor ontbrekende Office-native CRM-entiteiten en N:M-koppelingen. Niet activeren op basis van de legacy Drupal-structuur alleen.

## Besluit: lege start toegestaan (2026-10-24)
De bestaande CRM-invulling is uitsluitend voor testen gebruikt. Voor de nieuwe Office-native CRM is **geen inhoudelijke migratie van bestaande testrecords vereist**. De nieuwe registraties mogen leeg starten.

- Wel het volledige model en de N:M-relaties realiseren; geen dummy-bedrijven of dummy-contactpersonen nodig om formulieren te tonen.
- Lijstweergaven en formulieren blijven zichtbaar bij nul records, met expliciete lege toestand en toegestane aanmaakactie.
- Bestaande testdata **niet automatisch wissen**; bestaande project-, gebouw- en andere modulegegevens behouden.
- Hergebruik de bestaande schema-/relatiekennis, niet noodzakelijk de oude Drupal-inhoud.
- Nieuwe tabellen en migraties blijven additief en veilig; pas na expliciete vrijgave uitvoeren.
