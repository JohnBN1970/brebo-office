# BREBO Project Continuity

Peildatum: 5 september 2026
Status: actief overdrachtsdocument voor de laatste website-/projectpublicatieslices.

## Canonieke waarheid

BREBO Office is de bron van waarheid voor projecten. Het bestaande `brebo_project` is het canonieke tijdelijke projectobject. Het permanente gebouwmodel blijft leidend voor gebouwidentiteit, objectstructuur en historie.

De publieke keten is:

`BREBO Office -> expliciete publieke vrijgave -> Integration API -> publieke BREBO-website`

De website is consumer/publicatiekanaal en wordt geen tweede projectadministratie.

## Achterhaalde roadmaptekst

De eerdere conclusie dat de volgende ontwikkelsprong via roadmapitem `#28` eerst `brebo_project` opnieuw moest baselinen/opbouwen is achterhaald. `brebo_project` bestaat reeds als canoniek Office-projectobject en wordt hergebruikt.

Ook oudere voortgangspercentages zoals `Website 99%, CIM-integratie 56%, Roadmap 98%, totaal circa 85%` mogen niet als actuele technische waarheid worden herhaald zonder nieuwe meting. De ontwikkeling is verder dan die statusmelding aangaf.

## Afgerond

- Homepagehero is afgerond en runtime beeldvullend bevestigd.
- Homepagevoordelen zijn afgerond.
- Homepage Inzicht / Regie / Realisatie is afgerond.
- BREBO Lens is gerepareerd, gedeployed en runtime door gebruiker bevestigd; niet opnieuw wijzigen zonder nieuwe aanleiding.
- Projectenoverzicht op de website bevat al de gewenste klantgerichte presentatie-opbouw, maar de huidige website-nodebron is tijdelijk.
- Office PR #582 heeft het projectpublicatiecontract vastgelegd en is gemerged.
- Dat contract borgt opt-in publicatie, alleen gerealiseerde resultaten, afzonderlijke mediavrijgave en veilige intrekking/caching.

## Actief: Office projectpublicatiemodel

PR #585 bouwt een begrensde 1-op-1 publieke presentatieprojectie boven het bestaande `brebo_project`.

De projectie bevat uitsluitend publieke presentatiegegevens, waaronder stable public ID, slug, titel, intro, gebouwvraag, gekozen aanpak, aantoonbaar gerealiseerde resultaten, Lens-rollen, geschikte publieke status en afzonderlijk goedgekeurde media.

Niet publiek zijn onder meer calculaties, begrotingen, marges, leveranciersdata, interne communicatie/notities, persoonsgegevens en andere interne dossierinformatie.

Publicatie is default uit. Release en withdrawal zijn expliciete, beperkte handelingen. Extern relevante wijzigingen verhogen `publication_version`; intrekking moet binnen het afgesproken cachecontract doorwerken.

## Reviewstatus PR #585

De eerste Codex-P1 — module niet opgenomen in centrale deploy — is opgelost.

De tweede review vond drie punten die nu worden verwerkt:

1. behoud de bestaande productie-concurrencygroep `brebo-office-production`;
2. behoud de bewezen Composer-build/deployketen zodat `vendor`, Drupal core en contrib exact met `composer.lock` worden uitgerold;
3. declareer `brebo_office_core` als moduledependency omdat die het canonieke `brebo_project` levert.

De correctie herstelt daarom de bewezen bestaande deployworkflow en voegt alleen de projectpublicatiemodule en runtimebewijzen toe; de deployarchitectuur wordt niet onnodig herschreven.

## Verplichte vervolgroute

1. PR #585 volledig schoon door review krijgen.
2. #585 squash-mergen naar `develop`.
3. Office productie-deploy controleren: module enabled, drie publicatietabellen aanwezig, bestaande runtime intact.
4. Office-side read-only publieke projectie/service bouwen met release/withdrawal/versioning-regels.
5. Alleen die projectie via de bestaande Integration API beschikbaar maken; geen generieke Office-velden naar buiten.
6. `brebo-platform` read-only laten consumeren en de tijdelijke canonieke website-projectinhoud afbouwen.
7. Bilderdijkstraat / Da Costakade als eerste echte project vanuit Office publiceren; geen demo-data en geen interne prijsinformatie.
8. Laatste homepagegedeelte/CTA afronden.
9. Complete livegangcontrole uitvoeren: desktop/mobiel, routes, formulieren, projectfeed, cache/intrekking, foutafhandeling en deploy.

## Werkmethode

Per onderdeel blijft gelden:

`oude branch -> backup -> actuele develop -> doctrine/CIM -> beste versie samenstellen -> PR -> Codex/review -> fixes -> merge -> deploy -> runtimecontrole`

Geen blind herstel van oude branches. Geen duplicatie van bestaande objecten. Geen nieuw contenttype alleen omdat een procesconcept bestaat. Eerst bestaande objecten, velden, relaties, services en workflows hergebruiken.

## Autonomie

Routineontwikkeling wordt doorgezet zonder telkens toestemming te vragen:

`bouwen -> review controleren -> feedback oplossen -> mergen -> deployen -> resultaat controleren`

Alleen stoppen bij een echte blokkade, onverwachte productie-impact of een inhoudelijke beslissing die alleen de gebruiker kan nemen.

## Livefocus

Niet meer verbreden. De resterende websitefocus is projectpublicatie plus het laatste homepagegedeelte en daarna livegangcontrole. Verdere cosmetische finetuning is geen reden om livegang uit te stellen tenzij een concrete kwaliteits- of functionele fout wordt aangetoond.
