# BREBO Office — centrale ontwikkelstatus

**Momentopname 20 november 2026.** Dit bestand is een overdrachtshulp, geen realtime-feed. GitHub en Office-test zijn leidend.

## Verplicht voor iedere ontwikkelchat

1. Lees `AGENTS.md` en `docs/OFFICE_TEST_DEVELOPMENT_PIPELINE.md`.
2. Controleer **opnieuw** de actuele `develop`-HEAD, open PR's, overlappende wijzigingen en Actions. Vertrouw nooit blind op vorige chats of deze momentopname.
3. Hergebruik bestaande code. Werk via een eigen branch en PR. Omzeil branchbeveiliging of verplichte checks nooit.
4. Volg de bestaande centrale Office-test-pipeline; maak geen parallelle deployworkflow. Controleer na merge de VPS-release-SHA en de echte ingelogde gebruikersketen. Groene CI of SHA-match is geen functionele acceptatie.
5. Rapporteer `werkt / getest / blokkeert`, inclusief PR, merge-SHA, run, VPS-acceptatie en open punten. Werk deze overdracht bij bij wezenlijke wijzigingen, steeds met actuele GitHub-verificatie.
6. Alleen fictieve testdata op Office-test; productie uitsluitend na afzonderlijke goedgekeurde release.

## GitHub-momentopname

- `develop` HEAD tijdens inventarisatie: `e1ce58fb411e943f7dbe9c7f498ea1571645e95d`.
- PR #2008, #2009, #2017: gemerged, VPS-release-SHA bevestigd; inhoudelijke controle van financiële bedragen tegen Office-administratie blijft open.
- Open tijdens inventarisatie: #2026 (projectdetail HTTP 503), #2023 (gegunde CRM-verkoopkans naar project), #2021 (financiële datumgrenzen), #2020 (projectoverzicht contrast), #2015 (leadtrechter), #2013 (leadverantwoordelijke), #2001 (VPS-inventaris), #1999 (inkoop-schema installer). **Niet volledig:** controleer alle actuele PR's, ook Calc, OnSite en Werkbegroting.
- PR #2021: de source-gate-job slaagde, maar een mergepoging kreeg HTTP 405: verplichte check `Office-test source gate` werd nog verwacht. Controleer actuele check-context en branchregels, niet omzeilen.

## Functionele aandachtspunten — niet automatisch als gereed markeren

1. Projectdetail 503 herstellen en projectgebruikersketen op Office-test accepteren.
2. Gegunde verkoopkans → project, relatieaanmaak, lead-eigenaarschap en echte trechter controleren.
3. Financecockpit: omzet, debiteuren, crediteuren en vervallen bedragen vergelijken met Office-bronadministratie.
4. OnSite, Calculatie, Offertestudio en Werkbegroting vanuit bestaande PR's end-to-end afronden.

## Overdrachtsformat per run

| Veld | Vastlegging |
| --- | --- |
| Module | Route en functionele scope |
| Bron | Actuele develop-SHA en overlappende PR's |
| Code | Branch, PR, merge-SHA |
| Tests | CI-run, bronpoort en functionele scenario's |
| Office-test | Actieve VPS-SHA, HTTP en ingelogde acceptatie |
| Restpunten | Concrete blokkade en eerstvolgende actie |

**Belangrijk:** dit document werkt zichzelf niet bij. Iedere chat moet live GitHub opnieuw controleren.
