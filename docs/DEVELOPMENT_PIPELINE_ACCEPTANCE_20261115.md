# Acceptatie ontwikkelstraat — 15 november 2026

## Aantoonbaar geïnstalleerd

De bestaande centrale workflow installeert nu zelfstandig Office-test na integratie op `develop`. De eerste volledige functionele acceptatie is geslaagd op commit `e45e02cc8104247011a0d423919af9c8edd9fd28`.

Bewijs: [GitHub Actions-run 43701647027](https://github.com/JohnBN1970/brebo-office/actions/runs/43701647027). Zowel `Office-test source gate` als `Office-test deployment and acceptance` zijn geslaagd. De uitvoer bevat `OFFICE_TEST_HTTP_FOUR_EYES_ACCEPTANCE_PASS` en de geaccepteerde deploy-SHA. Het fictieve acceptatieproject heeft ID 7.

## Functionele proef via de echte routes

| Controle | Resultaat |
|---|---|
| Seeden tweemaal op dezelfde release | Zelfde fictieve project, geen duplicatie |
| Aanmelding projectleider, reviewer, lezer en buitenstaander | Geslaagd via HTTP-login |
| Anonieme toegang | Doorverwijzing naar login |
| Buitenstaander op project | Geweigerd met 404 |
| Lezer probeert goed te keuren | Geweigerd met 403 |
| Ongeldige CSRF en verouderde revisie | Geweigerd |
| Dossier opslaan en uit database teruglezen | Geslaagd |
| Ongewijzigd dossier opnieuw opslaan | Revisie behouden |
| Kickstartvraag, antwoord en besluit | Opgeslagen en zichtbaar |
| Dezelfde persoon geeft tweede goedkeuring | Geweigerd |
| Afwijzing zonder reden | Geweigerd |
| Afwijzing met reden | Opgeslagen; dossier terug voor aanpassing |
| Twee verschillende bevoegde goedkeurders | Vrijgave opgeslagen |
| Uitvoering starten zonder vrijgave | Server-side geweigerd |
| Uitvoering starten met vrijgave | Projectstatus opgeslagen |
| Materiële revisie na start | Goedkeuring ongeldig en status terug naar voorbereiding |
| Kritiek open punt | Indienen en starten geblokkeerd |
| Vier testsessies na intrekken | Alle vier via HTTP geweigerd |

## Terugval en bronverschillen

De oorspronkelijke Hostinger-release bevatte 46 afwijkende repositorybestanden. De installer behield 44 ongewijzigde overlays en voegde twee wijzigingen gecontroleerd samen. Runtime-only afhankelijkheden blijven behouden.

Mislukte HTTP-proeven hebben de vorige configuratie/release automatisch hersteld; zie onder meer [run 43699030918](https://github.com/JohnBN1970/brebo-office/actions/runs/43699030918). Een controllerconflict werd vóór activering geblokkeerd in [run 43701305670](https://github.com/JohnBN1970/brebo-office/actions/runs/43701305670). Er zijn geen destructieve downmigraties uitgevoerd.

De HTTP-proef gebruikt een begrensd verzoektempo op de gedeelde hosting. Een burst gaf 503-responses; de volledige proef slaagde met minder dan drie verzoeken per seconde. Er worden geen mislukte verzoeken blind herhaald.

## Integraties en productiegrens

Geïntegreerd: PR #1879 (scheiding productie), #1816 (startdossier) en #1880–#1885 (centrale installatie, acceptatie en herstel).

Productie is niet uitgerold. De productieworkflow is uitgeschakeld, de develop-pushtrigger is verwijderd en productie vereist een expliciete release-tag/dispatch plus de beschermde omgeving. Productiereview is verplicht, zelfreview is geblokkeerd en beheerders mogen deze bescherming niet omzeilen. Historische automatische remote routes zijn verwijderd; bestaande geplande bedrijfsoperaties blijven gedocumenteerde uitzonderingen.

## Nog open

1. GitHub vraagt opnieuw om accountverificatie voor het opslaan van de voorbereide branchregel op `develop`. De passkeyverificatie is geweigerd. De regel is daarom nog niet als actief aangetoond. Minimaal nodig: één herbevestiging van de GitHub-identiteit; daarna kan de uitvoerder de regel opslaan en verifiëren.
2. De SSH-identiteit is nog de bestaande gedeelde Hostinger-toegang. Er zijn geen nieuwe credentials of extra bevoegdheden aangemaakt.
3. Nieuwe modulemigraties en acceptatiescenario's worden expliciet aan de centrale route toegevoegd. De eerste geaccepteerde keten is het startdossier; dit is geen claim dat alle Office-modules volledig functioneel geaccepteerd zijn.
4. Een productierelease heeft afzonderlijk akkoord nodig en, door de ingestelde zelfreviewpreventie, een goedkeurder die verschilt van de startende identiteit. Productie is in deze opdracht bewust niet gereleased.

De actuele uitvoeringsinstructies staan in [OFFICE_TEST_DEVELOPMENT_PIPELINE.md](OFFICE_TEST_DEVELOPMENT_PIPELINE.md), [DEVELOPMENT_POLICY.md](DEVELOPMENT_POLICY.md) en het repository-`AGENTS.md`.
