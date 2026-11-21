# Calc Office-test: hostingdiagnose (11 november 2026)

Bron: [Hostinger audit #42981004440](https://github.com/JohnBN1970/brebo-office/actions/runs/42981004440), vanuit [PR #1792](https://github.com/JohnBN1970/brebo-office/pull/1792).

## Vastgesteld op de echte server
- SSH vanuit `brebo-office` werkt met de bestaande repository-secrets; Office router, `.htaccess` en configuratie zijn aanwezig.
- Node.js **bestaat wel** op Hostinger, buiten het gewone SSH-PATH: v18.20.8, v20.20.2, **v22.23.4**, v24.22.1. Gebruik voor Calc Node 22+.
- De Office-home retourneert HTTP 302 (login/redirect, geen bewijs van ingelogde sessie).
- `https://brebobv.nl/office-test/calc-app/` retourneert HTTP **404**.
- Geen verwijzing naar `/calc-app` in actieve router, geen bijpassende proxyregel in Office-`.htaccess`.
- Geen Passenger-directieven aangetroffen in de twee gecontroleerde rewritebestanden; dat **bewijst niet** dat Hostinger geen ondersteunde Passenger-mount kan aanbieden.
- Calc-runtime niet aanwezig op onderzochte Office-test-doelpaden.
- Bestaande calculaties en Calc-origin zijn niet gewijzigd.

## Gecontroleerde herstelvolgorde
1. Bevestig via Hostinger Node.js App / Passenger-configuratie welk app-root, startupbestand en base-URI voor een **aparte** Calc-testapp worden ondersteund. Een gevonden Node-binary is geen lopende webservice.
2. Reserveer `/office-test/calc-app` alleen na bevestigde Passenger-mount/reverse-proxy. **Niet** oplossen door een willekeurige PHP-proxy of een publiek ongeauthenticeerd `dist`-pad.
3. Neem alleen `calc`-codebuild uit [Calc PR #308](https://github.com/JohnBN1970/calc/pull/308), zonder bestaande calculatiegegevens. Maak afzonderlijke, lege testopslag; laat bestaande Calc-database staan.
4. Werk de `calc`-API bij voor projectloze v2 CRM-launch; de huidige v1-consumer en project-ID-validatie zijn onverenigbaar met CRM-first. Vergelijk [Calc PR #307](https://github.com/JohnBN1970/calc/pull/307) en [Office PR #1777](https://github.com/JohnBN1970/brebo-office/pull/1777). Eerst Office-sessie, CSRF, one-time grant en hercontrole van rechten voltooien.
5. Eerst HTTP-acceptatie met geïsoleerde testgebruiker, live revoke/replay/logout en lege calculatie + opslaan/heropenen. Pas daarna Office-navigatie en beschikbaarheid aanzetten.

## Go/no-go
**NO-GO voor live Office-test Calc zolang de mount/proxy en projectloze autorisatie niet bewezen zijn.**
Een groene build/audit is geen werkende installatie. Herstel uitsluitend met fail-closed featureflags en rollback; geen data-import of productieaanpassing.
